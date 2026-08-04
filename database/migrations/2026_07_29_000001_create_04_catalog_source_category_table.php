<?php

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = NewsTables::name('source_category');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->foreignId('source_id')->constrained(NewsTables::name('sources'))->cascadeOnDelete();
            $table->foreignId('category_id')->constrained(NewsTables::name('categories'))->cascadeOnDelete();
            $table->primary(['source_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(NewsTables::name('source_category'));
    }
};
