<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->latest()->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User(['status' => UserStatus::Active]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        // Admin accounts are never created from this screen.
        $data['role'] = UserRole::User;

        // The "hashed" cast on User::$password does the bcrypt work.
        $user = User::create($data);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "User \"{$user->name}\" berhasil ditambahkan.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate($this->rules(user: $user));

        $this->guardAgainstSelfLockout($user, $data);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Perubahan pada \"{$user->name}\" tersimpan.");
    }

    /**
     * Activate / deactivate an account.
     */
    public function status(Request $request, User $user): RedirectResponse
    {
        $this->guardAgainstSelfLockout($user, [
            'role' => $user->role,
            'status' => $user->isActive() ? UserStatus::Inactive : UserStatus::Active,
        ]);

        $user->update([
            'status' => $user->isActive() ? UserStatus::Inactive : UserStatus::Active,
        ]);

        return back()->with('status', "\"{$user->name}\" kini berstatus {$user->status->label()}.");
    }

    /**
     * Set a new password without touching the rest of the profile.
     */
    public function password(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', "Password untuk \"{$user->name}\" telah diganti.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($this->signedInUser())) {
            return back()->withErrors(['users' => 'Anda tidak dapat menghapus akun sendiri.']);
        }

        // Deleting a user cascades to its audio_conversions rows (FK constraint).
        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "User \"{$name}\" telah dihapus.");
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => $user
                ? ['nullable', 'string', Password::defaults(), 'confirmed']
                : ['required', 'string', Password::defaults(), 'confirmed'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            // Editing may change a role; creating from this screen always yields a "user".
            'role' => $user
                ? ['required', Rule::enum(UserRole::class)]
                : ['nullable', Rule::enum(UserRole::class)],
        ];
    }

    /**
     * An admin must not be able to lock themselves out of the admin area.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardAgainstSelfLockout(User $user, array $data): void
    {
        if (! $user->is(auth()->user())) {
            return;
        }

        $role = $data['role'] ?? $user->role;
        $status = $data['status'] ?? $user->status;

        $role = $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);
        $status = $status instanceof UserStatus ? $status : UserStatus::tryFrom((string) $status);

        $keepsAdmin = $role === UserRole::Admin;
        $staysActive = $status === UserStatus::Active;

        if (! $keepsAdmin || ! $staysActive) {
            throw ValidationException::withMessages([
                'status' => 'Anda tidak dapat menurunkan role atau menonaktifkan akun sendiri.',
            ]);
        }
    }
}
