<?php

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = NewsTables::name('posts');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_item_id')->unique()->constrained(NewsTables::name('source_items'))->restrictOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->text('source_url');
            $table->string('source_name');
            $table->timestamp('source_published_at')->index();
            $table->text('title_original');
            $table->text('description_original');
            $table->text('image_url')->nullable();
            $table->text('image_storage_key')->nullable();
            $table->string('read_more_label', 64)->default('Читать в источнике');
            $table->foreignId('category_id')->index()->constrained(NewsTables::name('categories'))->restrictOnDelete();
            $table->json('hashtags');
            $table->char('content_hash', 64)->unique();
            $table->string('status', 32)->default('ready')->index();
            $table->json('validation_meta');
            $table->timestamp('ready_at')->index();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT publication_posts_status_check CHECK (`status` IN ('ready','reserved','exported','export_failed','disabled'))");
            DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT publication_posts_hashtags_check CHECK (JSON_TYPE(`hashtags`) = 'ARRAY' AND JSON_LENGTH(`hashtags`) BETWEEN 1 AND 7)");
            DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT publication_posts_title_check CHECK (CHAR_LENGTH(TRIM(`title_original`)) > 0)");
            DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT publication_posts_description_check CHECK (CHAR_LENGTH(TRIM(`description_original`)) > 0)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(NewsTables::name('posts'));
    }
};
