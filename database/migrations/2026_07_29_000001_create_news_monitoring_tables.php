<?php

use App\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(NewsTables::name('categories'), function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 255);
            $table->string('hashtag', 128);
            $table->jsonb('keywords');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create(NewsTables::name('subjects'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained(NewsTables::name('categories'))->restrictOnDelete();
            $table->string('name');
            $table->jsonb('keywords');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create(NewsTables::name('sources'), function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('domain', 190);
            $table->string('type', 32)->default('rss');
            $table->text('base_url');
            $table->string('feed_url', 512)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_trusted')->default(false);
            $table->boolean('is_allowed')->default(false);
            $table->unsignedInteger('poll_interval_minutes')->default(30);
            $table->unsignedInteger('request_limit')->default(30);
            $table->unsignedInteger('timeout_seconds')->default(20);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->jsonb('queries')->nullable();
            $table->string('status', 32)->default('new');
            $table->text('last_error')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('next_poll_at')->nullable()->index();
            $table->timestampsTz();
            $table->unique(['domain', 'feed_url']);
        });

        Schema::create(NewsTables::name('source_category'), function (Blueprint $table): void {
            $table->foreignId('source_id')->constrained(NewsTables::name('sources'))->cascadeOnDelete();
            $table->foreignId('category_id')->constrained(NewsTables::name('categories'))->cascadeOnDelete();
            $table->primary(['source_id', 'category_id']);
        });

        Schema::create(NewsTables::name('source_items'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained(NewsTables::name('sources'))->restrictOnDelete();
            $table->text('discovery_url');
            $table->text('canonical_url');
            $table->string('canonical_url_hash', 64)->unique();
            $table->text('title_original')->nullable();
            $table->text('description_original')->nullable();
            $table->longText('body_text')->nullable();
            $table->text('image_url')->nullable();
            $table->timestampTz('source_published_at')->nullable()->index();
            $table->char('title_description_hash', 64)->nullable()->index();
            $table->char('content_hash', 64)->nullable()->index();
            $table->string('status', 32)->default('discovered')->index();
            $table->string('rejection_reason', 128)->nullable();
            $table->jsonb('extraction_meta')->nullable();
            $table->timestampTz('discovered_at');
            $table->timestampTz('fetched_at')->nullable();
            $table->timestampTz('extracted_at')->nullable();
            $table->timestampTz('analyzed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create(NewsTables::name('analyses'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_item_id')->unique()->constrained(NewsTables::name('source_items'))->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained(NewsTables::name('categories'))->restrictOnDelete();
            $table->boolean('is_actual')->default(false);
            $table->decimal('actuality_score', 5, 4)->default(0);
            $table->boolean('is_advertising')->default(false);
            $table->decimal('ad_confidence', 5, 4)->default(0);
            $table->decimal('category_confidence', 5, 4)->default(0);
            $table->jsonb('hashtags')->nullable();
            $table->jsonb('entities')->nullable();
            $table->string('provider', 64);
            $table->string('model', 128);
            $table->string('prompt_version', 64);
            $table->jsonb('decision_meta');
            $table->timestampTz('checked_at');
            $table->timestampsTz();
        });

        Schema::create(NewsTables::name('duplicates'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_item_id')->unique()->constrained(NewsTables::name('source_items'))->cascadeOnDelete();
            $table->foreignId('original_source_item_id')->constrained(NewsTables::name('source_items'))->restrictOnDelete();
            $table->string('method', 64);
            $table->decimal('similarity', 6, 5);
            $table->string('algorithm_version', 64);
            $table->jsonb('meta')->nullable();
            $table->timestampsTz();
        });

        Schema::create(NewsTables::name('events'), function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 128)->unique();
            $table->string('title');
            $table->timestampTz('event_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create(NewsTables::name('event_items'), function (Blueprint $table): void {
            $table->foreignId('news_event_id')->constrained(NewsTables::name('events'))->cascadeOnDelete();
            $table->foreignId('source_item_id')->unique()->constrained(NewsTables::name('source_items'))->cascadeOnDelete();
            $table->decimal('similarity', 6, 5);
            $table->primary(['news_event_id', 'source_item_id']);
        });

        Schema::create(NewsTables::name('posts'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_item_id')->unique()->constrained(NewsTables::name('source_items'))->restrictOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->text('source_url');
            $table->string('source_name');
            $table->timestampTz('source_published_at')->index();
            $table->text('title_original');
            $table->text('description_original');
            $table->text('image_url')->nullable();
            $table->text('image_storage_key')->nullable();
            $table->string('read_more_label', 64)->default('Читать в источнике');
            $table->foreignId('category_id')->index()->constrained(NewsTables::name('categories'))->restrictOnDelete();
            $table->jsonb('hashtags');
            $table->char('content_hash', 64)->unique();
            $table->string('status', 32)->default('ready')->index();
            $table->jsonb('validation_meta');
            $table->timestampTz('ready_at')->index();
            $table->timestampTz('exported_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create(NewsTables::name('processing_logs'), function (Blueprint $table): void {
            $table->id();
            $table->uuid('correlation_id')->index();
            $table->foreignId('source_id')->nullable()->index();
            $table->foreignId('source_item_id')->nullable()->index();
            $table->foreignId('publication_post_id')->nullable()->index();
            $table->string('stage', 64)->index();
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('reason_code', 128)->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('context')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create(NewsTables::name('audit_logs'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('correlation_id');
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('result', 32);
            $table->timestampTz('created_at');
        });

        Schema::create(NewsTables::name('settings'), function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->jsonb('value');
            $table->boolean('is_secret')->default(false);
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'mysql') {
            $posts = NewsTables::name('posts');
            DB::statement("ALTER TABLE `{$posts}` ADD CONSTRAINT publication_posts_status_check CHECK (`status` IN ('ready','reserved','exported','export_failed','disabled'))");
            DB::statement("ALTER TABLE `{$posts}` ADD CONSTRAINT publication_posts_hashtags_check CHECK (JSON_TYPE(`hashtags`) = 'ARRAY' AND JSON_LENGTH(`hashtags`) BETWEEN 1 AND 7)");
            DB::statement("ALTER TABLE `{$posts}` ADD CONSTRAINT publication_posts_title_check CHECK (CHAR_LENGTH(TRIM(`title_original`)) > 0)");
            DB::statement("ALTER TABLE `{$posts}` ADD CONSTRAINT publication_posts_description_check CHECK (CHAR_LENGTH(TRIM(`description_original`)) > 0)");
        }
    }

    public function down(): void
    {
        foreach ([
            'settings', 'audit_logs', 'processing_logs', 'posts', 'event_items', 'events',
            'duplicates', 'analyses', 'source_items', 'source_category', 'sources', 'subjects', 'categories',
        ] as $table) {
            Schema::dropIfExists(NewsTables::name($table));
        }
    }
};
