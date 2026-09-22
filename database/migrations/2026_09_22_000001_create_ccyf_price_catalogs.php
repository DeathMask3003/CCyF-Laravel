<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_catalogos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_cat_id')->unique();
            $table->string('tipo', 20);
            $table->timestamps();
        });

        Schema::create('ccyf_productos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalogo_id')->constrained('ccyf_catalogos')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('unidad', 40)->nullable();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['catalogo_id', 'activo', 'orden']);
        });

        Schema::create('ccyf_precio_borradores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalogo_id')->constrained('ccyf_catalogos')->cascadeOnDelete();
            $table->unsignedInteger('usu_id');
            $table->timestamps();
            $table->unique(['catalogo_id', 'usu_id']);
        });

        Schema::create('ccyf_precio_borrador_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('borrador_id')->constrained('ccyf_precio_borradores')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('ccyf_productos')->restrictOnDelete();
            $table->string('producto_nombre', 120);
            $table->string('unidad', 40)->nullable();
            $table->decimal('precio', 10, 2);
            $table->timestamps();
            $table->unique(['borrador_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_precio_borrador_detalles');
        Schema::dropIfExists('ccyf_precio_borradores');
        Schema::dropIfExists('ccyf_productos');
        Schema::dropIfExists('ccyf_catalogos');
    }
};
