<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_registration_mailings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registro_id')->constrained('ccyf_registros')->cascadeOnDelete();
            $table->string('audience', 20);
            $table->string('recipient', 255);
            $table->string('status', 20)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['registro_id', 'audience'], 'ccyf_registration_mailing_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_registration_mailings');
    }
};
