<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
            'releasedMaterialsDeleteUrl' => route('app-released-material-documents-destroy'),
            'canDeleteReleasedMaterialDocuments' => $this->canDeleteDocuments($request->user()),
            'documentType' => self::DOCUMENT_TYPE,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if (!$this->canDeleteDocuments($request->user())) {
            return response()->json([
                'message' => 'Nemate dozvolu za brisanje dokumenta.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'document_key' => ['required', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravni podaci za brisanje dokumenta.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $documentKey = trim((string) ($validated['document_key'] ?? ''));
        $connection = DB::connection('sqlsrv');

        try {
            $documentRow = $connection
                ->table($this->qualifiedReleasedMaterialMoveTableName())
                ->selectRaw($this->trimExpr('acKey') . ' as document_key')
                ->selectRaw($this->trimExpr('acKeyView') . ' as document_number')
                ->where('acKey', $documentKey)
                ->where('acDocType', self::DOCUMENT_TYPE)
                ->first();

            if ($documentRow === null) {
                return response()->json([
                    'message' => 'Dokument nije pronađen.',
                ], 404);
            }

            $documentNumber = trim((string) ($documentRow->document_number ?? ''));
            if ($documentNumber === '') {
                $documentNumber = $this->formatPantheonDocumentNumber($documentKey);
            }

            $deletedCounts = $connection->transaction(function () use ($connection, $documentKey) {
                $deleted = [
                    'move_item_links' => 0,
                    'move_work_order_links' => 0,
                    'fx_rates' => 0,
                    'items' => 0,
                    'documents' => 0,
                ];

                $deleted['move_item_links'] = $connection
                    ->table($this->qualifiedReleasedMaterialMoveItemWorkOrderItemLinkTableName())
                    ->where('acKey', $documentKey)
                    ->delete();

                $deleted['move_work_order_links'] = $connection
                    ->table($this->qualifiedReleasedMaterialMoveWorkOrderLinkTableName())
                    ->where('acKey', $documentKey)
                    ->delete();

                $deleted['fx_rates'] = $connection
                    ->table($this->qualifiedReleasedMaterialMoveFxRateTableName())
                    ->where('acKey', $documentKey)
                    ->delete();

                $deleted['items'] = $connection
                    ->table($this->qualifiedReleasedMaterialMoveItemTableName())
                    ->where('acKey', $documentKey)
                    ->delete();

                $deleted['documents'] = $connection
                    ->table($this->qualifiedReleasedMaterialMoveTableName())
                    ->where('acKey', $documentKey)
                    ->where('acDocType', self::DOCUMENT_TYPE)
                    ->delete();

                if ($deleted['documents'] < 1) {
                    throw new \RuntimeException('Dokument nije obrisan.');
                }

                return $deleted;
            });

            Log::info('Released material document deleted.', [
                'document_key' => $documentKey,
                'document_number' => $documentNumber,
                'user_id' => (int) ($request->user()->id ?? 0),
                'deleted_counts' => $deletedCounts,
            ]);

            return response()->json([
                'message' => 'Dokument je uspjesno obrisan.',
                'data' => [
                    'document_key' => $documentKey,
                    'document_number' => $documentNumber,
                    'deleted' => $deletedCounts,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Released material document delete failed.', [
                'document_key' => $documentKey,
                'user_id' => (int) ($request->user()->id ?? 0),
                'message' => $exception->getMessage(),
            ]);

            $isUserFacingException = $exception instanceof \RuntimeException || $exception instanceof \InvalidArgumentException;

            return response()->json([
                'message' => $isUserFacingException
                    ? $exception->getMessage()
                    : 'Greska pri brisanju dokumenta.',
            ], $isUserFacingException ? 422 : 500);
        }
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
            ->selectRaw('CONVERT(varchar(19), ' . $this->releasedMaterialDocumentDateTimeExpr() . ', 120) as document_date')
            ->selectRaw($this->trimExpr('m.acDoc1') . ' as order_reference')
            ->selectRaw($this->trimExpr('m.acDoc2') . ' as pantheon_work_order_reference')
            ->selectRaw($this->trimExpr('move_wo.acLnkKey') . ' as linked_work_order_key')
            ->selectRaw($this->trimExpr('wo.acKeyView') . ' as work_order_number')
            ->selectRaw($this->trimExpr('wo.acLnkKey') . ' as order_number')
            ->selectRaw('CAST(ISNULL(wo.anLnkNo, 0) as int) as order_position')
            ->selectRaw('CAST(ISNULL(mi.anNo, 0) as int) as position')
            ->selectRaw($this->releasedMaterialBarcodeCreatedExpr() . ' as is_enalog_created')
            ->selectRaw($this->trimExpr('mi.acIdent') . ' as material_code')
            ->selectRaw($this->trimExpr('mi.acName') . ' as material_name')
            ->selectRaw('CAST(mi.anQty as float) as quantity')
            ->selectRaw($this->trimExpr('mi.acUM') . ' as unit')
            ->selectRaw('CAST(mi.anPrice as float) as document_price')
            ->selectRaw("COALESCE(NULLIF({$this->trimExpr('mi.acNote')}, ''), NULLIF({$this->trimExpr('m.acNote')}, ''), '') as raw_note");
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
        $displayNoteExpr = $this->releasedMaterialDisplayNoteExpr();

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
                    $this->releasedMaterialDisplayNoteExpr(),
                ], $search, 'or');
            });
        }

        $this->applyLikeFilter($query, [$this->trimExpr('m.acKey'), $this->trimExpr('m.acKeyView')], (string) ($filters['dokument'] ?? ''));
        $this->applyLikeFilter($query, [$this->trimExpr('m.acDoc2'), $this->trimExpr('move_wo.acLnkKey'), $this->formatLongPantheonExpr('move_wo.acLnkKey'), $this->trimExpr('wo.acKeyView')], (string) ($filters['predracun'] ?? ''));
        $this->applyLikeFilter($query, [$this->trimExpr('m.acDoc1'), $this->trimExpr('wo.acLnkKey'), $this->formatLongPantheonExpr('wo.acLnkKey')], (string) ($filters['narudzba'] ?? ''));
        $this->applyLikeFilter($query, [$this->trimExpr('mi.acIdent')], (string) ($filters['sifra'] ?? ''));
        $this->applyLikeFilter($query, [$this->trimExpr('mi.acName')], (string) ($filters['naziv'] ?? ''));
        $this->applyLikeFilter($query, [$displayNoteExpr], (string) ($filters['napomena'] ?? ''));

        $dateFrom = $this->normalizeDate((string) ($filters['datum_od'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereRaw($this->releasedMaterialDocumentDateExpr() . ' >= ?', [$dateFrom]);
        }

        $dateTo = $this->normalizeDate((string) ($filters['datum_do'] ?? ''));
        if ($dateTo !== '') {
            $query->whereRaw($this->releasedMaterialDocumentDateExpr() . ' <= ?', [$dateTo]);
        }
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sortMap = [
            0 => 'm.acKey',
            1 => $this->releasedMaterialDocumentDateTimeExpr(),
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
        if ($columnIndex === 1) {
            $query->orderByRaw($sortColumn . ' ' . $direction);
        } else {
            $query->orderBy($sortColumn, $direction);
        }

        $query->orderBy('mi.anNo');
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
        $note = $this->resolveReleasedMaterialNote($row, $workOrderNumber);

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
            'napomena' => $note,
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

    private function releasedMaterialDisplayNoteExpr(): string
    {
        $rawNoteExpr = "COALESCE(NULLIF({$this->trimExpr('mi.acNote')}, ''), NULLIF({$this->trimExpr('m.acNote')}, ''), '')";
        $workOrderNumberExpr = $this->releasedMaterialWorkOrderNumberExpr();
        $materialCodeExpr = $this->trimExpr('mi.acIdent');
        $isEnalogCreatedExpr = $this->releasedMaterialBarcodeCreatedExpr();

        return "CASE WHEN {$isEnalogCreatedExpr} = 1 THEN CONCAT('Kreirano iz eNalog.app, RN ', {$workOrderNumberExpr}, ', za artikal ', {$materialCodeExpr}) ELSE {$rawNoteExpr} END";
    }

    private function releasedMaterialWorkOrderNumberExpr(): string
    {
        $candidates = [
            $this->formatLongPantheonExpr('move_wo.acLnkKey'),
            $this->formatLongPantheonExpr('wo.acKeyView'),
            $this->formatLongPantheonExpr('m.acDoc2'),
        ];

        return 'COALESCE(' . implode(', ', array_map(
            static fn (string $expression): string => "NULLIF({$expression}, '')",
            $candidates
        )) . ", '')";
    }

    private function releasedMaterialBarcodeCreatedExpr(): string
    {
        $insertedFromExpr = $this->trimExpr('m.acInsertedFrom');

        return "CASE WHEN EXISTS (
            SELECT 1
            FROM {$this->qualifiedReleasedMaterialMoveItemWorkOrderItemLinkTableName()} AS move_item_link
            WHERE move_item_link.anMoveItemQId = mi.anQId
        ) AND {$insertedFromExpr} = 'D' THEN 1 ELSE 0 END";
    }

    private function resolveReleasedMaterialNote(object $row, string $workOrderNumber = ''): string
    {
        if ($this->isEnalogReleasedMaterialDocument($row)) {
            return $this->buildEnalogReleasedMaterialNote(
                $workOrderNumber,
                trim((string) ($row->material_code ?? ''))
            );
        }

        return trim((string) ($row->raw_note ?? $row->note ?? ''));
    }

    private function isEnalogReleasedMaterialDocument(object $row): bool
    {
        return (int) ($row->is_enalog_created ?? 0) === 1;
    }

    private function buildEnalogReleasedMaterialNote(string $workOrderNumber, string $materialCode): string
    {
        return 'Kreirano preko eNalog.app/ RN ' . trim($workOrderNumber) . '/ šifra ' . trim($materialCode);
    }

    private function trimExpr(string $column): string
    {
        return "LTRIM(RTRIM(ISNULL(CONVERT(nvarchar(4000), {$column}), '')))";
    }

    private function qualifiedReleasedMaterialMoveItemWorkOrderItemLinkTableName(): string
    {
        return $this->tableSchema() . '.tHF_LinkMoveItemWOExItem';
    }

    private function qualifiedReleasedMaterialMoveTableName(): string
    {
        return $this->tableSchema() . '.tHE_Move';
    }

    private function qualifiedReleasedMaterialMoveItemTableName(): string
    {
        return $this->tableSchema() . '.tHE_MoveItem';
    }

    private function qualifiedReleasedMaterialMoveFxRateTableName(): string
    {
        return $this->tableSchema() . '.tHE_MoveFXRate';
    }

    private function qualifiedReleasedMaterialMoveWorkOrderLinkTableName(): string
    {
        return $this->tableSchema() . '.tHF_LinkMoveWOEx';
    }

    private function tableSchema(): string
    {
        return (string) config('workorders.schema', 'dbo');
    }

    private function releasedMaterialDocumentDateTimeExpr(): string
    {
        return 'CASE WHEN m.adTimeIns IS NOT NULL THEN m.adTimeIns ELSE CAST(m.adDate AS datetime) END';
    }

    private function releasedMaterialDocumentDateExpr(): string
    {
        return 'CAST(' . $this->releasedMaterialDocumentDateTimeExpr() . ' AS date)';
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

    private function canDeleteDocuments(mixed $user = null): bool
    {
        return $this->canAccessDocuments($user);
    }
}
