<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadAudioRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Converter screen + temporary audio upload (Tahap 4 & 5).
 *
 * No FFmpeg, no queue, no conversion: an accepted file is only validated and
 * parked on the private disk until the converter stage picks it up.
 */
class ConverterController extends Controller
{
    public function index(Request $request): View
    {
        return view('converter.index', [
            'upload' => $request->session()->get('audio_upload'),
        ]);
    }

    public function store(UploadAudioRequest $request): JsonResponse
    {
        $file = $request->file('audio');

        // The user's filename is never reused as a server filename: the object
        // is written as {uuid}.{ext} on a disk that is not web-served. The
        // extension comes from the sniffed bytes, because a file called .mp3
        // that carries an AAC stream is decoded as m4a by FFmpeg.
        $id = (string) Str::uuid();
        $extension = $request->detectedExtension();

        $this->forgetUpload($request);

        $disk = Storage::disk(config('audio.disk'));
        $path = $disk->putFileAs(config('audio.upload_path'), $file, $id.'.'.$extension);

        abort_unless($path !== false, 422, 'File gagal disimpan. Silakan coba lagi.');

        $upload = [
            'id' => $id,
            'filename' => Str::limit($file->getClientOriginalName(), 100, ''),
            'stored_path' => $path,
            'size' => (int) $file->getSize(),
            'format' => strtoupper($extension),
            'duration' => $request->filled('duration') ? round((float) $request->input('duration'), 2) : null,
        ];

        $request->session()->put('audio_upload', $upload);

        return response()->json(['data' => $this->present($upload)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->forgetUpload($request);

        return response()->json(['data' => null]);
    }

    private function forgetUpload(Request $request): void
    {
        $upload = $request->session()->get('audio_upload');
        $path = is_array($upload) ? ($upload['stored_path'] ?? null) : null;

        // Only ever delete the object this session created, never a caller-supplied
        // path: the pattern below is the exact shape the uploader writes.
        $pattern = '#^'.preg_quote(config('audio.upload_path'), '#').'/[0-9a-f-]{36}\.[a-z0-9]{2,5}$#';

        if (is_string($path) && preg_match($pattern, $path)) {
            Storage::disk(config('audio.disk'))->delete($path);
        }

        $request->session()->forget('audio_upload');
    }

    /**
     * @param  array<string, mixed>  $upload
     * @return array<string, mixed>
     */
    private function present(array $upload): array
    {
        return [
            ...$upload,
            'size_human' => number_format($upload['size'] / 1048576, 1).' MB',
            'duration_human' => $upload['duration'] === null
                ? null
                : gmdate('i:s', (int) $upload['duration']),
        ];
    }
}
