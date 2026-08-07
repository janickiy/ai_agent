<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Заменяет имя и email пользователя единым уникальным логином.
     *
     * Для существующих записей логин строится из части email до символа `@`.
     * Коллизии разрешаются числовым суффиксом, поэтому ни одна учётная запись не теряется.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('login', 64)->nullable()->after('id');
        });

        $usedLogins = [];
        $users = DB::table('users')->orderBy('id')->get(['id', 'email']);

        foreach ($users as $user) {
            $base = $this->loginBase((string) $user->email, (int) $user->id);
            $login = $this->uniqueLogin($base, $usedLogins);
            $usedLogins[] = $login;

            DB::table('users')->where('id', $user->id)->update(['login' => $login]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['name', 'email', 'email_verified_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('login', 64)->nullable(false)->change();
            $table->unique('login');
        });

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->renameColumn('email', 'login');
        });
    }

    /**
     * Восстанавливает прежнюю структуру пользователей для безопасного отката миграции.
     */
    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->renameColumn('login', 'email');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('login');
            $table->string('email')->nullable()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'name' => $user->login,
                'email' => $user->login.'@example.invalid',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
            $table->dropUnique(['login']);
            $table->dropColumn('login');
        });
    }

    /**
     * Нормализует прежний email в допустимую основу логина длиной не более 64 символов.
     */
    private function loginBase(string $email, int $id): string
    {
        $base = Str::lower(Str::before($email, '@'));
        $base = (string) preg_replace('/[^\pL\pN._-]+/u', '-', $base);
        $base = trim($base, '.-_');

        if (mb_strlen($base) < 3) {
            $base = 'user-'.$id;
        }

        return mb_substr($base, 0, 64);
    }

    /**
     * Подбирает свободный логин, добавляя суффикс при совпадении с предыдущей записью.
     *
     * @param  list<string>  $usedLogins
     */
    private function uniqueLogin(string $base, array $usedLogins): string
    {
        $login = $base;
        $suffix = 2;

        while (in_array($login, $usedLogins, true)) {
            $ending = '-'.$suffix++;
            $login = mb_substr($base, 0, 64 - mb_strlen($ending)).$ending;
        }

        return $login;
    }
};
