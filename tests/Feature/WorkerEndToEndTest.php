<?php

namespace Tests\Feature;

use App\Enums\ConversionStatus;
use App\Jobs\ProcessAudioConversion;
use App\Models\AudioConversion;
use App\Models\User;
use App\Services\AudioConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The whole pipeline through a real worker: upload -> queued row -> queue:work
 * -> FFmpeg -> completed -> preview/download. Uses the database queue driver,
 * so the back-pressure guard and the atomic claim run for real.
 */
class WorkerEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_real_worker_turns_an_upload_into_a_downloadable_ogg(): void
    {
        if (! is_file((string) config('audio.ffmpeg'))) {
            $this->markTestSkipped('FFmpeg is not installed on this machine.');
        }

        config()->set('queue.default', 'database');
        config()->set('audio.max_workers', 2);

        Storage::fake('audio');

        $user = User::factory()->create();
        $disk = Storage::disk('audio');
        $disk->makeDirectory('uploads');
        $source = 'uploads/'.Str::uuid().'.mp3';

        (new \Symfony\Component\Process\Process([
            (string) config('audio.ffmpeg'), '-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=4', '-ac', '1', '-ar', '44100',
            $disk->path($source),
        ]))->mustRun();

        $conversion = AudioConversion::factory()->for($user)->create([
            'original_path' => $source,
            'original_format' => 'mp3',
            'output_format' => 'ogg',
            'speed' => 2.3,
            'preserve_pitch' => true,
            'original_duration' => 4.0,
        ]);

        ProcessAudioConversion::dispatch($conversion->id);

        // The job really sits in the database queue on the audio queue name.
        $this->assertSame(1, DB::table('jobs')->where('queue', 'audio-conversions')->count());

        // One worker pass, exactly like `php artisan queue:work --once`.
        Artisan::call('queue:work', [
            '--queue' => 'audio-conversions',
            '--once' => true,
            '--stop-when-empty' => true,
        ]);

        $conversion->refresh();

        $this->assertSame(ConversionStatus::Completed, $conversion->status);
        $this->assertSame(1, $conversion->attempts);
        $this->assertSame(100, $conversion->progress);
        $this->assertNull($conversion->original_path, 'The temporary input must be gone.');
        $disk->assertExists((string) $conversion->output_path);

        // The queue is drained and nothing landed in failed_jobs.
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());

        // Preview + download for the owner, and nobody else.
        $this->actingAs($user)->getJson(route('conversions.result', $conversion))
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.output_format', 'OGG');

        $response = $this->actingAs($user)->get(route('conversions.download', $conversion));
        $response->assertOk()->assertHeader('Content-Type', 'audio/ogg');

        $bytes = $response->streamedContent();
        $this->assertStringStartsWith('OggS', $bytes, 'Not a real Ogg stream.');

        // 4s at 2.3x is about 1.7s; the player must not be handed a bogus length.
        $this->assertEqualsWithDelta(1.74, (float) $conversion->output_duration, 0.3);

        // History offers the working link.
        $this->actingAs($user)->get('/history')->assertOk()->assertSee('Download');

        $this->actingAs(User::factory()->create())
            ->get(route('conversions.download', $conversion))
            ->assertForbidden();
    }
}
