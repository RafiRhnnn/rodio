@extends('layouts.admin')

@section('title', 'Admin Dashboard — Audio Speed Converter')
@section('heading', 'Dashboard')
@section('subheading', 'Ringkasan aktivitas pengguna dan konversi audio.')

@section('content')
    @php
        $cards = [
            ['label' => 'Total User', 'value' => $stats['total_users'], 'accent' => 'text-indigo-700 bg-indigo-50'],
            ['label' => 'User Aktif', 'value' => $stats['active_users'], 'accent' => 'text-emerald-700 bg-emerald-50'],
            ['label' => 'Total Audio', 'value' => $stats['total_audio'], 'accent' => 'text-violet-700 bg-violet-50'],
            ['label' => 'Total Konversi', 'value' => $stats['total_conversions'], 'accent' => 'text-sky-700 bg-sky-50'],
            ['label' => 'Konversi Hari Ini', 'value' => $stats['conversions_today'], 'accent' => 'text-amber-700 bg-amber-50'],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($cards as $card)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-slate-500">{{ $card['label'] }}</p>
                    <span class="grid size-8 place-items-center rounded-lg {{ $card['accent'] }}">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 19V5m0 14h16M8 15v-4m4 4V8m4 7v-6" /></svg>
                    </span>
                </div>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ number_format($card['value'], 0, ',', '.') }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-sm font-semibold text-slate-900">Tindakan cepat</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('admin.users.create') }}" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">Tambah Pengguna</a>
                <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Kelola Pengguna</a>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-sm font-semibold text-slate-900">Riwayat Konversi</h2>
            <p class="mt-1 text-sm text-slate-500">Belum ada aktivitas konversi tercatat.</p>
            <a href="{{ route('admin.conversions') }}" class="mt-4 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-700">Lihat halaman riwayat &rarr;</a>
        </div>
    </div>
@endsection
