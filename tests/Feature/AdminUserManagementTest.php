<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_dashboard_reports_zeroes_without_any_conversion_data(): void
    {
        User::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Total User')
            ->assertSee('Konversi Hari Ini');
    }

    public function test_admin_can_create_a_user_with_a_hashed_password(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Budi Sarono',
            'email' => 'budi@example.com',
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
            'status' => 'active',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'budi@example.com')->firstOrFail();

        $this->assertSame(UserRole::User, $user->role);
        $this->assertNotSame('RahasiaKuat123', $user->password);
        $this->assertTrue(Hash::check('RahasiaKuat123', $user->password));
    }

    public function test_admins_are_not_created_from_this_screen(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Trying Admin',
            'email' => 'sneaky@example.com',
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
            'status' => 'active',
            'role' => 'admin',
        ])->assertRedirect();

        $this->assertSame(UserRole::User, User::where('email', 'sneaky@example.com')->value('role'));
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'duplikat@example.com']);

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Dua',
            'email' => 'duplikat@example.com',
            'password' => 'RahasiaKuat123',
            'password_confirmation' => 'RahasiaKuat123',
            'status' => 'active',
        ])->assertSessionHasErrors('email');

        $this->assertSame(2, User::count());
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Lemah',
            'email' => 'lemah@example.com',
            'password' => '123',
            'password_confirmation' => '123',
            'status' => 'active',
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_can_change_email_and_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'name' => 'Nama Baru',
            'email' => 'baru@example.com',
            'role' => 'user',
            'status' => 'inactive',
        ])->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame(UserStatus::Inactive, $user->status);
    }

    public function test_admin_can_reset_a_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.users.password', $user), [
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('PasswordBaru123', $user->refresh()->password));
    }

    public function test_status_can_be_toggled(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.users.status', $user));
        $this->assertSame(UserStatus::Inactive, $user->refresh()->status);

        $this->actingAs($this->admin)->patch(route('admin.users.status', $user));
        $this->assertSame(UserStatus::Active, $user->refresh()->status);
    }

    public function test_admin_can_delete_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_or_lock_out_themselves(): void
    {
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))
            ->assertSessionHasErrors('users');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);

        $this->actingAs($this->admin)->patch(route('admin.users.status', $this->admin))
            ->assertSessionHasErrors('status');
        $this->assertSame(UserStatus::Active, $this->admin->refresh()->status);

        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'user',
            'status' => 'active',
        ])->assertSessionHasErrors('status');
        $this->assertSame(UserRole::Admin, $this->admin->refresh()->role);
    }

    public function test_users_list_paginates(): void
    {
        User::factory()->count(12)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Nama')
            ->assertSee('Tanggal Dibuat');
    }
}
