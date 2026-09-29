<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccyf_usuarios', function (Blueprint $table): void {
            $table->string('google_sub', 255)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_usuarios', function (Blueprint $table): void {
            $table->dropUnique(['google_sub']);
            $table->dropColumn('google_sub');
        });
    }
};
