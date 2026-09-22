<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class LegacyUser extends Authenticatable
{
    protected $connection = 'sqlite';

    protected $table = 'ccyf_usuarios';

    protected $primaryKey = 'usu_id';

    public $timestamps = true;

    protected $guarded = ['*'];

    protected $hidden = ['usu_pass'];

    public function getAuthPasswordName(): string
    {
        return 'usu_pass';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->usu_pass;
    }
}
