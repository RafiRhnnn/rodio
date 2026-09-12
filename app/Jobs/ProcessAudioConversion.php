<?php

namespace App\Jobs;

use App\Enums\ConversionStatus;
use App\Exceptions\AudioConversionFailed;
use App\Models\AudioConversion;
use App\Services\AudioConversionService;
use App\Services\AudioConversionStateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs FFmpeg for one conversion, off the HTTP request.
 *
 * The job only carries an id: everything else is re-read from the database, so
 * a payload tampered with in the queue table cannot change whose file is
 * processed or at what speed (speed itself is already clamped by the service).
 */
class ProcessAudioConversion implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public function __construct(public int $conversionId)
    {
        $this->tries = (int) config('audio.queue_tries');
        $this->timeout = (int) config('audio.queue_timeout');
        $this->onQueue((string) config('audio.queue'));
    }

    /**
     * Seconds to wait between attempts: 10s, 30s, 60s.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(
        AudioConversionService $converter,
        AudioConversionStateService $states
    ): void {
        // Back-pressure: FFmpeg is CPU bound, so never run more conversions
        // than the machine is allowed to handle at once. The job waits in the
        // queue instead of overloading the box.
        if (AudioConversion::query()
            ->where('status', ConversionStatus::Processing->value)
            ->count() >= max(1, (int) config('audio.max_workers'))
        ) {
            Log::info('Conversion deferred: worker ceiling reached', ['conversion_id' => $this->conversionId]);

            if ($this->job) {
                $this->release(10);
            }

            return;
        }

        // Atomic claim (a single conditional UPDATE). Zero affected rows means
        // another worker already owns this conversion, or it is finished /
        // cancelled: in that case FFmpeg must NOT run.
        if (! $states->claim($this->conversionId)) {
            Log::info('Conversion not claimed; skipping FFmpeg', ['conversion_id' => $this->conversionId]);

            return;
        }

        $conversion = AudioConversion::find($this->conversionId);

        if (! $conversion) {
            return;
        }

        try {
            // Deliberately outside any transaction: long FFmpeg runs must never
            // hold a database transaction open.
            $result = $converter->convert(
                $conversion,
                fn (int $percent) => $states->updateProgress($this->conversionId, $percent)
            );
        } catch (AudioConversionFailed $e) {
            // Safe message only; the service already logged the real cause.
            $states->fail($this->conversionId, $e->getMessage());

            return;
        } catch (Throwable $e) {
            Log::error('Unexpected conversion failure', [
                'conversion_id' => $this->conversionId,
                'exception' => $e->getMessage(),
            ]);

            $states->fail($this->conversionId, 'Konversi gagal karena kesalahan sistem.');

            throw $e;
        }

        if (! $states->complete($this->conversionId, $result)) {
            // The row moved underneath us: do not leave an orphan file behind.
            $converter->discard(Storage::disk(config('audio.disk')), $result['output_path']);
        }
    }

    /**
     * Last resort bookkeeping: a crashed worker leaves the row at processing.
     */
    public function failed(Throwable $exception): void
    {
        app(AudioConversionStateService::class)->fail(
            $this->conversionId,
            'Konversi gagal karena kesalahan sistem.'
        );
    }
}
