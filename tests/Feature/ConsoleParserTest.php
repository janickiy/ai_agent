<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ConsoleParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_controls_are_not_available_in_admin_panel(): void
    {
        $administrator = User::factory()->create([
            'role' => 'administrator',
            'is_active' => true,
            'admin_access' => true,
        ]);

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Запустить парсер')
            ->assertDontSee('Остановить парсер');

        $this->actingAs($administrator)->post('/admin/parser/start')->assertNotFound();
        $this->actingAs($administrator)->post('/admin/parser/stop')->assertNotFound();
    }

    public function test_parser_can_be_started_from_console(): void
    {
        $this->artisan('news:monitor')
            ->expectsOutputToContain('Источников: 0; новых URL: 0; ошибок: 0')
            ->assertSuccessful();
    }
}
