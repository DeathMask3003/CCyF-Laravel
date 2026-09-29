<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_branding', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('title', 120);
            $table->string('motto', 160);
            $table->string('logo_path', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_branding');
    }
};
