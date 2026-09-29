<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_prevaluador_planteles', function (Blueprint $table): void {
            $table->id();
            $table->string('servicio', 20);
            $table->unsignedBigInteger('convocatoria_id');
            $table->string('plantel', 150);
            $table->unsignedInteger('evaluador_id');
            $table->unsignedInteger('asignado_por');
            $table->timestamps();
            $table->unique(['servicio', 'convocatoria_id', 'plantel'], 'ccyf_preval_asignacion_unica');
            $table->index(['evaluador_id', 'servicio', 'convocatoria_id'], 'ccyf_preval_asignado_idx');
            $table->foreign('evaluador_id')->references('usu_id')->on('ccyf_usuarios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_prevaluador_planteles');
    }
};
