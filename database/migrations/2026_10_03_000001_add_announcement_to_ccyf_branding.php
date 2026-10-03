<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ccyf_branding', function (Blueprint $table): void {
            $table->boolean('announcement_visible')->default(false);
            $table->string('announcement_title', 120)->nullable();
            $table->string('announcement_description', 240)->nullable();
            $table->string('announcement_image_path')->nullable();
            $table->string('announcement_video_path')->nullable();
            $table->string('announcement_link_1_label', 80)->nullable();
            $table->text('announcement_link_1_url')->nullable();
            $table->boolean('announcement_link_1_blank')->default(false);
            $table->string('announcement_link_2_label', 80)->nullable();
            $table->text('announcement_link_2_url')->nullable();
            $table->boolean('announcement_link_2_blank')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ccyf_branding', function (Blueprint $table): void {
            $table->dropColumn([
                'announcement_visible', 'announcement_title', 'announcement_description',
                'announcement_image_path', 'announcement_video_path',
                'announcement_link_1_label', 'announcement_link_1_url', 'announcement_link_1_blank',
                'announcement_link_2_label', 'announcement_link_2_url', 'announcement_link_2_blank',
            ]);
        });
    }
};
