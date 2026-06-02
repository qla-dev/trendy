<?php

namespace App\Http\Controllers;

use App\Models\OrderAiScan;
use App\Services\OrderAi\Support\OrderAiDocumentMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AiTokenHistoryController extends Controller
{
    private const ACTIVITY_ALL = 'all';
    private const ACTIVITY_AI_SCAN = 'ai_scan';
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    private const MONTH_OPTIONS = [
        1 => 'Januar',
        2 => 'Februar',
        3 => 'Mart',
        4 => 'April',
        5 => 'Maj',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Avgust',
        9 => 'Septembar',
        10 => 'Oktobar',
        11 => 'Novembar',
        12 => 'Decembar',
    ];

    private array $documentMetricsCache = [];

    public function index(Request $request)
    {
        $this->authorizeModuleAccess($request);

        $pageConfigs = ['pageHeader' => false];
        $showTokenUsage = $this->shouldShowTokenUsage($request);
        $filters = $this->resolveFilters($request);
        $perPage = $this->resolvePerPage($request);
        $baseQuery = $this->baseHistoryQuery();
        $historyQuery = clone $baseQuery;

        $this->applyFilters($historyQuery, $filters);

        $historyRows = $historyQuery
            ->orderByRaw($this->eventTimestampExpression() . ' DESC')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $historyRows->setCollection(
            $historyRows->getCollection()->map(function (OrderAiScan $scan) {
                return $this->mapHistoryRow($scan);
            })
        );

        return view('content.apps.ai.app-ai-token-history', [
            'pageConfigs' => $pageConfigs,
            'tokenHistoryRows' => $historyRows,
            'tokenHistoryFilters' => $filters,
            'tokenHistorySummary' => $this->buildMonthlySummary(clone $baseQuery, $filters),
            'tokenHistoryActivityOptions' => [
                self::ACTIVITY_ALL => 'Sve aktivnosti',
                self::ACTIVITY_AI_SCAN => 'AI scan',
            ],
            'tokenHistoryMonthOptions' => self::MONTH_OPTIONS,
            'tokenHistoryYearOptions' => $this->resolveYearOptions(),
            'tokenHistoryPerPage' => $perPage,
            'tokenHistoryPerPageOptions' => self::PER_PAGE_OPTIONS,
            'tokenHistoryLastLoadedAtDisplay' => now()->format('d.m.Y H:i:s'),
            'showAiTokenUsage' => $showTokenUsage,
        ]);
    }

    public function statuses(Request $request): JsonResponse
    {
        $this->authorizeModuleAccess($request);

        $ids = $this->resolveRequestedIds($request);

        if ($ids === []) {
            return response()->json([
                'rows' => [],
                'last_loaded_at' => now()->toIso8601String(),
                'last_loaded_at_display' => now()->format('d.m.Y H:i:s'),
            ]);
        }

        $rows = OrderAiScan::query()
            ->whereIn('id', $ids)
            ->get([
                'id',
                'status',
                'error_message',
                'processed_at',
                'transferred_at',
                'pantheon_order_key',
                'pantheon_order_view',
                'pantheon_order_qid',
            ]);

        return response()->json([
            'rows' => $rows->mapWithKeys(function (OrderAiScan $scan) {
                return [(string) $scan->id => $this->mapHistoryStatusRow($scan)];
            })->all(),
            'last_loaded_at' => now()->toIso8601String(),
            'last_loaded_at_display' => now()->format('d.m.Y H:i:s'),
        ]);
    }

    private function authorizeModuleAccess(Request $request): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $canAccess = method_exists($user, 'canAccessAiOrderModule')
            ? (bool) $user->canAccessAiOrderModule()
            : false;

        if (!$canAccess) {
            abort(403);
        }
    }

    private function shouldShowTokenUsage(Request $request): bool
    {
        $user = $request->user();

        return is_object($user)
            && method_exists($user, 'isQlaDevUser')
            && (bool) $user->isQlaDevUser();
    }

    private function baseHistoryQuery(): Builder
    {
        return OrderAiScan::query()
            ->where(function (Builder $query) {
                $query
                    ->where('credits_spent', '>', 0)
                    ->orWhereNotNull('processed_at')
                    ->orWhere('status', 'failed');
            });
    }

    private function resolveFilters(Request $request): array
    {
        $now = now();
        $currentYear = (int) $now->year;
        $currentMonth = (int) $now->month;
        $activity = trim((string) $request->query('activity', self::ACTIVITY_ALL));

        if (!in_array($activity, [self::ACTIVITY_ALL, self::ACTIVITY_AI_SCAN], true)) {
            $activity = self::ACTIVITY_ALL;
        }

        $month = (int) $request->query('month', $currentMonth);
        $year = (int) $request->query('year', $currentYear);
        $availableYears = $this->resolveYearOptions();

        if (!array_key_exists($month, self::MONTH_OPTIONS)) {
            $month = $currentMonth;
        }

        if (!in_array($year, $availableYears, true)) {
            $year = $currentYear;
        }

        $dateFromDisplay = trim((string) $request->query('date_from', ''));
        $dateToDisplay = trim((string) $request->query('date_to', ''));

        return [
            'month' => $month,
            'year' => $year,
            'date_from' => $this->parseFilterDate($dateFromDisplay, false),
            'date_to' => $this->parseFilterDate($dateToDisplay, true),
            'date_from_display' => $dateFromDisplay,
            'date_to_display' => $dateToDisplay,
            'activity' => $activity,
            'file_name' => trim((string) $request->query('file_name', '')),
        ];
    }

    private function parseFilterDate(string $value, bool $endOfDay): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        foreach (['d.m.Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                return $endOfDay ? $date->endOfDay() : $date->startOfDay();
            } catch (\Throwable $exception) {
                continue;
            }
        }

        return null;
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $eventExpression = $this->eventTimestampExpression();
        $monthRange = $this->resolveMonthRange($filters);

        $query
            ->whereRaw($eventExpression . ' >= ?', [$monthRange['start']->toDateTimeString()])
            ->whereRaw($eventExpression . ' <= ?', [$monthRange['end']->toDateTimeString()]);

        if ($filters['date_from'] instanceof Carbon) {
            $query->whereRaw($eventExpression . ' >= ?', [$filters['date_from']->toDateTimeString()]);
        }

        if ($filters['date_to'] instanceof Carbon) {
            $query->whereRaw($eventExpression . ' <= ?', [$filters['date_to']->toDateTimeString()]);
        }

        if ($filters['activity'] === self::ACTIVITY_AI_SCAN) {
            $query->whereNotNull('source_file_name');
        }

        if ($filters['file_name'] !== '') {
            $query->where('source_file_name', 'like', '%' . $filters['file_name'] . '%');
        }
    }

    private function buildMonthlySummary(Builder $baseQuery, array $filters): array
    {
        $eventExpression = $this->eventTimestampExpression();
        $monthRange = $this->resolveMonthRange($filters);
        $periodLabel = sprintf(
            '%s %d',
            self::MONTH_OPTIONS[(int) $filters['month']] ?? (string) $monthRange['start']->translatedFormat('F'),
            (int) $filters['year']
        );

        $monthlyRows = $baseQuery
            ->whereRaw($eventExpression . ' >= ?', [$monthRange['start']->toDateTimeString()])
            ->whereRaw($eventExpression . ' <= ?', [$monthRange['end']->toDateTimeString()])
            ->get();

        $documentsTotal = $monthlyRows->count();
        $chargedTokens = (int) $monthlyRows->sum(function (OrderAiScan $scan) {
            return $this->resolveDocumentMetrics($scan)['billed_tokens'];
        });
        $usdSpent = (float) $monthlyRows->sum(function (OrderAiScan $scan) {
            return $this->resolveUsageCostUsd($scan) ?? 0;
        });

        return [
            'period_label' => $periodLabel,
            'documents_total' => $documentsTotal,
            'documents_total_display' => number_format($documentsTotal, 0, ',', '.'),
            'charged_tokens' => $chargedTokens,
            'charged_tokens_display' => number_format($chargedTokens, 0, ',', '.'),
            'usd_spent' => round($usdSpent, 5),
            'usd_spent_display' => $this->formatUsd($usdSpent),
        ];
    }

    private function mapHistoryRow(OrderAiScan $scan): array
    {
        $eventTimestamp = $this->resolveEventTimestamp($scan);
        $metrics = $this->resolveDocumentMetrics($scan);
        $usageCostUsd = $this->resolveUsageCostUsd($scan);

        return array_merge([
            'id' => (int) $scan->id,
            'event_time_display' => $eventTimestamp ? $eventTimestamp->format('d.m.Y H:i') : '-',
            'usage_label' => (string) ($scan->source_origin ?? 'manual') === 'imap' ? 'AI Inbox' : 'AI narudžba',
            'activity_label' => 'AI scan',
            'file_name' => trim((string) ($scan->source_file_name ?? '')) ?: '-',
            'page_count' => $metrics['page_count'],
            'page_count_display' => $metrics['page_count'] > 0
                ? number_format($metrics['page_count'], 0, ',', '.')
                : '-',
            'billed_tokens' => $metrics['billed_tokens'],
            'billed_tokens_display' => $metrics['billed_tokens'] > 0
                ? number_format($metrics['billed_tokens'], 0, ',', '.')
                : '-',
            'usage_cost_usd' => $usageCostUsd,
            'usage_cost_usd_display' => $usageCostUsd !== null
                ? $this->formatUsd($usageCostUsd)
                : '-',
            'open_scan_url' => route('app-order-ai-scan', ['scan' => $scan->id, 'history' => 1]),
        ], $this->mapHistoryStatusRow($scan));
    }

    private function mapHistoryStatusRow(OrderAiScan $scan): array
    {
        $statusMeta = $this->resolveStatusMeta($scan);

        return [
            'id' => (int) $scan->id,
            'status_label' => $statusMeta['label'],
            'status_tone' => $statusMeta['tone'],
        ];
    }

    private function resolveDocumentMetrics(OrderAiScan $scan): array
    {
        $cacheKey = (string) ($scan->id ?: md5((string) ($scan->source_file_path ?? '')));

        if (array_key_exists($cacheKey, $this->documentMetricsCache)) {
            return $this->documentMetricsCache[$cacheKey];
        }

        $pageCount = max(0, (int) ($scan->page_count ?? 0));
        $billedTokens = max(0, (int) ($scan->billed_tokens ?? 0));
        $resolver = app(OrderAiDocumentMetrics::class);

        if ($pageCount <= 0) {
            $pageCount = max(0, (int) data_get($scan->normalized_payload, 'order.page_count', 0));
        }

        if ($pageCount > 0 && $billedTokens <= 0) {
            $billedTokens = $resolver->calculateBilledTokens($pageCount);
        }

        if ($pageCount <= 0 || $billedTokens <= 0) {
            $resolvedMetrics = $resolver->resolveForStoredFile(
                (string) config('ai-order-scan.storage_disk', 'local'),
                (string) ($scan->source_file_path ?? ''),
                (string) ($scan->source_mime_type ?? ''),
                (string) ($scan->source_file_name ?? '')
            );

            if ($pageCount <= 0) {
                $pageCount = $resolvedMetrics['page_count'];
            }

            if ($billedTokens <= 0) {
                $billedTokens = $resolvedMetrics['billed_tokens'];
            }
        }

        if ($pageCount > 0 && $billedTokens <= 0) {
            $billedTokens = $resolver->calculateBilledTokens($pageCount);
        }

        return $this->documentMetricsCache[$cacheKey] = [
            'page_count' => max(0, $pageCount),
            'billed_tokens' => max(0, $billedTokens),
        ];
    }

    private function resolveUsageCostUsd(OrderAiScan $scan): ?float
    {
        foreach ([
            'usage.cost',
            'usage.total_cost',
            'usage.cost_details.upstream_inference_cost',
            'cost',
        ] as $path) {
            $value = data_get($scan->raw_provider_response, $path);

            if (is_numeric($value) && (float) $value >= 0) {
                return round((float) $value, 5);
            }
        }

        return null;
    }

    private function formatUsd(float $value): string
    {
        return '$' . number_format($value, 5, '.', ',');
    }

    private function resolveEventTimestamp(OrderAiScan $scan): ?Carbon
    {
        foreach ([$scan->processed_at, $scan->completed_at, $scan->created_at] as $value) {
            if ($value instanceof Carbon) {
                return $value;
            }

            if ($value !== null && $value !== '') {
                return Carbon::parse($value);
            }
        }

        return null;
    }

    private function eventTimestampExpression(): string
    {
        return 'COALESCE(processed_at, completed_at, created_at)';
    }

    private function resolveStatusMeta(OrderAiScan $scan): array
    {
        $status = trim((string) ($scan->status ?? ''));
        $hasTransfer = $scan->transferred_at !== null
            || trim((string) ($scan->pantheon_order_key ?? '')) !== ''
            || trim((string) ($scan->pantheon_order_view ?? '')) !== ''
            || (int) ($scan->pantheon_order_qid ?? 0) > 0
            || $status === 'transferred';

        if ($status === 'failed' || (!$hasTransfer && trim((string) ($scan->error_message ?? '')) !== '')) {
            return ['label' => 'Neuspješno', 'tone' => 'danger'];
        }

        if ($hasTransfer) {
            return ['label' => 'Završeno', 'tone' => 'success'];
        }

        if (in_array($status, ['completed', 'ready_for_transfer'], true) || $scan->processed_at !== null) {
            return ['label' => 'Uspješno', 'tone' => 'info'];
        }

        return ['label' => 'Obrada', 'tone' => 'secondary'];
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);

        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            return 10;
        }

        return $perPage;
    }

    private function resolveYearOptions(): array
    {
        $currentYear = (int) now()->year;
        $startYear = min(2026, $currentYear);

        return range($startYear, $currentYear);
    }

    private function resolveMonthRange(array $filters): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);
        $start = Carbon::create($year, $month, 1)->startOfMonth();

        return [
            'start' => $start,
            'end' => $start->copy()->endOfMonth(),
        ];
    }

    private function resolveRequestedIds(Request $request): array
    {
        $values = $request->query('ids', []);

        if (!is_array($values)) {
            $values = [$values];
        }

        return collect($values)
            ->map(function ($value) {
                return (int) $value;
            })
            ->filter(function (int $value) {
                return $value > 0;
            })
            ->unique()
            ->take(100)
            ->values()
            ->all();
    }
}
