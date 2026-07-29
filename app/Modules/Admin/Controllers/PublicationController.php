<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\NewsMonitor\Models\PublicationPost;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PublicationController extends Controller
{
    public function index(Request $request): View
    {
        $posts = PublicationPost::query()
            ->with('category')
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('ready_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.posts.index', compact('posts'));
    }
}
