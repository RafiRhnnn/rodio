<?php

namespace App\Models;

use App\Enums\ConversionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'original_filename',
    'original_path',
    'original_format',
    'original_size',
    'original_duration',
    'output_format',
    'output_path',
    'output_size',
    'output_duration',
    'speed',
    'preserve_pitch',
    'status',
    'progress',
    'attempts',
    'error_message',
    'queued_at',
    'started_at',
    'completed_at',
    'failed_at',
])]
class AudioConversion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ConversionStatus::class,
            'original_size' => 'integer',
            'output_size' => 'integer',
            'original_duration' => 'decimal:2',
            'output_duration' => 'decimal:2',
            'speed' => 'decimal:2',
            'preserve_pitch' => 'boolean',
            'progress' => 'integer',
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<AudioConversion>  $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', ConversionStatus::Completed);
    }
}
