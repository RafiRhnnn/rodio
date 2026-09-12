{{-- Session flash toasts + the client-side toast helper used by fetch flows. --}}
@php
    $flashes = [
        ['kind' => 'success', 'text' => session('status')],
        ['kind' => 'error', 'text' => session('error')],
    ];
    $flashes = array_filter($flashes, fn ($flash) => is_string($flash['text']) && $flash['text'] !== '');
@endphp

<div id="toasts" class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:items-end" aria-live="polite" aria-atomic="true">
    @foreach ($flashes as $flash)
        <div class="toast pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl px-4 py-3 text-sm shadow-lg ring-1 {{ $flash['kind'] === 'success' ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-red-50 text-red-800 ring-red-200' }}">
            <span aria-hidden="true">{{ $flash['kind'] === 'success' ? '✓' : '✕' }}</span>
            <p class="min-w-0 flex-1">{{ $flash['text'] }}</p>
            <button type="button" class="toast-close text-xs opacity-60 transition hover:opacity-100" aria-label="Tutup notifikasi">Tutup</button>
        </div>
    @endforeach
</div>

@once
    @push('scripts')
        <script>
            window.toast = (message, kind = 'success') => {
                const host = document.getElementById('toasts')
                if (! host) return

                const el = document.createElement('div')
                el.className = `toast pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl px-4 py-3 text-sm shadow-lg ring-1 ${
                    kind === 'success' ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-red-50 text-red-800 ring-red-200'
                }`
                el.innerHTML = `<span aria-hidden="true">${kind === 'success' ? '✓' : '✕'}</span><p class="min-w-0 flex-1"></p>`
                el.querySelector('p').textContent = message
                host.appendChild(el)
                setTimeout(() => el.remove(), 5000)
            }

            document.addEventListener('click', (event) => {
                if (event.target.classList.contains('toast-close')) event.target.closest('.toast').remove()
            })
        </script>
    @endpush
@endonce
