@extends('layouts.admin')

@section('title', 'Pengguna — Admin')
@section('heading', 'Pengguna')
@section('subheading', 'Buat, ubah, aktifkan, atau hapus akun pengguna.')

@section('content')
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Daftar Pengguna</h2>
                <p class="text-sm text-slate-500">{{ $users->total() }} akun terdaftar</p>
            </div>
            <a href="{{ route('admin.users.create') }}" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">+ Tambah User</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Nama</th>
                        <th class="px-6 py-3">Email</th>
                        <th class="px-6 py-3">Role</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Tanggal Dibuat</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                    <span class="font-medium text-slate-900">{{ $user->name }}</span>
                                    @if ($user->is(auth()->user()))
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Anda</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $user->email }}</td>
                            <td class="px-6 py-4">
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
                                    'bg-violet-50 text-violet-700 ring-violet-200' => $user->isAdmin(),
                                    'bg-slate-50 text-slate-700 ring-slate-200' => ! $user->isAdmin(),
                                ])>{{ $user->role->label() }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span @class([
                                    'rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
                                    'bg-emerald-50 text-emerald-700 ring-emerald-200' => $user->isActive(),
                                    'bg-red-50 text-red-700 ring-red-200' => ! $user->isActive(),
                                ])>{{ $user->status->label() }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-500">{{ $user->created_at->translatedFormat('d M Y') }}</td>
                            <td class="px-6 py-4">@include('admin.users._actions', ['user' => $user])</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <p class="text-sm font-medium text-slate-900">Belum ada pengguna</p>
                                <p class="mt-1 text-sm text-slate-500">Mulai dengan menambahkan akun pengguna pertama.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-slate-200 px-6 py-4">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    @include('partials.confirm-modal')
@endsection
