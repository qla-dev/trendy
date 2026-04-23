<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReleasedMaterialDocumentController extends Controller
{
    private const DOCUMENT_TYPE = '6400';

    public function index(Request $request)
    {
        if (!$this->canAccessDocuments($request->user())) {
            return redirect()->route('misc-not-authorized');
        }

        return view('content.apps.documents.released-materials', [
            'pageConfigs' => ['pageHeader' => false],
            'releasedMaterialsDataUrl' => route('app-released-material-documents-data'),
            'documentType' => self::DOCUMENT_TYPE,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$this->canAccessDocuments($request->user())) {
            return response()->json([
                'message' => 'Nemate dozvolu za pristup dokumentima.',
                'data' => [],
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
            ], 403);
        }

        try {
            $length = $this->resolveLength((int) $request->input('length', 10));
            $start = max(0, (int) $request->input('start', 0));
            $filters = $this->extractFilters($request);

            $total = (clone $this->baseQuery())->count();

            $filteredQuery = $this->baseQuery();
            $this->applyFilters($filteredQuery, $filters);
            $filteredTotal = (clone $filteredQuery)->count();

            $this->applySort($filteredQuery, $request);

            $rows = $filteredQuery
                ->offset($start)
                ->limit($length)
                ->get()
                ->map(fn ($row) => $this->mapRow($row))
                ->values()
                ->all();

            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => $total,
                'recordsFiltered' => $filteredTotal,
                'data' => $rows,
                'meta' => [
                    'count' => count($rows),
                    'total' => $total,
                    'filtered_total' => $filteredTotal,
                    'document_type' => self::DOCUMENT_TYPE,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Released material documents list failed.', [
                'document_type' => self::DOCUMENT_TYPE,
                'filters' => $request->except(['_token']),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greska pri ucitavanju razduzenih materijala.',
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ], 500);
        }
    }

    private function baseQuery(): Builder
    {
        return DB::connection('sqlsrv')
            ->table('dbo.tHE_Move as m')
            ->join('dbo.tHE_MoveItem as mi', 'mi.acKey', '=', 'm.acKey')
            ->leftJoin('dbo.tHF_LinkMoveWOEx as move_wo', 'move_wo.acKey', '=', 'm.acKey')
            ->leftJoin('dbo.tHF_WOEx as wo', 'wo.acKey', '=', 'move_wo.acLnkKey')
            ->where('m.acDocType', self::DOCUMENT_TYPE)
            ->selectRaw($this->trimExpr('m.acKey') . ' as document_key')
            ->selectRaw($this->trimExpr('m.acKeyView') . ' as document_number')
            ->selectRaw($this->trimExpr('m.acDocType') . ' as document_type')
            ->selectRaw('CONVERT(varchar(10), m.adDate, 120) as document_date')
            ->selectRaw($this->trimExpr('m.acDoc1') . ' as order_reference')
            ->selectRaw($this->trimExpr('m.acDoc2') . ' as pantheon_work_order_reference')
            ->selectRaw($this->trimExpr('move_wo.acLnkKey') . ' as linked_work_order_key')
            ->selectRaw($this->trimExpr('wo.acKeyView') . ' as work_order_number')
            ->selectRaw($this->trimExpr('wo.acLnkKey') . ' as order_number')
            ->selectRaw('CAST(ISNULL(wo.anLnkNo, 0) as int) as order_position')
            ->selectRaw('CAST(ISNULL(mi.anNo, 0) as int) as position')
            ->selectRaw($this->trimExpr('mi.acIdent') . ' as material_code')
            ->selectRaw($this->trimExpr('mi.acName') . ' as material_name')
            ->selectRaw('CAST(mi.anQty as float) as quantity')
            ->selectRaw($this->trimExpr('mi.acUM') . ' as unit')
            ->selectRaw('CAST(mi.anPrice as float) as document_price')
            ->selectRaw("COALESCE(NULLIF({$this->trimExpr('mi.acNote')}, ''), NULLIF({$this->trimExpr('m.acNote')}, ''), '') as note");
    }

    private function extractFilters(Request $request): array
    {
        $rawSearch = $request->input('search.value');

        if ($rawSearch === null) {
            $rawSearch = $request->input('search', '');
        }

        return [
            'search' => is_string($rawSearch) ? trim($rawSearch) : '',
            'dokument' => trim((string) $request->input('dokument', '')),
            'predracun' => trim((string) $request->input('predracun', '')),
            'narudzba' => trim((string) $request->input('narudzba', '')),
            'sifra' => trim((string) $request->input('sifra', '')),
            'naziv' => trim((string) $request->input('naziv', '')),
            'napomena' => trim((string) $request->input('napomena', '')),
            'datum_od' => trim((string) $request->input('datum_od', '')),
            'datum_do' => trim((string) $request->input('datum_do', '')),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = (string) ($filters['search'] ?? '');

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $this->applyLikeAny($searchQuery, [
                    $this->trimExpr('m.acKey'),
                    $this->trimExpr('m.acKeyView'),
                    $this->trimExpr('m.acDoc1'),
                    $this->trimExpr('m.acDoc2'),
                    $this->trimExpr('move_wo.acLnkKey'),
                    $this->formatLongPantheonExpr('move_wo.acLnkKey'),
                    $this->trimExpr('wo.acKeyView'),
                    $this->trimExpr('wo.acLnkKey'),
                    $this->formatLongPantheonExpr('wo.acLnkKey'),
                    $this->trimExpr('mi.acIdent'),
                    $this->trimExpr('mi.acName'),
                    $this->trimExpr('mi.acNote'),
                ], $search, 'or');
            });
        }

        $this->applyLikeFilter($query, [$this->trimExpr('m.acKey'), $this->trimExpr('m.acKeyView')], (string) ($filters['dokument'] ?? ''));
        $this->applyLikeFilter($query, [$this->trimExpr('m.acDoc2'), $this->trimExpr('move_wo.acLnkKey'), $this->formatLongPantheonExpr('move_wo.acLnkKey'), $this->trimExpr('wo.acKeyView')], (string) ($filters['predracun'] ?? ''));
        $this->applyLikeFilter($query, [$this->trimExpr('m.acDoc1'), $this->trimExpr('wo.acLnkKey'), $this->formatLongPantheonExpr('wo.acLnkKey')], (string) ($filters['narudzba'] ?? ''));
        $this->applyLikeFilter($query, [$this->trimExpr('mi.acIdent')], (string) ($filters['sifra'] ?? ''));
        $this->applyLikeFilter($query, [$this->trimExpr('mi.acName')], (string) ($filters['naziv'] ?? ''));
        $this->applyLikeFilter($query, [$this->trimExpr('mi.acNote'), $this->trimExpr('m.acNote')], (string) ($filters['napomena'] ?? ''));

        $dateFrom = $this->normalizeDate((string) ($filters['datum_od'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('m.adDate', '>=', $dateFrom);
        }

        $dateTo = $this->normalizeDate((string) ($filters['datum_do'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('m.adDate', '<=', $dateTo);
        }
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sortMap = [
            0 => 'm.acKey',
            1 => 'm.adDate',
            2 => 'wo.acKeyView',
            3 => 'wo.acLnkKey',
            4 => 'mi.anNo',
            5 => 'mi.acIdent',
            6 => 'mi.acName',
            7 => 'mi.anQty',
            8 => 'mi.acUM',
            9 => 'mi.anPrice',
            10 => 'mi.acNote',
        ];

        $columnIndex = (int) $request->input('order.0.column', 0);
        $direction = strtolower(trim((string) $request->input('order.0.dir', 'desc')));

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $sortColumn = $sortMap[$columnIndex] ?? 'm.acKey';
        $query->orderBy($sortColumn, $direction)
            ->orderBy('mi.anNo');
    }

    private function applyLikeFilter(Builder $query, array $expressions, string $value): void
    {
        $value = trim($value);

        if ($value === '') {
            return;
        }

        $query->where(function (Builder $filterQuery) use ($expressions, $value): void {
            $this->applyLikeAny($filterQuery, $expressions, $value, 'or');
        });
    }

    private function applyLikeAny(Builder $query, array $expressions, string $value, string $boolean = 'and'): void
    {
        $needle = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value) . '%';

        foreach ($expressions as $index => $expression) {
            $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
            $query->{$method}($expression . ' LIKE ?', [$needle]);
        }
    }

    private function mapRow(object $row): array
    {
        $documentKey = trim((string) ($row->document_key ?? ''));
        $documentNumber = trim((string) ($row->document_number ?? ''));
        $workOrderNumber = $this->firstFilled([
            $this->formatLongPantheonNumber((string) ($row->linked_work_order_key ?? '')),
            $this->formatLongPantheonNumber((string) ($row->work_order_number ?? '')),
            trim((string) ($row->pantheon_work_order_reference ?? '')),
        ]);
        $orderReference = $this->resolveOrderReference($row);
        $orderNumber = trim((string) ($row->order_number ?? ''));
        $resolvedOrderNumber = $orderNumber !== '' ? $orderNumber : $this->extractOrderNumberFromReference($orderReference);
        $documentPrice = $this->nullableFloat($row->document_price ?? null);
        $roundedDocumentPrice = $documentPrice === null ? null : round($documentPrice, 2);

        return [
            'document_key' => $documentKey,
            'document_number' => $documentNumber !== '' ? $documentNumber : $this->formatPantheonDocumentNumber($documentKey),
            'document_type' => trim((string) ($row->document_type ?? self::DOCUMENT_TYPE)),
            'document_date' => trim((string) ($row->document_date ?? '')),
            'document_date_display' => $this->formatDate((string) ($row->document_date ?? '')),
            'predracun' => $workOrderNumber,
            'work_order_key' => trim((string) ($row->linked_work_order_key ?? '')),
            'rn' => $orderReference,
            'narudzba' => $this->formatLongPantheonNumber($resolvedOrderNumber),
            'order_number' => $resolvedOrderNumber,
            'order_position' => (int) ($row->order_position ?? 0),
            'napomena' => trim((string) ($row->note ?? '')),
            'pozicija' => (int) ($row->position ?? 0),
            'sifra' => trim((string) ($row->material_code ?? '')),
            'naziv' => trim((string) ($row->material_name ?? '')),
            'kolicina' => $this->nullableFloat($row->quantity ?? null),
            'jm' => strtoupper(trim((string) ($row->unit ?? ''))),
            'cijena' => $roundedDocumentPrice,
            'cijena_display' => $this->formatMoney($roundedDocumentPrice),
            'cijena_u_dokumentu' => $roundedDocumentPrice,
            'cijena_u_dokumentu_display' => $this->formatMoney($roundedDocumentPrice),
        ];
    }

    private function resolveOrderReference(object $row): string
    {
        $rawReference = trim((string) ($row->order_reference ?? ''));

        if ($rawReference !== '') {
            return $rawReference;
        }

        $orderNumber = trim((string) ($row->order_number ?? ''));
        $position = (int) ($row->order_position ?? 0);

        if ($orderNumber === '') {
            return '';
        }

        return $position > 0 ? $orderNumber . ' - ' . $position : $orderNumber;
    }

    private function extractOrderNumberFromReference(string $reference): string
    {
        $reference = trim($reference);

        if ($reference === '') {
            return '';
        }

        $parts = preg_split('/\s*-\s*/', $reference);

        return trim((string) ($parts[0] ?? $reference));
    }

    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            $normalized = trim((string) $value);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function trimExpr(string $column): string
    {
        return "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(4000), {$column}), '')))";
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        try {
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value) === 1) {
                return Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return '';
        }
    }

    private function formatDate(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d.m.Y');
        } catch (Throwable) {
            return $value;
        }
    }

    private function formatPantheonDocumentNumber(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) >= 12) {
            return substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, -6);
        }

        return $value;
    }

    private function formatLongPantheonNumber(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 13) {
            return substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6);
        }

        return $value;
    }

    private function formatLongPantheonExpr(string $column): string
    {
        $trimmed = $this->trimExpr($column);

        return "CASE WHEN LEN({$trimmed}) = 13 AND {$trimmed} NOT LIKE '%[^0-9]%' THEN STUFF(STUFF({$trimmed}, 7, 0, '-'), 3, 0, '-') ELSE {$trimmed} END";
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric((string) $value) ? (float) $value : null;
    }

    private function formatMoney(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        return number_format($value, 2, '.', '') . ' KM';
    }

    private function resolveLength(int $requestedLength): int
    {
        if ($requestedLength < 1) {
            return 10;
        }

        return min($requestedLength, 250);
    }

    private function canAccessDocuments(mixed $user = null): bool
    {
        if (!is_object($user)) {
            return false;
        }

        if (method_exists($user, 'isAdmin')) {
            return (bool) $user->isAdmin();
        }

        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }
}
