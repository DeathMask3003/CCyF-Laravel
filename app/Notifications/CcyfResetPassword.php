<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CcyfResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'cuenta' => $notifiable->getKey(),
        ]);

        $message = (new MailMessage)
            ->subject('Restablece tu contraseña de CCyF')
            ->greeting('Hola, '.$notifiable->usu_area)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta de CCyF.')
            ->action('Restablecer contraseña', $url)
            ->line('Este enlace vence en '.config('auth.passwords.users.expire').' minutos.')
            ->line('Si no solicitaste el cambio, puedes ignorar este mensaje.');

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
