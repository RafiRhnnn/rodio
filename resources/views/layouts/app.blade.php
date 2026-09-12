@extends('layouts.base')

@php
    $navLinks = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'patterns' => ['dashboard', 'home']],
        ['label' => 'Converter', 'route' => 'converter', 'patterns' => ['converter*']],
        ['label' => 'Riwayat', 'route' => 'history', 'patterns' => ['history']],
    ];
@endphp

@section('body')
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3 sm:px-6">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-xl bg-indigo-600 text-white">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path stroke-linecap="round" d="M4 12h2l2-6 3 14 3-11 2 3h4" />
                    </svg>
                </span>
                <span class="font-semibold tracking-tight text-slate-900">Audio Speed Converter</span>
            </a>

            <nav class="order-3 -mx-1 flex w-full items-center gap-1 overflow-x-auto sm:order-none sm:mx-0 sm:w-auto" aria-label="Navigasi utama">
                @foreach ($navLinks as $link)
                    <a href="{{ route($link['route']) }}"
                       @class([
                           'rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition',
                           'bg-indigo-50 text-indigo-700' => collect($link['patterns'])->contains(fn ($p) => request()->is($p)),
                           'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! collect($link['patterns'])->contains(fn ($p) => request()->is($p)),
                       ])>{{ $link['label'] }}</a>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-3">
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-medium text-slate-900">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-500">{{ auth()->user()->role->label() }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6">
        @include('partials.flash')
        @yield('content')
    </main>

    @include('partials.toasts')
@endsection
