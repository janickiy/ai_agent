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
        $this->get('/login')
            ->assertOk()
            ->assertSee('Вход в административную панель')
            ->assertSee('name="login"', false)
            ->assertDontSee('name="email"', false);
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
            'login' => 'admin-login',
            'password' => Hash::make('SecurePassword2026'),
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->post(route('login.store'), [
            'login' => $administrator->login,
            'password' => 'SecurePassword2026',
        ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($administrator);

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_admin_layout_uses_sweetalert_for_destructive_confirmation(): void
    {
        $administrator = User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(asset('plugins/sweetalert2/sweetalert2.min.css'), false)
            ->assertSee(asset('plugins/sweetalert2/sweetalert2.min.js'), false)
            ->assertSee(asset('js/admin-confirmations.js'), false)
            ->assertSee('data-confirm-dialog', false)
            ->assertDontSee('confirm(', false);
    }
}
