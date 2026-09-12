@extends('layouts.guest')

@section('title', 'Login — Audio Speed Converter')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight text-slate-900">Masuk ke akun Anda</h1>
    <p class="mt-1 text-sm text-slate-500">Akun dibuat hanya oleh administrator. Tidak ada pendaftaran mandiri.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5" novalidate>
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
            <input id="email" name="email" type="email" autocomplete="username" required autofocus
                   value="{{ old('email') }}"
                   class="block w-full rounded-xl border-0 py-2.5 pr-3 pl-3.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
                   placeholder="anda@perusahaan.com">
            @error('email')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   class="block w-full rounded-xl border-0 py-2.5 pr-3 pl-3.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
                   placeholder="••••••••">
            @error('password')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1"
                   class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
            Ingat saya
        </label>

        <button type="submit"
                class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
            Masuk
        </button>
    </form>
@endsection
