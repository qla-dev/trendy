<?php

namespace App\Http\Controllers;

use App\Models\OrderAiScan;
use App\Services\OrderAi\OrderAiScanService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    private array $historyResultPayloadCache = [];

    public function index(Request $request)
    {
        $this->authorizeModuleAccess($request);

        $requestStartedAt = microtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;
        $checkpointAt = $requestStartedAt;

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTimeMs): void {
            $queryCount++;
            $queryTimeMs += max(0, (float) $query->time);
        });

        $pageConfigs = ['pageHeader' => false];
        $showUsdSpend = $this->shouldShowUsdSpend($request);
        $filters = $this->resolveFilters($request);
        $perPage = $this->resolvePerPage($request);
        $baseQuery = $this->baseHistoryQuery();
        $historyQuery = clone $baseQuery;

        $this->applyFilters($historyQuery, $filters);

        $historyRows = $historyQuery
            ->orderByRaw($this->eventTimestampExpression() . ' DESC')
            ->orderByDesc('id')
            ->paginate($perPage, $this->historyListColumns($showUsdSpend))
            ->withQueryString();
        $paginateMs = round((microtime(true) - $checkpointAt) * 1000, 2);
        $checkpointAt = microtime(true);

        $historyRows->setCollection(
            $historyRows->getCollection()->map(function (OrderAiScan $scan) {
                return $this->mapHistoryRow($scan);
            })
        );
        $mapRowsMs = round((microtime(true) - $checkpointAt) * 1000, 2);
        $checkpointAt = microtime(true);

        $summary = $this->buildMonthlySummary(clone $baseQuery, $filters, $showUsdSpend);
        $summaryMs = round((microtime(true) - $checkpointAt) * 1000, 2);

        Log::info('AI token history load timings.', [
            'path' => $request->path(),
            'user_id' => (int) ($request->user()->id ?? 0),
            'total_request_ms' => round((microtime(true) - $requestStartedAt) * 1000, 2),
            'query_count' => $queryCount,
            'total_query_ms' => round($queryTimeMs, 2),
            'paginate_ms' => $paginateMs,
            'map_rows_ms' => $mapRowsMs,
            'summary_ms' => $summaryMs,
            'page' => $historyRows->currentPage(),
            'per_page' => $perPage,
            'rows' => $historyRows->count(),
            'has_file_filter' => $filters['file_name'] !== '',
            'activity' => $filters['activity'],
        ]);

        return view('content.apps.ai.app-ai-token-history', [
            'pageConfigs' => $pageConfigs,
            'tokenHistoryRows' => $historyRows,
            'tokenHistoryFilters' => $filters,
            'tokenHistorySummary' => $summary,
            'tokenHistoryActivityOptions' => [
                self::ACTIVITY_ALL => 'Sve aktivnosti',
                self::ACTIVITY_AI_SCAN => 'AI scan',
            ],
            'tokenHistoryMonthOptions' => self::MONTH_OPTIONS,
            'tokenHistoryYearOptions' => $this->resolveYearOptions(),
            'tokenHistoryPerPage' => $perPage,
            'tokenHistoryPerPageOptions' => self::PER_PAGE_OPTIONS,
            'tokenHistoryLastLoadedAtDisplay' => now()->format('d.m.Y H:i:s'),
            'showAiTokenUsdSpend' => $showUsdSpend,
        ]);
    }

    public function statuses(Request $request): JsonResponse
    {
        $this->authorizeModuleAccess($request);

        $ids = $this->resolveRequestedIds($request);

        if ($ids === []) {
            return $this->jsonNoStore([
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
                'processing_step',
                'error_message',
                'processed_at',
                'transferred_at',
                'pantheon_order_key',
                'pantheon_order_view',
                'pantheon_order_qid',
            ]);

        $payload = [
            'rows' => $rows->mapWithKeys(function (OrderAiScan $scan) {
                return [(string) $scan->id => $this->mapHistoryStatusRow($scan)];
            })->all(),
            'last_loaded_at' => now()->toIso8601String(),
            'last_loaded_at_display' => now()->format('d.m.Y H:i:s'),
        ];

        return $this->jsonNoStore($payload);
    }

    public function retry(Request $request, OrderAiScan $scan, OrderAiScanService $scanService): JsonResponse
    {
        $this->authorizeModuleAccess($request);

        if (!$this->isRetryEligible($scan) || !$scanService->canRescan($scan)) {
            return $this->jsonNoStore([
                'message' => 'Ponovno AI skeniranje je dostupno samo za neuspješne AI scanove.',
                'last_loaded_at_display' => now()->format('d.m.Y H:i:s'),
                'data' => $this->mapHistoryRow($scan->fresh()),
            ], 422);
        }

        $retriedScan = $scanService->rescan(
            $scan,
            $request->user(),
            (string) ($scan->source_origin ?? 'manual') === 'imap'
        );
        $retriedScan->refresh();

        if ((string) ($retriedScan->status ?? '') === 'failed') {
            return $this->jsonNoStore([
                'message' => trim((string) ($retriedScan->error_message ?? '')) !== ''
                    ? (string) $retriedScan->error_message
                    : 'AI skeniranje nije uspjelo ni nakon ponovnog pokretanja.',
                'last_loaded_at_display' => now()->format('d.m.Y H:i:s'),
                'data' => $this->mapHistoryRow($retriedScan),
            ], 422);
        }

        return $this->jsonNoStore([
            'message' => (string) ($retriedScan->source_origin ?? 'manual') === 'imap'
                ? 'AI skeniranje je ponovo pokrenuto. Status će biti osvježen automatski.'
                : 'AI skeniranje je uspješno ponovo pokrenuto.',
            'last_loaded_at_display' => now()->format('d.m.Y H:i:s'),
            'data' => $this->mapHistoryRow($retriedScan),
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

    private function shouldShowUsdSpend(Request $request): bool
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

    private function buildMonthlySummary(Builder $baseQuery, array $filters, bool $includeUsdSpend = false): array
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
            ->get($this->historySummaryColumns($includeUsdSpend));

        $documentsTotal = $monthlyRows->count();
        $chargedTokens = (int) $monthlyRows->sum(function (OrderAiScan $scan) {
            return $this->resolveDocumentMetrics($scan)['billed_tokens'];
        });
        $successfulTotal = $monthlyRows->filter(function (OrderAiScan $scan) {
            return $this->resolveStatusOutcome($scan) === 'success';
        })->count();
        $failedTotal = $monthlyRows->filter(function (OrderAiScan $scan) {
            return $this->resolveStatusOutcome($scan) === 'failed';
        })->count();
        $usdSpent = $includeUsdSpend
            ? (float) $monthlyRows->sum(function (OrderAiScan $scan) {
                return $this->resolveUsageCostUsd($scan) ?? 0;
            })
            : 0.0;

        return [
            'period_label' => $periodLabel,
            'documents_total' => $documentsTotal,
            'documents_total_display' => number_format($documentsTotal, 0, ',', '.'),
            'charged_tokens' => $chargedTokens,
            'charged_tokens_display' => number_format($chargedTokens, 0, ',', '.'),
            'successful_total' => $successfulTotal,
            'successful_total_display' => number_format($successfulTotal, 0, ',', '.'),
            'failed_total' => $failedTotal,
            'failed_total_display' => number_format($failedTotal, 0, ',', '.'),
            'usd_spent' => round($usdSpent, 5),
            'usd_spent_display' => $this->formatUsd($usdSpent),
        ];
    }

    private function mapHistoryRow(OrderAiScan $scan): array
    {
        $eventTimestamp = $this->resolveEventTimestamp($scan);
        $metrics = $this->resolveDocumentMetrics($scan);
        $usageCostUsd = $this->resolveUsageCostUsd($scan);
        $amountMeta = $this->resolveAmountMeta($scan);
        $transferMeta = $this->resolveTransferActionMeta($scan);

        return array_merge([
            'id' => (int) $scan->id,
            'event_time_display' => $eventTimestamp ? $eventTimestamp->format('d.m.Y H:i') : '-',
            'usage_label' => (string) ($scan->source_origin ?? 'manual') === 'imap' ? 'AI Inbox' : 'AI narudžba',
            'activity_label' => 'AI scan',
            'amount_display' => $amountMeta['display'],
            'amount_tone' => $amountMeta['tone'],
            'amount_valid' => $amountMeta['valid'],
            'amount_title' => $amountMeta['title'],
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
            'download_source_url' => $this->resolveSourceDownloadUrl($scan),
        ], $this->mapHistoryStatusRow($scan), $transferMeta);
    }

    private function mapHistoryStatusRow(OrderAiScan $scan): array
    {
        $statusOutcome = $this->resolveStatusOutcome($scan);
        $statusMeta = $this->resolveStatusMeta($scan);
        $transferMeta = $this->resolveTransferActionMeta($scan);
        $retryMeta = $this->resolveRetryActionMeta($scan);

        return [
            'id' => (int) $scan->id,
            'status_outcome' => $statusOutcome,
            'status_label' => $statusMeta['label'],
            'status_tone' => $statusMeta['tone'],
            'status_error_message' => trim((string) ($scan->error_message ?? '')) ?: null,
            'retry_enabled' => $retryMeta['retry_enabled'],
            'retry_icon' => $retryMeta['retry_icon'],
            'retry_tooltip' => $retryMeta['retry_tooltip'],
            'transfer_enabled' => $transferMeta['transfer_enabled'],
            'transfer_completed' => $transferMeta['transfer_completed'],
            'transfer_icon' => $transferMeta['transfer_icon'],
            'transfer_tooltip' => $transferMeta['transfer_tooltip'],
        ];
    }

    private function resolveDocumentMetrics(OrderAiScan $scan): array
    {
        $cacheKey = (string) ($scan->id ?: md5((string) ($scan->source_file_path ?? '')));

        if (array_key_exists($cacheKey, $this->documentMetricsCache)) {
            return $this->documentMetricsCache[$cacheKey];
        }
        $metrics = app(OrderAiScanService::class)->resolveDisplayDocumentMetrics($scan);

        return $this->documentMetricsCache[$cacheKey] = [
            'page_count' => max(0, (int) ($metrics['page_count'] ?? 0)),
            'billed_tokens' => max(0, (int) ($metrics['billed_tokens'] ?? 0)),
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
        $outcome = $this->resolveStatusOutcome($scan);
        $hasTransfer = $this->hasTransferResult($scan, $status);

        if ($outcome === 'failed') {
            return ['label' => 'Neuspješan AI scan', 'tone' => 'danger'];
        }

        if ($hasTransfer) {
            return ['label' => 'Uspješan transfer', 'tone' => 'success'];
        }

        if ($outcome === 'success') {
            return ['label' => 'Čeka na transfer u bazu', 'tone' => 'info'];
        }

        return ['label' => 'Obrada', 'tone' => 'secondary'];
    }

    private function resolveStatusOutcome(OrderAiScan $scan): string
    {
        $status = trim((string) ($scan->status ?? ''));
        $hasTransfer = $this->hasTransferResult($scan, $status);

        if ($status === 'failed' || (!$hasTransfer && trim((string) ($scan->error_message ?? '')) !== '')) {
            return 'failed';
        }

        if (in_array($status, ['completed', 'ready_for_transfer'], true) || $scan->processed_at !== null || $hasTransfer) {
            return 'success';
        }

        return 'processing';
    }

    private function hasTransferResult(OrderAiScan $scan, string $status): bool
    {
        return $scan->transferred_at !== null
            || trim((string) ($scan->pantheon_order_key ?? '')) !== ''
            || trim((string) ($scan->pantheon_order_view ?? '')) !== ''
            || (int) ($scan->pantheon_order_qid ?? 0) > 0
            || $status === 'transferred';
    }

    private function resolveSourceDownloadUrl(OrderAiScan $scan): ?string
    {
        return trim((string) ($scan->source_file_path ?? '')) !== ''
            ? route('app-order-ai-scan-source-download', ['scan' => $scan->id])
            : null;
    }

    private function resolveAmountMeta(OrderAiScan $scan): array
    {
        $payload = $this->resolveHistoryResultPayload($scan);
        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $currency = trim((string) data_get($payload, 'order.currency', ''));
        $amountValue = $this->resolveDisplayedDocumentAmount($summary);
        $itemsTotal = $this->resolveItemsTotal($items);
        $hasComparableValues = $amountValue > 0 || $itemsTotal > 0;
        $matches = $hasComparableValues && abs(round($amountValue - $itemsTotal, 2)) <= 0.01;
        $difference = round($amountValue - $itemsTotal, 2);
        $display = $this->formatMoney($amountValue);

        if ($currency !== '') {
            $display .= ' ' . $currency;
        }

        return [
            'display' => $display,
            'tone' => !$hasComparableValues ? 'secondary' : ($matches ? 'success' : 'danger'),
            'valid' => $matches,
            'title' => !$hasComparableValues
                ? 'Ukupan iznos nije potvrđen iz skeniranog dokumenta.'
                : ($matches
                    ? 'Iznos odgovara zbiru stavki.'
                    : 'Razlika: ' . $this->formatMoney($difference)),
        ];
    }

    private function resolveHistoryResultPayload(OrderAiScan $scan): array
    {
        $cacheKey = (string) ($scan->id ?: spl_object_id($scan));

        if (array_key_exists($cacheKey, $this->historyResultPayloadCache)) {
            return $this->historyResultPayloadCache[$cacheKey];
        }

        $resultPayload = is_array($scan->normalized_payload) ? $scan->normalized_payload : [];
        $transferPreview = is_array($scan->pantheon_transfer_payload) ? $scan->pantheon_transfer_payload : [];
        $preparedPayload = is_array($transferPreview['payload'] ?? null) ? $transferPreview['payload'] : [];

        if ($preparedPayload !== []) {
            $resultPayload = $this->overlayHistoryTransferPreviewPayload($resultPayload, $preparedPayload);
        }

        return $this->historyResultPayloadCache[$cacheKey] = $resultPayload;
    }

    private function overlayHistoryTransferPreviewPayload(array $payload, array $preparedPayload): array
    {
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        $items = is_array($payload['items'] ?? null) ? array_values($payload['items']) : [];
        $preparedItems = is_array($preparedPayload['items'] ?? null) ? array_values($preparedPayload['items']) : [];

        $payload['order'] = array_merge($order, [
            'currency' => trim((string) ($preparedPayload['currency'] ?? ($order['currency'] ?? ''))),
        ]);

        foreach ($preparedItems as $index => $preparedItem) {
            if (!is_array($preparedItem)) {
                continue;
            }

            $existingItem = is_array($items[$index] ?? null) ? $items[$index] : [];

            $items[$index] = array_merge($existingItem, [
                'quantity' => (float) ($preparedItem['quantity'] ?? ($existingItem['quantity'] ?? 0)),
                'unit_price' => (float) ($existingItem['unit_price'] ?? ($preparedItem['unit_price'] ?? 0)),
                'line_total' => (float) ($existingItem['line_total'] ?? ($preparedItem['line_total'] ?? 0)),
            ]);
        }

        $payload['items'] = $items;
        $payload['summary'] = array_merge($summary, [
            'subtotal' => (float) ($preparedPayload['subtotal'] ?? ($summary['subtotal'] ?? 0)),
            'vat_total' => (float) ($preparedPayload['vat_total'] ?? ($summary['vat_total'] ?? 0)),
            'grand_total' => (float) ($preparedPayload['grand_total'] ?? ($summary['grand_total'] ?? 0)),
        ]);

        return $payload;
    }

    private function resolveTransferActionMeta(OrderAiScan $scan): array
    {
        $status = trim((string) ($scan->status ?? ''));
        $hasTransfer = $this->hasTransferResult($scan, $status);

        if ($hasTransfer) {
            return [
                'transfer_enabled' => false,
                'transfer_completed' => true,
                'transfer_icon' => 'check',
                'transfer_tooltip' => 'Narudžba je već prebačena u bazu.',
            ];
        }

        $statusOutcome = $this->resolveStatusOutcome($scan);

        if ($status === 'transferring') {
            return [
                'transfer_enabled' => false,
                'transfer_completed' => false,
                'transfer_icon' => 'arrow-right',
                'transfer_tooltip' => 'Transfer u bazu je u toku.',
            ];
        }

        if (in_array($status, ['completed', 'ready_for_transfer'], true) && $statusOutcome === 'success') {
            $payload = is_array($scan->normalized_payload) ? $scan->normalized_payload : null;
            $transferReady = is_array($payload) ? $this->looksTransferReady($payload) : true;

            if ($transferReady) {
                return [
                    'transfer_enabled' => true,
                    'transfer_completed' => false,
                    'transfer_icon' => 'arrow-right',
                    'transfer_tooltip' => 'Transfer u bazu',
                ];
            }
        }

        if ($statusOutcome === 'failed') {
            return [
                'transfer_enabled' => false,
                'transfer_completed' => false,
                'transfer_icon' => 'arrow-right',
                'transfer_tooltip' => 'Transfer nije dostupan jer AI scan nije uspio.',
            ];
        }

        return [
            'transfer_enabled' => false,
            'transfer_completed' => false,
            'transfer_icon' => 'arrow-right',
            'transfer_tooltip' => 'Transfer će biti dostupan nakon uspješne AI obrade.',
        ];
    }

    private function resolveRetryActionMeta(OrderAiScan $scan): array
    {
        $status = trim((string) ($scan->status ?? ''));

        if ($this->isRetryEligible($scan)) {
            return [
                'retry_enabled' => true,
                'retry_icon' => 'refresh-cw',
                'retry_tooltip' => 'Ponovi AI scan',
            ];
        }

        if (in_array($status, ['uploaded', 'extracting', 'ready_for_transfer', 'transferring'], true)) {
            return [
                'retry_enabled' => false,
                'retry_icon' => 'refresh-cw',
                'retry_tooltip' => 'AI scan je trenutno u obradi.',
            ];
        }

        if ($this->hasTransferResult($scan, $status) || in_array($status, ['completed', 'transferred'], true)) {
            return [
                'retry_enabled' => false,
                'retry_icon' => 'refresh-cw',
                'retry_tooltip' => 'AI scan je uspješno završen.',
            ];
        }

        return [
            'retry_enabled' => false,
            'retry_icon' => 'refresh-cw',
            'retry_tooltip' => 'Ponovno AI skeniranje trenutno nije dostupno.',
        ];
    }

    private function isRetryEligible(OrderAiScan $scan): bool
    {
        return app(OrderAiScanService::class)->canRescan($scan);
    }

    private function isTransferFailure(OrderAiScan $scan): bool
    {
        $haystack = trim((string) ($scan->processing_step ?? '')) . ' ' . trim((string) ($scan->error_message ?? ''));

        return preg_match('/transfer|baza|pantheon/i', $haystack) === 1;
    }

    private function looksTransferReady(array $payload): bool
    {
        $customerName = trim((string) data_get($payload, 'order.customer_name', ''));
        $items = data_get($payload, 'items', []);

        return $customerName !== ''
            && is_array($items)
            && !empty($items);
    }

    private function resolveDisplayedDocumentAmount(array $summary): float
    {
        $subtotal = max(0, (float) ($summary['subtotal'] ?? 0));
        $grandTotal = max(0, (float) ($summary['grand_total'] ?? 0));

        return round($subtotal > 0 ? $subtotal : $grandTotal, 2);
    }

    private function resolveItemsTotal(array $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $total += $this->resolveLineSourceTotal($item);
        }

        return round($total, 2);
    }

    private function resolveLineSourceTotal(array $item): float
    {
        $quantity = max(0, (float) ($item['quantity'] ?? 0));
        $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));
        $lineTotal = max(0, (float) ($item['line_total'] ?? 0));
        $discountPercent = max(0, (float) ($item['discount_percent'] ?? 0));
        $discountFactor = max(0, 1 - ($discountPercent / 100));
        $baseValue = $lineTotal > 0 ? $lineTotal : ($quantity * $unitPrice * $discountFactor);

        return round(max(0, $baseValue), 2);
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);

        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            return 10;
        }

        return $perPage;
    }

    private function historyListColumns(bool $includeUsdSpend = false): array
    {
        $columns = [
            'id',
            'status',
            'processing_step',
            'error_message',
            'processed_at',
            'completed_at',
            'created_at',
            'source_origin',
            'source_file_name',
            'source_file_path',
            'page_count',
            'billed_tokens',
            'normalized_payload',
            'pantheon_transfer_payload',
            'transferred_at',
            'pantheon_order_key',
            'pantheon_order_view',
            'pantheon_order_qid',
        ];

        if ($includeUsdSpend) {
            $columns[] = 'raw_provider_response';
        }

        return $columns;
    }

    private function historySummaryColumns(bool $includeUsdSpend = false): array
    {
        $columns = [
            'id',
            'status',
            'processing_step',
            'error_message',
            'processed_at',
            'page_count',
            'billed_tokens',
            'transferred_at',
            'pantheon_order_key',
            'pantheon_order_view',
            'pantheon_order_qid',
        ];

        if ($includeUsdSpend) {
            $columns[] = 'raw_provider_response';
        }

        return $columns;
    }

    private function resolveBilledTokensFromPageCount(int $pageCount): int
    {
        if ($pageCount <= 0) {
            return 0;
        }

        return max(10, $pageCount);
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

    private function jsonNoStore(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
