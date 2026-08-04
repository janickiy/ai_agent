<?php

declare(strict_types=1);

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(NewsTables::name('sources'), function (Blueprint $table): void {
            $table->string('source_class', 32)
                ->default('industry_media')
                ->after('type')
                ->index();
            $table->unsignedTinyInteger('trust_score')
                ->default(70)
                ->after('source_class');
        });
    }

    public function down(): void
    {
        Schema::table(NewsTables::name('sources'), function (Blueprint $table): void {
            $table->dropIndex(['source_class']);
            $table->dropColumn(['source_class', 'trust_score']);
        });
    }
};
