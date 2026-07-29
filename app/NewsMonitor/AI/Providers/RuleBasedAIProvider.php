<?php

declare(strict_types=1);

namespace App\NewsMonitor\AI\Providers;

use App\NewsMonitor\AI\Contracts\AIProvider;
use App\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\NewsMonitor\AI\DTO\ArticleAnalysisResult;
use App\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\NewsMonitor\AI\DTO\ArticleComparisonResult;
use App\NewsMonitor\AI\DTO\EmbeddingRequest;
use App\NewsMonitor\AI\DTO\EmbeddingResult;
use App\NewsMonitor\AI\DTO\PublicationDraft;
use App\NewsMonitor\AI\DTO\TextOperationRequest;
use App\NewsMonitor\AI\DTO\TextOperationResult;

final class RuleBasedAIProvider implements AIProvider
{
    private const MODEL = 'deterministic-rules-v1';

    public function analyzeArticle(ArticleAnalysisRequest $request): ArticleAnalysisResult
    {
        $text = mb_strtolower("{$request->title}\n{$request->description}\n{$request->body}");
        $scores = [];
        $entities = [];

        foreach ($request->categories as $code => $category) {
            $scores[$code] = 0;
            foreach ($category['keywords'] as $keyword) {
                if (str_contains($text, mb_strtolower($keyword))) {
                    $scores[$code]++;
                    $entities[] = $keyword;
                }
            }
        }

        arsort($scores);
        $categoryCode = array_key_first($scores);
        $topScore = $categoryCode === null ? 0 : $scores[$categoryCode];
        if ($topScore === 0) {
            $categoryCode = null;
        }

        $adMarkers = array_values(array_filter(
            config('news.advertising_markers', []),
            fn (string $marker): bool => str_contains($text, mb_strtolower($marker)),
        ));
        $isAdvertising = $adMarkers !== [];
        $hashtags = [];

        if ($categoryCode !== null) {
            $hashtags[] = $request->categories[$categoryCode]['hashtag'];
            foreach ($scores as $code => $score) {
                if ($code !== $categoryCode && $score > 0) {
                    $hashtags[] = $request->categories[$code]['hashtag'];
                }
            }
        }

        return new ArticleAnalysisResult(
            categoryCode: $categoryCode,
            categoryConfidence: $topScore === 0 ? 0.0 : min(0.99, 0.55 + (($topScore - 1) * 0.10)),
            isAdvertising: $isAdvertising,
            adConfidence: $isAdvertising ? 0.99 : 0.05,
            hashtags: array_slice(array_values(array_unique($hashtags)), 0, 7),
            entities: array_slice(array_values(array_unique($entities)), 0, 20),
            reason: $isAdvertising
                ? 'advertising_markers:'.implode(',', $adMarkers)
                : ($categoryCode === null ? 'no_category_match' : "category_keywords:{$topScore}"),
            provider: 'rules',
            model: self::MODEL,
        );
    }

    public function classifySubjects(TextOperationRequest $request): TextOperationResult
    {
        return $this->textResult($request->payload);
    }

    public function extractFacts(TextOperationRequest $request): TextOperationResult
    {
        return $this->textResult(['facts' => [], 'input' => $request->payload]);
    }

    public function compareArticles(ArticleComparisonRequest $request): ArticleComparisonResult
    {
        $first = $this->tokens($request->first);
        $second = $this->tokens($request->second);
        $union = array_unique([...$first, ...$second]);
        $intersection = array_intersect($first, $second);
        $similarity = $union === [] ? 0.0 : count(array_unique($intersection)) / count($union);

        return new ArticleComparisonResult($similarity, 'rules', self::MODEL);
    }

    public function generatePublication(TextOperationRequest $request): PublicationDraft
    {
        return new PublicationDraft(
            titleOriginal: (string) ($request->payload['title_original'] ?? ''),
            descriptionOriginal: (string) ($request->payload['description_original'] ?? ''),
            hashtags: array_slice((array) ($request->payload['hashtags'] ?? []), 0, 7),
            meta: ['generated_by' => 'structure_only', 'model' => self::MODEL],
        );
    }

    public function verifyPublication(TextOperationRequest $request): TextOperationResult
    {
        $valid = ($request->payload['title_original'] ?? null) === ($request->payload['source_title'] ?? null)
            && ($request->payload['description_original'] ?? null) === ($request->payload['source_description'] ?? null);

        return $this->textResult(['valid' => $valid]);
    }

    public function createEmbedding(EmbeddingRequest $request): EmbeddingResult
    {
        $vector = array_fill(0, 128, 0.0);
        foreach ($this->tokens($request->text) as $token) {
            $vector[abs(crc32($token)) % 128] += 1.0;
        }
        $length = sqrt(array_sum(array_map(static fn (float $value): float => $value ** 2, $vector)));
        if ($length > 0) {
            $vector = array_map(static fn (float $value): float => $value / $length, $vector);
        }

        return new EmbeddingResult($vector, 'rules', 'hashed-bag-of-words-v1');
    }

    /** @param array<string, mixed> $data */
    private function textResult(array $data): TextOperationResult
    {
        return new TextOperationResult($data, 'rules', self::MODEL);
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]{3,}/u', mb_strtolower($text), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
