<?php

namespace Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use App\Enums\ConversionStatus;
use App\Models\AudioConversion;
use App\Models\User;
use App\Services\AudioConversionService;
use App\Services\AudioConversionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tahap 11: the completed result, its player, and a download that nobody else
 * can reach — including when the file is already gone.
 */
class ConversionResultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('audio');
        Queue::fake();
    }

    private function finished(User $user, string $bytes = 'ogg-bytes'): AudioConversion
    {
        $path = 'converted/1/'.Str::uuid().'.ogg';
        Storage::disk('audio')->put($path, $bytes);

        return AudioConversion::factory()->for($user)->completed()->create([
            'output_path' => $path,
            'original_filename' => 'lagu aneh & "kutip".mp3',
            'original_duration' => 600,
            'output_duration' => 261,
            'speed' => 2.3,
        ]);
    }

    public function test_owner_downloads_the_result_with_correct_headers(): void
    {
        $user = User::factory()->create();
        $conversion = $this->finished($user);

        $response = $this->actingAs($user)->get(route('conversions.download', $conversion));

        $response->assertOk()
            ->assertHeader('Content-Type', 'audio/ogg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        // A raw user filename never reaches the header: no quotes, no path.
        $this->assertStringNotContainsString('"kutip"', $disposition);
        $this->assertStringNotContainsString('converted', $disposition);
        $this->assertSame('ogg-bytes', $response->streamedContent());
    }

    public function test_only_the_owner_and_an_admin_can_download(): void
    {
        $conversion = $this->finished(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('conversions.download', $conversion))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('conversions.download', $conversion))->assertOk();
    }

    public function test_a_guest_is_sent_to_login_instead_of_leaking_anything(): void
    {
        $conversion = $this->finished(User::factory()->create());

        $this->get(route('conversions.download', $conversion))->assertRedirect(route('login'));
    }

    public function test_a_completed_row_without_a_file_is_reported_not_broken(): void
    {
        $user = User::factory()->create();
        $conversion = $this->finished($user);
        Storage::disk('audio')->delete($conversion->output_path);

        $this->actingAs($user)
            ->get(route('conversions.download', $conversion))
            ->assertStatus(410)
            ->assertJson(['message' => 'File sudah tidak tersedia.']);

        $this->actingAs($user)->getJson(route('conversions.result', $conversion))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.download_url', null)
            ->assertJsonPath('data.message', 'File sudah tidak tersedia.');

        $this->actingAs($user)->get('/history')->assertOk()->assertSee('File tidak tersedia');
    }

    public function test_result_payload_reports_durations_and_player_url(): void
    {
        $user = User::factory()->create();
        $conversion = $this->finished($user);

        $data = $this->actingAs($user)->getJson(route('conversions.result', $conversion))
            ->assertOk()->json('data');

        $this->assertSame('10:00', $data['original_duration']);
        $this->assertSame('04:21', $data['result_duration']);
        $this->assertSame('OGG', $data['output_format']);
        $this->assertSame(2.3, $data['speed']);
        $this->assertStringContainsString('/conversions/'.$conversion->id.'/download', $data['download_url']);
    }

    public function test_other_users_cannot_read_the_result_payload(): void
    {
        $conversion = $this->finished(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->getJson(route('conversions.result', $conversion))->assertForbidden();
    }

    public function test_polling_payload_stays_limited_to_status_and_progress(): void
    {
        $user = User::factory()->create();
        $conversion = AudioConversion::factory()->for($user)->create();

        $this->actingAs($user)->getJson(route('conversions.status', $conversion))
            ->assertOk()->assertExactJson(['status' => 'queued', 'progress' => 0]);
    }

    public function test_a_real_ffmpeg_result_can_be_downloaded(): void
    {
        if (! is_file((string) config('audio.ffmpeg'))) {
            $this->markTestSkipped('FFmpeg is not installed on this machine.');
        }

        $user = User::factory()->create();
        Storage::disk('audio')->makeDirectory('uploads');
        $source = 'uploads/'.Str::uuid().'.mp3';

        (new \Symfony\Component\Process\Process([
            (string) config('audio.ffmpeg'), '-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=3', '-ac', '1', '-ar', '44100',
            Storage::disk('audio')->path($source),
        ]))->run();

        // Exactly what a worker does: claim (queued -> processing), convert,
        // then persist the result through the state service.
        $states = app(AudioConversionStateService::class);
        $conversion = AudioConversion::factory()->for($user)->create([
            'original_path' => $source,
            'original_format' => 'mp3',
            'output_format' => 'ogg',
            'speed' => 2.3,
            'preserve_pitch' => true,
            'original_duration' => 3.0,
        ]);

        $this->assertTrue($states->claim($conversion->id));

        $attributes = app(AudioConversionService::class)->convert($conversion, fn () => null);

        $this->assertTrue($states->complete($conversion->id, $attributes));

        $conversion->refresh();
        $this->assertSame(ConversionStatus::Completed, $conversion->status);
        $this->assertSame(1, $conversion->attempts);
        $this->audioDisk()->assertExists((string) $conversion->output_path);
        // Temporary input is gone once the worker is done with it.
        $this->assertNull($conversion->original_path);

        $response = $this->actingAs($user)->get(route('conversions.download', $conversion));

        $response->assertOk()->assertHeader('Content-Type', 'audio/ogg');

        // A real OGG stream starts with "OggS": proof the browser can play it,
        // not just that some bytes were stored.
        $bytes = $response->streamedContent();
        $this->assertStringStartsWith('OggS', $bytes);
        $this->assertGreaterThan(1024, strlen($bytes));
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
