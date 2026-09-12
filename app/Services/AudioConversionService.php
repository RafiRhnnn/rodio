<?php

namespace App\Services;

use App\Exceptions\AudioConversionFailed;
use App\Models\AudioConversion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Runs FFmpeg for one conversion record and returns the output metadata.
 *
 * Safety:
 *  - Symfony Process gets an argument *array*: no shell, no interpolation.
 *  - Speed and sample rate are re-clamped and formatted with %.4F, so no piece
 *    of user input can ever be smuggled into a filter expression.
 *  - Paths come from config plus our own UUIDs, never from a user filename.
 *  - The temporary input file is always removed (finally block).
 *  - FFmpeg output goes to the log only; users get one safe sentence.
 */
class AudioConversionService
{
    /**
     * @return array{output_path: string, output_format: string, output_size: int, output_duration: float|null}
     */
    public function convert(AudioConversion $conversion, ?callable $onProgress = null): array
    {
        $disk = Storage::disk(config('audio.disk'));
        $inputRelative = $conversion->original_path;

        try {
            $ffmpeg = $this->binary('FFmpeg', (string) config('audio.ffmpeg'));
            $ffprobe = $this->binary('ffprobe', (string) config('audio.ffprobe'));

            if (! is_string($inputRelative) || $inputRelative === '' || ! is_file($disk->path($inputRelative))) {
                throw new AudioConversionFailed('File audio sumber tidak ditemukan. Silakan unggah ulang.');
            }

            $format = (string) ($conversion->output_format ?: config('audio.default_output_format'));
            $encoder = config('audio.outputs')[$format] ?? null;

            if (! is_array($encoder)) {
                throw new AudioConversionFailed('Format output tidak didukung.');
            }

            $speed = $this->speed($conversion);
            $source = $this->probe($ffprobe, $disk->path($inputRelative));
            $relative = trim((string) config('audio.output_path'), '/').'/'.$conversion->id.'/'.Str::uuid().'.'.$format;
            $output = $disk->path($relative);

            $this->ensureDirectory($disk, dirname($relative));

            $this->run(
                $ffmpeg,
                $disk->path($inputRelative),
                $output,
                $this->filter((bool) $conversion->preserve_pitch, $speed, $source['sample_rate']),
                $encoder,
                max(0.0, (float) ($conversion->original_duration ?: $source['duration'] ?: 0)),
                $speed,
                $onProgress
            );

            return [
                'output_path' => $relative,
                'output_format' => $format,
                'output_size' => (int) filesize($output),
                'output_duration' => $this->probe($ffprobe, $output)['duration'],
            ];
        } finally {
            // Temporary input cleanup, success or failure: remove the object and
            // the now meaningless pointer to it.
            $this->discard($disk, $inputRelative);

            if (is_string($inputRelative) && $inputRelative !== '') {
                $conversion->forceFill(['original_path' => null])->save();
            }
        }
    }

    /**
     * Remove a file this session/disk owns; used for temporary input cleanup
     * and for orphaned output when a state transition lost the race.
     */
    public function discard(Filesystem $disk, ?string $relativePath): void
    {
        if (is_string($relativePath) && $relativePath !== '' && $disk->exists($relativePath)) {
            $disk->delete($relativePath);
        }
    }

    public function outputExists(AudioConversion $conversion): bool
    {
        return is_string($conversion->output_path)
            && Storage::disk(config('audio.disk'))->exists($conversion->output_path);
    }

    private function ensureDirectory(Filesystem $disk, string $relativeDirectory): void
    {
        // FilesystemAdapter only exposes makeDirectory(); it is a no-op-safe
        // call for a directory that already exists.
        if (! is_dir($disk->path($relativeDirectory)) && ! $disk->makeDirectory($relativeDirectory)) {
            throw new AudioConversionFailed('Hasil konversi tidak dapat disimpan.');
        }
    }

    private function binary(string $label, string $path): string
    {
        if ($path === '' || ! is_file($path)) {
            throw new AudioConversionFailed("Tool {$label} tidak tersedia di server ini.");
        }

        return $path;
    }

    /**
     * FFmpeg speed factor, clamped to the configured safe range.
     */
    public function speed(AudioConversion $conversion): float
    {
        $speed = (float) $conversion->speed;

        if (! is_finite($speed) || $speed <= 0) {
            $speed = (float) config('audio.speed.default');
        }

        return round(max(
            (float) config('audio.speed.min'),
            min((float) config('audio.speed.max'), $speed)
        ), 2);
    }

    /**
     * @return array{duration: float|null, sample_rate: int}
     */
    public function probe(string $ffprobe, string $file): array
    {
        if (! is_file($file)) {
            return ['duration' => null, 'sample_rate' => 0];
        }

        $process = new Process(
            [
                $ffprobe, '-v', 'error', '-select_streams', 'a:0',
                '-show_entries', 'stream=sample_rate',
                '-show_entries', 'format=duration',
                '-of', 'json', $file,
            ],
            null,
            null,
            null,
            (float) config('audio.probe_timeout')
        );

        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('ffprobe failed', ['stderr' => mb_substr($process->getErrorOutput(), 0, 500)]);

            return ['duration' => null, 'sample_rate' => 0];
        }

        $data = json_decode($process->getOutput(), true) ?: [];

        return [
            'duration' => isset($data['format']['duration'])
                ? round((float) $data['format']['duration'], 2)
                : null,
            'sample_rate' => (int) ($data['streams'][0]['sample_rate'] ?? 0),
        ];
    }

    /**
     * preserve_pitch = true  -> time stretch (atempo chain, pitch unchanged).
     * preserve_pitch = false -> tape style (asetrate: pitch follows tempo).
     *
     * atempo only accepts a 0.5-2.0 factor per stage, hence the chain.
     */
    public function filter(bool $preservePitch, float $speed, int $sampleRate): string
    {
        if (! $preservePitch && $sampleRate > 0) {
            return sprintf('asetrate=%d*%.4F,aresample=%d', $sampleRate, $speed, $sampleRate);
        }

        $stages = [];
        $remaining = $speed;

        while ($remaining > 2.0) {
            $stages[] = 'atempo=2.0000';
            $remaining /= 2.0;
        }

        while ($remaining < 0.5) {
            $stages[] = 'atempo=0.5000';
            $remaining /= 0.5;
        }

        $stages[] = sprintf('atempo=%.4F', $remaining);

        return implode(',', $stages);
    }

    /**
     * @param  array{codec: string, bitrate: string}  $encoder
     */
    private function run(
        string $ffmpeg,
        string $input,
        string $output,
        string $filter,
        array $encoder,
        float $totalSeconds,
        float $speed,
        ?callable $onProgress
    ): void {
        $process = new Process(
            [
                $ffmpeg,
                '-hide_banner',
                '-nostdin',
                '-loglevel', 'error',
                '-progress', 'pipe:1',
                '-y',
                '-i', $input,
                '-vn',
                '-map', '0:a:0',
                '-filter:a', $filter,
                '-c:a', (string) $encoder['codec'],
                '-b:a', (string) $encoder['bitrate'],
                $output,
            ],
            null,
            null,
            null,
            (float) config('audio.process_timeout')
        );

        // The expected output length is the input length divided by the speed
        // factor: that ratio is what makes the reported percentage real.
        $expected = $totalSeconds > 0 ? $totalSeconds / $speed : 0.0;
        $step = max(1, (int) config('audio.progress_step'));
        $buffer = '';
        $reported = 0;
        $stderr = '';

        $process->run(function (string $type, string $data) use (&$buffer, &$reported, &$stderr, $expected, $step, $onProgress): void {
            if ($type === Process::ERR) {
                $stderr .= $data;

                return;
            }

            if ($onProgress === null || $expected <= 0) {
                return;
            }

            $buffer .= $data;
            $lines = explode("\n", $buffer);
            $buffer = (string) array_pop($lines);

            foreach ($lines as $line) {
                if (! preg_match('/^out_time=(\d+):(\d+):(\d{2}\.\d+)$/D', trim($line), $match)) {
                    continue;
                }

                $seconds = ((int) $match[1]) * 3600 + ((int) $match[2]) * 60 + (float) $match[3];
                $percent = (int) floor(min(99.0, ($seconds / $expected) * 100));

                if ($percent >= $reported + $step) {
                    $reported = $percent;
                    $onProgress($percent);
                }
            }
        });

        if (! $process->isSuccessful() || ! is_file($output) || (int) filesize($output) === 0) {
            Log::error('FFmpeg failed', [
                'exit_code' => $process->getExitCode(),
                'stderr' => mb_substr($stderr ?: $process->getErrorOutput(), 0, 4000),
            ]);

            if (is_file($output)) {
                @unlink($output);
            }

            throw new AudioConversionFailed('Konversi audio gagal diproses. Silakan coba lagi.');
        }
    }
}
