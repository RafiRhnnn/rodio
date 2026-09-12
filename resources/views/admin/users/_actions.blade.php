<div class="flex items-center justify-end gap-1">
    <a href="{{ route('admin.users.edit', $user) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100">Edit</a>

    <form method="POST" action="{{ route('admin.users.status', $user) }}">
        @csrf
        @method('PATCH')
        <button type="button" data-confirm
                data-title="{{ $user->isActive() ? 'Nonaktifkan akun?' : 'Aktifkan akun?' }}"
                data-message="{{ $user->isActive() ? "\"{$user->name}\" tidak akan bisa login lagi." : "\"{$user->name}\" akan bisa login kembali." }}"
                class="rounded-lg px-2.5 py-1.5 text-xs font-medium transition hover:bg-slate-100 {{ $user->isActive() ? 'text-amber-700' : 'text-emerald-700' }}">
            {{ $user->isActive() ? 'Nonaktifkan' : 'Aktifkan' }}
        </button>
    </form>

    <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
        @csrf
        @method('DELETE')
        <button type="button" data-confirm data-title="Hapus pengguna?"
                data-message="Akun &quot;{{ $user->name }}&quot; dan seluruh data konversinya dihapus permanen."
                class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50">Hapus</button>
    </form>
</div>
