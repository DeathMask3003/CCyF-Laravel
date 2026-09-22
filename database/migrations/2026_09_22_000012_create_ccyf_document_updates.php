<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_document_updates', function (Blueprint $table): void {
            $table->id();
            $table->string('origin', 12);
            $table->unsignedBigInteger('registration_id');
            $table->string('field_key', 80);
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('mime', 100);
            $table->unsignedBigInteger('bytes');
            $table->unsignedInteger('uploaded_by');
            $table->timestamp('created_at');
            $table->index(['origin', 'registration_id', 'field_key', 'id'], 'ccyf_document_updates_lookup');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_document_updates');
    }
};
