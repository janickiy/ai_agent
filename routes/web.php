<?php

use App\Modules\Admin\Controllers\AdministratorController;
use App\Modules\Admin\Controllers\AuthController;
use App\Modules\Admin\Controllers\CategoryController;
use App\Modules\Admin\Controllers\DashboardController;
use App\Modules\Admin\Controllers\NewsItemController;
use App\Modules\Admin\Controllers\ProcessingLogController;
use App\Modules\Admin\Controllers\PublicationController;
use App\Modules\Admin\Controllers\SettingsController;
use App\Modules\Admin\Controllers\SourceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'active_user', 'admin_access'])
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::resource('administrators', AdministratorController::class)->except('show');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::get('/sources', [SourceController::class, 'index'])->name('sources.index');
        Route::get('/sources/create', [SourceController::class, 'create'])->name('sources.create');
        Route::post('/sources', [SourceController::class, 'store'])->name('sources.store');
        Route::get('/sources/{source}/edit', [SourceController::class, 'edit'])->name('sources.edit');
        Route::put('/sources/{source}', [SourceController::class, 'update'])->name('sources.update');
        Route::patch('/sources/{source}/toggle', [SourceController::class, 'toggle'])->name('sources.toggle');
        Route::delete('/sources/{source}', [SourceController::class, 'destroy'])->name('sources.destroy');
        Route::get('/items', [NewsItemController::class, 'index'])->name('items.index');
        Route::post('/items/{item}/retry', [NewsItemController::class, 'retry'])->name('items.retry');
        Route::get('/posts', [PublicationController::class, 'index'])->name('posts.index');
        Route::get('/logs', ProcessingLogController::class)->name('logs.index');
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
