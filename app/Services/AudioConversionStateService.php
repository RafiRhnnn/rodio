<?php

namespace App\Services;

use App\Enums\ConversionStatus;
use App\Models\AudioConversion;
use Illuminate\Support\Carbon;

/**
 * The only place allowed to move an AudioConversion between statuses.
 *
 * Every write is a single conditional UPDATE (a compare-and-swap on the status
 * column), so the "read status -> decide -> save" race cannot happen: among two
 * workers claiming the same queued job exactly one sees affected-rows = 1.
 */
class AudioConversionStateService
{
    /**
     * queued -> processing, processing -> completed|failed,
     * queued -> cancelled, failed -> queued. Everything else is illegal.
     */
    private const TRANSITIONS = [
        'queued' => ['processing', 'cancelled'],
        'processing' => ['completed', 'failed'],
        'failed' => ['queued'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function canTransition(ConversionStatus $from, ConversionStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * Become the single owner of a queued conversion and count the attempt.
     */
    public function claim(int|string $conversionId): bool
    {
        return AudioConversion::query()
            ->whereKey($conversionId)
            ->where('status', ConversionStatus::Queued->value)
            ->update([
                'status' => ConversionStatus::Processing->value,
                'started_at' => Carbon::now(),
                'progress' => 0,
                'attempts' => \Illuminate\Support\Facades\DB::raw('attempts + 1'),
            ]) > 0;
    }

    /**
     * @param  array<string, mixed>  $attributes  output_path, output_size, ...
     */
    public function complete(int|string $conversionId, array $attributes = []): bool
    {
        return $this->apply($conversionId, ConversionStatus::Processing, ConversionStatus::Completed, [
            'progress' => 100,
            'completed_at' => Carbon::now(),
            'error_message' => null,
        ] + $attributes) > 0;
    }

    /**
     * The stored message must already be safe: never pass tool output here.
     */
    public function fail(int|string $conversionId, string $message): bool
    {
        return $this->apply($conversionId, ConversionStatus::Processing, ConversionStatus::Failed, [
            'failed_at' => Carbon::now(),
            'error_message' => mb_substr($message, 0, 1000),
        ]) > 0;
    }

    /**
     * Only a still-queued conversion can be cancelled: a running FFmpeg
     * process is not interrupted, so processing -> cancelled is refused.
     */
    public function cancel(int|string $conversionId): bool
    {
        return $this->apply($conversionId, ConversionStatus::Queued, ConversionStatus::Cancelled, [
            'completed_at' => Carbon::now(),
        ]) > 0;
    }

    public function retry(int|string $conversionId): bool
    {
        return $this->apply($conversionId, ConversionStatus::Failed, ConversionStatus::Queued, [
            'queued_at' => Carbon::now(),
            'progress' => 0,
            'started_at' => null,
            'failed_at' => null,
            'error_message' => null,
        ]) > 0;
    }

    /**
     * Recovery: fail rows that a crashed worker left in "processing" longer
     * than the configured threshold. Atomic, so concurrent runs of the recovery
     * command cannot both write the same row.
     *
     * @return array<int, int> ids that were marked failed
     */
    public function failStuck(?Carbon $olderThan = null, int $limit = 100): array
    {
        $cutoff = $olderThan ?? Carbon::now()->subMinutes((int) config('audio.stuck_after_minutes'));

        $ids = AudioConversion::query()
            ->where('status', ConversionStatus::Processing->value)
            ->where('started_at', '<', $cutoff)
            ->orderBy('started_at')
            ->limit($limit)
            ->pluck('id')
            ->all();

        foreach ($ids as $id) {
            $this->fail($id, 'Konversi terhenti karena worker tidak menyelesaikan pekerjaan. Silakan coba lagi.');
        }

        return $ids;
    }

    /**
     * Progress may only move forward, and only while processing.
     */
    public function updateProgress(int|string $conversionId, int $percent): bool
    {
        $percent = max(0, min(99, $percent));

        return $this->queryFor($conversionId)
            ->where('status', ConversionStatus::Processing->value)
            ->where('progress', '<', $percent)
            ->update(['progress' => $percent]) > 0;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function apply(int|string $conversionId, ConversionStatus $from, ConversionStatus $to, array $attributes): int
    {
        if (! $this->canTransition($from, $to)) {
            throw new \InvalidArgumentException("Transisi status {$from->value} -> {$to->value} tidak diizinkan.");
        }

        return $this->queryFor($conversionId)
            ->where('status', $from->value)
            ->update(['status' => $to->value] + $attributes);
    }

    private function queryFor(int|string $conversionId)
    {
        return AudioConversion::query()->whereKey($conversionId);
    }
}
