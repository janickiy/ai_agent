<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdminAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active_user' => EnsureActiveUser::class,
            'admin_access' => EnsureAdminAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'gigachat_auth_key',
            'gigachat_client_id',
            'gigachat_client_secret',
            'yandexgpt_api_key',
            'yandexgpt_iam_token',
            'openai_api_key',
            'gemini_api_key',
        ]);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
