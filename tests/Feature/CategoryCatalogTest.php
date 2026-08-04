<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\NewsMonitor\Models\NewsCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CategoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_ten_required_categories_are_seeded_verbatim(): void
    {
        $this->seed();

        self::assertSame([
            'Строительство',
            'Девелопмент',
            'Инфраструктура',
            'Недвижимость',
            'ЖКХ',
            'Архитектура',
            'Строительные материалы',
            'Государственные программы',
            'Транспортная инфраструктура',
            'Промышленное строительство',
        ], NewsCategory::query()->orderBy('id')->pluck('name')->all());
    }
}
