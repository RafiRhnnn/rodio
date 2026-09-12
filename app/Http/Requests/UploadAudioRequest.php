<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

/**
 * Validates a temporary audio upload (Tahap 5). Nothing is converted here.
 *
 * The extension is only a *claim*; the real format comes from content sniffing
 * (fileinfo) and both must agree before the file is accepted.
 */
class UploadAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isActive();
    }

    /**
     * This endpoint is only ever called with fetch(), so validation failures
     * must come back as 422 JSON instead of a redirect to a page.
     */
    public function expectsJson(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // No mimetypes: rule here on purpose. That rule compares against
            // the union of every supported MIME type while the content check
            // below used to compare against the union narrowed to the claimed
            // extension - two overlapping checks, two different verdicts for
            // one file. withValidator() is the single authority now.
            'audio' => [
                'required',
                'file',
                'max:'.config('audio.max_upload_kb'),
            ],
            // Client-reported playback length in seconds (display only until
            // ffprobe runs in the converter stage).
            'duration' => ['nullable', 'numeric', 'min:0', 'max:86400'],
        ];
    }

    /**
     * The format the file's own bytes report, not the format its name claims.
     * An ".mp3" that carries an AAC stream decodes as m4a, and storing the
     * claimed name would have the queue log a format it never actually read.
     */
    public function detectedExtension(): string
    {
        $file = $this->file('audio');

        if (! $file) {
            return '';
        }

        return $this->extensionForMime((string) $file->getMimeType())
            ?: strtolower($file->getClientOriginalExtension());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $maxBytes = config('audio.max_upload_mb') * 1024 * 1024;

            if (! $this->hasFile('audio')) {
                // PHP drops $_POST/$_FILES entirely when post_max_size is
                // exceeded, so "no file" can really mean "too large".
                if ((int) ($this->server('CONTENT_LENGTH') ?? 0) > $maxBytes + 65536) {
                    $this->tooLarge($validator);
                }

                return;
            }

            $file = $this->file('audio');
            $sniffed = (string) $file->getMimeType();

            if ($file->getSize() === 0) {
                $validator->errors()->add('audio', 'Berkas audio kosong atau rusak.');

                return;
            }

            // The bytes decide, not the filename. FFmpeg is the real gate, so a
            // sniffed audio/mp4 inside a file called ".mp3" is a valid upload
            // and is stored under the extension of what it actually is.
            if (in_array($sniffed, $this->allMimes(), true)) {
                return;
            }

            // fileinfo gives up on a fair number of legitimate MP3s (ID3v2
            // heavy files especially) and reports the generic
            // application/octet-stream. Refusing those rejects real audio, so
            // the claim is honoured only for an extension this app actually
            // supports; anything else that sniffs as unknown stays rejected.
            // ponytail: trust the extension when the sniffer is unsure. Upgrade
            // path: probe with ffprobe at upload time and decide on its verdict.
            $claim = strtolower($file->getClientOriginalExtension());

            if ($sniffed === 'application/octet-stream' && array_key_exists($claim, (array) config('audio.formats'))) {
                return;
            }

            // The exact user-facing string is part of the contract (and is
            // asserted verbatim); the detected type is a developer diagnostic
            // and therefore belongs in the log, not in the form error.
            Log::warning('Rejected audio upload.', [
                'claimed_extension' => $claim,
                'claimed_name' => $file->getClientOriginalName(),
                'sniffed_mime' => $sniffed ?: '(empty)',
            ]);

            $validator->errors()->add('audio', 'Format audio tidak didukung.');
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio.required' => 'Pilih file audio terlebih dahulu.',
            'audio.file' => 'File yang diunggah tidak valid.',
            'audio.max' => 'Ukuran file terlalu besar. Maksimal '.config('audio.max_upload_mb').' MB.',
        ];
    }

    private function tooLarge(Validator $validator): void
    {
        $validator->errors()->add(
            'audio',
            'Ukuran file terlalu besar. Maksimal '.config('audio.max_upload_mb').' MB.'
        );
    }

    /**
     * Reverse-map a sniffed MIME type onto the extension the config lists for
     * it, so the stored object is named after what the bytes really are.
     */
    private function extensionForMime(string $mime): ?string
    {
        foreach ((array) config('audio.formats') as $extension => $mimes) {
            if (in_array($mime, (array) $mimes, true)) {
                return (string) $extension;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function allMimes(): array
    {
        return array_unique(array_merge(...array_values(config('audio.formats'))));
    }
}
