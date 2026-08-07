<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Управляет входом и выходом пользователей административной панели по логину и паролю.
 */
final class AuthController extends Controller
{
    /**
     * Отображает форму входа для неавторизованного пользователя админ-панели.
     */
    public function create(): View
    {
        return view('admin.auth.login');
    }

    /**
     * Проверяет учётные данные, создаёт авторизованную сессию и дополнительно
     * запрещает вход отключённым пользователям либо пользователям без доступа к панели.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
        ]);
        $credentials['login'] = Str::lower(trim($credentials['login']));

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'Неверный логин или пароль.'])->onlyInput('login');
        }
        $request->session()->regenerate();
        if (! $request->user()?->is_active || ! $request->user()?->admin_access) {
            Auth::logout();

            return back()->withErrors(['login' => 'Доступ к панели запрещён.']);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Завершает административную сессию, инвалидирует её данные и обновляет CSRF-токен,
     * чтобы прежняя сессия не могла использоваться повторно.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
