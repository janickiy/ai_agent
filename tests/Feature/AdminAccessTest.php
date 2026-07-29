<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
