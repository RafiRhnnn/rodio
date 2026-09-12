@extends('layouts.app')

@section('title', 'Converter — Audio Speed Converter')

@php
    $maxMb = rtrim(rtrim(number_format(config('audio.max_upload_mb'), 1, '.', ''), '.'), '.');
    $accept = collect(array_keys(config('audio.formats')))->map(fn ($ext) => '.'.$ext)->implode(',');
    $formatLabels = strtoupper(implode(' • ', array_keys(config('audio.formats'))));
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Converter</h1>
        <p class="mt-1 text-sm text-slate-500">Tiga langkah: unggah audio, pilih kecepatan, konversi.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- 1. Upload --}}
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <span class="grid size-6 place-items-center rounded-full bg-indigo-50 text-xs text-indigo-700">1</span>
                    Upload
                </h2>

                <div id="dropzone" tabindex="0" role="button" aria-label="Area unggah audio"
                     class="mt-4 cursor-pointer rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50/60 px-6 py-12 text-center outline-none transition hover:border-indigo-400 hover:bg-indigo-50/40 focus-visible:ring-2 focus-visible:ring-indigo-600">
                    <div id="dropzone-empty">
                        <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-white text-2xl shadow-sm ring-1 ring-slate-200" aria-hidden="true">🎵</div>
                        <p class="mt-4 text-base font-semibold text-slate-900">Upload Audio</p>
                        <p class="mt-1 text-sm text-slate-500">Drag &amp; Drop audio di sini</p>
                        <p class="my-2 text-xs uppercase tracking-wide text-slate-400">atau</p>
                        <span class="inline-flex rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">Browse Files</span>
                        <p class="mt-5 text-xs font-medium tracking-wide text-slate-400">{{ $formatLabels }}</p>
                        <p class="mt-1 text-xs text-slate-400">Maks. {{ $maxMb }} MB per file</p>
                    </div>

                    <input id="audio-input" name="audio" type="file" accept="{{ $accept }}" class="sr-only">

                    {{-- Filled in by converter.js after a file is picked --}}
                    <div id="file-card" class="hidden text-left">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-700" aria-hidden="true">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M9 18V6l10-2v12" /><circle cx="6" cy="18" r="3" /><circle cx="16" cy="16" r="3" /></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900" id="file-name">—</p>
                                    <dl class="mt-2 grid grid-cols-3 gap-3 text-xs text-slate-500">
                                        <div><dt class="uppercase tracking-wide">Size</dt><dd class="mt-0.5 font-medium text-slate-800" id="file-size">—</dd></div>
                                        <div><dt class="uppercase tracking-wide">Format</dt><dd class="mt-0.5 font-medium text-slate-800" id="file-format">—</dd></div>
                                        <div><dt class="uppercase tracking-wide">Duration</dt><dd class="mt-0.5 font-medium text-slate-800" id="file-duration">—</dd></div>
                                    </dl>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" id="change-file" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Change File</button>
                                <button type="button" id="remove-file" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50">Remove</button>
                            </div>
                        </div>
                        <audio id="audio-preview" controls class="mt-4 w-full" preload="metadata"></audio>
                    </div>

                    <div id="uploading" class="hidden">
                        <div class="mx-auto grid size-12 animate-pulse place-items-center rounded-2xl bg-white text-2xl shadow-sm ring-1 ring-slate-200" aria-hidden="true">⏳</div>
                        <p class="mt-4 text-sm font-medium text-slate-900">Mengunggah file…</p>
                        <div class="mx-auto mt-3 h-1.5 w-48 overflow-hidden rounded-full bg-slate-200">
                            <div class="h-full w-1/2 animate-pulse rounded-full bg-indigo-600"></div>
                        </div>
                    </div>
                </div>

                <p id="upload-error" class="mt-3 hidden rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200"></p>
                <p id="upload-ok" class="mt-3 hidden rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200"></p>
            </section>

            {{-- 2. Choose speed --}}
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <span class="grid size-6 place-items-center rounded-full bg-indigo-50 text-xs text-indigo-700">2</span>
                    Choose Speed
                </h2>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach (config('audio.speed.presets') as $preset)
                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-indigo-300 hover:bg-indigo-50/40 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50 has-[:checked]:ring-1 has-[:checked]:ring-indigo-600">
                            <span>
                                <span class="block text-sm font-medium text-slate-900">{{ $preset['label'] }}</span>
                                @if ((float) $preset['value'] === (float) config('audio.speed.default'))
                                    <span class="text-xs text-indigo-600">rekomendasi default</span>
                                @endif
                            </span>
                            <span class="text-sm font-semibold text-slate-700">{{ number_format($preset['value'], 1, ',', '.') }}x</span>
                            <input type="radio" name="speed" value="{{ $preset['value'] }}" class="sr-only"
                                   @checked((float) $preset['value'] === (float) config('audio.speed.default'))>
                        </label>
                    @endforeach

                    <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <label for="custom-speed" class="text-sm font-medium text-slate-900">Custom Speed</label>
                        <div class="flex items-center gap-1.5">
                            <input id="custom-speed" type="number" step="0.01" min="{{ config('audio.speed.min') }}" max="{{ config('audio.speed.max') }}"
                                   placeholder="{{ config('audio.speed.default') }}"
                                   class="w-24 rounded-lg border-0 bg-white py-1.5 px-2.5 text-right text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600">
                            <span class="text-sm text-slate-500">x</span>
                        </div>
                    </div>
                </div>

                <p class="mt-3 text-xs text-slate-500">Rentang yang diizinkan: {{ config('audio.speed.min') }}x &ndash; {{ config('audio.speed.max') }}x.</p>
            </section>
        </div>


        {{-- 3. Convert --}}
        <div class="space-y-6 lg:col-span-1">
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:sticky lg:top-24">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <span class="grid size-6 place-items-center rounded-full bg-indigo-50 text-xs text-indigo-700">3</span>
                    Convert
                </h2>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">File</dt><dd class="max-w-[10rem] truncate font-medium text-slate-900" id="summary-file">Belum ada file</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Kecepatan</dt><dd class="font-medium text-slate-900" id="summary-speed">2.3x</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Pitch</dt><dd class="font-medium text-slate-900" id="summary-pitch">Dipertahankan</dd></div>
                </dl>

                <label class="mt-5 flex cursor-pointer items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <input id="preserve-pitch" type="checkbox" value="1" checked
                           class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                    Preserve Pitch
                </label>

                <button type="button" id="convert-button" disabled
                        class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none">
                    <svg id="convert-spinner" class="hidden size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" /></svg>
                    <span id="convert-label">Convert Audio</span>
                </button>

                <p id="convert-help" class="mt-3 text-xs text-slate-500">Hasil conversion disimpan sebagai OGG.</p>
            </section>

            {{-- Conversion status (Tahap 10) --}}
            <section id="status-card" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                {{-- Skeleton: shown while the first status poll is in flight --}}
                <div id="status-skeleton" class="hidden space-y-3" aria-hidden="true">
                    <div class="h-4 w-1/3 animate-pulse rounded bg-slate-200"></div>
                    <div class="h-2 w-full animate-pulse rounded bg-slate-200"></div>
                    <div class="h-4 w-2/3 animate-pulse rounded bg-slate-200"></div>
                </div>

                <div id="status-empty" class="text-sm text-slate-500">
                    Belum ada konversi berjalan.
                </div>

                <div id="status-body" class="hidden">
                    <div class="flex items-center gap-2">
                        <span id="status-icon" class="text-base leading-none" aria-hidden="true">⏳</span>
                        <p id="status-text" class="text-sm font-semibold text-slate-900">Menunggu diproses…</p>
                    </div>

                    <div id="progress-wrap" class="mt-4 hidden">
                        <div id="progress-bar" class="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                            <div id="progress-fill" class="h-full w-0 rounded-full bg-indigo-600 transition-[width] duration-500"></div>
                        </div>
                        <p id="progress-label" class="mt-2 text-xs text-slate-500">Menghitung progres…</p>
                    </div>

                    <p id="status-result" class="mt-3 hidden text-sm"></p>

                    {{-- Result panel (Tahap 11): only rendered when the file exists --}}
                    <div id="result-panel" class="mt-4 hidden rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <p class="flex items-center gap-2 text-sm font-semibold text-emerald-700">
                            <span aria-hidden="true">✓</span> Audio Successfully Converted
                        </p>
                        <dl class="mt-3 space-y-1.5 text-xs">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">File</dt><dd class="max-w-[11rem] truncate font-medium text-slate-900" id="result-file">—</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Speed</dt><dd class="font-medium text-slate-900" id="result-speed">—</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Output</dt><dd class="font-medium text-slate-900" id="result-format">—</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Original Duration</dt><dd class="font-medium text-slate-900" id="result-original-duration">—</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Result Duration</dt><dd class="font-medium text-slate-900" id="result-duration">—</dd></div>
                        </dl>
                        <audio id="result-player" controls preload="none" class="mt-4 w-full"></audio>
                        <a id="result-download" href="#" download
                           class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0 4-4m-4 4-4-4M5 19h14" /></svg>
                            <span id="result-download-label">Download OGG</span>
                        </a>
                    </div>

                    {{-- Missing-file state: never a broken player or link --}}
                    <div id="result-missing" class="mt-4 hidden rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                        File sudah tidak tersedia.
                    </div>

                    <div id="result-skeleton" class="mt-4 hidden space-y-2" aria-hidden="true">
                        <div class="h-4 w-1/2 animate-pulse rounded bg-slate-200"></div>
                        <div class="h-9 w-full animate-pulse rounded bg-slate-200"></div>
                        <div class="h-9 w-full animate-pulse rounded bg-slate-200"></div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <button type="button" id="cancel-button" class="hidden rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Batalkan</button>
                        <a id="history-link" href="{{ route('history') }}" class="hidden rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Lihat Riwayat</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $converterConfig = [
            'uploadUrl' => route('converter.upload.store'),
            'destroyUrl' => route('converter.upload.destroy'),
            'convertUrl' => route('conversion.store'),
            'statusUrl' => '/api/conversions/',
            'csrf' => csrf_token(),
            'maxBytes' => (int) (config('audio.max_upload_mb') * 1024 * 1024),
            'formats' => array_map('strtolower', array_keys(config('audio.formats'))),
            'defaultSpeed' => config('audio.speed.default'),
            'minSpeed' => config('audio.speed.min'),
            'maxSpeed' => config('audio.speed.max'),
            'upload' => $upload,
        ];
    @endphp
    <script type="application/json" id="converter-config">@json($converterConfig)</script>
@endpush

