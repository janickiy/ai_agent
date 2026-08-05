<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Неверный email или пароль.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        if (! $request->user()?->is_active || ! $request->user()?->admin_access) {
            Auth::logout();

            return back()->withErrors(['email' => 'Доступ к панели запрещён.']);
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
