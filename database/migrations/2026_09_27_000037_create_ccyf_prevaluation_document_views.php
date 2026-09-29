<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_prevaluacion_vistas', function (Blueprint $table): void {
            $table->id();
            $table->string('origen', 12);
            $table->unsignedBigInteger('registro_id');
            $table->unsignedInteger('usuario_id');
            $table->string('clave', 80);
            $table->timestamps();
            $table->unique(['origen', 'registro_id', 'usuario_id', 'clave'], 'ccyf_preval_vistas_unicas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_prevaluacion_vistas');
    }
};
