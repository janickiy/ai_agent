<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_viewer_cannot_mutate_sources(): void
    {
        $this->get('/login')->assertOk()->assertSee('Вход в административную панель');
        $this->get('/admin')->assertRedirect('/login');

        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'admin_access' => true,
        ]);
        $this->actingAs($viewer)
            ->post('/admin/sources', [])
            ->assertForbidden();
    }

    public function test_inactive_user_is_blocked(): void
    {
        $user = User::factory()->create([
            'role' => 'administrator',
            'is_active' => false,
            'admin_access' => true,
        ]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_active_administrator_can_login_and_logout(): void
    {
        $administrator = User::factory()->create([
            'email' => 'admin-login@example.test',
            'password' => Hash::make('SecurePassword2026'),
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $administrator->email,
            'password' => 'SecurePassword2026',
        ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($administrator);

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
