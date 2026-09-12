<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ConversionStatus;
use App\Http\Controllers\Controller;
use App\Models\AudioConversion;
use App\Services\AudioConversionStateService;
use App\Services\ConversionResultService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin history (Tahap 12): every conversion, filterable, with an eager-loaded
 * owner and a bounded page size.
 */
class ConversionController extends Controller
{
    public function __construct(
        private readonly AudioConversionStateService $states,
        private readonly ConversionResultService $results
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'user' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ConversionStatus::class)],
            'speed' => ['nullable', 'numeric', 'min:0.1', 'max:9.99'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = AudioConversion::query()
            ->with('user:id,name,email')
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['speed'] ?? null, fn (Builder $q, $speed) => $q->where('speed', (float) $speed))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['user'] ?? null, function (Builder $q, $term) {
                $q->whereHas('user', fn (Builder $u) => $u
                    ->where('email', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%"));
            })
            ->latest('id');

        $conversions = $query->paginate(15)->withQueryString();

        return view('admin.conversions.index', [
            'conversions' => $conversions,
            'filters' => $filters,
            'statuses' => ConversionStatus::cases(),
            'available' => $this->results->availablePaths($conversions->pluck('output_path')->all()),
        ]);
    }

    /**
     * Re-queue a failed conversion. Illegal transitions are refused by the
     * state service, so a completed row can never be pushed back to queued.
     */
    public function retry(AudioConversion $conversion): RedirectResponse
    {
        $this->authorize('retry', $conversion);

        if (! $this->states->retry($conversion->id)) {
            return back()->withErrors(['conversion' => 'Hanya konversi berstatus gagal yang dapat diulang.']);
        }

        \App\Jobs\ProcessAudioConversion::dispatch($conversion->id);

        return back()->with('status', "Konversi \"{$conversion->original_filename}\" dikembalikan ke antrian.");
    }
}
