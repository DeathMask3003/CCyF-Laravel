<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_convocatoria_plantillas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('servicio_id')->unique()->constrained('ccyf_tipos_servicio')->restrictOnDelete();
            $table->longText('cuerpo_html');
            $table->string('font_family', 30)->default('dejavusans');
            $table->decimal('font_size', 4, 1)->default(9);
            $table->unsignedInteger('actualizado_por')->nullable();
            $table->timestamps();
        });

        Schema::table('ccyf_convocatoria_documentos', function (Blueprint $table): void {
            $table->string('font_family', 30)->default('dejavusans');
            $table->decimal('font_size', 4, 1)->default(9);
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_convocatoria_documentos', function (Blueprint $table): void {
            $table->dropColumn(['font_family', 'font_size']);
        });
        Schema::dropIfExists('ccyf_convocatoria_plantillas');
    }
};
