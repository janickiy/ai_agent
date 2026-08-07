<?php

declare(strict_types=1);

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Добавляет UID Kaboom и заполняет его каноническими URL существующих публикаций.
     *
     * UID ограничен контрактом внешнего API 512 символами. Редкие коллизии старых
     * данных получают устойчивый суффикс, чтобы миграция не теряла публикации.
     */
    public function up(): void
    {
        $posts = NewsTables::name('posts');

        Schema::table($posts, function (Blueprint $table): void {
            $column = $table->string('uid', 512)->nullable()->after('id');

            if (DB::getDriverName() === 'mysql') {
                $column->collation('utf8mb4_bin');
            }
        });

        $used = [];
        DB::table($posts)
            ->select(['id', 'source_url'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($posts, &$used): void {
                foreach ($rows as $post) {
                    $base = Str::limit((string) $post->source_url, 512, '');
                    $uid = $base;
                    $suffix = 2;

                    while (isset($used[$uid])) {
                        $ending = '#post-'.$post->id.'-'.$suffix++;
                        $uid = Str::limit($base, 512 - strlen($ending), '').$ending;
                    }

                    $used[$uid] = true;
                    DB::table($posts)->where('id', $post->id)->update(['uid' => $uid]);
                }
            });

        Schema::table($posts, function (Blueprint $table): void {
            $column = $table->string('uid', 512)->nullable(false);

            if (DB::getDriverName() === 'mysql') {
                $column->collation('utf8mb4_bin');
            }

            $column->change();
            $table->unique('uid');
        });
    }

    /**
     * Удаляет UID при откате интеграции с внешней платформой.
     */
    public function down(): void
    {
        $posts = NewsTables::name('posts');

        Schema::table($posts, function (Blueprint $table): void {
            $table->dropUnique(['uid']);
            $table->dropColumn('uid');
        });
    }
};
