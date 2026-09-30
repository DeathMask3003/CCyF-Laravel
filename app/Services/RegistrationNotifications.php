<?php

namespace App\Services;

use App\Mail\RegistrationNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class RegistrationNotifications
{
    public function status(int $recordId)
    {
        return DB::table('ccyf_registration_mailings')->where('registro_id', $recordId)
            ->orderBy('audience')->get();
    }

    public function dispatch(int $recordId): array
    {
        $record = $this->record($recordId);
        if (! $record) {
            throw new RuntimeException('No se encontró el registro solicitado.');
        }

        $audiences = [
            'participant' => (string) $record->participant_email,
            'office' => (string) config('ccyf.mail_ccyf'),
        ];
        $sent = $pending = $failed = $alreadySent = 0;
        foreach ($audiences as $audience => $recipient) {
            $where = ['registro_id' => $recordId, 'audience' => $audience];
            DB::table('ccyf_registration_mailings')->insertOrIgnore($where + [
                'recipient' => $recipient, 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $row = DB::table('ccyf_registration_mailings')->where($where)->first();
            if ($row->status === 'sent') {
                $alreadySent++;
                continue;
            }
            if ($row->status === 'sending') {
                $pending++;
                continue;
            }
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $this->mark($row->id, 'failed', 'Falta un correo válido para '.$audience.'.');
                $failed++;
                continue;
            }
            if (! $this->canSendTo($recipient)) {
                $this->mark($row->id, 'pending', 'El envío está desactivado o este destinatario no está autorizado en pruebas.');
                $pending++;
                continue;
            }

            $claimed = DB::table('ccyf_registration_mailings')->where('id', $row->id)
                ->whereIn('status', ['pending', 'failed'])->update([
                    'status' => 'sending', 'recipient' => $recipient, 'last_error' => null, 'updated_at' => now(),
                ]);
            if (! $claimed) {
                $pending++;
                continue;
            }

            try {
                $cc = [];
                if ($audience === 'office' && filter_var($record->campus_email, FILTER_VALIDATE_EMAIL)
                    && mb_strtolower($record->campus_email) !== mb_strtolower($recipient)
                    && $this->canSendTo($record->campus_email)) {
                    $cc[] = $record->campus_email;
                }
                Mail::mailer('smtp')->to($recipient)->cc($cc)
                    ->send(new RegistrationNotice($record, $audience));
                DB::table('ccyf_registration_mailings')->where('id', $row->id)->update([
                    'status' => 'sent', 'sent_at' => now(), 'last_error' => null, 'updated_at' => now(),
                ]);
                $sent++;
            } catch (Throwable $error) {
                $this->mark($row->id, 'failed', mb_substr($error->getMessage(), 0, 1000));
                report($error);
                $failed++;
            }
        }

        return compact('sent', 'pending', 'failed', 'alreadySent');
    }

    public function record(int $recordId): ?object
    {
        return DB::table('ccyf_registros as registro')
            ->join('ccyf_convocatorias as convocatoria', 'convocatoria.id', '=', 'registro.convocatoria_id')
            ->join('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'registro.servicio_id')
            ->join('ccyf_planteles as plantel', 'plantel.id', '=', 'registro.plantel_id')
            ->leftJoin('ccyf_usuarios as usuario', 'usuario.usu_id', '=', 'registro.usu_id')
            ->where('registro.id', $recordId)->select([
                'registro.id', 'registro.folio', 'registro.solicitante', 'registro.enviado_at',
                'convocatoria.numero as convocatoria_nombre', 'servicio.nombre as servicio_nombre',
                'plantel.nombre as plantel_nombre', 'plantel.correo as campus_email',
                'usuario.usu_correo as participant_email',
            ])->selectSub(DB::table('ccyf_registro_archivos')
                ->selectRaw('count(*)')->whereColumn('registro_id', 'registro.id'), 'document_count')
            ->first();
    }

    private function canSendTo(string $recipient): bool
    {
        $smtp = config('mail.mailers.smtp');
        if (! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            || ! filled($smtp['host'] ?? null) || ! filled($smtp['port'] ?? null)
            || ! filled($smtp['username'] ?? null) || ! filled($smtp['password'] ?? null)) {
            return false;
        }
        if (config('app.env') === 'production') {
            return config('mail.default') === 'smtp';
        }
        if (config('app.env') !== 'staging') {
            return false;
        }

        $allowed = array_map('mb_strtolower', preg_split('/\s*,\s*/',
            (string) config('ccyf.staging_registration_smtp_emails'), -1, PREG_SPLIT_NO_EMPTY));

        return in_array(mb_strtolower($recipient), $allowed, true);
    }

    private function mark(int $id, string $status, string $error): void
    {
        DB::table('ccyf_registration_mailings')->where('id', $id)->update([
            'status' => $status, 'last_error' => $error, 'updated_at' => now(),
        ]);
    }
}
