<?php

namespace App\Services;

use App\Mail\DecisionNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ResultNotifications
{
    private const MAX_DOCUMENT_BYTES_PER_MESSAGE = 12 * 1024 * 1024;

    public function __construct(private readonly DocumentFiles $files, private readonly ResultLetterPdf $letter) {}

    public function ready(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', 'failover', 'roundrobin'], true)
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && ! str_ends_with((string) config('mail.from.address'), '@example.com');
    }

    public function status(int $recordId)
    {
        return DB::table('ccyf_result_mailings')->where('registro_id', $recordId)
            ->orderBy('audience')->orderBy('part')->get();
    }

    public function dispatch(int $recordId): array
    {
        $record = $this->record($recordId);
        if (! $record || $record->estado !== 'Finalizado') {
            throw new RuntimeException('El expediente aún no tiene una resolución final.');
        }
        $documents = $record->decision === 'designado' ? $this->documents($recordId) : [];
        if ($record->decision === 'designado' && ! $documents) {
            throw new RuntimeException('El expediente no tiene los documentos originales del registro.');
        }
        $audiences = [
            'participant' => ['to' => $record->participant_email, 'cc' => [], 'parts' => [[]]],
        ];
        if ($record->decision === 'designado') {
            $cc = [$record->campus_email, config('ccyf.mail_management'), config('ccyf.mail_ccyf')];
            $audiences['internal'] = ['to' => config('ccyf.mail_legal'),
                'cc' => array_values(array_unique(array_filter($cc, fn ($mail) =>
                    filter_var($mail, FILTER_VALIDATE_EMAIL)
                    && mb_strtolower($mail) !== mb_strtolower((string) config('ccyf.mail_legal'))))),
                'parts' => $this->parts($documents)];
        } else {
            $audiences['participant']['cc'] = filter_var(config('ccyf.mail_ccyf'), FILTER_VALIDATE_EMAIL)
                ? [config('ccyf.mail_ccyf')] : [];
        }

        $warnings = [];
        if ($record->decision === 'designado' && ! filter_var($record->campus_email, FILTER_VALIDATE_EMAIL)) {
            $warnings[] = 'El plantel no tiene un correo válido; Unidad Jurídica sí está incluida.';
        }
        $sent = 0;
        $pending = 0;
        $failed = 0;
        $letter = null;
        foreach ($audiences as $audience => $recipients) {
            foreach ($recipients['parts'] as $index => $part) {
                $number = $index + 1;
                $where = ['registro_id' => $recordId, 'audience' => $audience, 'part' => $number];
                DB::table('ccyf_result_mailings')->insertOrIgnore($where + [
                    'recipient' => (string) $recipients['to'], 'status' => 'pending',
                    'attachment_count' => count($part) + 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $row = DB::table('ccyf_result_mailings')->where($where)->first();
                if ($row->status === 'sent') continue;
                if (! filter_var($recipients['to'], FILTER_VALIDATE_EMAIL)) {
                    $this->mark($row->id, 'failed', 'Falta un correo válido para '.$audience.'.');
                    $failed++;
                    continue;
                }
                if (! $this->ready()) {
                    $this->mark($row->id, 'pending', 'Configura un transporte de correo real para enviar.');
                    $pending++;
                    continue;
                }
                $claimed = DB::table('ccyf_result_mailings')->where('id', $row->id)
                    ->whereIn('status', ['pending', 'failed'])->update([
                        'status' => 'sending', 'recipient' => $recipients['to'],
                        'attachment_count' => count($part) + 1, 'last_error' => null,
                        'updated_at' => now(),
                    ]);
                if (! $claimed) {
                    $pending++;
                    continue;
                }
                try {
                    $letter ??= $this->letter->render($record);
                    Mail::to($recipients['to'])->cc($recipients['cc'])
                        ->send(new DecisionNotice($record, $audience, $part, $letter,
                            $number, count($recipients['parts'])));
                    DB::table('ccyf_result_mailings')->where('id', $row->id)->update([
                        'status' => 'sent', 'sent_at' => now(), 'last_error' => null, 'updated_at' => now(),
                    ]);
                    $sent++;
                } catch (Throwable $error) {
                    $this->mark($row->id, 'failed', mb_substr($error->getMessage(), 0, 1000));
                    report($error);
                    $failed++;
                }
            }
        }

        return compact('sent', 'pending', 'failed', 'warnings');
    }

    private function record(int $recordId): ?object
    {
        $record = DB::table('ccyf_registros as registro')
            ->join('ccyf_convocatorias as convocatoria', 'convocatoria.id', '=', 'registro.convocatoria_id')
            ->join('ccyf_tipos_servicio as servicio', 'servicio.id', '=', 'registro.servicio_id')
            ->join('ccyf_planteles as plantel', 'plantel.id', '=', 'registro.plantel_id')
            ->leftJoin('ccyf_usuarios as usuario', 'usuario.usu_id', '=', 'registro.usu_id')
            ->where('registro.id', $recordId)->first([
                'registro.*', 'convocatoria.numero as convocatoria_nombre',
                'servicio.nombre as servicio_nombre', 'plantel.nombre as plantel_nombre',
                'plantel.correo as campus_email', 'plantel.legacy_area_id',
                'usuario.usu_correo as participant_email',
            ]);
        if ($record && ! filter_var($record->campus_email, FILTER_VALIDATE_EMAIL) && $record->legacy_area_id) {
            $record->campus_email = DB::connection('legacy')->table('tm_areas')
                ->where('area_id', $record->legacy_area_id)->value('area_correo');
        }

        return $record;
    }

    private function documents(int $recordId): array
    {
        $rows = DB::table('ccyf_registro_archivos as archivo')
            ->join('ccyf_requisitos_documento as requisito', 'requisito.id', '=', 'archivo.requisito_id')
            ->where('archivo.registro_id', $recordId)->orderBy('requisito.orden')->orderBy('archivo.id')
            ->get(['archivo.id', 'archivo.requisito_id', 'archivo.bytes', 'requisito.nombre']);
        $folder = realpath(Storage::disk('local')->path('ccyf/registros/'.$recordId));
        $storageRoot = realpath(Storage::disk('local')->path(''));
        $documents = [];
        foreach ($rows as $index => $row) {
            $original = $this->files->original('actual', $recordId, 'req-'.$row->requisito_id);
            if (! $original || ! $folder) {
                throw new RuntimeException('Falta el documento '.$row->id.' cargado en el registro.');
            }
            $path = realpath($original['path']);
            if (! $path || ! str_starts_with(strtolower($path), strtolower($folder.DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('La ruta del documento '.$row->id.' no pertenece al expediente.');
            }
            $baseName = sprintf('%02d-%s', $index + 1, Str::slug($row->nombre));
            $documents[] = ['path' => $path, 'name' => $baseName.'-original.pdf',
                'mime' => 'application/pdf', 'bytes' => filesize($path)];

            $updated = $this->files->latest('actual', $recordId, 'req-'.$row->requisito_id);
            if ($updated) {
                $updatePath = $this->files->versionPath($updated);
                $resolved = $updatePath ? realpath($updatePath) : false;
                if (! $resolved || ! $storageRoot
                    || ! str_starts_with(strtolower($resolved), strtolower($storageRoot.DIRECTORY_SEPARATOR))) {
                    throw new RuntimeException('Falta la actualización del documento '.$row->id.'.');
                }
                $extension = strtolower(pathinfo($updated->original_name, PATHINFO_EXTENSION));
                $documents[] = ['path' => $resolved,
                    'name' => $baseName.'-actualizado.'.(preg_match('/^(pdf|jpg|jpeg|png)$/', $extension) ? $extension : 'pdf'),
                    'mime' => $updated->mime, 'bytes' => filesize($resolved)];
            }
        }

        return $documents;
    }

    private function parts(array $documents): array
    {
        $parts = [[]];
        $size = 0;
        foreach ($documents as $document) {
            if ($document['bytes'] < 1 || $document['bytes'] > self::MAX_DOCUMENT_BYTES_PER_MESSAGE) {
                throw new RuntimeException('Hay un adjunto vacío o demasiado grande para enviarse por correo.');
            }
            if ($size + $document['bytes'] > self::MAX_DOCUMENT_BYTES_PER_MESSAGE) {
                $parts[] = [];
                $size = 0;
            }
            $parts[array_key_last($parts)][] = $document;
            $size += $document['bytes'];
        }

        return $parts;
    }

    private function mark(int $id, string $status, string $error): void
    {
        DB::table('ccyf_result_mailings')->where('id', $id)->update([
            'status' => $status, 'last_error' => $error, 'updated_at' => now(),
        ]);
    }
}
