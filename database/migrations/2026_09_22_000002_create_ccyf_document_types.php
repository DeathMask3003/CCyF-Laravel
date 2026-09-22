<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_tipos_documento', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_tipo_id')->nullable()->unique();
            $table->string('nombre', 50);
            $table->string('nombre_clave', 50)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['activo', 'nombre']);
        });

        Schema::table('ccyf_precio_borradores', function (Blueprint $table): void {
            $table->foreignId('tipo_documento_id')->nullable()
                ->constrained('ccyf_tipos_documento')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_precio_borradores', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tipo_documento_id');
        });
        Schema::dropIfExists('ccyf_tipos_documento');
    }
};
