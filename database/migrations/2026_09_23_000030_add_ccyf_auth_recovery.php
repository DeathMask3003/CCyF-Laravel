<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccyf_usuarios', function (Blueprint $table): void {
            $table->rememberToken();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            // El identificador de la cuenta ocupa esta columna: hay correos históricos duplicados.
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::table('ccyf_usuarios', function (Blueprint $table): void {
            $table->dropRememberToken();
        });
    }
};
