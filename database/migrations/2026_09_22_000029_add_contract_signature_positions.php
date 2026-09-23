<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccyf_contract_templates', function (Blueprint $table): void {
            $table->json('additional_signers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_contract_templates', function (Blueprint $table): void {
            $table->dropColumn('additional_signers');
        });
    }
};
