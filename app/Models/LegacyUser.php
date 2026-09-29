<?php

namespace App\Models;

use App\Notifications\CcyfResetPassword;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LegacyUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'ccyf_usuarios';

    protected $primaryKey = 'usu_id';

    public $timestamps = true;

    protected $guarded = ['*'];

    protected $hidden = ['usu_pass', 'remember_token'];

    public function getAuthPasswordName(): string
    {
        return 'usu_pass';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->usu_pass;
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->getKey();
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->usu_correo;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CcyfResetPassword($token));
    }
}
