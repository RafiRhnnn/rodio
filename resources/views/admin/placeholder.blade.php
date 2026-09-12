@extends('layouts.admin')

@section('title', 'Segera Hadir — Admin')
@section('heading', 'Segera Hadir')

@section('content')
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
        <p class="text-sm font-medium text-slate-900">Bagian ini belum diaktifkan</p>
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Navigasinya sudah tersedia. Fiturnya menyusul pada tahap pengembangan berikutnya.</p>
        <a href="{{ route('admin.dashboard') }}" class="mt-6 inline-block rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Kembali ke Dashboard</a>
    </div>
@endsection
