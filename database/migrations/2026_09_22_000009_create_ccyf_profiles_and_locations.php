<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccyf_usuarios', function (Blueprint $table): void {
            $table->string('rfc', 13)->nullable();
            $table->string('curp', 18)->nullable();
            $table->string('ine_clave', 30)->nullable();
            $table->text('direcc')->nullable();
            $table->string('cont_alter', 150)->nullable();
            $table->string('telf_alter', 30)->nullable();
            $table->string('telegram_chat_id', 30)->nullable();
            $table->timestamp('legacy_profile_imported_at')->nullable();
        });

        Schema::create('ccyf_ubicaciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('usu_id')->index();
            $table->unsignedInteger('legacy_ubic_id')->nullable()->unique();
            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);
            $table->decimal('precision_gps', 10, 2)->nullable();
            $table->boolean('es_aproximada')->default(false);
            $table->string('ciudad', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('pais', 120)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('fuente', 80)->nullable();
            $table->timestamp('fecha_registro');
            $table->timestamps();
            $table->index(['usu_id', 'fecha_registro']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_ubicaciones');
        Schema::table('ccyf_usuarios', function (Blueprint $table): void {
            $table->dropColumn(['rfc', 'curp', 'ine_clave', 'direcc', 'cont_alter', 'telf_alter', 'telegram_chat_id', 'legacy_profile_imported_at']);
        });
    }
};
