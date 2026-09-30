<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class CcyfResetPassword extends ResetPassword
{
    private const HEADER_CID = 'cabecera-institucional@ccyf.cobaemex.edu.mx';

    public function toMail($notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'cuenta' => $notifiable->getKey(),
        ]);

        $message = (new MailMessage)
            ->subject('Restablece tu contraseña | CCyF')
            ->view(['html' => 'emails.password-reset', 'text' => 'emails.password-reset-text'], [
                'recipientName' => (string) $notifiable->usu_area,
                'resetUrl' => $url,
                'expiresInMinutes' => (int) config('auth.passwords.users.expire'),
                'institutionalHeaderCid' => 'cid:'.self::HEADER_CID,
            ])
            ->withSymfonyMessage(function (Email $email): void {
                $email->addPart((new DataPart(
                    new File(resource_path('images/cabezera.png')),
                    'cabecera-institucional.png',
                    'image/png',
                ))->asInline()->setContentId(self::HEADER_CID));
            });

        if (config('app.env') === 'staging' && config('mail.default') === 'log') {
            $allowed = array_map('mb_strtolower', preg_split('/\s*,\s*/',
                (string) config('ccyf.staging_recovery_smtp_emails'), -1, PREG_SPLIT_NO_EMPTY));
            if (in_array(mb_strtolower((string) $notifiable->usu_correo), $allowed, true)) {
                $message->mailer('smtp');
            }
        }

        return $message;
    }
}
