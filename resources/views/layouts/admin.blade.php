@extends('layouts.base')

@php
    $sidebar = [
        ['label' => 'Dashboard', 'icon' => 'M3 12h7V3H3v9Zm11 0h7V3h-7v9ZM3 21h7v-9H3v9Zm11 0h7v-9h-7v9Z', 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard'],
        ['label' => 'Pengguna', 'icon' => 'M15 19a4 4 0 0 0-6 0M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7 8a4 4 0 0 0-3-3.9M5 15a4 4 0 0 0-3 4M18 8a3 3 0 1 0 .2-6M6 8a3 3 0 1 0-.2-6', 'route' => 'admin.users.index', 'pattern' => 'admin.users.*'],
        ['label' => 'Tambah Pengguna', 'icon' => 'M12 5v14M5 12h14', 'route' => 'admin.users.create', 'pattern' => 'admin.users.create'],
        ['label' => 'Riwayat Konversi', 'icon' => 'M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', 'route' => 'admin.conversions', 'pattern' => 'admin.conversions'],
        ['label' => 'Pengaturan', 'icon' => 'M4 7h16M4 12h16M4 17h16', 'route' => 'admin.settings', 'pattern' => 'admin.settings'],
    ];
@endphp

@section('body')
    <div class="lg:grid lg:grid-cols-[17rem_1fr]">
        <aside class="border-b border-slate-200 bg-white lg:sticky lg:top-0 lg:h-screen lg:border-b-0 lg:border-r">
            <div class="flex items-center gap-2.5 px-5 py-4">
                <span class="grid size-9 place-items-center rounded-xl bg-indigo-600 text-white">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path stroke-linecap="round" d="M4 12h2l2-6 3 14 3-11 2 3h4" />
                    </svg>
                </span>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-slate-900">Admin Panel</p>
                    <p class="text-xs text-slate-500">{{ config('app.name') }}</p>
                </div>
            </div>

            <nav class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-col lg:gap-0.5 lg:overflow-visible lg:pb-0" aria-label="Navigasi admin">
                @foreach ($sidebar as $item)
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition lg:whitespace-normal',
                           'bg-indigo-50 text-indigo-700' => request()->routeIs($item['pattern']),
                           'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs($item['pattern']),
                       ])>
                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $item['icon'] }}" /></svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach

                <form method="POST" action="{{ route('logout') }}" class="lg:mt-4">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-red-50 hover:text-red-700">
                        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 17l4-5-4-5M9 7H4v10h5M19 12H9" /></svg>
                        Logout
                    </button>
                </form>
            </nav>
        </aside>

        <div class="min-w-0">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-4">
                <div>
                    <h1 class="text-lg font-semibold tracking-tight text-slate-900">@yield('heading', 'Dashboard')</h1>
                    <p class="text-sm text-slate-500">@yield('subheading')</p>
                </div>
                <div class="flex items-center gap-2 text-sm text-slate-600">
                    <span class="grid size-8 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    {{ auth()->user()->email }}
                </div>
            </header>

            <main class="px-5 py-6">
                @include('partials.flash')
                @yield('content')
            </main>
        </div>
    </div>

    @include('partials.toasts')
@endsection
