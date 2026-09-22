<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_contract_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('legacy_trami_id');
            $table->longText('body');
            $table->unsignedInteger('edited_by');
            $table->timestamps();
            $table->index(['legacy_trami_id', 'id']);
        });

        Schema::create('ccyf_contract_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('origin', 12);
            $table->unsignedBigInteger('registration_id');
            $table->string('status', 20);
            $table->string('recipient', 200)->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->string('template_hash', 64)->nullable();
            $table->unsignedInteger('sent_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['origin', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_contract_deliveries');
        Schema::dropIfExists('ccyf_contract_templates');
    }
};
