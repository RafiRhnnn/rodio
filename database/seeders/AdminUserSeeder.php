<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the first admin account. Public registration is disabled, so this
     * seeder (and the admin UI) is the only way accounts come to exist.
     *
     * Credentials may be overridden in .env for non-local environments.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@converter.test');

        $admin = User::query()->firstOrNew(['email' => $email]);

        if (! $admin->exists) {
            $admin->password = env('ADMIN_PASSWORD', 'Admin@12345');
            $admin->name = env('ADMIN_NAME', 'Administrator');
        }

        $admin->role = UserRole::Admin;
        $admin->status = UserStatus::Active;
        $admin->save();
    }
}
