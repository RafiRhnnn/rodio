// Converter screen (Tahap 4 & 5): drag & drop upload + speed picker.
// No conversion happens here: the file is validated, parked on the private
// disk, and its duration is read from the browser's own audio metadata.

const config = JSON.parse(document.getElementById('converter-config')?.textContent ?? '{}')
const dropzone = document.getElementById('dropzone')

if (dropzone) {
    const input = document.getElementById('audio-input')
    const emptyState = document.getElementById('dropzone-empty')
    const fileCard = document.getElementById('file-card')
    const uploading = document.getElementById('uploading')
    const errorBox = document.getElementById('upload-error')
    const okBox = document.getElementById('upload-ok')
    const preview = document.getElementById('audio-preview')
    const convertButton = document.getElementById('convert-button')
    const customSpeed = document.getElementById('custom-speed')
    const pitch = document.getElementById('preserve-pitch')
    const summaryFile = document.getElementById('summary-file')
    const show = (el, on) => el.classList.toggle('hidden', !on)
    let upload = null

    const humanSize = (bytes) => `${(bytes / 1048576).toFixed(1)} MB`
    const humanDuration = (seconds) => {
        const s = Math.max(0, Math.round(Number(seconds) || 0))
        return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
    }

    const reset = (message) => {
        show(uploading, false)
        show(fileCard, false)
        show(emptyState, true)
        upload = null
        summaryFile.textContent = 'Belum ada file'
        convertButton.disabled = true

        if (message) {
            errorBox.textContent = message
            show(errorBox, true)
            show(okBox, false)
        } else {
            show(errorBox, false)
        }
    }

    const render = (data) => {
        show(errorBox, false)
        show(uploading, false)
        upload = data
        document.getElementById('file-name').textContent = data.filename
        document.getElementById('file-size').textContent = data.size_human ?? humanSize(data.size)
        document.getElementById('file-format').textContent = data.format
        document.getElementById('file-duration').textContent = data.duration_human ?? humanDuration(data.duration)
        summaryFile.textContent = data.filename
        show(emptyState, false)
        show(fileCard, true)
        convertButton.disabled = false
    }

    const post = async (file, duration) => {
        const body = new FormData()
        body.append('audio', file)
        if (duration !== null) body.append('duration', duration)

        const response = await fetch(config.uploadUrl, {
            method: 'POST',
            body,
            headers: { 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
        }).catch(() => null)

        // A redirect (an expired session bouncing the auth middleware to
        // /login) makes fetch replay this POST as GET, which answers with an
        // HTML page: neither JSON nor an accepted upload.
        const isJson = String(response?.headers?.get('content-type') ?? '').includes('json')

        if (! response || ! isJson) {
            reset('Sesi berakhir atau permintaan dialihkan. Muat ulang halaman, lalu unggah ulang.')

            return
        }


        const payload = (await response?.json().catch(() => ({}))) ?? {}

        if (!response?.ok) {
            reset(payload?.errors?.audio?.[0] ?? 'File gagal diunggah. Silakan coba lagi.')
            return
        }

        render({ ...payload.data, duration_human: payload.data.duration_human ?? humanDuration(payload.data.duration) })
        okBox.textContent = 'File terunggah dan lolos validasi.'
        show(okBox, true)
    }

    const handleFile = (file) => {
        if (!file) return
        show(okBox, false)

        const extension = (file.name.split('.').pop() || '').toLowerCase()

        // config.formats is the extension list from config('audio.formats'). An
        // empty list means the config did not reach the page (a stale config
        // cache is the usual cause); that must not lock the user out, because
        // the server still validates extension + sniffed MIME.
        const allowed = Array.isArray(config.formats) ? config.formats : []

        if (allowed.length > 0 && !allowed.includes(extension)) return reset('Format audio tidak didukung.')
        if (file.size === 0) return reset('Berkas audio kosong atau rusak.')
        if (file.size > config.maxBytes) return reset('Ukuran file terlalu besar.')

        preview.src = URL.createObjectURL(file)
        preview.classList.remove('hidden')

        show(fileCard, false)
        show(emptyState, false)
        show(uploading, true)

        // Duration is reported by the browser; the server still sniffs the file
        // contents itself before accepting it.
        const probe = new Audio()
        probe.src = preview.src
        probe.preload = 'metadata'
        probe.onloadedmetadata = () => post(file, Number.isFinite(probe.duration) ? probe.duration : null)
        probe.onerror = () => post(file, null)
    }

    const removeUpload = async () => {
        if (upload?.id) {
            await fetch(config.destroyUrl, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
            }).catch(() => null)
        }

        input.value = ''
        preview.removeAttribute('src')
        preview.load()
        reset()
    }

    dropzone.addEventListener('click', () => input.click())
    dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault()
            input.click()
        }
    })
    input.addEventListener('change', () => handleFile(input.files[0]))
    document.getElementById('remove-file').addEventListener('click', (event) => {
        event.stopPropagation()
        removeUpload()
    })
    document.getElementById('change-file').addEventListener('click', (event) => {
        event.stopPropagation()
        input.click()
    })

    for (const type of ['dragenter', 'dragover']) {
        dropzone.addEventListener(type, (event) => {
            event.preventDefault()
            dropzone.classList.add('border-indigo-500', 'bg-indigo-50/60')
        })
    }
    for (const type of ['dragleave', 'drop']) {
        dropzone.addEventListener(type, (event) => {
            event.preventDefault()
            dropzone.classList.remove('border-indigo-500', 'bg-indigo-50/60')
        })
    }
    dropzone.addEventListener('drop', (event) => {
        event.preventDefault()
        handleFile(event.dataTransfer?.files?.[0])
    })

    // --- speed & pitch summary ---
    const summarySpeed = document.getElementById('summary-speed')
    const summaryPitch = document.getElementById('summary-pitch')

    const summarise = () => {
        const checked = document.querySelector('input[name="speed"]:checked')
        const custom = parseFloat(customSpeed.value)

        if (Number.isFinite(custom)) {
            if (custom < config.minSpeed || custom > config.maxSpeed) {
                summarySpeed.textContent = `Kecepatan harus ${config.minSpeed}x–${config.maxSpeed}x`
                return
            }

            summarySpeed.textContent = `${custom.toFixed(2).replace('.', ',')}x`
            return
        }

        summarySpeed.textContent = checked ? `${parseFloat(checked.value).toFixed(1).replace('.', ',')}x` : '—'
    }

    document.querySelectorAll('input[name="speed"]').forEach((radio) => radio.addEventListener('change', () => {
        customSpeed.value = ''
        summarise()
    }))
    customSpeed.addEventListener('input', summarise)
    pitch.addEventListener('change', () => {
        summaryPitch.textContent = pitch.checked ? 'Dipertahankan' : 'Tidak dipertahankan'
    })
    summarise()

    // --- convert (Tahap 6/8) + status polling (Tahap 10) ---
    const spinner = document.getElementById('convert-spinner')
    const label = document.getElementById('convert-label')
    const skeleton = document.getElementById('status-skeleton')
    const statusEmpty = document.getElementById('status-empty')
    const statusBody = document.getElementById('status-body')
    const statusIcon = document.getElementById('status-icon')
    const statusText = document.getElementById('status-text')
    const statusResult = document.getElementById('status-result')
    const progressWrap = document.getElementById('progress-wrap')
    const progressFill = document.getElementById('progress-fill')
    const progressLabel = document.getElementById('progress-label')
    const cancelButton = document.getElementById('cancel-button')
    const historyLink = document.getElementById('history-link')

    let conversionId = null
    let pollTimer = null

    const VIEWS = {
        queued: { icon: '⏳', text: 'Menunggu diproses…' },
        processing: { icon: '⚙️', text: 'Sedang memproses…' },
        completed: { icon: '✓', text: 'Conversion Complete' },
        failed: { icon: '✕', text: 'Conversion Failed' },
        cancelled: { icon: '✕', text: 'Dibatalkan' },
    }

    // Result panel (Tahap 11): fetched once per completed conversion.
    const resultPanel = document.getElementById('result-panel')
    const resultMissing = document.getElementById('result-missing')
    const resultSkeleton = document.getElementById('result-skeleton')
    const resultPlayer = document.getElementById('result-player')
    let resultLoaded = false

    const loadResult = async () => {
        if (!conversionId || resultLoaded) return
        resultLoaded = true
        show(resultSkeleton, true)

        const response = await fetch(`${config.statusUrl}${conversionId}/result`, {
            headers: { Accept: 'application/json' },
        }).catch(() => null)

        show(resultSkeleton, false)
        const data = (await response?.json().catch(() => null))?.data

        if (!response?.ok || !data) {
            show(resultMissing, true)
            return
        }

        if (!data.available) {
            show(resultPanel, false)
            resultPlayer.removeAttribute('src')
            show(resultMissing, true)
            return
        }

        document.getElementById('result-file').textContent = data.filename
        document.getElementById('result-speed').textContent = `${data.speed}x`
        document.getElementById('result-format').textContent = data.output_format
        document.getElementById('result-original-duration').textContent = data.original_duration ?? '—'
        document.getElementById('result-duration').textContent = data.result_duration ?? '—'
        document.getElementById('result-download-label').textContent = `Download ${data.output_format}`
        resultPlayer.src = data.download_url
        show(resultMissing, false)
        show(resultPanel, true)
    }

    const stopPolling = () => {
        if (pollTimer) {
            clearTimeout(pollTimer)
            pollTimer = null
        }
    }

    const paint = (status, progress) => {
        const view = VIEWS[status] ?? VIEWS.queued
        show(skeleton, false)
        show(statusEmpty, false)
        show(statusBody, true)
        statusIcon.textContent = view.icon
        statusText.textContent = view.text

        // Real numbers only: show the bar only once the worker reported one.
        const hasProgress = typeof progress === 'number' && progress > 0 && status !== 'completed'
        show(progressWrap, hasProgress || status === 'completed')
        progressFill.style.width = `${status === 'completed' ? 100 : Math.min(99, progress)}%`
        progressLabel.textContent = status === 'completed'
            ? 'Selesai 100%'
            : hasProgress
                ? `Progres ${progress}%`
                : 'Mengirim ke FFmpeg…'

        show(cancelButton, status === 'queued')
        show(historyLink, ['completed', 'failed', 'cancelled'].includes(status))

        show(statusResult, ['completed', 'failed', 'cancelled'].includes(status))
        statusResult.textContent = status === 'completed'
            ? 'Hasil siap di riwayat (format OGG).'
            : status === 'cancelled'
                ? 'Konversi dibatalkan.'
                : 'Konversi gagal. Silakan coba lagi atau unggah file lain.'
        statusResult.className = `mt-3 text-sm ${status === 'completed' ? 'text-emerald-700' : 'text-red-600'}`
    }

    const poll = async () => {
        if (!conversionId) return

        const response = await fetch(`${config.statusUrl}${conversionId}/status`, {
            headers: { 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
        }).catch(() => null)

        if (! response?.ok) {
            stopPolling()
            spinner.classList.add('hidden')
            label.textContent = 'Convert Audio'
            convertButton.disabled = ! upload

            // This is a transport/auth failure, NOT a failed conversion: the
            // row is still queued with attempts=0 and error_message=NULL, so
            // painting 'failed' here blamed FFmpeg for a dead session and sent
            // the user off looking for a bug that did not exist.
            show(skeleton, false)
            show(statusBody, true)
            show(statusResult, true)
            statusResult.className = `mt-3 text-sm ${response?.status === 401 ? 'text-amber-700' : 'text-red-600'}`
            statusResult.textContent = response?.status === 401
                ? 'Sesi Anda berakhir, jadi status tidak dapat dibaca. Login ulang lalu buka riwayat: konversi Anda masih antre dan tidak hilang.'
                : response?.status === 429
                    ? 'Terlalu banyak permintaan status. Tunggu sebentar lalu muat ulang halaman.'
                    : `Status tidak dapat diambil (HTTP ${response?.status ?? 0}). Konversi belum tentu gagal - periksa Riwayat untuk keadaan sebenarnya.`
            show(historyLink, true)

            return
        }

        const { status, progress } = await response.json()
        paint(status, progress)

        if (['completed', 'failed', 'cancelled'].includes(status)) {
            stopPolling()
            spinner.classList.add('hidden')
            label.textContent = 'Convert Audio'
            convertButton.disabled = !upload

            if (status === 'completed') {
                loadResult()
                window.toast?.('Konversi selesai — hasil siap diputar dan diunduh.', 'success')
            } else if (status === 'failed') {
                window.toast?.('Konversi gagal. Silakan coba lagi.', 'error')
            }

            return
        }

        // 2.5s between polls, per the spec's 2-3 second window.
        pollTimer = setTimeout(poll, 2500)
    }

    convertButton.addEventListener('click', async () => {
        if (!upload) return

        show(okBox, false)
        convertButton.disabled = true
        spinner.classList.remove('hidden')
        label.textContent = 'Mengirim…'
        show(skeleton, true)
        show(statusEmpty, false)
        show(statusBody, false)

        const speed = parseFloat(customSpeed.value) || (document.querySelector('input[name="speed"]:checked')?.value ?? config.defaultSpeed)

        const response = await fetch(config.convertUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrf,
                Accept: 'application/json',
            },
            body: JSON.stringify({
                speed,
                output_format: 'ogg',
                preserve_pitch: pitch.checked,
            }),
        }).catch(() => null)

        const payload = (await response?.json().catch(() => ({}))) ?? {}

        if (!response?.ok) {
            spinner.classList.add('hidden')
            label.textContent = 'Convert Audio'
            convertButton.disabled = false
            show(skeleton, false)
            errorBox.textContent = payload?.errors?.speed?.[0] ?? payload?.message ?? 'Konversi gagal dibuat.'
            show(errorBox, true)
            return
        }

        conversionId = payload.data.id
        label.textContent = 'Diproses…'
        paint(payload.data.status, payload.data.progress)
        pollTimer = setTimeout(poll, 2500)
    })

    cancelButton.addEventListener('click', async () => {
        if (!conversionId) return

        await fetch(`${config.statusUrl}${conversionId}/cancel`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
        }).catch(() => null)

        poll()
    })

    // Restore an upload still held in the session (page reload / revisit).
    if (config.upload?.id) {
        preview.classList.add('hidden')
        render({ ...config.upload, duration_human: config.upload.duration_human ?? humanDuration(config.upload.duration) })
    }
}

