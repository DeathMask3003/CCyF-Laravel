<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_prevaluaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('origen', 12);
            $table->unsignedBigInteger('registro_id');
            $table->unsignedInteger('evaluador_id');
            $table->unsignedTinyInteger('resultado')->nullable();
            $table->timestamps();
            $table->unique(['origen', 'registro_id']);
        });

        Schema::create('ccyf_prevaluacion_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prevaluacion_id')->constrained('ccyf_prevaluaciones')->cascadeOnDelete();
            $table->string('clave', 80);
            $table->boolean('cumple')->nullable();
            $table->text('comentario')->nullable();
            $table->timestamps();
            $table->unique(['prevaluacion_id', 'clave']);
        });

        Schema::create('ccyf_prevaluacion_notas', function (Blueprint $table): void {
            $table->id();
            $table->string('origen', 12);
            $table->unsignedBigInteger('registro_id');
            $table->text('observaciones');
            $table->unsignedInteger('administrador_id');
            $table->timestamps();
            $table->unique(['origen', 'registro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_prevaluacion_notas');
        Schema::dropIfExists('ccyf_prevaluacion_items');
        Schema::dropIfExists('ccyf_prevaluaciones');
    }
};
