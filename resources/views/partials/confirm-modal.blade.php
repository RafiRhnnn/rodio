{{-- Confirmation modal used by every [data-confirm] trigger. --}}
<div id="confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 p-4">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
        <h3 id="confirm-title" class="text-base font-semibold text-slate-900">Konfirmasi</h3>
        <p id="confirm-message" class="mt-2 text-sm text-slate-600"></p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" id="confirm-cancel" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Batal</button>
            <button type="button" id="confirm-ok" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-500">Ya, Lanjutkan</button>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
        (() => {
            const modal = document.getElementById('confirm-modal')
            if (! modal) return
            const title = document.getElementById('confirm-title')
            const message = document.getElementById('confirm-message')
            const ok = document.getElementById('confirm-ok')
            let pending = null

            const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); pending = null }

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-confirm]')
                if (! trigger) return
                event.preventDefault()
                pending = trigger.closest('form')
                title.textContent = trigger.dataset.title || 'Konfirmasi aksi ini'
                message.textContent = trigger.dataset.message || ''
                modal.classList.remove('hidden')
                modal.classList.add('flex')
            })

            ok.addEventListener('click', () => { const form = pending; close(); if (form) form.submit() })
            document.getElementById('confirm-cancel').addEventListener('click', close)
            modal.addEventListener('click', (event) => { if (event.target === modal) close() })
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close() })
        })()
    </script>
@endpush
@endonce
