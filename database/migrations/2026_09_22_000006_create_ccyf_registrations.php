<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_requisitos_documento', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('servicio_id')->constrained('ccyf_tipos_servicio')->cascadeOnDelete();
            $table->string('clave', 60);
            $table->string('nombre', 150);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('requerido')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['servicio_id', 'clave']);
            $table->index(['servicio_id', 'activo', 'orden']);
        });

        Schema::create('ccyf_registros', function (Blueprint $table): void {
            $table->id();
            $table->string('folio', 30)->nullable()->unique();
            $table->foreignId('convocatoria_id')->constrained('ccyf_convocatorias')->restrictOnDelete();
            $table->foreignId('catalogo_id')->constrained('ccyf_catalogos')->restrictOnDelete();
            $table->foreignId('servicio_id')->constrained('ccyf_tipos_servicio')->restrictOnDelete();
            $table->foreignId('plantel_id')->constrained('ccyf_planteles')->restrictOnDelete();
            $table->foreignId('tipo_documento_id')->constrained('ccyf_tipos_documento')->restrictOnDelete();
            $table->unsignedInteger('usu_id');
            $table->string('solicitante', 200);
            $table->string('dirigido_a', 200);
            $table->text('comentarios');
            $table->string('estado', 30)->default('Recibido');
            $table->timestamp('enviado_at');
            $table->timestamps();
            $table->unique(['usu_id', 'convocatoria_id', 'plantel_id', 'servicio_id'], 'ccyf_registro_participacion_unique');
            $table->index(['usu_id', 'estado']);
        });

        Schema::create('ccyf_registro_precios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registro_id')->constrained('ccyf_registros')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('ccyf_productos')->restrictOnDelete();
            $table->string('producto_nombre', 120);
            $table->string('unidad', 50)->nullable();
            $table->decimal('precio', 10, 2);
            $table->timestamps();
            $table->unique(['registro_id', 'producto_id']);
        });

        Schema::create('ccyf_registro_archivos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registro_id')->constrained('ccyf_registros')->cascadeOnDelete();
            $table->foreignId('requisito_id')->constrained('ccyf_requisitos_documento')->restrictOnDelete();
            $table->string('nombre_original', 255);
            $table->string('ruta', 500);
            $table->string('mime', 100);
            $table->unsignedBigInteger('bytes');
            $table->timestamps();
            $table->unique(['registro_id', 'requisito_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_registro_archivos');
        Schema::dropIfExists('ccyf_registro_precios');
        Schema::dropIfExists('ccyf_registros');
        Schema::dropIfExists('ccyf_requisitos_documento');
    }
};
