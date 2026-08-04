<?php

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = NewsTables::name('analyses');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_item_id')->unique()->constrained(NewsTables::name('source_items'))->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained(NewsTables::name('categories'))->restrictOnDelete();
            $table->boolean('is_actual')->default(false);
            $table->decimal('actuality_score', 5, 4)->default(0);
            $table->boolean('is_advertising')->default(false);
            $table->decimal('ad_confidence', 5, 4)->default(0);
            $table->decimal('category_confidence', 5, 4)->default(0);
            $table->json('hashtags')->nullable();
            $table->json('entities')->nullable();
            $table->string('provider', 64);
            $table->string('model', 128);
            $table->string('prompt_version', 64);
            $table->json('decision_meta');
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(NewsTables::name('analyses'));
    }
};
