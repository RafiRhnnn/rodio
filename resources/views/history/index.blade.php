@extends('layouts.app')

@section('title', 'Riwayat — Audio Speed Converter')

@php
    $size = fn ($bytes) => $bytes ? number_format($bytes / 1048576, 1).' MB' : '—';
    $clock = fn ($seconds) => $seconds === null ? '—' : gmdate('i:s', (int) $seconds);
@endphp

@section('content')
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-4">
            <div>
                <h1 class="text-sm font-semibold text-slate-900">Riwayat Konversi</h1>
                <p class="text-sm text-slate-500">{{ $conversions->total() }} konversi milik Anda sendiri.</p>
            </div>
            <a href="{{ route('converter') }}" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">Konversi Baru</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Filename</th>
                        <th class="px-6 py-3">Format</th>
                        <th class="px-6 py-3">Speed</th>
                        <th class="px-6 py-3">Original Size</th>
                        <th class="px-6 py-3">Original Duration</th>
                        <th class="px-6 py-3">Output</th>
                        <th class="px-6 py-3">Output Duration</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($conversions as $conversion)
                        @php $downloadable = in_array($conversion->output_path, $available, true); @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="max-w-[14rem] truncate px-6 py-4 font-medium text-slate-900">{{ $conversion->original_filename }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ strtoupper($conversion->original_format) }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ number_format((float) $conversion->speed, 2, ',', '.') }}x</td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-600">{{ $size($conversion->original_size) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-600">{{ $clock($conversion->original_duration) }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ strtoupper($conversion->output_format ?? 'OGG') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-600">{{ $clock($conversion->output_duration) }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $conversion->status->badgeClasses() }}">{{ $conversion->status->label() }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-500">{{ ($conversion->queued_at ?? $conversion->created_at)->translatedFormat('d M Y H:i') }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($downloadable)
                                        <button type="button" class="preview-button rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                                                data-url="{{ route('conversions.download', $conversion) }}" data-name="{{ $conversion->original_filename }}">Preview</button>
                                        <a href="{{ route('conversions.download', $conversion) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-700 transition hover:bg-indigo-50">Download</a>
                                    @elseif ($conversion->status === \App\Enums\ConversionStatus::Completed)
                                        <span class="text-xs text-slate-400">File tidak tersedia</span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-16 text-center">
                                <p class="text-sm font-medium text-slate-900">Belum ada riwayat</p>
                                <p class="mt-1 text-sm text-slate-500">Konversi pertama Anda akan muncul di sini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($conversions->hasPages())
            <div class="border-t border-slate-200 px-6 py-4">{{ $conversions->links() }}</div>
        @endif
    </div>
    {{-- preview modal + script: see include below --}}
    @include('history.preview')
@endsection
