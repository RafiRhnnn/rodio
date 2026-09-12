<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The converter needs to know where the accepted upload lives. The
     * user-facing filename stays in original_filename; this is the server path.
     */
    public function up(): void
    {
        Schema::table('audio_conversions', function (Blueprint $table) {
            $table->string('original_path')->nullable()->after('original_filename');
        });
    }

    public function down(): void
    {
        Schema::table('audio_conversions', function (Blueprint $table) {
            $table->dropColumn('original_path');
        });
    }
};
