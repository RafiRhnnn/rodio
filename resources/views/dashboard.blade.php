@extends('layouts.app')

@section('title', 'Dashboard — Audio Speed Converter')

@section('content')
    @php
        $steps = [
            ['title' => 'Upload audio Anda', 'desc' => 'Seret file MP3, WAV, M4A, OGG, atau FLAC ke area upload.'],
            ['title' => 'Ubah kecepatan audio', 'desc' => 'Pilih preset 2.1x sampai 2.9x, atau tentukan kecepatan custom.'],
            ['title' => 'Preview hasil', 'desc' => 'Dengarkan hasilnya sebelum diunduh.'],
            ['title' => 'Download hasil', 'desc' => 'Simpan hasil konversi ke perangkat Anda.'],
        ];
    @endphp

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="border-b border-slate-200 bg-gradient-to-br from-indigo-600 to-violet-600 px-6 py-10 sm:px-10">
            <p class="text-sm font-medium text-indigo-100">Audio Speed Converter</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                Percepat audio Anda tanpa ribet
            </h1>
            <p class="mt-2 max-w-xl text-sm text-indigo-100">
                Unggah, atur kecepatan, lalu unduh hasilnya. Empat langkah sederhana dari file mentah ke audio siap pakai.
            </p>
            <a href="{{ route('converter') }}"
               class="mt-6 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50">
                Buka Converter
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </a>
        </div>

        <ul class="grid gap-px bg-slate-200 sm:grid-cols-2">
            @foreach ($steps as $i => $step)
                <li class="flex gap-4 bg-white p-6">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700">{{ $i + 1 }}</span>
                    <div>
                        <p class="font-medium text-slate-900">{{ $step['title'] }}</p>
                        <p class="mt-0.5 text-sm text-slate-500">{{ $step['desc'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        @foreach ([['Total Konversi', '0'], ['Konversi Hari Ini', '0'], ['Riwayat', 'Belum ada']] as [$label, $value])
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $value }}</p>
            </div>
        @endforeach
    </div>
@endsection
