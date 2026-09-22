<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccyf_contract_templates', function (Blueprint $table): void {
            $table->string('institution_signer', 180)->nullable();
            $table->string('institution_role', 180)->nullable();
            $table->string('witness_signer', 180)->nullable();
            $table->string('witness_role', 180)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_contract_templates', function (Blueprint $table): void {
            $table->dropColumn(['institution_signer', 'institution_role', 'witness_signer', 'witness_role']);
        });
    }
};
