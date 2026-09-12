<?php

namespace App\Console\Commands;

use App\Enums\ConversionStatus;
use App\Models\AudioConversion;
use App\Services\ConversionResultService;
use Illuminate\Console\Command;

/**
 * Retention sweeper (Tahap 13).
 *
 * History rows are never deleted here: only the physical result (after
 * audio.retention_hours) and temporary uploads that never became a conversion.
 * A row whose file was swept renders as "file no longer available".
 */
class CleanupAudioFiles extends Command
{
    protected $signature = 'audio:cleanup
                            {--hours= : Override audio.retention_hours}
                            {--uploads-only : Only sweep stale temporary uploads}
                            {--stale-minutes=1440}';

    protected $description = 'Hapus file hasil yang kedaluwarsa dan file upload sementara yang telantar';

    public function handle(ConversionResultService $results): int
    {
        $hours = (int) ($this->option('hours') ?: config('audio.retention_hours'));

        if (! $this->option('uploads-only')) {
            $swept = 0;

            if ($hours > 0) {
                $cutoff = now()->subHours($hours);

                AudioConversion::query()
                    ->where('status', ConversionStatus::Completed->value)
                    ->whereNotNull('output_path')
                    ->where('completed_at', '<', $cutoff)
                    ->chunkById(200, function ($conversions) use ($results, &$swept) {
                        foreach ($conversions as $conversion) {
                            if ($results->purgeFile($conversion)) {
                                $swept++;
                            }
                        }
                    });
            }

            $this->components->info("File hasil kedaluwarsa dihapus: {$swept} (riwayat tetap utuh).");
        }

        $stale = $results->purgeStaleUploads((int) $this->option('stale-minutes'));
        $this->components->info("Upload sementara dihapus: {$stale}.");

        return self::SUCCESS;
    }
}
