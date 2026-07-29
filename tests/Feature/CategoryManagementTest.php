<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\NewsMonitor\Models\NewsCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_edit_and_delete_a_category(): void
    {
        $this->seed();
        $administrator = User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Список тематик')
            ->assertSee(route('admin.categories.create'));

        $this->actingAs($administrator)
            ->getJson(route('admin.datatables.categories'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Строительство']);

        $this->actingAs($administrator)
            ->post(route('admin.categories.store'), [
                'name' => 'Энергоэффективность',
                'code' => '',
                'hashtag' => '',
                'keywords' => "энергоэффективность\nэнергосбережение",
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('status', 'Тематика добавлена.');

        $category = NewsCategory::query()->where('name', 'Энергоэффективность')->firstOrFail();
        self::assertSame('energoeffektivnost', $category->code);
        self::assertSame('#Энергоэффективность', $category->hashtag);
        self::assertSame(['энергоэффективность', 'энергосбережение'], $category->keywords);

        $this->actingAs($administrator)
            ->get(route('admin.categories.edit', $category))
            ->assertOk()
            ->assertSee('Редактирование тематики');

        $this->actingAs($administrator)
            ->put(route('admin.categories.update', $category), [
                'name' => 'Зелёное строительство',
                'code' => 'green_construction',
                'hashtag' => 'ЗеленоеСтроительство',
                'keywords' => 'зелёное строительство, экологичное строительство',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category->refresh();
        self::assertSame('Зелёное строительство', $category->name);
        self::assertSame('green_construction', $category->code);
        self::assertSame('#ЗеленоеСтроительство', $category->hashtag);
        self::assertFalse($category->is_active);

        $this->actingAs($administrator)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing($category->getTable(), ['id' => $category->id]);
    }

    public function test_viewer_can_list_categories_but_cannot_manage_them(): void
    {
        $this->seed();
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->actingAs($viewer)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertDontSee(route('admin.categories.create'));

        $this->actingAs($viewer)
            ->post(route('admin.categories.store'), [])
            ->assertForbidden();
    }
}
