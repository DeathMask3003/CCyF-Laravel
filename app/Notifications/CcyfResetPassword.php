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

        return (new MailMessage)
            ->subject('Restablece tu contraseña de CCyF')
            ->greeting('Hola, '.$notifiable->usu_area)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta de CCyF.')
            ->action('Restablecer contraseña', $url)
            ->line('Este enlace vence en '.config('auth.passwords.users.expire').' minutos.')
            ->line('Si no solicitaste el cambio, puedes ignorar este mensaje.');
    }
}
