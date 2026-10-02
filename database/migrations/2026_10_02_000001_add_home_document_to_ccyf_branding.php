<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccyf_branding', function (Blueprint $table): void {
            $table->string('document_title', 120)->nullable();
            $table->string('document_description', 240)->nullable();
            $table->string('document_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_branding', function (Blueprint $table): void {
            $table->dropColumn(['document_title', 'document_description', 'document_path']);
        });
    }
};
