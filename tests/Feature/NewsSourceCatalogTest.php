<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\NewsMonitor\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NewsSourceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_screenshot_source_catalog_is_seeded_idempotently(): void
    {
        $this->seed();
        Source::query()->where('domain', 'minstroyrf.gov.ru')->update([
            'feed_url' => 'https://minstroyrf.gov.ru/rss',
            'status' => 'healthy',
        ]);
        $this->seed();

        self::assertSame(30, Source::query()->count());
        self::assertSame(
            [
                'name' => 'Минстрой России',
                'source_class' => 'official_federal',
                'trust_score' => 100,
                'poll_interval_minutes' => 30,
                'status' => 'healthy',
            ],
            Source::query()
                ->where('domain', 'minstroyrf.gov.ru')
                ->firstOrFail()
                ->only(['name', 'source_class', 'trust_score', 'poll_interval_minutes', 'status']),
        );
        self::assertSame(
            'https://minstroyrf.gov.ru/rss',
            Source::query()->where('domain', 'minstroyrf.gov.ru')->value('feed_url'),
        );
    }

    public function test_verified_official_feeds_are_seeded_with_relevant_categories(): void
    {
        $this->seed();

        $expected = [
            'дом.рф' => [
                'feed_url' => 'https://xn--d1aqf.xn--p1ai/media/news/rss/',
                'categories' => ['architecture', 'construction', 'development', 'government_programs', 'infrastructure', 'real_estate'],
            ],
            'government.ru' => [
                'feed_url' => 'http://government.ru/all/rss/',
                'categories' => ['construction', 'development', 'government_programs', 'housing_utilities', 'industrial_construction', 'infrastructure', 'real_estate', 'transport_infrastructure'],
            ],
            'gov.spb.ru' => [
                'feed_url' => 'https://www.gov.spb.ru/news/rss/',
                'categories' => ['architecture', 'construction', 'development', 'government_programs', 'housing_utilities', 'industrial_construction', 'infrastructure', 'real_estate', 'transport_infrastructure'],
            ],
            'mintrans.gov.ru' => [
                'feed_url' => 'http://government.ru/department/68/events/rss/',
                'categories' => ['construction', 'government_programs', 'infrastructure', 'transport_infrastructure'],
            ],
            'mtdi.mosreg.ru' => [
                'feed_url' => 'https://mtdi.mosreg.ru/sobytiya/novosti-ministerstva?format=rss',
                'categories' => ['construction', 'government_programs', 'infrastructure', 'transport_infrastructure'],
            ],
            'msk.mosreg.ru' => [
                'feed_url' => 'https://msk.mosreg.ru/sobytiya/novosti-ministerstva?format=rss',
                'categories' => ['construction', 'development', 'government_programs', 'infrastructure', 'real_estate'],
            ],
            'minstroyrf.gov.ru' => [
                'feed_url' => 'https://minstroyrf.gov.ru/bitrix/rss.php?ID=2&LIMIT=50',
                'categories' => ['architecture', 'building_materials', 'construction', 'development', 'government_programs', 'housing_utilities', 'infrastructure', 'real_estate'],
            ],
            'rosavtodor.gov.ru' => [
                'feed_url' => 'https://rosavtodor.gov.ru/rss',
                'categories' => ['construction', 'government_programs', 'infrastructure', 'transport_infrastructure'],
            ],
            'stroi.mos.ru' => [
                'feed_url' => 'https://stroi.mos.ru/news/rss',
                'categories' => ['architecture', 'building_materials', 'construction', 'development', 'government_programs', 'industrial_construction', 'infrastructure', 'real_estate', 'transport_infrastructure'],
            ],
            'realty.rbc.ru' => [
                'feed_url' => 'https://rssexport.rbc.ru/realty/news/30/full.rss',
                'categories' => ['architecture', 'building_materials', 'construction', 'development', 'government_programs', 'housing_utilities', 'infrastructure', 'real_estate'],
            ],
            'veb.ru' => [
                'feed_url' => 'https://veb.ru/rss/',
                'categories' => ['construction', 'development', 'government_programs', 'housing_utilities', 'industrial_construction', 'infrastructure', 'real_estate', 'transport_infrastructure'],
            ],
        ];

        foreach ($expected as $domain => $sourceExpected) {
            $source = Source::query()
                ->with('categories')
                ->where('domain', $domain)
                ->firstOrFail();

            self::assertSame('rss', $source->type);
            self::assertSame($sourceExpected['feed_url'], $source->feed_url);
            self::assertSame(
                $sourceExpected['categories'],
                $source->categories->pluck('code')->sort()->values()->all(),
            );
        }
    }

    public function test_legacy_moscow_region_domain_is_migrated_without_creating_a_duplicate(): void
    {
        $this->seed();
        Source::query()->where('domain', 'msk.mosreg.ru')->update([
            'domain' => 'minstroy.mosreg.ru',
            'feed_url' => null,
        ]);

        $this->seed();

        self::assertSame(30, Source::query()->count());
        self::assertFalse(Source::query()->where('domain', 'minstroy.mosreg.ru')->exists());
        self::assertSame(
            'https://msk.mosreg.ru/sobytiya/novosti-ministerstva?format=rss',
            Source::query()->where('domain', 'msk.mosreg.ru')->value('feed_url'),
        );
    }

    public function test_catalog_only_sources_are_inactive_and_have_narrow_category_profiles(): void
    {
        $this->seed();

        $expected = [
            'company.rzd.ru' => ['construction', 'government_programs', 'industrial_construction', 'infrastructure', 'transport_infrastructure'],
            'mos.ru' => ['architecture', 'construction', 'development', 'government_programs', 'housing_utilities', 'industrial_construction', 'infrastructure', 'real_estate', 'transport_infrastructure'],
            'russianhighways.ru' => ['construction', 'government_programs', 'infrastructure', 'transport_infrastructure'],
            'transport.mos.ru' => ['construction', 'government_programs', 'infrastructure', 'transport_infrastructure'],
            'наш.дом.рф' => ['construction', 'development', 'government_programs', 'infrastructure', 'real_estate'],
            'строим.дом.рф' => ['architecture', 'building_materials', 'construction', 'development', 'government_programs', 'infrastructure', 'real_estate'],
        ];

        foreach ($expected as $domain => $categoryCodes) {
            $source = Source::query()
                ->with('categories')
                ->where('domain', $domain)
                ->firstOrFail();

            self::assertFalse($source->is_active);
            self::assertNull($source->feed_url);
            self::assertSame(
                $categoryCodes,
                $source->categories->pluck('code')->sort()->values()->all(),
            );
        }
    }

    public function test_legacy_moscow_region_transport_domain_is_migrated(): void
    {
        $this->seed();
        Source::query()->where('domain', 'mtdi.mosreg.ru')->update([
            'domain' => 'mostrans.gov.ru',
            'feed_url' => null,
        ]);

        $this->seed();

        self::assertSame(30, Source::query()->count());
        self::assertFalse(Source::query()->where('domain', 'mostrans.gov.ru')->exists());
        self::assertSame(
            'https://mtdi.mosreg.ru/sobytiya/novosti-ministerstva?format=rss',
            Source::query()->where('domain', 'mtdi.mosreg.ru')->value('feed_url'),
        );
    }
}
