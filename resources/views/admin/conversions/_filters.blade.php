@php $field = 'block w-full rounded-xl border-0 bg-white py-2 px-3 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600'; @endphp

<form method="GET" action="{{ route('admin.conversions') }}" class="mb-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div>
            <label for="f-user" class="mb-1.5 block text-xs font-medium text-slate-600">User</label>
            <input id="f-user" name="user" type="text" value="{{ $filters['user'] ?? '' }}" placeholder="Nama atau email" class="{{ $field }}">
        </div>
        <div>
            <label for="f-status" class="mb-1.5 block text-xs font-medium text-slate-600">Status</label>
            <select id="f-status" name="status" class="{{ $field }}">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-speed" class="mb-1.5 block text-xs font-medium text-slate-600">Speed</label>
            <input id="f-speed" name="speed" type="number" step="0.01" min="0.1" max="9.99" value="{{ $filters['speed'] ?? '' }}" placeholder="2.30" class="{{ $field }}">
        </div>
        <div>
            <label for="f-from" class="mb-1.5 block text-xs font-medium text-slate-600">Tanggal dari</label>
            <input id="f-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="{{ $field }}">
        </div>
        <div>
            <label for="f-to" class="mb-1.5 block text-xs font-medium text-slate-600">Tanggal sampai</label>
            <div class="flex gap-2">
                <input id="f-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="{{ $field }}">
                <button type="submit" class="shrink-0 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">Filter</button>
            </div>
        </div>
    </div>

    @foreach (['user', 'status', 'speed', 'date_from', 'date_to'] as $key)
        @error($key) <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
    @endforeach

    @if (request()->hasAny(['user', 'status', 'speed', 'date_from', 'date_to']))
        <a href="{{ route('admin.conversions') }}" class="mt-3 inline-block text-xs font-medium text-indigo-600 hover:text-indigo-700">Reset filter</a>
    @endif
</form>
