<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class PublicationController extends Controller
{
    /**
     * Открывает страницу готовых публикаций.
     *
     * Данные таблицы загружаются отдельным AJAX-запросом, поэтому метод отвечает
     * только за отображение административного интерфейса списка публикаций.
     */
    public function index(): View
    {
        return view('admin.posts.index');
    }
}
