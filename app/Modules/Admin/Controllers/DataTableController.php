<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Repositories\AdministratorRepository;
use App\Modules\Admin\Repositories\AdminReadRepository;
use App\Modules\NewsMonitor\Models\NewsCategory;
use App\Modules\NewsMonitor\Models\ProcessingLog;
use App\Modules\NewsMonitor\Models\PublicationPost;
use App\Modules\NewsMonitor\Models\Source;
use App\Modules\NewsMonitor\Models\SourceItem;
use App\Modules\NewsMonitor\Repositories\Catalog\NewsCategoryRepository;
use App\Modules\NewsMonitor\Repositories\Catalog\SourceRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Exceptions\Exception;
use Yajra\DataTables\Facades\DataTables;

final class DataTableController extends Controller
{
    /**
     * Получает специализированные репозитории, которые строят серверные запросы
     * для всех таблиц административной панели.
     */
    public function __construct(
        private readonly NewsCategoryRepository $categories,
        private readonly SourceRepository $sources,
        private readonly AdministratorRepository $administrators,
        private readonly AdminReadRepository $adminReads,
    ) {}

    /** @var list<string> */
    private const ITEM_STATUSES = [
        'discovered',
        'fetched',
        'extracted',
        'analyzed',
        'rejected_irrelevant',
        'rejected_advertising',
        'duplicate',
        'validation_failed',
        'accepted',
    ];

    /** @var array<string, string> */
    private const ITEM_STATUS_CLASSES = [
        'discovered' => 'secondary',
        'fetched' => 'info',
        'extracted' => 'primary',
        'analyzed' => 'warning',
        'rejected_irrelevant' => 'secondary',
        'rejected_advertising' => 'danger',
        'duplicate' => 'dark',
        'validation_failed' => 'danger',
        'accepted' => 'success',
    ];

    /**
     * Возвращает JSON для таблицы тематик с серверным поиском по названию и коду.
     *
     * Метод также форматирует ключевые слова и добавляет HTML-действия для каждой строки.
     *
     * @throws Exception
     */
    public function categories(): JsonResponse
    {
        $query = $this->categories->forDataTable();

        return DataTables::eloquent($query)
            ->filterColumn('name', static function (Builder $query, string $keyword): void {
                $query->where(static fn (Builder $filter) => $filter
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%"));
            })
            ->editColumn(
                'keywords',
                static fn (NewsCategory $category): string => implode(', ', $category->keywords ?? []),
            )
            ->addColumn(
                'actions',
                static fn (NewsCategory $category): string => view(
                    'admin.datatables.category-actions',
                    compact('category'),
                )->render(),
            )
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * Возвращает JSON для таблицы источников и визуально выделяет отключённые записи.
     *
     * В ответ добавляется колонка доступных пользователю действий над источником.
     *
     * @throws Exception
     */
    public function sources(): JsonResponse
    {
        $query = $this->sources->forDataTable();

        return DataTables::eloquent($query)
            ->setRowClass(static fn (Source $source): string => $source->is_active ? '' : 'table-danger')
            ->addColumn(
                'actions',
                static fn (Source $source): string => view(
                    'admin.datatables.source-actions',
                    compact('source'),
                )->render(),
            )
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * Возвращает отфильтрованный JSON-список найденных материалов.
     *
     * Метод проверяет фильтр статуса, форматирует даты и добавляет классы статусов
     * и действия, необходимые интерфейсу DataTables.
     *
     * @throws Exception
     */
    public function items(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::ITEM_STATUSES)],
        ]);
        $query = $this->adminReads->sourceItemsForDataTable($filters['status'] ?? null);
        $items = $query->getModel()->getTable();
        $canPublish = Gate::allows('operate-pipeline');

        return DataTables::eloquent($query)
            ->filterColumn(
                'title_original',
                static fn (Builder $query, string $keyword) => $query->where(
                    "{$items}.title_original",
                    'like',
                    "%{$keyword}%",
                ),
            )
            ->editColumn(
                'source_published_at',
                fn (SourceItem $item): string => $this->date($item->source_published_at),
            )

            ->addColumn(
                'status_class',
                static fn (SourceItem $item): string => self::ITEM_STATUS_CLASSES[$item->status] ?? 'secondary',
            )
            ->addColumn(
                'actions',
                static fn (SourceItem $item): string => view(
                    'admin.datatables.item-actions',
                    compact('item'),
                )->render(),
            )
            ->addColumn(
                'manual_publication_available',
                static fn (SourceItem $item): bool => $canPublish && $item->isAwaitingManualPublication(),
            )
            ->editColumn(
                'created_at',
                fn (SourceItem $item): string => $this->date($item->created_at),
            )
            ->editColumn(
                'updated_at',
                fn (SourceItem $item): string => $this->date($item->updated_at),
            )
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * Возвращает JSON для таблицы опубликованных в Kaboom постов с серверным поиском,
     * форматированием дат и объединением хештегов в отображаемую строку.
     *
     * @throws Exception
     */
    public function posts(): JsonResponse
    {
        $query = $this->adminReads->publicationPostsForDataTable();
        $posts = $query->getModel()->getTable();

        return DataTables::eloquent($query)
            ->filterColumn(
                'title_original',
                static fn (Builder $query, string $keyword) => $query->where(
                    "{$posts}.title_original",
                    'like',
                    "%{$keyword}%",
                ),
            )
            ->editColumn(
                'source_published_at',
                fn (PublicationPost $post): string => $this->date($post->source_published_at),
            )
            ->editColumn(
                'created_at',
                fn (PublicationPost $post): string => $this->date($post->created_at),
            )
            ->editColumn(
                'updated_at',
                fn (PublicationPost $post): string => $this->date($post->updated_at),
            )
            ->editColumn(
                'hashtags',
                static fn (PublicationPost $post): string => implode(' ', $post->hashtags ?? []),
            )
            ->toJson();
    }

    /**
     * Возвращает отфильтрованный журнал обработки в формате DataTables.
     *
     * Метод валидирует этап, статус и период, переводит даты в часовой пояс интерфейса
     * и добавляет человекочитаемые подписи и CSS-классы перечислений.
     *
     * @throws Exception
     */
    public function logs(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'stage' => ['nullable', Rule::enum(ProcessingStage::class)],
            'status' => ['nullable', Rule::enum(ProcessingStatus::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $displayTimezone = (string) config('app.display_timezone');
        $query = $this->adminReads->processingLogsForDataTable($filters, $displayTimezone);

        return DataTables::eloquent($query)
            ->editColumn(
                'started_at',
                fn (ProcessingLog $log): string => $this->date($log->started_at, true),
            )
            ->addColumn(
                'stage_label',
                static fn (ProcessingLog $log): string => ProcessingStage::tryFrom($log->stage)?->label()
                    ?? $log->stage,
            )
            ->addColumn(
                'status_label',
                static fn (ProcessingLog $log): string => ProcessingStatus::tryFrom($log->status)?->label()
                    ?? $log->status,
            )
            ->addColumn(
                'status_class',
                static fn (ProcessingLog $log): string => ProcessingStatus::tryFrom($log->status)?->badgeClass()
                    ?? 'secondary',
            )
            ->addColumn(
                'ai_provider',
                static fn (ProcessingLog $log): ?string => data_get($log->context, 'ai_provider'),
            )
            ->filterColumn('source_name', static function (Builder $query, string $keyword): void {
                $query->where(static fn (Builder $filter) => $filter
                    ->where('source_table.name', 'like', "%{$keyword}%")
                    ->orWhere('item_table.title_original', 'like', "%{$keyword}%"));
            })
            ->filterColumn('error_message', static function (Builder $query, string $keyword): void {
                $query->where(static fn (Builder $filter) => $filter
                    ->where('error_message', 'like', "%{$keyword}%")
                    ->orWhere('reason_code', 'like', "%{$keyword}%")
                    ->orWhere('correlation_id', 'like', "%{$keyword}%"));
            })
            ->toJson();
    }

    /**
     * Возвращает JSON для таблицы администраторов после проверки права управления ими.
     *
     * Для каждой учётной записи в ответ добавляется HTML-колонка доступных действий.
     *
     * @throws Exception
     */
    public function administrators(): JsonResponse
    {
        Gate::authorize('manage-administrators');

        $query = $this->administrators->forDataTable();

        return DataTables::eloquent($query)
            ->addColumn(
                'actions',
                static fn (User $administrator): string => view(
                    'admin.datatables.administrator-actions',
                    compact('administrator'),
                )->render(),
            )
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * Преобразует дату из UTC в часовой пояс интерфейса и форматирует её для таблицы.
     *
     * Пустая дата отображается типографским прочерком, а флаг позволяет включить секунды
     * для подробного журнала обработки.
     */
    private function date(mixed $date, bool $withSeconds = false): string
    {
        if ($date === null) {
            return '—';
        }

        return $date
            ->timezone((string) config('app.display_timezone'))
            ->format($withSeconds ? 'd.m.Y H:i:s' : 'd.m.Y H:i');
    }
}
