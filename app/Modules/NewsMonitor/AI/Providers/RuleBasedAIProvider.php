<?php

declare(strict_types=1);

namespace App\Modules\NewsMonitor\AI\Providers;

use App\Modules\NewsMonitor\AI\Contracts\AIProvider;
use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleAnalysisResult;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonRequest;
use App\Modules\NewsMonitor\AI\DTO\ArticleComparisonResult;

final class RuleBasedAIProvider implements AIProvider
{
    private const MODEL = 'deterministic-rules-v1';

    /**
     * Возвращает код локального rule-based провайдера для логов и результатов обработки.
     */
    public function code(): string
    {
        return 'rules';
    }

    /**
     * Классифицирует статью без внешнего AI по ключевым словам категорий и рекламным маркерам;
     * используется как самостоятельный провайдер и безопасный fallback удалённых сервисов.
     */
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

    /**
     * Оценивает сходство статей без внешнего API по коэффициенту Жаккара для уникальных токенов.
     */
    public function compareArticles(ArticleComparisonRequest $request): ArticleComparisonResult
    {
        $first = $this->tokens($request->first);
        $second = $this->tokens($request->second);
        $union = array_unique([...$first, ...$second]);
        $intersection = array_intersect($first, $second);
        $similarity = $union === [] ? 0.0 : count(array_unique($intersection)) / count($union);

        return new ArticleComparisonResult($similarity, 'rules', self::MODEL);
    }

    /**
     * Извлекает уникальные слова и числа длиной от трёх символов для локального сравнения текстов.
     *
     * @return list<string> Нормализованный набор уникальных токенов.
     */
    private function tokens(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]{3,}/u', mb_strtolower($text), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
