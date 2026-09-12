{{-- Shared create/edit fields for admin user forms. $user is a User instance. --}}
@php $editing = $user->exists; @endphp

<div class="space-y-5">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Nama</label>
        <input id="name" name="name" type="text" required value="{{ old('name', $user->name) }}"
               class="block w-full rounded-xl border-0 py-2.5 px-3.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
               placeholder="Nama lengkap">
        @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
        <input id="email" name="email" type="email" required value="{{ old('email', $user->email) }}"
               class="block w-full rounded-xl border-0 py-2.5 px-3.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
               placeholder="nama@perusahaan.com">
        @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">
                Password @unless($editing)<span class="text-red-500">*</span>@endunless
            </label>
            <input id="password" name="password" type="password" autocomplete="new-password"
                   @required(! $editing)
                   class="block w-full rounded-xl border-0 py-2.5 px-3.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
                   placeholder="{{ $editing ? 'Kosongkan jika tidak diganti' : 'Minimal 8 karakter' }}">
            @error('password') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Konfirmasi Password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                   @required(! $editing)
                   class="block w-full rounded-xl border-0 py-2.5 px-3.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
                   placeholder="Ulangi password">
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <span class="mb-1.5 block text-sm font-medium text-slate-700">Role</span>
            @if ($editing)
                <select name="role" id="role"
                        class="block w-full rounded-xl border-0 py-2.5 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm">
                    @foreach (config('audio.roles') as $role)
                        <option value="{{ $role }}" @selected(old('role', $user->role->value) === $role)>{{ ucfirst($role) }}</option>
                    @endforeach
                </select>
                @error('role') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            @else
                <input type="text" value="User" disabled
                       class="block w-full cursor-not-allowed rounded-xl border-0 bg-slate-50 py-2.5 px-3.5 text-slate-500 ring-1 ring-inset ring-slate-200 sm:text-sm">
                <p class="mt-1.5 text-xs text-slate-500">Akun admin tidak dibuat dari halaman ini.</p>
            @endif
        </div>

        <div>
            <span class="mb-1.5 block text-sm font-medium text-slate-700">Status</span>
            <div class="flex gap-2">
                @foreach (config('audio.statuses') as $status)
                    <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-600 transition has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50 has-[:checked]:text-indigo-700">
                        <input type="radio" name="status" value="{{ $status }}" class="sr-only"
                               @checked(old('status', $user->status->value) === $status)>
                        {{ ucfirst($status) }}
                    </label>
                @endforeach
            </div>
            @error('status') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
