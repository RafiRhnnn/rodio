@extends('layouts.admin')

@section('title', 'Tambah Pengguna — Admin')
@section('heading', 'Tambah Pengguna')
@section('subheading', 'Buat akun baru. Pengguna tidak dapat mendaftar sendiri.')

@section('content')
    <div class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            @include('admin.users._form')

            <div class="mt-8 flex items-center gap-3">
                <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">Simpan Pengguna</button>
                <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Batal</a>
            </div>
        </form>
    </div>
@endsection
