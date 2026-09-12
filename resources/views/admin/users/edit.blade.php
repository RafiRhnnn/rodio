@extends('layouts.admin')

@section('title', 'Edit Pengguna — Admin')
@section('heading', 'Edit Pengguna')
@section('subheading', $user->name.' &middot; dibuat '.\Illuminate\Support\Carbon::parse($user->created_at)->translatedFormat('d M Y'))

@section('content')
    <div class="grid max-w-4xl gap-6 lg:grid-cols-5">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:col-span-3">
            <h2 class="text-sm font-semibold text-slate-900">Profil</h2>
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-5">
                @csrf
                @method('PUT')
                @include('admin.users._form')

                <div class="mt-8 flex items-center gap-3">
                    <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">Simpan Perubahan</button>
                    <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Batal</a>
                </div>
            </form>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-sm font-semibold text-slate-900">Ganti Password</h2>
                <p class="mt-1 text-xs text-slate-500">Password disimpan sebagai hash bcrypt, tidak pernah teks polos.</p>
                <form method="POST" action="{{ route('admin.users.password', $user) }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="new_password" class="mb-1.5 block text-sm font-medium text-slate-700">Password baru</label>
                        <input id="new_password" name="password" type="password" autocomplete="new-password"
                               class="block w-full rounded-xl border-0 py-2.5 px-3.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm">
                    </div>

                    <div>
                        <label for="new_password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Konfirmasi</label>
                        <input id="new_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                               class="block w-full rounded-xl border-0 py-2.5 px-3.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm">
                        @error('password') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">Perbarui Password</button>
                </form>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-sm font-semibold text-slate-900">Zona bahaya</h2>
                <p class="mt-1 text-xs text-slate-500">Menghapus pengguna juga menghapus seluruh riwayat konversinya.</p>
                @unless ($user->is(auth()->user()))
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <button type="button" data-confirm data-title="Hapus pengguna?"
                                data-message="Akun &quot;{{ $user->name }}&quot; dan seluruh data konversinya dihapus permanen."
                                class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-500">Hapus Pengguna</button>
                    </form>
                @else
                    <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500">Anda tidak dapat menghapus akun sendiri.</p>
                @endunless
            </div>
        </div>
    </div>

    @include('partials.confirm-modal')
@endsection
