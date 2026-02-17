<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkOrderController extends Controller
{
    public function invoiceList()
    {
        $pageConfigs = ['pageHeader' => false];

        try {
            $orders = $this->fetchWorkOrders();

            return view('/content/apps/invoice/app-invoice-list', [
                'pageConfigs' => $pageConfigs,
                'radniNalozi' => $orders,
                'statusStats' => $this->calculateStatusStats($orders),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order list query failed.', [
                'connection' => $this->connectionName(),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return view('/content/apps/invoice/app-invoice-list', [
                'pageConfigs' => $pageConfigs,
                'radniNalozi' => [],
                'statusStats' => $this->emptyStatusStats(),
                'error' => 'Greska pri ucitavanju radnih naloga iz baze.',
            ]);
        }
    }

    public function invoicePreview(Request $request, ?string $id = null)
    {
        $pageConfigs = ['pageHeader' => false];
        $workOrderId = $id ?? $request->query('id');

        if (!$workOrderId) {
            return redirect()->route('app-invoice-list');
        }

        try {
            $workOrder = $this->findMappedWorkOrder((string) $workOrderId, true);

            if (!$workOrder) {
                return redirect()->route('app-invoice-list')
                    ->with('error', 'Radni nalog nije pronadjen.');
            }

            $raw = $workOrder['raw'] ?? [];
            unset($workOrder['raw']);

            $sender = [
                'name' => (string) $this->value($raw, ['acConsignee', 'acReceiver', 'acPartner'], $workOrder['klijent'] ?? ''),
                'address' => (string) $this->value($raw, ['acAddress', 'acConsigneeAddress', 'acAddress1'], ''),
                'phone' => (string) $this->value($raw, ['acPhone', 'acConsigneePhone', 'acPhone1'], ''),
                'email' => (string) $this->value($raw, ['acEmail', 'acConsigneeEmail'], ''),
            ];

            $recipient = [
                'name' => (string) $this->value($raw, ['acReceiver', 'acConsignee', 'acPartner'], ''),
                'address' => (string) $this->value($raw, ['acReceiverAddress', 'acAddress2', 'acAddress'], ''),
                'phone' => (string) $this->value($raw, ['acReceiverPhone', 'acPhone2', 'acPhone'], ''),
                'email' => (string) $this->value($raw, ['acReceiverEmail', 'acEmail'], ''),
            ];

            return view('/content/apps/invoice/app-invoice-preview', [
                'pageConfigs' => $pageConfigs,
                'workOrder' => $workOrder,
                'sender' => $sender,
                'recipient' => $recipient,
                'invoiceNumber' => (string) ($workOrder['broj_naloga'] ?? ''),
                'issueDate' => $this->displayDate($workOrder['datum_kreiranja'] ?? null),
                'dueDate' => $this->displayDate($workOrder['datum_zavrsetka'] ?? null),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order preview query failed.', [
                'id' => $workOrderId,
                'connection' => $this->connectionName(),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('app-invoice-list')
                ->with('error', 'Greska pri ucitavanju detalja radnog naloga.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $requestedLimit = $request->integer('limit', $this->resolveLimit());
            $orders = $this->fetchWorkOrders($requestedLimit);

            return response()->json([
                'data' => $orders,
                'statusStats' => $this->calculateStatusStats($orders),
                'meta' => [
                    'count' => count($orders),
                    'limit' => $this->resolveLimit($requestedLimit),
                    'connection' => $this->connectionName(),
                    'table' => $this->qualifiedTableName(),
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order API list query failed.', [
                'connection' => $this->connectionName(),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to fetch work orders from database.',
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $workOrder = $this->findMappedWorkOrder($id);

            if (!$workOrder) {
                return response()->json([
                    'message' => 'Work order not found.',
                ], 404);
            }

            return response()->json([
                'data' => $workOrder,
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order API show query failed.', [
                'id' => $id,
                'connection' => $this->connectionName(),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to fetch work order from database.',
            ], 500);
        }
    }

    private function fetchWorkOrders(?int $limit = null): array
    {
        $columns = $this->tableColumns();
        $query = $this->newTableQuery();
        $this->applyDefaultOrdering($query, $columns);

        return $query
            ->limit($this->resolveLimit($limit))
            ->get()
            ->map(function ($row) {
                return $this->mapRow((array) $row);
            })
            ->values()
            ->all();
    }

    private function findMappedWorkOrder(string $id, bool $includeRaw = false): ?array
    {
        $row = $this->findWorkOrderRow($id);

        if (!$row) {
            return null;
        }

        return $this->mapRow($row, $includeRaw);
    }

    private function findWorkOrderRow(string $id): ?array
    {
        $columns = $this->tableColumns();
        $query = $this->newTableQuery();
        $hasCondition = false;

        if (in_array('acRefNo1', $columns, true)) {
            $query->where('acRefNo1', $id);
            $hasCondition = true;
        }

        if (in_array('anNo', $columns, true) && is_numeric($id)) {
            if ($hasCondition) {
                $query->orWhere('anNo', (int) $id);
            } else {
                $query->where('anNo', (int) $id);
                $hasCondition = true;
            }
        }

        if (in_array('acKey', $columns, true)) {
            if ($hasCondition) {
                $query->orWhere('acKey', $id);
            } else {
                $query->where('acKey', $id);
                $hasCondition = true;
            }
        }

        if (in_array('id', $columns, true)) {
            if ($hasCondition) {
                $query->orWhere('id', $id);
            } else {
                $query->where('id', $id);
                $hasCondition = true;
            }
        }

        if (!$hasCondition) {
            return null;
        }

        $row = $query->first();

        return $row ? (array) $row : null;
    }

    private function mapRow(array $row, bool $includeRaw = false): array
    {
        $brojNaloga = (string) $this->value($row, ['acRefNo1', 'acKey', 'anNo', 'id'], 'N/A');
        $id = $this->value($row, ['acRefNo1', 'acKey'], null);

        if ($id === null || $id === '' || ((is_int($id) || is_float($id) || is_numeric((string) $id)) && (float) $id === 0.0)) {
            $id = $this->value($row, ['anNo', 'id'], $brojNaloga);
        }

        $status = $this->mapStatus($this->value($row, ['acStatus', 'status'], 'N/A'));
        $priority = $this->mapPriority($this->value($row, ['acPriority', 'acWayOfSale', 'priority'], 'Srednji'));
        $createdDate = $this->normalizeDate($this->value($row, ['adDate', 'adDateIns', 'created_at']));
        $endDate = $this->normalizeDate($this->value($row, ['adDeliveryDeadline', 'adDateOut', 'actual_end']));

        $mapped = [
            'responsive_id' => '',
            'id' => $id,
            'broj_naloga' => $brojNaloga,
            'naziv' => (string) $this->value($row, ['acDocType', 'acName', 'title'], 'Radni nalog'),
            'opis' => (string) $this->value($row, ['acNote', 'acStatement', 'acDescr', 'description'], ''),
            'status' => $status,
            'prioritet' => $priority,
            'datum_kreiranja' => $createdDate,
            'datum_zavrsetka' => $endDate,
            'dodeljen_korisnik' => (string) $this->value($row, ['anClerk', 'created_by', 'acUser'], ''),
            'klijent' => (string) $this->value($row, ['acConsignee', 'acReceiver', 'client_name', 'acPartner'], 'N/A'),
            'vrednost' => $this->normalizeNumber($this->value($row, ['anValue', 'anDocValue', 'total'], 0)),
            'valuta' => (string) $this->value($row, ['acCurrency', 'currency'], 'BAM'),
            'magacin' => (string) $this->value($row, ['acWarehouse', 'linked_document', 'acWarehouseFrom'], ''),
        ];

        if ($includeRaw) {
            $mapped['raw'] = $row;
        }

        return $mapped;
    }

    private function calculateStatusStats(array $workOrders): array
    {
        $stats = $this->emptyStatusStats();
        $stats['svi'] = count($workOrders);

        foreach ($workOrders as $workOrder) {
            $status = strtolower((string) ($workOrder['status'] ?? ''));

            if (str_contains($status, 'planiran') || str_contains($status, 'novo')) {
                $stats['planiran']++;
                continue;
            }

            if (str_contains($status, 'otvoren')) {
                $stats['otvoren']++;
                continue;
            }

            if (str_contains($status, 'rezerviran')) {
                $stats['rezerviran']++;
                continue;
            }

            if (str_contains($status, 'raspisan')) {
                $stats['raspisan']++;
                continue;
            }

            if (str_contains($status, 'u toku') || str_contains($status, 'u radu')) {
                $stats['u_radu']++;
                continue;
            }

            if (str_contains($status, 'djelimicno') || str_contains($status, 'djelomicno')) {
                $stats['djelimicno_zakljucen']++;
                continue;
            }

            if (str_contains($status, 'zavrseno') || str_contains($status, 'zakljucen')) {
                $stats['zakljucen']++;
            }
        }

        return $stats;
    }

    private function emptyStatusStats(): array
    {
        return [
            'svi' => 0,
            'planiran' => 0,
            'otvoren' => 0,
            'rezerviran' => 0,
            'raspisan' => 0,
            'u_radu' => 0,
            'djelimicno_zakljucen' => 0,
            'zakljucen' => 0,
        ];
    }

    private function mapStatus(mixed $status): string
    {
        if ($status === null || $status === '') {
            return 'N/A';
        }

        $statusString = trim((string) $status);
        $normalizedStatus = strtoupper($statusString);

        $statusMap = [
            'F' => 'Zavrseno',
            'P' => 'U toku',
            'I' => 'U toku',
            'N' => 'Novo',
            'C' => 'Otkazano',
            'D' => 'Nacrt',
            'O' => 'Otvoren',
            'PLANIRAN' => 'Planiran',
            'OTVOREN' => 'Otvoren',
            'REZERVIRAN' => 'Rezerviran',
            'RASPISAN' => 'Raspisan',
            'U TOKU' => 'U toku',
            'U RADU' => 'U toku',
            'ZAVRSENO' => 'Zavrseno',
            'ZAKLJUCEN' => 'Zavrseno',
        ];

        return $statusMap[$normalizedStatus] ?? $statusString;
    }

    private function mapPriority(mixed $priority): string
    {
        if ($priority === null || $priority === '') {
            return 'Srednji';
        }

        $priorityString = trim((string) $priority);
        $normalizedPriority = strtoupper($priorityString);

        $priorityMap = [
            'V' => 'Visok',
            'Z' => 'Visok',
            'VISOK' => 'Visok',
            'HIGH' => 'Visok',
            'S' => 'Srednji',
            'M' => 'Srednji',
            'SREDNJI' => 'Srednji',
            'MEDIUM' => 'Srednji',
            'D' => 'Nizak',
            'N' => 'Nizak',
            'NIZAK' => 'Nizak',
            'LOW' => 'Nizak',
        ];

        return $priorityMap[$normalizedPriority] ?? $priorityString;
    }

    private function applyDefaultOrdering(Builder $query, array $columns): void
    {
        foreach (['adTimeIns', 'adDate', 'anNo'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderByDesc($column);
            }
        }
    }

    private function tableColumns(): array
    {
        return DB::connection($this->connectionName())
            ->table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->tableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();
    }

    private function value(array $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if ($value === null || $value === '') {
                continue;
            }

            return $value;
        }

        return $default;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->format('Y-m-d');
            }

            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable $exception) {
            $stringValue = substr((string) $value, 0, 10);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stringValue) === 1) {
                return $stringValue;
            }

            return null;
        }
    }

    private function displayDate(?string $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d.m.Y');
        } catch (Throwable $exception) {
            return '';
        }
    }

    private function normalizeNumber(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        return $value ?? 0;
    }

    private function newTableQuery(): Builder
    {
        return DB::connection($this->connectionName())->table($this->qualifiedTableName());
    }

    private function resolveLimit(?int $requestedLimit = null): int
    {
        $maxLimit = max(1, (int) config('workorders.max_limit', 500));
        $defaultLimit = max(1, (int) config('workorders.default_limit', 100));
        $limit = $requestedLimit ?? $defaultLimit;

        if ($limit < 1) {
            return $defaultLimit;
        }

        return min($limit, $maxLimit);
    }

    private function connectionName(): string
    {
        return (string) config('workorders.connection', 'workorders_sqlsrv');
    }

    private function tableSchema(): string
    {
        return (string) config('workorders.schema', 'dbo');
    }

    private function tableName(): string
    {
        return (string) config('workorders.table', 'tHF_WOEx');
    }

    private function qualifiedTableName(): string
    {
        return $this->tableSchema() . '.' . $this->tableName();
    }
}
