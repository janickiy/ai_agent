<?php

declare(strict_types=1);

namespace App\NewsMonitor\Contracts;

use App\NewsMonitor\DTO\FetchResult;

interface HttpFetcher
{
    public function get(string $url, bool $checkRobots = true): FetchResult;

    public function assertPublicUrl(string $url): void;
}
