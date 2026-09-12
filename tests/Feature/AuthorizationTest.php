<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_cannot_open_admin_pages(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $paths = [
            '/admin/dashboard',
            '/admin/users',
            '/admin/users/create',
            "/admin/users/{$target->id}/edit",
        ];

        foreach ($paths as $path) {
            $this->actingAs($user)->get($path)->assertForbidden();
        }

        $this->actingAs($user)
            ->post('/admin/users', ['name' => 'X', 'email' => 'x@example.com'])
            ->assertForbidden();
    }

    public function test_admins_reach_every_admin_page(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['/admin/dashboard', '/admin/users', '/admin/users/create', '/admin/conversions', '/admin/settings'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_users_cannot_use_the_upload_endpoints(): void
    {
        $this->post('/converter/upload')->assertRedirect(route('login'));
    }
}
