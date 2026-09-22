<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccyf_registros', function (Blueprint $table): void {
            $table->string('decision', 30)->nullable();
            $table->string('respuesta', 250)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->decimal('monto', 12, 2)->nullable();
            $table->unsignedInteger('revisado_por')->nullable();
            $table->timestamp('finalizado_at')->nullable();
            $table->index(['estado', 'enviado_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_registros', function (Blueprint $table): void {
            $table->dropIndex(['estado', 'enviado_at']);
            $table->dropColumn(['decision', 'respuesta', 'fecha_inicio', 'fecha_fin', 'monto', 'revisado_por', 'finalizado_at']);
        });
    }
};
