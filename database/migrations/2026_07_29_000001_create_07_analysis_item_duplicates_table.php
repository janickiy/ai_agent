<?php

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = NewsTables::name('duplicates');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_item_id')->unique()->constrained(NewsTables::name('source_items'))->cascadeOnDelete();
            $table->foreignId('original_source_item_id')->constrained(NewsTables::name('source_items'))->restrictOnDelete();
            $table->string('method', 64);
            $table->decimal('similarity', 6, 5);
            $table->string('algorithm_version', 64);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(NewsTables::name('duplicates'));
    }
};
