<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\AI\Contracts;

use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisResult;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonResult;

interface AIProvider
{
    public function code(): string;

    public function analyzeArticle(ArticleAnalysisRequest $request): ArticleAnalysisResult;

    public function compareArticles(ArticleComparisonRequest $request): ArticleComparisonResult;
}
