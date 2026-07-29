<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\NewsMonitor\AI\Contracts\AIProvider;
use App\NewsMonitor\AI\Providers\GigaChatProvider;
use App\NewsMonitor\AI\Providers\RuleBasedAIProvider;
use App\NewsMonitor\Contracts\HttpFetcher;
use App\NewsMonitor\Services\SafeHttpClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class NewsMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HttpFetcher::class, SafeHttpClient::class);

        $this->app->singleton(AIProvider::class, function (): AIProvider {
            $provider = (string) config('ai.default');

            return match ($provider) {
                'rules' => new RuleBasedAIProvider,
                'gigachat' => new GigaChatProvider((array) config('ai.providers.gigachat')),
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
