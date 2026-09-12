<?php

namespace Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use App\Exceptions\AudioConversionFailed;
use App\Models\AudioConversion;
use App\Models\User;
use App\Services\AudioConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Tahap 7: real FFmpeg conversions. Skipped when this machine has no FFmpeg.
 */
class AudioConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file((string) config('audio.ffmpeg'))) {
            $this->markTestSkipped('FFmpeg is not installed on this machine.');
        }

        Storage::fake('audio');
    }

    /** Generate a real, decodable source file with lavfi (argument array, no shell). */
    private function fixture(string $extension, int $seconds = 3): string
    {
        $disk = Storage::disk('audio');
        $disk->makeDirectory('uploads');

        $relative = 'uploads/'.Str::uuid().'.'.$extension;
        $target = $disk->path($relative);

        $process = new Process([
            (string) config('audio.ffmpeg'),
            '-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'lavfi', '-i', "sine=frequency=440:duration={$seconds}",
            '-ac', '1', '-ar', '44100',
            $target,
        ]);

        $process->run();

        $this->assertTrue($process->isSuccessful(), 'fixture generation failed: '.$process->getErrorOutput());

        return $relative;
    }

    private function conversion(string $extension, float $speed, bool $preservePitch = true): AudioConversion
    {
        return AudioConversion::factory()->for(User::factory()->create())->create([
            'original_path' => $this->fixture($extension),
            'original_format' => $extension,
            'output_format' => 'ogg',
            'speed' => $speed,
            'preserve_pitch' => $preservePitch,
            'original_duration' => 3.0,
        ]);
    }

    public static function inputs(): array
    {
        return [
            'MP3 at 2.3x' => ['mp3', 2.3],
            'WAV at 2.5x' => ['wav', 2.5],
            'M4A at 2.9x' => ['m4a', 2.9],
        ];
    }

    #[DataProvider('inputs')]
    public function test_input_is_converted_to_a_playable_ogg(string $extension, float $speed): void
    {
        $conversion = $this->conversion($extension, $speed);
        $service = app(AudioConversionService::class);

        $result = $service->convert($conversion->fresh());

        $this->assertSame('ogg', $result['output_format']);
        $this->assertStringEndsWith('.ogg', $result['output_path']);
        $this->assertGreaterThan(0, $result['output_size']);
        $this->audioDisk()->assertExists($result['output_path']);

        // ffprobe can decode it => playable, and it is shorter by the speed factor.
        $probed = $service->probe((string) config('audio.ffprobe'), Storage::disk('audio')->path($result['output_path']));
        $this->assertNotNull($probed['duration']);
        $this->assertEqualsWithDelta(3 / $speed, $probed['duration'], 0.5);

        $streams = new Process([
            (string) config('audio.ffprobe'), '-v', 'error',
            '-show_entries', 'stream=codec_type', '-of', 'csv=p=0',
            Storage::disk('audio')->path($result['output_path']),
        ]);
        $streams->run();

        $this->assertStringContainsString('audio', $streams->getOutput());
    }

    public function test_temporary_input_is_removed_after_success(): void
    {
        $conversion = $this->conversion('mp3', 2.3);
        $input = $conversion->original_path;

        app(AudioConversionService::class)->convert($conversion->fresh());

        $this->audioDisk()->assertMissing($input);
        $this->assertNull($conversion->fresh()->original_path);
    }

    public function test_temporary_input_is_removed_after_failure_and_hides_ffmpeg_output(): void
    {
        $conversion = $this->conversion('mp3', 2.3);
        Storage::disk('audio')->put($conversion->original_path, 'bukan audio sama sekali');
        $input = $conversion->original_path;

        try {
            app(AudioConversionService::class)->convert($conversion->fresh());
            $this->fail('Expected AudioConversionFailed');
        } catch (AudioConversionFailed $e) {
            $this->assertStringNotContainsString('ffmpeg', strtolower($e->getMessage()));
            $this->assertStringNotContainsString('Invalid data', $e->getMessage());
        }

        $this->audioDisk()->assertMissing($input);
    }

    public function test_missing_source_file_fails_cleanly(): void
    {
        $conversion = $this->conversion('mp3', 2.3);
        Storage::disk('audio')->delete($conversion->original_path);

        $this->expectException(AudioConversionFailed::class);

        app(AudioConversionService::class)->convert($conversion->fresh());
    }

    public function test_unsupported_output_format_is_refused(): void
    {
        $conversion = $this->conversion('mp3', 2.3);
        $conversion->forceFill(['output_format' => 'exe'])->save();

        $this->expectException(AudioConversionFailed::class);

        app(AudioConversionService::class)->convert($conversion);
    }

    public function test_progress_callback_reports_real_output_time(): void
    {
        $conversion = $this->conversion('wav', 2.3);
        $reported = [];

        app(AudioConversionService::class)->convert(
            $conversion->fresh(),
            function (int $percent) use (&$reported): void {
                $reported[] = $percent;
            }
        );

        $this->assertNotEmpty($reported, 'FFmpeg progress could not be parsed');
        $this->assertLessThanOrEqual(99, max($reported));
    }

    /**
     * The audio disk as the concrete adapter: assertExists(), assertMissing()
     * and friends are test helpers on FilesystemAdapter, not on the Filesystem
     * contract Storage::disk() is typed against.
     */
    protected function audioDisk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(config('audio.disk'));

        return $disk;
    }
}
