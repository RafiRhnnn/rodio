<?php

namespace Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use App\Enums\ConversionStatus;
use App\Jobs\ProcessAudioConversion;
use App\Models\AudioConversion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tahap 8 (queue hand-off) and Tahap 10 (status endpoint + authorization).
 */
class ConversionQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('audio');
        Queue::fake();
    }

    private function queuedConversion(User $user): AudioConversion
    {
        $this->actingAs($user);

        $this->postJson('/converter/upload', [
            'audio' => UploadedFile::fake()->create('lagu.mp3', 64, 'audio/mpeg'),
            'duration' => 272,
        ])->assertOk();

        return AudioConversion::findOrFail(
            $this->postJson('/converter', ['speed' => 2.3])->assertCreated()->json('data.id')
        );
    }

    public function test_the_http_response_only_queues_the_job(): void
    {
        $user = User::factory()->create();
        $conversion = $this->queuedConversion($user);

        Queue::assertPushedOn((string) config('audio.queue'), ProcessAudioConversion::class);
        Queue::assertPushed(ProcessAudioConversion::class, fn (ProcessAudioConversion $job) => $job->conversionId === $conversion->id);

        // Nothing ran: the record is still queued, no output file exists.
        $this->assertSame(ConversionStatus::Queued, $conversion->refresh()->status);
        $this->assertSame(0, $conversion->progress);
        $this->audioDisk()->assertExists($conversion->original_path);
    }

    public function test_job_declares_retry_backoff_and_timeout(): void
    {
        config()->set('audio.queue_tries', 3);
        config()->set('audio.queue_timeout', 600);

        $job = new ProcessAudioConversion(7);

        $this->assertSame(7, $job->conversionId);
        $this->assertSame(3, $job->tries);
        $this->assertSame(600, $job->timeout);
        $this->assertSame([10, 30, 60], $job->backoff());
        $this->assertSame((string) config('audio.queue'), $job->queue);
    }

    public function test_timeout_and_retry_are_configurable(): void
    {
        config()->set('audio.queue_tries', 5);
        config()->set('audio.queue_timeout', 900);
        config()->set('audio.queue', 'other-queue');

        $job = new ProcessAudioConversion(1);

        $this->assertSame(5, $job->tries);
        $this->assertSame(900, $job->timeout);
        $this->assertSame('other-queue', $job->queue);
    }

    public function test_status_endpoint_reports_the_documented_shape(): void
    {
        $user = User::factory()->create();
        $conversion = AudioConversion::factory()->for($user)->processing()->create(['progress' => 75]);

        $this->actingAs($user)
            ->getJson("/api/conversions/{$conversion->id}/status")
            ->assertOk()
            ->assertExactJson(['status' => 'processing', 'progress' => 75]);
    }

    public function test_status_of_another_user_is_forbidden_but_admin_may_read_it(): void
    {
        $conversion = AudioConversion::factory()->for(User::factory()->create())->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/conversions/{$conversion->id}/status")
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/conversions/{$conversion->id}/status")
            ->assertOk();
    }

    public function test_guests_get_401_from_the_status_endpoint(): void
    {
        $conversion = AudioConversion::factory()->create();

        $this->getJson("/api/conversions/{$conversion->id}/status")->assertStatus(401);
    }

    public function test_queued_conversion_can_be_cancelled_once(): void
    {
        $user = User::factory()->create();
        $conversion = AudioConversion::factory()->for($user)->create();

        $this->actingAs($user)->postJson("/api/conversions/{$conversion->id}/cancel")->assertOk();
        $this->assertSame(ConversionStatus::Cancelled, $conversion->refresh()->status);

        // Cancelling twice is a no-op, and never touches a running job.
        $this->actingAs($user)->postJson("/api/conversions/{$conversion->id}/cancel")->assertStatus(409);

        $running = AudioConversion::factory()->for($user)->processing()->create();
        $this->actingAs($user)->postJson("/api/conversions/{$running->id}/cancel")->assertStatus(409);
        $this->assertSame(ConversionStatus::Processing, $running->refresh()->status);
    }

    public function test_history_is_limited_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        AudioConversion::factory()->for($user)->create(['original_filename' => 'milik-saya.mp3']);
        AudioConversion::factory()->for(User::factory()->create())->create(['original_filename' => 'milik-orang.yaml']);

        $this->actingAs($user)
            ->get('/history')
            ->assertOk()
            ->assertSee('milik-saya')
            ->assertDontSee('milik-orang');
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
