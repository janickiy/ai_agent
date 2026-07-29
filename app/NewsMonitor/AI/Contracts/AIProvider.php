<?php

declare(strict_types=1);

namespace App\NewsMonitor\AI\Contracts;

use App\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\NewsMonitor\AI\DTO\ArticleAnalysisResult;
use App\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\NewsMonitor\AI\DTO\ArticleComparisonResult;
use App\NewsMonitor\AI\DTO\EmbeddingRequest;
use App\NewsMonitor\AI\DTO\EmbeddingResult;
use App\NewsMonitor\AI\DTO\PublicationDraft;
use App\NewsMonitor\AI\DTO\TextOperationRequest;
use App\NewsMonitor\AI\DTO\TextOperationResult;

interface AIProvider
{
    public function analyzeArticle(ArticleAnalysisRequest $request): ArticleAnalysisResult;

    public function classifySubjects(TextOperationRequest $request): TextOperationResult;

    public function extractFacts(TextOperationRequest $request): TextOperationResult;

    public function compareArticles(ArticleComparisonRequest $request): ArticleComparisonResult;

    public function generatePublication(TextOperationRequest $request): PublicationDraft;

    public function verifyPublication(TextOperationRequest $request): TextOperationResult;

    public function createEmbedding(EmbeddingRequest $request): EmbeddingResult;
}
