<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_convocatoria_documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convocatoria_id')->constrained('ccyf_convocatorias')->restrictOnDelete();
            $table->string('titulo', 200);
            $table->longText('detalles_html');
            $table->unsignedInteger('creado_por')->nullable();
            $table->unsignedInteger('actualizado_por')->nullable();
            $table->timestamps();
            $table->index(['convocatoria_id', 'created_at']);
        });

        Schema::create('ccyf_convocatoria_documento_planteles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('documento_id')->constrained('ccyf_convocatoria_documentos')->cascadeOnDelete();
            $table->foreignId('plantel_id')->constrained('ccyf_planteles')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('direccion', 500)->nullable();
            $table->string('espacio', 100);
            $table->unsignedInteger('matricula')->nullable();
            $table->decimal('monto', 11, 2);
            $table->decimal('garantia', 11, 2)->nullable();
            $table->date('fecha_inicio');
            $table->timestamps();
            $table->unique(['documento_id', 'plantel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_convocatoria_documento_planteles');
        Schema::dropIfExists('ccyf_convocatoria_documentos');
    }
};
