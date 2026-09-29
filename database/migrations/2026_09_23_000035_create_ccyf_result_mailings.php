<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_result_mailings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registro_id')->constrained('ccyf_registros')->cascadeOnDelete();
            $table->string('audience', 20);
            $table->unsignedSmallInteger('part');
            $table->string('recipient', 255);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attachment_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['registro_id', 'audience', 'part'], 'ccyf_result_mailing_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_result_mailings');
    }
};
