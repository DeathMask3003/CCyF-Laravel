<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccyf_contract_templates', function (Blueprint $table): void {
            $table->string('font_family', 24)->default('dejavusans');
            $table->decimal('font_size', 3, 1)->default(9);
        });
        Schema::table('ccyf_contract_terms', function (Blueprint $table): void {
            $table->string('name', 180)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_contract_terms', fn (Blueprint $table) => $table->dropColumn('name'));
        Schema::table('ccyf_contract_templates', fn (Blueprint $table) => $table->dropColumn(['font_family', 'font_size']));
    }
};
