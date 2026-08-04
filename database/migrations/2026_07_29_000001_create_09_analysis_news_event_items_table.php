<?php

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = NewsTables::name('event_items');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->foreignId('news_event_id')->constrained(NewsTables::name('events'))->cascadeOnDelete();
            $table->foreignId('source_item_id')->unique()->constrained(NewsTables::name('source_items'))->cascadeOnDelete();
            $table->decimal('similarity', 6, 5);
            $table->primary(['news_event_id', 'source_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(NewsTables::name('event_items'));
    }
};
