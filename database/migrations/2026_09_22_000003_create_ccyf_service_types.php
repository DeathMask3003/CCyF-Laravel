<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_tipos_servicio', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_trami_id')->nullable()->unique();
            $table->string('nombre', 50);
            $table->string('nombre_clave', 50)->unique();
            $table->string('descripcion', 200);
            $table->string('plantilla', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['activo', 'nombre']);
        });

        Schema::table('ccyf_catalogos', function (Blueprint $table): void {
            $table->foreignId('servicio_id')->nullable()
                ->constrained('ccyf_tipos_servicio')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_catalogos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('servicio_id');
        });
        Schema::dropIfExists('ccyf_tipos_servicio');
    }
};
