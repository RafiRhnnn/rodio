<?php

use App\Enums\ConversionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Original (source) audio.
            $table->string('original_filename');
            $table->string('original_format', 10);
            $table->unsignedBigInteger('original_size')->nullable()->comment('bytes');
            $table->decimal('original_duration', 10, 2)->nullable()->comment('seconds');

            // Converted audio. Nullable until the converter job produces output.
            $table->string('output_format', 10)->nullable();
            $table->string('output_path')->nullable();
            $table->unsignedBigInteger('output_size')->nullable()->comment('bytes');
            $table->decimal('output_duration', 10, 2)->nullable()->comment('seconds');

            $table->decimal('speed', 4, 2)->default(2.30);
            $table->boolean('preserve_pitch')->default(false);
            $table->enum('status', array_column(ConversionStatus::cases(), 'value'))
                ->default(ConversionStatus::Queued->value);
            $table->unsignedTinyInteger('progress')->default(0)->comment('percent 0-100');
            $table->text('error_message')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_conversions');
    }
};
