<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccyf_convocatoria_documentos', function (Blueprint $table): void {
            $table->string('firmante_nombre', 180)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_convocatoria_documentos', function (Blueprint $table): void {
            $table->dropColumn('firmante_nombre');
        });
    }
};
