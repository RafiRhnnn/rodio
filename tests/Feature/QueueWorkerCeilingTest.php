<?php

namespace Tests\Feature;

use App\Enums\ConversionStatus;
use App\Jobs\ProcessAudioConversion;
use App\Models\AudioConversion;
use App\Services\AudioConversionService;
use App\Services\AudioConversionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The machine ceiling from config('audio.max_workers') must actually be
 * enforced: FFmpeg may not start when that many conversions are already
 * running, otherwise a small server just melts.
 */
class QueueWorkerCeilingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('audio');
        Queue::fake();
    }

    public function test_a_worker_waits_instead_of_starting_a_third_ffmpeg(): void
    {
        config()->set('audio.max_workers', 2);

        AudioConversion::factory()->count(2)->processing()->create();
        $waiting = AudioConversion::factory()->create();

        $converter = $this->mock(AudioConversionService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('convert');
        });

        app()->call([new ProcessAudioConversion($waiting->id), 'handle'], [
            'converter' => $converter,
            'states' => app(AudioConversionStateService::class),
        ]);

        $this->assertSame(ConversionStatus::Queued, $waiting->refresh()->status);
        $this->assertSame(0, $waiting->attempts, 'A deferred job must not count as an attempt.');
    }

    public function test_a_worker_runs_when_below_the_ceiling(): void
    {
        config()->set('audio.max_workers', 2);

        AudioConversion::factory()->processing()->create();
        $waiting = AudioConversion::factory()->create();

        $converter = $this->mock(AudioConversionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('convert')->once()->andReturn([
                'output_path' => 'converted/1/x.ogg',
                'output_format' => 'ogg',
                'output_size' => 2048,
                'output_duration' => 1.5,
            ]);
        });

        app()->call([new ProcessAudioConversion($waiting->id), 'handle'], [
            'converter' => $converter,
            'states' => app(AudioConversionStateService::class),
        ]);

        $this->assertSame(ConversionStatus::Completed, $waiting->refresh()->status);
    }

    public function test_the_ceiling_never_drops_below_one_worker(): void
    {
        config()->set('audio.max_workers', 0);
        $conversion = AudioConversion::factory()->create();

        $converter = $this->mock(AudioConversionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('convert')->once()->andReturn([
                'output_path' => 'converted/1/y.ogg',
                'output_format' => 'ogg',
                'output_size' => 2048,
                'output_duration' => 1.5,
            ]);
        });

        app()->call([new ProcessAudioConversion($conversion->id), 'handle'], [
            'converter' => $converter,
            'states' => app(AudioConversionStateService::class),
        ]);

        $this->assertSame(ConversionStatus::Completed, $conversion->refresh()->status);
    }
}
