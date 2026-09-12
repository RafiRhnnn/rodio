<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attempts + the two indexes the history screens actually use:
     * admin listing (newest first) and user listing (own rows, newest first).
     */
    public function up(): void
    {
        Schema::table('audio_conversions', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('progress');
        });

        Schema::table('audio_conversions', function (Blueprint $table) {
            $table->index(['created_at', 'id'], 'audio_conversions_feed_index');
            $table->index(['user_id', 'created_at'], 'audio_conversions_user_feed_index');
        });
    }

    public function down(): void
    {
        Schema::table('audio_conversions', function (Blueprint $table) {
            $table->dropIndex('audio_conversions_feed_index');
            $table->dropIndex('audio_conversions_user_feed_index');
            $table->dropColumn('attempts');
        });
    }
};
