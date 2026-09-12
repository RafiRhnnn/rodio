<?php

namespace App\Console\Commands;

use App\Models\AudioConversion;
use App\Services\AudioConversionStateService;
use App\Services\ConversionResultService;
use Illuminate\Console\Command;

/**
 * Crash recovery (Tahap 13).
 *
 * A worker that dies mid-FFmpeg leaves the row at "processing" with an open
 * input file. Anything older than audio.stuck_after_minutes is marked failed
 * through the state service (so the transition stays legal) and its temporary
 * input is removed. An admin can then retry it.
 */
class RecoverStuckConversions extends Command
{
    protected $signature = 'audio:recover {--minutes= : Override audio.stuck_after_minutes}';

    protected $description = 'Tandai conversion yang macet di status processing sebagai gagal lalu bersihkan file sementara';

    public function handle(AudioConversionStateService $states, ConversionResultService $results): int
    {
        $minutes = (int) ($this->option('minutes') ?: config('audio.stuck_after_minutes'));
        $ids = $states->failStuck(now()->subMinutes($minutes));

        foreach (AudioConversion::whereIn('id', $ids)->cursor() as $conversion) {
            $results->purgeFile($conversion);
            $results->purgeInput($conversion);
        }

        $this->components->info(count($ids) === 0
            ? 'Tidak ada conversion yang macet.'
            : 'Conversion macet ditandai gagal: '.count($ids).'.');

        return self::SUCCESS;
    }
}
