<div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="border-b border-slate-200 px-6 py-4 text-sm font-semibold text-slate-900">{{ $conversions->total() }} konversi</div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-6 py-3">User</th>
                    <th class="px-6 py-3">File</th>
                    <th class="px-6 py-3">Speed</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3">Created</th>
                    <th class="px-6 py-3">Started</th>
                    <th class="px-6 py-3">Completed</th>
                    <th class="px-6 py-3">Attempts</th>
                    <th class="px-6 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($conversions as $conversion)
                    @php $downloadable = in_array($conversion->output_path, $available, true); @endphp
                    <tr class="align-top hover:bg-slate-50/70">
                        <td class="px-6 py-4">
                            <p class="font-medium text-slate-900">{{ $conversion->user->name ?? '(terhapus)' }}</p>
                            <p class="text-xs text-slate-500">{{ $conversion->user->email ?? '—' }}</p>
                        </td>
                        <td class="max-w-xs px-6 py-4">
                            <p class="truncate font-medium text-slate-900">{{ $conversion->original_filename }}</p>
                            <p class="text-xs text-slate-500">
                                {{ strtoupper($conversion->original_format) }} → {{ strtoupper($conversion->output_format ?? 'OGG') }}
                                · {{ $size($conversion->original_size) }}
                                · {{ $clock($conversion->original_duration) }} → {{ $clock($conversion->output_duration) }}
                            </p>
                            @if ($conversion->status === \App\Enums\ConversionStatus::Failed && $conversion->error_message)
                                <p class="mt-1 text-xs text-red-600">{{ $conversion->error_message }}</p>
                            @endif
                            @if ($conversion->status === \App\Enums\ConversionStatus::Completed && ! $downloadable)
                                <p class="mt-1 text-xs text-amber-600">File kedaluwarsa — riwayat tetap tersimpan.</p>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-600">{{ number_format((float) $conversion->speed, 2, ',', '.') }}x</td>
                        <td class="px-6 py-4">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $conversion->status->badgeClasses() }}">{{ $conversion->status->label() }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-500">{{ $stamp($conversion->created_at) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-500">{{ $stamp($conversion->started_at) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-slate-500">{{ $stamp($conversion->completed_at) }}</td>
                        <td class="px-6 py-4 text-slate-600">{{ $conversion->attempts }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1">
                                @if ($downloadable)
                                    <a href="{{ route('conversions.download', $conversion) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-700 transition hover:bg-indigo-50">Unduh</a>
                                @endif
                                @if ($conversion->status === \App\Enums\ConversionStatus::Failed)
                                    <form method="POST" action="{{ route('admin.conversions.retry', $conversion) }}">
                                        @csrf
                                        <button type="button" data-confirm data-title="Ulangi konversi?"
                                                data-message="Konversi dikembalikan ke antrian FFmpeg."
                                                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-50">Retry</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-6 py-16 text-center text-sm text-slate-500">Tidak ada konversi sesuai filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($conversions->hasPages())
        <div class="border-t border-slate-200 px-6 py-4">{{ $conversions->links() }}</div>
    @endif
</div>
