<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class LegacyUser extends Authenticatable
{
    protected $connection = 'legacy';

    protected $table = 'tm_usuario';

    protected $primaryKey = 'usu_id';

    public $timestamps = false;

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
