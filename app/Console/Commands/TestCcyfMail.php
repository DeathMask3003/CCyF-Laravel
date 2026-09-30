<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestCcyfMail extends Command
{
    protected $signature = 'ccyf:mail-test {recipient : Correo único que recibirá la prueba}';

    protected $description = 'Envía un único correo de prueba mediante el SMTP configurado, sin cambiar el transporte del sitio';

    public function handle(): int
    {
        $recipient = trim((string) $this->argument('recipient'));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Indica una dirección de correo válida.');

            return self::FAILURE;
        }

        $smtp = config('mail.mailers.smtp');
        $sender = config('mail.from.address');
        $required = [
            'MAIL_HOST' => $smtp['host'] ?? null,
            'MAIL_PORT' => $smtp['port'] ?? null,
            'MAIL_USERNAME' => $smtp['username'] ?? null,
            'MAIL_PASSWORD' => $smtp['password'] ?? null,
        ];
        $missing = array_keys(array_filter($required, fn ($value) => ! filled($value)));
        if (! filter_var($sender, FILTER_VALIDATE_EMAIL)
            || str_ends_with(mb_strtolower((string) $sender), '@example.com')) {
            $missing[] = 'MAIL_FROM_ADDRESS';
        }
        if ($missing !== []) {
            $this->error('Completa en el .env: '.implode(', ', $missing).'.');

            return self::FAILURE;
        }

        try {
            Mail::mailer('smtp')->raw(
                'Prueba de envío de CCyF. Este mensaje se envió de forma manual para verificar el correo del servidor.',
                fn ($message) => $message->to($recipient)->subject('Prueba de correo CCyF'),
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->error('El servidor SMTP no aceptó el envío. Revisa storage/logs/laravel.log en el servidor.');

            return self::FAILURE;
        }

        $this->info('El servidor SMTP aceptó el mensaje de prueba para '.$recipient.'.');
        $this->line('El transporte habitual del sitio sigue configurado como '.config('mail.default').'.');

        return self::SUCCESS;
    }
}
