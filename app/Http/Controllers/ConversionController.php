<?php

namespace App\Http\Controllers;

use App\Enums\ConversionStatus;
use App\Http\Requests\StoreConversionRequest;
use App\Jobs\ProcessAudioConversion;
use App\Models\AudioConversion;
use App\Services\AudioConversionStateService;
use App\Services\ConversionResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Conversion records (Tahap 6), queue hand-off (Tahap 8), status polling
 * (Tahap 10) and result download (Tahap 11).
 *
 * Authorization is model + policy based: a path, filename or id taken from the
 * request is never trusted to imply ownership.
 */
class ConversionController extends Controller
{
    public function __construct(
        private readonly AudioConversionStateService $states,
        private readonly ConversionResultService $results
    ) {
    }

    public function store(StoreConversionRequest $request): JsonResponse
    {
        $upload = $request->session()->get('audio_upload');
        $path = is_array($upload) ? ($upload['stored_path'] ?? null) : null;
        $disk = Storage::disk(config('audio.disk'));

        if (! is_string($path) || ! $disk->exists($path)) {
            return response()->json([
                'message' => 'Unggah audio terlebih dahulu.',
                'errors' => ['audio' => ['Unggah audio terlebih dahulu.']],
            ], 422);
        }

        // user_id comes from the authenticated session: create() through the
        // relation sets it, so no request field can point at another user.
        $conversion = $request->user()->audioConversions()->create([
            'original_filename' => $upload['filename'] ?? 'audio',
            'original_path' => $path,
            'original_format' => strtolower((string) ($upload['format'] ?? pathinfo($path, PATHINFO_EXTENSION))),
            'original_size' => (int) ($upload['size'] ?? 0) ?: (int) $disk->size($path),
            'original_duration' => $upload['duration'] ?? null,
            'output_format' => $request->outputFormat(),
            'speed' => $request->speed(),
            'preserve_pitch' => $request->boolean('preserve_pitch'),
            'status' => ConversionStatus::Queued,
            'progress' => 0,
            'queued_at' => now(),
        ]);

        $request->session()->forget('audio_upload');

        ProcessAudioConversion::dispatch($conversion->id);

        return response()->json(['data' => $this->present($conversion)], 201);
    }

    public function status(Request $request, AudioConversion $conversion): JsonResponse
    {
        $this->authorize('view', $conversion);

        return response()->json([
            'status' => $conversion->status->value,
            'progress' => $conversion->progress,
        ]);
    }

    public function cancel(Request $request, AudioConversion $conversion): JsonResponse
    {
        $this->authorize('cancel', $conversion);

        if (! $this->states->cancel($conversion->id)) {
            return response()->json([
                'message' => 'Konversi sudah diproses sehingga tidak dapat dibatalkan.',
                'data' => $this->present($conversion->refresh()),
            ], 409);
        }

        return response()->json(['data' => $this->present($conversion->refresh())]);
    }

    /**
     * The only route that touches result bytes. Policy first, then a real
     * existence check, so an expired file cannot produce a broken link.
     */
    public function download(Request $request, AudioConversion $conversion): BinaryFileResponse|JsonResponse
    {
        $this->authorize('download', $conversion);

        if (! $this->results->isAvailable($conversion)) {
            return response()->json(['message' => 'File sudah tidak tersedia.'], 410);
        }

        $disk = $this->results->disk();

        // BinaryFileResponse sets Content-Disposition itself; only the type and
        // a nosniff guard are added here.
        return response()->download($disk->path($conversion->output_path), $this->results->downloadName($conversion), [
            'Content-Type' => $this->results->mimeType($conversion),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Result payload for a finished conversion (Tahap 11). Kept separate from
     * /status so the polling contract stays {status, progress}.
     */
    public function result(Request $request, AudioConversion $conversion): JsonResponse
    {
        $this->authorize('view', $conversion);

        $available = $this->results->isAvailable($conversion);

        return response()->json([
            'data' => [
                'id' => $conversion->id,
                'status' => $conversion->status->value,
                'filename' => $conversion->original_filename,
                'speed' => (float) $conversion->speed,
                'output_format' => strtoupper((string) $conversion->output_format),
                'original_duration' => $conversion->original_duration === null
                    ? null
                    : gmdate('i:s', (int) $conversion->original_duration),
                'result_duration' => $conversion->output_duration === null
                    ? null
                    : gmdate('i:s', (int) round((float) $conversion->output_duration)),
                'size' => $available ? (int) $this->results->disk()->size($conversion->output_path) : null,
                'available' => $available,
                'download_url' => $available ? route('conversions.download', $conversion) : null,
                'message' => $available ? null : 'File sudah tidak tersedia.',
            ],
        ]);
    }

    public function history(Request $request): View
    {
        // Paginated, plus ONE directory listing to know which results still
        // exist: no per-row filesystem stat, no loading all rows.
        $conversions = $request->user()->audioConversions()->latest('id')->paginate(10)->withQueryString();

        return view('history.index', [
            'conversions' => $conversions,
            'available' => $this->results->availablePaths($conversions->pluck('output_path')->all()),
        ]);
    }

    private function present(AudioConversion $conversion): array
    {
        return [
            'id' => $conversion->id,
            'status' => $conversion->status->value,
            'progress' => $conversion->progress,
            'speed' => (float) $conversion->speed,
            'output_format' => $conversion->output_format,
            'download_url' => $this->results->isAvailable($conversion)
                ? route('conversions.download', $conversion)
                : null,
            'original_filename' => $conversion->original_filename,
            'original_duration' => $conversion->original_duration === null
                ? null
                : gmdate('i:s', (int) $conversion->original_duration),
            'result_duration' => $conversion->output_duration === null
                ? null
                : gmdate('i:s', (int) round((float) $conversion->output_duration)),
            'missing_file' => $conversion->status === ConversionStatus::Completed
                && ! $this->results->isAvailable($conversion),
        ];
    }
}
