{{-- Result player for the history table. --}}
<div id="preview-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-label="Preview hasil konversi">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h3 class="truncate text-base font-semibold text-slate-900" id="preview-name">Preview</h3>
                <p class="mt-1 text-sm text-slate-500">Hasil konversi (OGG).</p>
            </div>
            <button type="button" id="preview-close" class="rounded-lg px-2 py-1 text-sm text-slate-500 transition hover:bg-slate-100">Tutup</button>
        </div>
        <audio id="preview-audio" controls class="mt-5 w-full" preload="none"></audio>
        <p id="preview-error" class="mt-3 hidden text-sm text-red-600">File sudah tidak tersedia.</p>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (() => {
                const modal = document.getElementById('preview-modal')
                if (! modal) return
                const audio = document.getElementById('preview-audio')
                const error = document.getElementById('preview-error')

                const close = () => {
                    audio.pause()
                    audio.removeAttribute('src')
                    audio.load()
                    modal.classList.add('hidden')
                    modal.classList.remove('flex')
                }

                document.addEventListener('click', (event) => {
                    const trigger = event.target.closest('.preview-button')

                    if (trigger) {
                        event.preventDefault()
                        error.classList.add('hidden')
                        document.getElementById('preview-name').textContent = trigger.dataset.name || 'Preview'
                        modal.classList.remove('hidden')
                        modal.classList.add('flex')
                        audio.src = trigger.dataset.url
                        audio.play().catch(() => {})

                        return
                    }

                    if (event.target === modal || event.target.id === 'preview-close') close()
                })

                document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close() })
                audio.addEventListener('error', () => error.classList.remove('hidden'))
            })()
        </script>
    @endpush
@endonce
