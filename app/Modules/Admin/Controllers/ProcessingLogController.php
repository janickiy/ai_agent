<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Http\Controllers\Controller;
use App\Modules\Admin\Repositories\AdminReadRepository;
use Illuminate\View\View;

final class ProcessingLogController extends Controller
{
    public function __construct(private readonly AdminReadRepository $adminReads) {}

    public function __invoke(): View
    {
        $displayTimezone = (string) config('app.display_timezone');

        $today = now()->timezone($displayTimezone)->startOfDay()->utc();

        return view('admin.logs.index', [
            'stages' => ProcessingStage::options(),
            'statuses' => ProcessingStatus::options(),
            'summary' => $this->adminReads->processingLogSummarySince($today),
        ]);
    }
}
