<?php

namespace Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AudioUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('audio');
        $this->actingAs(User::factory()->create());
    }

    private function mp3(int $kb = 64): UploadedFile
    {
        return UploadedFile::fake()->create('lagu.mp3', $kb, 'audio/mpeg');
    }

    /** Upload one fake file and return the first validation message for "audio". */
    private function errorFor(string $file, string $mime): string
    {
        $response = $this->postJson('/converter/upload', [
            'audio' => UploadedFile::fake()->create($file, 10, $mime),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('audio');

        return (string) $response->json('errors.audio.0');
    }

    public function test_converter_page_renders_the_upload_ui(): void
    {
        $this->get('/converter')
            ->assertOk()
            ->assertSee('Upload Audio')
            ->assertSee('Browse Files')
            ->assertSee('Preserve Pitch')
            ->assertSee('Convert Audio')
            ->assertSee('Custom Speed');
    }

    public function test_speed_presets_are_rendered(): void
    {
        $page = $this->get('/converter')->assertOk();

        foreach (['Lambat', 'Default', 'Cepat', 'Lebih Cepat', 'Ultra Cepat'] as $label) {
            $page->assertSee($label);
        }

        foreach (['2.1', '2.3', '2.5', '2.7', '2.9'] as $speed) {
            $page->assertSee($speed);
        }
    }

    public function test_a_valid_audio_file_is_accepted_and_stored_privately(): void
    {
        $response = $this->postJson('/converter/upload', [
            'audio' => $this->mp3(),
            'duration' => 272,
        ])->assertOk();

        $data = $response->json('data');

        $this->assertSame('lagu.mp3', $data['filename']);
        $this->assertSame('MP3', $data['format']);
        $this->assertSame('04:32', $data['duration_human']);
        $this->assertSame('uploads/'.$data['id'].'.mp3', $data['stored_path']);
        $this->assertSame(272.0, (float) $data['duration']);

        // Generated UUID name on a private disk, never the user's filename.
        $this->assertTrue(Str::isUuid($data['id']));
        $this->audioDisk()->assertExists('uploads/'.$data['id'].'.mp3');
        $this->audioDisk()->assertMissing('uploads/lagu.mp3');
        $this->assertSame($data['id'], session('audio_upload.id'));
    }

    public function test_unsupported_format_is_rejected(): void
    {
        $this->assertSame('Format audio tidak didukung.', $this->errorFor('catatan.txt', 'text/plain'));
    }

    public function test_extension_alone_is_not_trusted(): void
    {
        // Claims to be a supported extension, but sniffs as something else.
        $this->assertSame('Format audio tidak didukung.', $this->errorFor('palsu.m4a', 'application/x-php'));

        $this->assertSame([], Storage::disk('audio')->allFiles());
    }

    public function test_double_extension_files_are_rejected(): void
    {
        $this->assertSame('Format audio tidak didukung.', $this->errorFor('lagu.mp3.phar', 'text/plain'));
    }

    public function test_oversized_file_is_rejected(): void
    {
        $response = $this->postJson('/converter/upload', [
            'audio' => $this->mp3(((int) config('audio.max_upload_mb') + 1) * 1024),
        ])->assertStatus(422)->assertJsonValidationErrors('audio');

        $this->assertStringContainsString(
            'Ukuran file terlalu besar',
            (string) $response->json('errors.audio.0')
        );

        $this->assertSame([], Storage::disk('audio')->allFiles());
    }

    public function test_missing_file_is_rejected(): void
    {
        $this->postJson('/converter/upload')->assertStatus(422)->assertJsonValidationErrors('audio');
    }

    public function test_upload_can_be_removed_again(): void
    {
        $id = $this->postJson('/converter/upload', ['audio' => $this->mp3()])->json('data.id');
        $this->audioDisk()->assertExists('uploads/'.$id.'.mp3');

        $this->deleteJson('/converter/upload')->assertOk();

        $this->audioDisk()->assertMissing('uploads/'.$id.'.mp3');
        $this->assertNull(session('audio_upload'));
    }

    public function test_uploading_a_new_file_replaces_the_previous_one(): void
    {
        $first = $this->postJson('/converter/upload', ['audio' => $this->mp3()])->json('data.id');
        $second = $this->postJson('/converter/upload', ['audio' => $this->mp3(32)])->json('data.id');

        $this->audioDisk()->assertMissing('uploads/'.$first.'.mp3');
        $this->audioDisk()->assertExists('uploads/'.$second.'.mp3');
        $this->assertCount(1, Storage::disk('audio')->allFiles());
    }

    public function test_uploads_are_not_reachable_over_http(): void
    {
        // The audio disk root lives under storage/, outside public/.
        $root = (string) realpath(Storage::disk('audio')->path(''));
        $public = (string) realpath(public_path());

        $this->assertFalse(str_starts_with($root, $public));
        $this->assertFalse(config('filesystems.disks.audio.serve'));
    }

    /**
     * The bug this test guards: a genuine MP3 whose bytes sniff as something
     * other than the claimed extension used to be refused. Real ".mp3" files
     * carrying an AAC stream are common, and it is FFmpeg that decodes them,
     * not the filename - so the content decides and the object is stored under
     * the extension it really is.
     */
    public function test_audio_that_sniffs_differently_than_its_name_is_stored_as_what_it_really_is(): void
    {
        $response = $this->postJson('/converter/upload', [
            'audio' => UploadedFile::fake()->create('lagu.mp3', 32, 'audio/mp4'),
        ])->assertOk();

        $this->assertSame('M4A', $response->json('data.format'));
        $this->assertStringEndsWith('.m4a', (string) $response->json('data.stored_path'));
    }

    /**
     * fileinfo reports application/octet-stream for a fair number of
     * legitimate MP3s (ID3v2-heavy ones especially). Shrugging is not evidence
     * of a non-audio file, so a supported extension is given the benefit of the
     * doubt; an unsupported one with the same verdict is still refused.
     */
    public function test_an_unrecognisable_content_is_judged_by_a_supported_extension(): void
    {
        $this->postJson('/converter/upload', [
            'audio' => UploadedFile::fake()->create('lagu.mp3', 32, 'application/octet-stream'),
        ])->assertOk();

        $this->assertSame(
            'Format audio tidak didukung.',
            $this->errorFor('lagu.zip', 'application/octet-stream')
        );
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
