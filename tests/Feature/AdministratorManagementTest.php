<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\NewsMonitor\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdministratorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_edit_and_delete_another_administrator(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->get(route('admin.administrators.index'))
            ->assertOk()
            ->assertSee('Администраторы')
            ->assertSee(route('admin.administrators.create'));

        $this->actingAs($administrator)
            ->post(route('admin.administrators.store'), [
                'name' => 'Второй администратор',
                'email' => 'SECOND.ADMIN@example.test',
                'password' => 'SecureAdmin2026',
                'password_confirmation' => 'SecureAdmin2026',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.administrators.index'))
            ->assertSessionHas('status', 'Администратор добавлен.');

        $created = User::query()->where('email', 'second.admin@example.test')->firstOrFail();
        self::assertSame('administrator', $created->role);
        self::assertTrue($created->admin_access);
        self::assertTrue($created->is_active);
        self::assertTrue(Hash::check('SecureAdmin2026', $created->password));

        $this->actingAs($administrator)
            ->put(route('admin.administrators.update', $created), [
                'name' => 'Редактор новостей',
                'email' => 'editor@example.test',
                'password' => '',
                'password_confirmation' => '',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.administrators.index'));

        $created->refresh();
        self::assertSame('Редактор новостей', $created->name);
        self::assertSame('editor@example.test', $created->email);
        self::assertTrue(Hash::check('SecureAdmin2026', $created->password));

        $this->actingAs($administrator)
            ->delete(route('admin.administrators.destroy', $created))
            ->assertRedirect(route('admin.administrators.index'));

        $this->assertDatabaseMissing('users', ['id' => $created->id]);
        self::assertSame(3, AuditLog::query()->where('entity_type', User::class)->count());
    }

    public function test_administrator_cannot_delete_or_disable_self(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->from(route('admin.administrators.index'))
            ->delete(route('admin.administrators.destroy', $administrator))
            ->assertRedirect(route('admin.administrators.index'))
            ->assertSessionHasErrors('administrator');

        $this->actingAs($administrator)
            ->from(route('admin.administrators.edit', $administrator))
            ->put(route('admin.administrators.update', $administrator), [
                'name' => $administrator->name,
                'email' => $administrator->email,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.administrators.edit', $administrator))
            ->assertSessionHasErrors('administrator');

        self::assertTrue($administrator->fresh()->is_active);
    }

    public function test_non_administrator_cannot_open_administrator_management(): void
    {
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->actingAs($viewer)
            ->get(route('admin.administrators.index'))
            ->assertForbidden();
    }

    private function administrator(): User
    {
        return User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);
    }
}
