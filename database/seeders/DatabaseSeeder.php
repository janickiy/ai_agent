<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\NewsMonitor\Models\NewsCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (config('news.categories') as $code => $category) {
            NewsCategory::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $category['name'],
                    'hashtag' => $category['hashtag'],
                    'keywords' => $category['keywords'],
                    'is_active' => true,
                ],
            );
        }

        $this->call(NewsSourceSeeder::class);

        $password = trim((string) env('ADMIN_PASSWORD'));
        if ($password !== '') {
            User::query()->updateOrCreate(
                ['login' => Str::lower(trim((string) env('ADMIN_LOGIN', 'administrator')))],
                [
                    'password' => $password,
                    'role' => 'administrator',
                    'is_active' => true,
                    'admin_access' => true,
                ],
            );
        }
    }
}
