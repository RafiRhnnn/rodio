<?php

namespace Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use App\Enums\ConversionStatus;
use App\Models\AudioConversion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tahap 13: retention, crash recovery and throttling. History rows are never
 * removed here — only files.
 */
class ConversionMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('audio');
        Queue::fake();
    }

    private function finished(User $user, int $hoursOld = 1): AudioConversion
    {
        $path = 'converted/1/'.Str::uuid().'.ogg';
        Storage::disk('audio')->put($path, 'ogg');

        return AudioConversion::factory()->for($user)->completed()->create([
            'output_path' => $path,
            'completed_at' => now()->subHours($hoursOld),
        ]);
    }

    public function test_expired_results_are_swept_while_history_survives(): void
    {
        $user = User::factory()->create();
        $expired = $this->finished($user, 25);
        $fresh = $this->finished($user, 2);

        $this->artisan('audio:cleanup')->assertSuccessful();

        $this->audioDisk()->assertMissing($expired->output_path);
        $this->audioDisk()->assertExists($fresh->output_path);

        $this->assertDatabaseHas('audio_conversions', ['id' => $expired->id, 'status' => 'completed']);
        $this->actingAs($user)->get(route('conversions.download', $expired))->assertStatus(410);
    }

    public function test_sweeping_can_be_turned_off_with_a_zero_window(): void
    {
        config()->set('audio.retention_hours', 0);
        $conversion = $this->finished(User::factory()->create(), 500);

        $this->artisan('audio:cleanup')->assertSuccessful();

        $this->audioDisk()->assertExists($conversion->output_path);
    }

    public function test_uploads_that_never_became_a_conversion_are_swept(): void
    {
        Storage::disk('audio')->put('uploads/lama.mp3', 'x');
        touch(Storage::disk('audio')->path('uploads/lama.mp3'), now()->subDays(3)->timestamp);
        Storage::disk('audio')->put('uploads/baru.mp3', 'y');

        $this->artisan('audio:cleanup')->assertSuccessful();

        $this->audioDisk()->assertMissing('uploads/lama.mp3');
        $this->audioDisk()->assertExists('uploads/baru.mp3');
    }

    public function test_a_conversion_stuck_in_processing_is_recovered(): void
    {
        config()->set('audio.stuck_after_minutes', 30);

        $stuck = AudioConversion::factory()->processing()->create([
            'started_at' => now()->subMinutes(31),
            'original_path' => 'uploads/macet.mp3',
        ]);
        Storage::disk('audio')->put('uploads/macet.mp3', 'x');
        $running = AudioConversion::factory()->processing()->create(['started_at' => now()->subMinutes(5)]);

        $this->artisan('audio:recover')->assertSuccessful();

        $this->assertSame(ConversionStatus::Failed, $stuck->refresh()->status);
        $this->assertSame(ConversionStatus::Processing, $running->refresh()->status);
        $this->audioDisk()->assertMissing('uploads/macet.mp3');
        // The message shown to users is a fixed sentence, no tool output.
        $this->assertStringNotContainsString('ffmpeg', strtolower((string) $stuck->error_message));
    }

    public function test_status_polling_is_throttled(): void
    {
        config()->set('audio.rate_limits.status', 3);

        $user = User::factory()->create();
        $conversion = AudioConversion::factory()->for($user)->create();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)->getJson(route('conversions.status', $conversion))->assertOk();
        }

        $this->actingAs($user)->getJson(route('conversions.status', $conversion))->assertStatus(429);
    }

    public function test_downloads_are_throttled_separately_from_polling(): void
    {
        config()->set('audio.rate_limits.download', 2);

        $user = User::factory()->create();
        $conversion = $this->finished($user);

        $this->actingAs($user)->getJson(route('conversions.status', $conversion))->assertOk();
        $this->actingAs($user)->get(route('conversions.download', $conversion))->assertOk();
        $this->actingAs($user)->get(route('conversions.download', $conversion))->assertOk();
        $this->actingAs($user)->get(route('conversions.download', $conversion))->assertStatus(429);
    }

    public function test_creating_conversions_is_throttled(): void
    {
        config()->set('audio.rate_limits.conversion', 2);

        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (range(1, 2) as $ignored) {
            $this->postJson('/converter', ['speed' => 2.3])->assertStatus(422);
        }

        $this->postJson('/converter', ['speed' => 2.3])->assertStatus(429);
    }

    public function test_each_worker_claim_counts_one_attempt(): void
    {
        $conversion = AudioConversion::factory()->create(['attempts' => 0]);
        $states = app(\App\Services\AudioConversionStateService::class);

        $this->assertTrue($states->claim($conversion->id));
        $this->assertFalse($states->claim($conversion->id));
        $this->assertSame(1, $conversion->refresh()->attempts);
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
