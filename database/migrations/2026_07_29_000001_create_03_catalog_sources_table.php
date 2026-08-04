<?php

use App\Modules\NewsMonitor\Support\NewsTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = NewsTables::name('sources');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
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
            $table->json('queries')->nullable();
            $table->string('status', 32)->default('new');
            $table->text('last_error')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('next_poll_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['domain', 'feed_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(NewsTables::name('sources'));
    }
};
