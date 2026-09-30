<?php

namespace App\Console\Commands;

use App\Services\RegistrationNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegistrationMail extends Command
{
    protected $signature = 'ccyf:registration-mail {folio : Folio exacto del expediente} {--send : Envía solo los avisos pendientes de este folio}';

    protected $description = 'Consulta o reintenta los correos de un registro CCyF sin reenviar los ya aceptados';

    public function handle(RegistrationNotifications $notifications): int
    {
        $folio = trim((string) $this->argument('folio'));
        $id = DB::table('ccyf_registros')->where('folio', $folio)->value('id');
        if (! $id) {
            $this->error('No se encontró ese folio. No se envió ningún correo.');

            return self::FAILURE;
        }
        $record = $notifications->record((int) $id);
        $this->line('Folio: '.$record->folio);
        $this->line('Participante: '.($record->participant_email ?: 'sin correo'));
        $this->line('CCyF: '.config('ccyf.mail_ccyf'));

        if ($this->option('send')) {
            $result = $notifications->dispatch((int) $id);
            $this->info("Aceptados por SMTP: {$result['sent']}; ya enviados: {$result['alreadySent']}; pendientes: {$result['pending']}; fallidos: {$result['failed']}.");
        } else {
            $this->comment('Consulta únicamente. Usa --send para intentar el envío de este folio.');
        }

        foreach ($notifications->status((int) $id) as $row) {
            $this->line("{$row->audience}: {$row->status} · {$row->recipient}".
                ($row->last_error ? " · {$row->last_error}" : ''));
        }

        return ! $this->option('send') || ($result['pending'] === 0 && $result['failed'] === 0)
            ? self::SUCCESS : self::FAILURE;
    }
}
