<?php

namespace App\Services;

use App\Enums\ConversionStatus;
use App\Models\AudioConversion;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Everything about a finished result: is it still physically there, what is it
 * called, what MIME type does it get, and when may it be deleted.
 *
 * The row is the source of truth for history; the file is only a payload with
 * a retention window. Deleting the file never deletes the row.
 */
class ConversionResultService
{
    private const MIME = [
        'ogg' => 'audio/ogg',
        'opus' => 'audio/ogg',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'flac' => 'audio/flac',
        'm4a' => 'audio/mp4',
    ];

    public function disk(): FilesystemAdapter
    {
        return Storage::disk(config('audio.disk'));
    }

    /**
     * A download link may only be rendered when this is true.
     */
    public function isAvailable(AudioConversion $conversion): bool
    {
        return $conversion->status === ConversionStatus::Completed
            && is_string($conversion->output_path)
            && $conversion->output_path !== ''
            && $this->disk()->exists($conversion->output_path);
    }

    public function mimeType(AudioConversion $conversion): string
    {
        return self::MIME[strtolower((string) $conversion->output_format)] ?? 'application/octet-stream';
    }

    /**
     * "<original basename>-2.30x.ogg": derived from the stored name, escaped by
     * the browser's own filename handling, never taken from a query parameter.
     */
    public function downloadName(AudioConversion $conversion): string
    {
        $extension = pathinfo((string) $conversion->output_path, PATHINFO_EXTENSION)
            ?: strtolower((string) $conversion->output_format);

        $base = Str::of(pathinfo((string) $conversion->original_filename, PATHINFO_FILENAME))
            ->ascii('')
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-._')
            ->limit(60, '')
            ->value();

        $speed = rtrim(rtrim(number_format((float) $conversion->speed, 2, '.', ''), '0'), '.');

        return ($base !== '' ? $base : 'audio').'-'.$speed.'x.'.$extension;
    }

    public function expiresAt(AudioConversion $conversion): ?Carbon
    {
        $hours = (int) config('audio.retention_hours');

        if ($hours <= 0 || $conversion->completed_at === null) {
            return null;
        }

        return $conversion->completed_at->copy()->addHours($hours);
    }

    public function isExpired(AudioConversion $conversion): bool
    {
        $expiresAt = $this->expiresAt($conversion);

        return $expiresAt !== null && $expiresAt->isPast();
    }

    /**
     * Delete the physical result, keep the history row.
     */
    public function purgeFile(AudioConversion $conversion): bool
    {
        if (! is_string($conversion->output_path) || $conversion->output_path === '') {
            return false;
        }

        $deleted = $this->disk()->delete($conversion->output_path);

        // Keep the folder tidy; a missing directory is not an error.
        $directory = dirname($conversion->output_path);
        if ($deleted && $this->disk()->directoryExists($directory) && $this->disk()->files($directory) === []) {
            $this->disk()->deleteDirectory($directory);
        }

        return $deleted;
    }

    /**
     * Remove the temporary input pointer + object for a conversion that will
     * never run again (stuck recovery, cancellation before the worker starts).
     */
    public function purgeInput(AudioConversion $conversion): bool
    {
        $disk = $this->disk();

        if (! is_string($conversion->original_path) || $conversion->original_path === '') {
            return false;
        }

        $deleted = $disk->exists($conversion->original_path) ? $disk->delete($conversion->original_path) : true;

        if ($deleted) {
            $conversion->forceFill(['original_path' => null])->save();
        }

        return $deleted;
    }

    /**
     * Which of the given result paths still physically exist.
     *
     * One directory listing for a whole page of rows, instead of a filesystem
     * stat per row.
     *
     * @param  array<int, string|null>  $paths
     * @return array<int, string>
     */
    public function availablePaths(array $paths): array
    {
        $paths = array_values(array_filter(array_unique($paths)));

        if ($paths === []) {
            return [];
        }

        $present = $this->disk()->allFiles('converted');

        return array_values(array_intersect($paths, $present));
    }

    /**
     * Discard an upload that never turned into a conversion record.
     */
    public function purgeStaleUploads(int $minutes = 1440): int
    {
        $disk = $this->disk();
        $cutoff = Carbon::now()->subMinutes(max(1, $minutes));
        $removed = 0;

        foreach ($disk->files('uploads') as $path) {
            if (Carbon::createFromTimestamp($disk->lastModified($path))->lt($cutoff)) {
                $disk->delete($path);
                $removed++;
            }
        }

        return $removed;
    }
}
