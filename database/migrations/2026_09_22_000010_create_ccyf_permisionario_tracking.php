<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_seguimientos', function (Blueprint $table): void {
            $table->id();
            $table->string('origen', 12);
            $table->unsignedBigInteger('registro_id');
            $table->unsignedInteger('legacy_detapermi_id')->nullable()->unique();
            $table->decimal('metros_cuadrados', 10, 2)->nullable();
            $table->string('matricula', 80)->nullable();
            $table->decimal('monto', 12, 2)->nullable();
            $table->date('convocatoria1')->nullable();
            $table->date('convocatoria2')->nullable();
            $table->date('convocatoria3')->nullable();
            $table->string('pagosalmes', 40)->nullable();
            $table->string('construidapor', 80)->nullable();
            $table->string('servicioenergia', 80)->nullable();
            $table->text('observaciones')->nullable();
            $table->date('fecha_inicio_renovada')->nullable();
            $table->date('fecha_fin_renovada')->nullable();
            $table->unsignedInteger('actualizado_por')->nullable();
            $table->timestamps();
            $table->unique(['origen', 'registro_id']);
        });

        Schema::create('ccyf_seguimiento_archivos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seguimiento_id')->constrained('ccyf_seguimientos')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');
            $table->string('origen', 12);
            $table->string('ruta', 500);
            $table->string('nombre', 255);
            $table->timestamps();
            $table->unique(['seguimiento_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_seguimiento_archivos');
        Schema::dropIfExists('ccyf_seguimientos');
    }
};
