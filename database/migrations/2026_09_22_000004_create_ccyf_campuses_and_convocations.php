<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_planteles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_area_id')->nullable()->unique();
            $table->string('nombre', 100);
            $table->string('nombre_clave', 120)->unique();
            $table->string('correo', 100)->nullable();
            $table->string('direccion', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['activo', 'nombre']);
        });

        Schema::create('ccyf_plantel_servicios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plantel_id')->constrained('ccyf_planteles')->cascadeOnDelete();
            $table->foreignId('servicio_id')->nullable()->constrained('ccyf_tipos_servicio')->restrictOnDelete();
            $table->string('espacio', 50)->nullable();
            $table->unsignedInteger('matricula')->nullable();
            $table->decimal('monto', 11, 2)->nullable();
            $table->decimal('garantia', 11, 2)->nullable();
            $table->timestamps();
            $table->unique(['plantel_id', 'servicio_id']);
        });

        Schema::create('ccyf_convocatorias', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_cat_id')->nullable()->unique();
            $table->string('numero', 100);
            $table->string('numero_clave', 120)->unique();
            $table->foreignId('servicio_id')->constrained('ccyf_tipos_servicio')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['activo', 'numero']);
        });

        Schema::create('ccyf_convocatoria_planteles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('convocatoria_id')->constrained('ccyf_convocatorias')->cascadeOnDelete();
            $table->foreignId('plantel_id')->constrained('ccyf_planteles')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['convocatoria_id', 'plantel_id']);
        });

        Schema::table('ccyf_catalogos', function (Blueprint $table): void {
            $table->unsignedInteger('legacy_cat_id')->nullable()->change();
            $table->foreignId('convocatoria_id')->nullable()->unique()
                ->constrained('ccyf_convocatorias')->cascadeOnDelete();
        });

        Schema::table('ccyf_precio_borradores', function (Blueprint $table): void {
            $table->foreignId('plantel_id')->nullable()
                ->constrained('ccyf_planteles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_precio_borradores', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plantel_id');
        });
        Schema::table('ccyf_catalogos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('convocatoria_id');
            $table->unsignedInteger('legacy_cat_id')->nullable(false)->change();
        });
        Schema::dropIfExists('ccyf_convocatoria_planteles');
        Schema::dropIfExists('ccyf_convocatorias');
        Schema::dropIfExists('ccyf_plantel_servicios');
        Schema::dropIfExists('ccyf_planteles');
    }
};
