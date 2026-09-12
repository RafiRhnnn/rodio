<?php

namespace Database\Factories;

use App\Enums\ConversionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\AudioConversion>
 */
class AudioConversionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seconds = fake()->numberBetween(30, 600);

        return [
            'user_id' => User::factory(),
            'original_filename' => fake()->slug().'.mp3',
            'original_path' => 'uploads/'.Str::uuid().'.mp3',
            'original_format' => 'mp3',
            'original_size' => $seconds * 12000,
            'original_duration' => $seconds,
            'output_format' => 'ogg',
            'speed' => 2.3,
            'preserve_pitch' => true,
            'status' => ConversionStatus::Queued,
            'progress' => 0,
            'queued_at' => now(),
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConversionStatus::Processing,
            'started_at' => now(),
            'progress' => 40,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConversionStatus::Completed,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'progress' => 100,
            'output_path' => 'converted/1/'.Str::uuid().'.ogg',
            'output_size' => 102400,
            'output_duration' => round($attributes['original_duration'] / $attributes['speed'], 2),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConversionStatus::Failed,
            'started_at' => now()->subMinute(),
            'failed_at' => now(),
            'error_message' => 'Konversi audio gagal diproses.',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConversionStatus::Cancelled,
            'completed_at' => now(),
        ]);
    }
}
