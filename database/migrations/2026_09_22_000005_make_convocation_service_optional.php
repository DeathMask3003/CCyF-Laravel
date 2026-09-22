<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccyf_convocatorias', function (Blueprint $table): void {
            $table->foreignId('servicio_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Los registros históricos sin servicio impiden volver la columna obligatoria sin perder datos.
    }
};
