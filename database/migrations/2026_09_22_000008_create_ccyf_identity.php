<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_roles', function (Blueprint $table): void {
            $table->increments('rol_id');
            $table->string('rol_nom', 80);
            $table->boolean('est')->default(true);
            $table->unsignedInteger('legacy_rol_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('ccyf_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('rol_id');
            $table->string('menu_key', 50);
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['rol_id', 'menu_key']);
            $table->foreign('rol_id')->references('rol_id')->on('ccyf_roles')->cascadeOnDelete();
        });

        Schema::create('ccyf_usuarios', function (Blueprint $table): void {
            $table->increments('usu_id');
            $table->string('usu_area', 150);
            $table->string('usu_correo', 150)->nullable();
            $table->text('usu_pass');
            $table->unsignedInteger('rol_id')->nullable();
            $table->unsignedInteger('area_id')->nullable();
            $table->string('usu_telf', 30)->nullable();
            $table->boolean('est')->default(true);
            $table->unsignedInteger('legacy_usu_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['rol_id', 'est']);
            $table->index('usu_correo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_usuarios');
        Schema::dropIfExists('ccyf_role_permissions');
        Schema::dropIfExists('ccyf_roles');
    }
};
