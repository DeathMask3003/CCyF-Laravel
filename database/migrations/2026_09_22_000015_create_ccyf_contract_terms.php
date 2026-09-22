<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccyf_contract_terms', function (Blueprint $table): void {
            $table->id();
            $table->string('origin', 12);
            $table->unsignedBigInteger('registration_id');
            $table->decimal('amount', 12, 2)->nullable();
            $table->date('starts')->nullable();
            $table->date('ends')->nullable();
            $table->string('campus_address', 500)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('ine', 40)->nullable();
            $table->string('alternate', 180)->nullable();
            $table->string('alternate_phone', 40)->nullable();
            $table->unsignedInteger('updated_by');
            $table->timestamps();
            $table->index(['origin', 'registration_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccyf_contract_terms');
    }
};
