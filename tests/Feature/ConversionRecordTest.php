<?php

namespace Tests\Feature;

use App\Enums\ConversionStatus;
use App\Jobs\ProcessAudioConversion;
use App\Models\AudioConversion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tahap 6 (conversion record), Tahap 8 (queue hand-off), Tahap 10 (status API).
 */
class ConversionRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('audio');
        Queue::fake();
    }

    private function upload(User $user): array
    {
        $this->actingAs($user);

        $this->postJson('/converter/upload', [
            'audio' => UploadedFile::fake()->create('lagu.mp3', 64, 'audio/mpeg'),
            'duration' => 272,
        ])->assertOk();

        return session('audio_upload');
    }

    public function test_conversion_request_creates_a_queued_record(): void
    {
        $user = User::factory()->create();
        $upload = $this->upload($user);

        $id = $this->postJson('/converter', ['speed' => 2.3, 'preserve_pitch' => 1])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('audio_conversions', [
            'id' => $id,
            'user_id' => $user->id,
            'original_filename' => 'lagu.mp3',
            'original_format' => 'mp3',
            'output_format' => 'ogg',
            'speed' => '2.30',
            'status' => 'queued',
            'progress' => 0,
        ]);

        $conversion = AudioConversion::findOrFail($id);

        $this->assertNotNull($conversion->queued_at);
        $this->assertNull($conversion->started_at);
        $this->assertNull($conversion->completed_at);
        $this->assertSame($upload['size'], $conversion->original_size);
        $this->assertSame(272.0, (float) $conversion->original_duration);
        $this->assertTrue($conversion->preserve_pitch);

        // The stored file now belongs to the record, not to the upload session.
        $this->assertNull(session('audio_upload'));
    }

    public function test_defaults_apply_when_the_client_sends_only_speed(): void
    {
        $user = User::factory()->create();
        $this->upload($user);

        $this->postJson('/converter', ['speed' => 2.35])->assertCreated();

        $this->assertDatabaseHas('audio_conversions', [
            'user_id' => $user->id,
            'output_format' => 'ogg',
            'speed' => '2.35',
            'status' => 'queued',
            'progress' => 0,
        ]);
    }

    public function test_user_id_status_and_progress_cannot_be_injected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->upload($user);

        $id = $this->postJson('/converter', [
            'speed' => 2.3,
            'user_id' => $other->id,
            'status' => 'completed',
            'progress' => 100,
            'output_path' => '../../etc/passwd',
        ])->assertCreated()->json('data.id');

        $conversion = AudioConversion::findOrFail($id);

        $this->assertSame($user->id, $conversion->user_id);
        $this->assertSame(ConversionStatus::Queued, $conversion->status);
        $this->assertSame(0, $conversion->progress);
        $this->assertNull($conversion->output_path);
    }

    public function test_conversion_requires_a_prior_upload(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/converter', ['speed' => 2.3])
            ->assertStatus(422);

        $this->assertSame(0, AudioConversion::count());
    }

    public function test_only_supported_output_formats_are_accepted(): void
    {
        $user = User::factory()->create();
        $this->upload($user);

        $this->postJson('/converter', ['speed' => 2.3, 'output_format' => 'exe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('output_format');

        $this->assertSame(0, AudioConversion::count());
    }

    public static function speedProvider(): array
    {
        return [
            'preset 2.1' => [2.1, true],
            'preset 2.3' => [2.3, true],
            'preset 2.5' => [2.5, true],
            'preset 2.7' => [2.7, true],
            'preset 2.9' => [2.9, true],
            'custom 2.35' => [2.35, true],
            'min bound' => [0.5, true],
            'max bound' => [4.0, true],
            'below min' => [0.4, false],
            'above max' => [4.5, false],
            'zero' => [0, false],
            'negative' => [-2.3, false],
            'text' => ['2.3; rm -rf /', false],
            'too many decimals' => [2.345, false],
        ];
    }

    #[DataProvider('speedProvider')]
    public function test_speed_range_is_validated(mixed $speed, bool $accepted): void
    {
        $this->upload(User::factory()->create());

        $response = $this->postJson('/converter', ['speed' => $speed]);

        if ($accepted) {
            $response->assertCreated();

            return;
        }

        $response->assertStatus(422)->assertJsonValidationErrors('speed');
        $this->assertSame(0, AudioConversion::count());
    }
}
