<?php

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = NewsTables::name('source_items');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained(NewsTables::name('sources'))->restrictOnDelete();
            $table->text('discovery_url');
            $table->text('canonical_url');
            $table->string('canonical_url_hash', 64)->unique();
            $table->text('title_original')->nullable();
            $table->text('description_original')->nullable();
            $table->longText('body_text')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamp('source_published_at')->nullable()->index();
            $table->char('title_description_hash', 64)->nullable()->index();
            $table->char('content_hash', 64)->nullable()->index();
            $table->string('status', 32)->default('discovered')->index();
            $table->string('rejection_reason', 128)->nullable();
            $table->json('extraction_meta')->nullable();
            $table->timestamp('discovered_at');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(NewsTables::name('source_items'));
    }
};
