<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class PublicationController extends Controller
{
    public function index(): View
    {
        return view('admin.posts.index');
    }
}
