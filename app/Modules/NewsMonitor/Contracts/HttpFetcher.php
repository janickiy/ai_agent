<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\Contracts;

use App\Modules\NewsMonitor\DTO\FetchResult;

interface HttpFetcher
{
    public function get(string $url, bool $checkRobots = true): FetchResult;

    public function assertPublicUrl(string $url): void;
}
