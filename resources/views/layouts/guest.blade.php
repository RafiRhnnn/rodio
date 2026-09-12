@extends('layouts.base')

@section('body')
    <div class="min-h-full flex flex-col items-center justify-center px-4 py-12">
        <a href="{{ route('login') }}" class="mb-8 flex items-center gap-3">
            <span class="grid size-11 place-items-center rounded-2xl bg-indigo-600 text-white shadow-sm">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" d="M4 12h2l2-6 3 14 3-11 2 3h4" />
                </svg>
            </span>
            <span class="text-lg font-semibold tracking-tight text-slate-900">{{ config('app.name') }}</span>
        </a>

        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
            @include('partials.flash')
            @yield('content')
        </div>

        <p class="mt-8 text-xs text-slate-400">
            &copy; {{ date('Y') }} {{ config('app.name') }} &middot; Akun dibuat hanya oleh admin.
        </p>
    </div>
@endsection
