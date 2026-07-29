<?php

namespace Database\Seeders;

use App\Models\User;
use App\NewsMonitor\Models\NewsCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
                ['email' => (string) env('ADMIN_EMAIL', 'admin@example.test')],
                [
                    'name' => 'Administrator',
                    'password' => $password,
                    'role' => 'administrator',
                    'is_active' => true,
                    'admin_access' => true,
                ],
            );
        }
    }
}
