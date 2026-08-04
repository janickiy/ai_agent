<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Modules\NewsMonitor\AI\Contracts\AIProvider;
use App\Modules\NewsMonitor\AI\Providers\GigaChatProvider;
use App\Modules\NewsMonitor\AI\Providers\OpenAIProvider;
use App\Modules\NewsMonitor\AI\Providers\RuleBasedAIProvider;
use App\Modules\NewsMonitor\AI\Providers\YandexGPTProvider;
use App\Modules\NewsMonitor\Contracts\HttpFetcher;
use App\Modules\NewsMonitor\Services\AISettings;
use App\Modules\NewsMonitor\Services\SafeHttpClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class NewsMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HttpFetcher::class, SafeHttpClient::class);

        $this->app->bind(AIProvider::class, function (): AIProvider {
            $settings = app(AISettings::class);
            $provider = $settings->provider();

            return match ($provider) {
                'rules' => new RuleBasedAIProvider,
                'gigachat' => new GigaChatProvider($settings->gigachatConfig()),
                'yandexgpt' => new YandexGPTProvider($settings->yandexgptConfig()),
                'openai' => new OpenAIProvider($settings->openaiConfig()),
                default => throw new InvalidArgumentException("Unsupported AI provider: {$provider}"),
            };
        });
    }

    public function boot(): void
    {
        Gate::define('manage-administrators', static fn (User $user): bool => $user->isAdministrator());
        Gate::define('manage-categories', static fn (User $user): bool => $user->isAdministrator());
        Gate::define('manage-settings', static fn (User $user): bool => $user->isAdministrator());
        Gate::define('manage-sources', static fn (User $user): bool => $user->isAdministrator());
        Gate::define('purge-content', static fn (User $user): bool => $user->isAdministrator());
        Gate::define('operate-pipeline', static fn (User $user): bool => $user->canOperate());
    }
}
