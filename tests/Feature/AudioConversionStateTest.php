<?php

namespace Tests\Feature;

use App\Enums\ConversionStatus;
use App\Jobs\ProcessAudioConversion;
use App\Models\AudioConversion;
use App\Models\User;
use App\Services\AudioConversionService;
use App\Services\AudioConversionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Tahap 9: atomic status transitions. A claim is one conditional UPDATE, so two
 * workers for the same job can never both reach FFmpeg.
 */
class AudioConversionStateTest extends TestCase
{
    use RefreshDatabase;

    private AudioConversionStateService $states;

    protected function setUp(): void
    {
        parent::setUp();

        $this->states = app(AudioConversionStateService::class);
        Storage::fake('audio');
    }

    private function converterDouble(): \Mockery\MockInterface
    {
        $spy = Mockery::mock(AudioConversionService::class);
        $spy->shouldReceive('convert')->andReturn([
            'output_path' => 'converted/1/x.ogg',
            'output_format' => 'ogg',
            'output_size' => 10,
            'output_duration' => 1.0,
        ]);
        $spy->shouldReceive('discard')->byDefault();

        app()->instance(AudioConversionService::class, $spy);

        return $spy;
    }

    public function test_only_one_of_two_workers_claims_a_queued_conversion(): void
    {
        $conversion = AudioConversion::factory()->create();

        $this->assertTrue($this->states->claim($conversion->id));
        $this->assertFalse($this->states->claim($conversion->id));

        $conversion->refresh();
        $this->assertSame(ConversionStatus::Processing, $conversion->status);
        $this->assertNotNull($conversion->started_at);
    }

    public function test_the_second_job_for_the_same_id_never_calls_ffmpeg(): void
    {
        $conversion = AudioConversion::factory()->create();
        $spy = $this->converterDouble();

        // The container injects the same dependencies the queue worker would.
        app()->call([new ProcessAudioConversion($conversion->id), 'handle']);
        app()->call([new ProcessAudioConversion($conversion->id), 'handle']);

        $spy->shouldHaveReceived('convert')->once();
        $this->assertSame(ConversionStatus::Completed, $conversion->refresh()->status);
        $this->assertSame(100, $conversion->progress);
    }

    public function test_valid_transition_paths(): void
    {
        $conversion = AudioConversion::factory()->create();
        $this->assertTrue($this->states->claim($conversion->id));
        $this->assertTrue($this->states->complete($conversion->id, ['output_path' => 'a.ogg']));
        $this->assertSame(ConversionStatus::Completed, $conversion->refresh()->status);

        $failed = AudioConversion::factory()->failed()->create();
        $this->assertTrue($this->states->retry($failed->id));
        $failed->refresh();
        $this->assertSame(ConversionStatus::Queued, $failed->status);
        $this->assertNull($failed->error_message);

        $queued = AudioConversion::factory()->create();
        $this->assertTrue($this->states->cancel($queued->id));
        $this->assertSame(ConversionStatus::Cancelled, $queued->refresh()->status);
    }

    public function test_illegal_transitions_are_refused_at_the_write_level(): void
    {
        $this->assertFalse($this->states->canTransition(ConversionStatus::Queued, ConversionStatus::Completed));
        $this->assertFalse($this->states->canTransition(ConversionStatus::Processing, ConversionStatus::Cancelled));
        $this->assertFalse($this->states->canTransition(ConversionStatus::Completed, ConversionStatus::Processing));
        $this->assertFalse($this->states->canTransition(ConversionStatus::Cancelled, ConversionStatus::Queued));

        $queued = AudioConversion::factory()->create();
        $this->assertFalse($this->states->complete($queued->id));
        $this->assertFalse($this->states->fail($queued->id, 'boom'));
        $this->assertSame(ConversionStatus::Queued, $queued->refresh()->status);
    }

    public function test_a_running_conversion_cannot_be_cancelled(): void
    {
        $conversion = AudioConversion::factory()->processing()->create();

        $this->assertFalse($this->states->cancel($conversion->id));
        $this->assertSame(ConversionStatus::Processing, $conversion->refresh()->status);
    }

    public function test_completion_and_failure_require_processing(): void
    {
        $processing = AudioConversion::factory()->processing()->create();

        $this->assertTrue($this->states->fail($processing->id, 'Konversi gagal.'));
        $processing->refresh();
        $this->assertSame(ConversionStatus::Failed, $processing->status);
        $this->assertNotNull($processing->failed_at);

        $this->assertFalse($this->states->complete($processing->id));
        $this->assertSame(ConversionStatus::Failed, $processing->refresh()->status);
    }

    public function test_progress_only_moves_forward_while_processing(): void
    {
        $processing = AudioConversion::factory()->processing()->create(['progress' => 40]);

        $this->assertFalse($this->states->updateProgress($processing->id, 20));
        $this->assertSame(40, $processing->refresh()->progress);

        $this->assertTrue($this->states->updateProgress($processing->id, 75));
        $this->assertSame(75, $processing->refresh()->progress);

        $done = AudioConversion::factory()->completed()->create();
        $this->assertFalse($this->states->updateProgress($done->id, 10));
    }
}
