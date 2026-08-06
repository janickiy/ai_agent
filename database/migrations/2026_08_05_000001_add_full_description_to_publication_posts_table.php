<?php

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляет полное описание готового поста и переносит в него уже извлечённый
     * текст исходной статьи для существующих публикаций.
     */
    public function up(): void
    {
        $posts = NewsTables::name('posts');
        $sourceItems = NewsTables::name('source_items');

        if (! Schema::hasColumn($posts, 'full_description_original')) {
            Schema::table($posts, function (Blueprint $table): void {
                $table->longText('full_description_original')->nullable()->after('description_original');
            });
        }

        DB::table($posts)
            ->whereNull('full_description_original')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($posts, $sourceItems): void {
                $bodyByItem = DB::table($sourceItems)
                    ->whereIn('id', $rows->pluck('source_item_id'))
                    ->pluck('body_text', 'id');

                foreach ($rows as $row) {
                    $body = $bodyByItem->get($row->source_item_id);
                    if (is_string($body) && trim($body) !== '') {
                        DB::table($posts)
                            ->where('id', $row->id)
                            ->update(['full_description_original' => $body]);
                    }
                }
            });
    }

    /**
     * Удаляет поле полного описания при откате миграции.
     */
    public function down(): void
    {
        $posts = NewsTables::name('posts');

        if (Schema::hasColumn($posts, 'full_description_original')) {
            Schema::table($posts, function (Blueprint $table): void {
                $table->dropColumn('full_description_original');
            });
        }
    }
};
