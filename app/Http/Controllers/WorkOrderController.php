<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class WorkOrderController extends Controller
{
    private ?array $deliveryPriorityMap = null;
    private ?array $orderTableColumnsCache = null;
    private array $linkedOrderCache = [];

    public function invoiceList()
    {
        $pageConfigs = ['pageHeader' => false];

        try {
            return view('/content/apps/invoice/app-invoice-list', [
                'pageConfigs' => $pageConfigs,
                'radniNalozi' => [],
                'statusStats' => $this->fetchStatusStats(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order list query failed.', [
                'connection' => config('database.default'),
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
        $routeWorkOrderId = trim((string) ($id ?? ''));
        $queryWorkOrderId = trim((string) $request->query('id', ''));
        $scanQueryFlag = trim((string) $request->query('scan', ''));
        $sourceQueryFlag = trim((string) $request->query('source', ''));
        $isScanLookup = in_array(strtolower($scanQueryFlag), ['1', 'true', 'yes'], true);
        $normalizedSource = strtolower($sourceQueryFlag);

        if ($routeWorkOrderId === '' && $queryWorkOrderId !== '') {
            $redirectParams = ['id' => $queryWorkOrderId];

            if ($scanQueryFlag !== '') {
                $redirectParams['scan'] = $scanQueryFlag;
            }

            if ($sourceQueryFlag !== '') {
                $redirectParams['source'] = $sourceQueryFlag;
            }

            return redirect()->route('app-invoice-preview', $redirectParams);
        }

        $workOrderId = $routeWorkOrderId !== '' ? $routeWorkOrderId : $queryWorkOrderId;

        if (!$workOrderId) {
            return $this->emptyInvoicePreviewResponse($pageConfigs);
        }

        try {
            $workOrder = $this->findMappedWorkOrder((string) $workOrderId, true);

            if (!$workOrder) {
                return redirect()
                    ->route('app-invoice-preview')
                    ->with('scan_lookup_notice', [
                        'icon' => 'error',
                        'title' => 'Nalog nije pronađen',
                        'text' => 'Ne postoji nalog za odabrane parametre. Probaj sa drugim QR kodom.',
                    ]);
            }

            $raw = $workOrder['raw'] ?? [];
            $workOrderItems = $this->fetchMappedWorkOrderItems($raw);
            $workOrderItemResources = $this->fetchMappedWorkOrderItemResources($raw);
            $workOrderRegOperations = $this->fetchMappedWorkOrderRegOperations($raw);
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

            $workOrderMeta = $this->buildWorkOrderMetadata(
                $raw,
                $workOrder,
                $workOrderItems,
                $workOrderItemResources,
                $workOrderRegOperations
            );

            $successNotice = null;

            if ($isScanLookup) {
                $successNotice = [
                    'icon' => 'success',
                    'title' => 'Nalog učitan',
                    'text' => 'Radni nalog je uspješno otvoren iz QR koda',
                ];
            } elseif (in_array($normalizedSource, ['invoice_list', 'upravljanje_nalozima', 'lista_naloga'], true)) {
                $successNotice = [
                    'icon' => 'success',
                    'title' => 'Nalog učitan',
                    'text' => 'Radni nalog je otvoren kroz administraciju',
                ];
            } elseif (in_array($normalizedSource, ['dashboard_home', 'home_table', 'kontrolna_ploca'], true)) {
                $successNotice = [
                    'icon' => 'success',
                    'title' => 'Nalog učitan',
                    'text' => 'Radni nalog je otvoren kroz administraciju',
                ];
            }

            return view('/content/apps/invoice/app-invoice-preview', [
                'pageConfigs' => $pageConfigs,
                'workOrder' => $workOrder,
                'workOrderItems' => $workOrderItems,
                'workOrderItemResources' => $workOrderItemResources,
                'workOrderRegOperations' => $workOrderRegOperations,
                'workOrderMeta' => $workOrderMeta,
                'sender' => $sender,
                'recipient' => $recipient,
                'invoiceNumber' => (string) ($workOrder['broj_naloga'] ?? ''),
                'issueDate' => $this->displayDate($workOrder['datum_kreiranja'] ?? null),
                'plannedStartDate' => $this->formatMetaDateTime($this->value($raw, ['adSchedStartTime'], null)),
                'dueDate' => $this->displayDate($workOrder['datum_zavrsetka'] ?? null),
                'scanLookupNotice' => $successNotice,
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order preview query failed.', [
                'id' => $workOrderId,
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'items_table' => $this->qualifiedItemTableName(),
                'item_resources_table' => $this->qualifiedItemResourcesTableName(),
                'reg_operations_table' => $this->qualifiedRegOperationsTableName(),
                'message' => $exception->getMessage(),
            ]);

            return $this->emptyInvoicePreviewResponse($pageConfigs, (string) $workOrderId);
        }
    }

    public function invoicePrint(Request $request, ?string $id = null)
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
            $workOrderItems = $this->fetchMappedWorkOrderItems($raw);
            $workOrderItemResources = $this->fetchMappedWorkOrderItemResources($raw);
            $workOrderRegOperations = $this->fetchMappedWorkOrderRegOperations($raw);
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

            $workOrderMeta = $this->buildWorkOrderMetadata(
                $raw,
                $workOrder,
                $workOrderItems,
                $workOrderItemResources,
                $workOrderRegOperations
            );

            return view('/content/apps/invoice/app-invoice-print', [
                'pageConfigs' => $pageConfigs,
                'workOrder' => $workOrder,
                'workOrderItems' => $workOrderItems,
                'workOrderItemResources' => $workOrderItemResources,
                'workOrderRegOperations' => $workOrderRegOperations,
                'workOrderMeta' => $workOrderMeta,
                'sender' => $sender,
                'recipient' => $recipient,
                'invoiceNumber' => $this->formatWorkOrderNumberForCalendar((string) ($workOrder['broj_naloga'] ?? '')),
                'issueDate' => $this->displayDate($workOrder['datum_kreiranja'] ?? null),
                'plannedStartDate' => $this->formatMetaDateTime($this->value($raw, ['adSchedStartTime'], null)),
                'dueDate' => $this->displayDate($workOrder['datum_zavrsetka'] ?? null),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order print query failed.', [
                'id' => $workOrderId,
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'items_table' => $this->qualifiedItemTableName(),
                'item_resources_table' => $this->qualifiedItemResourcesTableName(),
                'reg_operations_table' => $this->qualifiedRegOperationsTableName(),
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('app-invoice-list')
                ->with('error', 'Greska pri ucitavanju print prikaza radnog naloga.');
        }
    }

    public function updateInvoiceStatus(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'max:80'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravan status.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $selectedStatus = $this->normalizeStatusSelection((string) $validator->validated()['status']);

        if ($selectedStatus === null) {
            return response()->json([
                'message' => 'Odabrani status nije podržan.',
            ], 422);
        }

        try {
            $row = $this->findWorkOrderRow($id);

            if ($row === null) {
                return response()->json([
                    'message' => 'Radni nalog nije pronađen.',
                ], 404);
            }

            $columns = $this->tableColumns();
            $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF', 'acStatus', 'status']);

            if ($statusColumn === null) {
                return response()->json([
                    'message' => 'Kolona za status nije pronađena.',
                ], 500);
            }

            $resolvedStatusValue = $this->resolveStatusStorageValue($selectedStatus, $row, $statusColumn);
            $updates = [$statusColumn => $resolvedStatusValue];

            if ($this->rowAlreadyHasUpdates($row, $updates)) {
                return response()->json([
                    'message' => 'Status je već postavljen na odabranu vrijednost.',
                    'data' => [
                        'id' => $id,
                        'status' => $selectedStatus,
                        'changed' => false,
                    ],
                ]);
            }

            if (!$this->updateWorkOrderRow($row, $updates)) {
                return response()->json([
                    'message' => 'Status nije ažuriran.',
                ], 500);
            }

            return response()->json([
                'message' => 'Status je uspješno ažuriran.',
                'data' => [
                    'id' => $id,
                    'status' => $selectedStatus,
                    'changed' => true,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order status update failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greška pri ažuriranju statusa.',
            ], 500);
        }
    }

    public function updateInvoicePriority(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'priority' => ['required', 'string', 'max:120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravan prioritet.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $priorityCode = $this->resolvePriorityCode((string) $validator->validated()['priority']);

        if ($priorityCode === null) {
            return response()->json([
                'message' => 'Odabrani prioritet nije podržan.',
            ], 422);
        }

        $priorityLabel = (string) ($this->deliveryPriorityMap()[$priorityCode] ?? '');

        if ($priorityLabel === '') {
            return response()->json([
                'message' => 'Odabrani prioritet nije podržan.',
            ], 422);
        }

        try {
            $row = $this->findWorkOrderRow($id);

            if ($row === null) {
                return response()->json([
                    'message' => 'Radni nalog nije pronađen.',
                ], 404);
            }

            $columns = $this->tableColumns();
            $priorityCodeColumn = $this->firstExistingColumn($columns, ['anPriority']);
            $priorityTextColumn = $this->firstExistingColumn($columns, ['acPriority', 'priority']);
            $updates = [];

            if ($priorityCodeColumn !== null) {
                $updates[$priorityCodeColumn] = $priorityCode;
            } elseif ($priorityTextColumn !== null) {
                $updates[$priorityTextColumn] = $priorityLabel;
            }

            if (empty($updates)) {
                return response()->json([
                    'message' => 'Kolona za prioritet nije pronađena.',
                ], 500);
            }

            if ($this->rowAlreadyHasUpdates($row, $updates)) {
                return response()->json([
                    'message' => 'Prioritet je već postavljen na odabranu vrijednost.',
                    'data' => [
                        'id' => $id,
                        'priority' => $priorityLabel,
                        'priority_code' => $priorityCode,
                        'changed' => false,
                    ],
                ]);
            }

            if (!$this->updateWorkOrderRow($row, $updates)) {
                return response()->json([
                    'message' => 'Prioritet nije ažuriran.',
                ], 500);
            }

            return response()->json([
                'message' => 'Prioritet je uspješno ažuriran.',
                'data' => [
                    'id' => $id,
                    'priority' => $priorityLabel,
                    'priority_code' => $priorityCode,
                    'changed' => true,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order priority update failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greška pri ažuriranju prioriteta.',
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $requestedLimit = $request->filled('limit')
                ? (int) $request->input('limit')
                : ($request->filled('length') ? (int) $request->input('length') : null);

            $limit = $this->resolveLimit($requestedLimit);
            $page = $request->integer('page', 0);

            if ($page < 1 && $request->filled('start')) {
                $start = max(0, (int) $request->input('start'));
                $page = (int) floor($start / $limit) + 1;
            }

            if ($page < 1) {
                $page = 1;
            }

            $filters = $this->extractFilters($request);
            $sort = $this->extractSort($request);
            $result = $this->fetchWorkOrders($limit, $page, $filters, $sort);
            $statusStats = $this->fetchStatusStats($filters);

            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'data' => $result['data'],
                'statusStats' => $statusStats,
                'meta' => array_merge($result['meta'], [
                    'connection' => config('database.default'),
                    'table' => $this->qualifiedTableName(),
                ]),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order API list query failed.', [
                'connection' => config('database.default'),
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
            $workOrder = $this->findMappedWorkOrder($id, true);

            if (!$workOrder) {
                return response()->json([
                    'message' => 'Work order not found.',
                ], 404);
            }

            $raw = $workOrder['raw'] ?? [];
            $workOrder['items'] = $this->fetchMappedWorkOrderItems($raw);
            $workOrder['item_resources'] = $this->fetchMappedWorkOrderItemResources($raw);
            $workOrder['reg_operations'] = $this->fetchMappedWorkOrderRegOperations($raw);
            unset($workOrder['raw']);

            return response()->json([
                'data' => $workOrder,
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order API show query failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'items_table' => $this->qualifiedItemTableName(),
                'item_resources_table' => $this->qualifiedItemResourcesTableName(),
                'reg_operations_table' => $this->qualifiedRegOperationsTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to fetch work order from database.',
            ], 500);
        }
    }

    public function calendar(Request $request): JsonResponse
    {
        try {
            $startDate = $this->normalizeDateInput((string) $request->input('start', ''));
            $endDate = $this->normalizeDateInput((string) $request->input('end', ''));
            $statusBuckets = $this->normalizeCalendarStatuses($request->input('statuses', []));
            $priorityCodes = $this->normalizeCalendarPriorities($request->input('priorities', []));

            return response()->json([
                'data' => $this->fetchCalendarEvents($startDate, $endDate, $statusBuckets, $priorityCodes),
                'statusStats' => $this->fetchCalendarStatusStats($startDate, $endDate, $priorityCodes),
                'priorityStats' => $this->fetchCalendarPriorityStats($startDate, $endDate, $statusBuckets),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order API calendar query failed.', [
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to fetch calendar work orders from database.',
            ], 500);
        }
    }

    public function yearlySummary(Request $request): JsonResponse
    {
        try {
            $currentYear = max(2022, (int) $request->integer('current_year', (int) now()->year));
            $compareYear = (int) $request->integer('compare_year', $currentYear - 1);

            if ($compareYear >= $currentYear) {
                $compareYear = $currentYear - 1;
            }

            if ($compareYear < 2022) {
                $compareYear = 2022;
            }

            return response()->json([
                'data' => $this->fetchYearlySummary($currentYear, $compareYear),
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order API yearly summary query failed.', [
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to fetch yearly work order summary from database.',
            ], 500);
        }
    }

    public function workOrderProducts(Request $request, string $id): JsonResponse
    {
        try {
            $workOrderRow = $this->findWorkOrderRow($id);

            if ($workOrderRow === null) {
                return response()->json([
                    'message' => 'Radni nalog nije pronadjen.',
                ], 404);
            }

            $search = trim((string) $request->query('q', ''));
            $products = $this->resolveWorkOrderProducts($workOrderRow, $search);

            return response()->json([
                'data' => $products,
                'meta' => [
                    'count' => count($products),
                    'limit' => $this->bomLimit(),
                    'search' => $search,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order products query failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'work_orders_table' => $this->qualifiedTableName(),
                'items_table' => $this->qualifiedItemTableName(),
                'product_structure_table' => $this->qualifiedProductStructureTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greska pri ucitavanju proizvoda.',
            ], 500);
        }
    }

    public function workOrderBom(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravan proizvod.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $productId = (string) $validator->validated()['product_id'];

        if (trim($productId) === '') {
            return response()->json([
                'message' => 'Product ID (acIdent) je obavezan.',
            ], 422);
        }

        try {
            $workOrderRow = $this->findWorkOrderRow($id);

            if ($workOrderRow === null) {
                return response()->json([
                    'message' => 'Radni nalog nije pronadjen.',
                ], 404);
            }

            $components = $this->fetchBomComponentsByProduct($productId, $this->bomLimit());

            return response()->json([
                'data' => $components,
                'meta' => [
                    'count' => count($components),
                    'product_id' => $productId,
                    'limit' => $this->bomLimit(),
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order BOM query failed.', [
                'id' => $id,
                'product_id' => $productId,
                'connection' => config('database.default'),
                'product_structure_table' => $this->qualifiedProductStructureTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greska pri ucitavanju BOM strukture.',
            ], 500);
        }
    }

    public function storePlannedConsumption(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'quantity_unit' => ['nullable', 'string', 'in:AUTO,KG,RDS'],
            'description' => ['nullable', 'string', 'max:500'],
            'components' => ['required', 'array', 'min:1', 'max:100'],
            'components.*.acIdentChild' => ['required', 'string', 'max:64'],
            'components.*.anNo' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravni podaci za planiranu potrosnju.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $workOrderRow = $this->findWorkOrderRow($id);

            if ($workOrderRow === null) {
                return response()->json([
                    'message' => 'Radni nalog nije pronadjen.',
                ], 404);
            }

            $validated = $validator->validated();
            $productId = (string) ($validated['product_id'] ?? '');
            $quantityFactor = (float) ($validated['quantity'] ?? 0);
            $quantityUnit = strtoupper(trim((string) ($validated['quantity_unit'] ?? 'AUTO')));
            $userDescription = trim((string) ($validated['description'] ?? ''));

            if (trim($productId) === '') {
                return response()->json([
                    'message' => 'Product ID (acIdent) je obavezan.',
                ], 422);
            }

            if ($quantityFactor <= 0) {
                return response()->json([
                    'message' => 'Kolicina mora biti veca od 0.',
                ], 422);
            }

            $workOrderKey = trim((string) $this->valueTrimmed($workOrderRow, ['acKey'], ''));

            if ($workOrderKey === '') {
                return response()->json([
                    'message' => 'RN kljuc nije pronadjen.',
                ], 422);
            }

            $bomRows = $this->fetchBomComponentsByProduct($productId, $this->bomLimit());

            if (empty($bomRows)) {
                return response()->json([
                    'message' => 'Za odabrani proizvod nema BOM stavki.',
                ], 422);
            }

            $selectedKeys = [];

            foreach ((array) ($validated['components'] ?? []) as $component) {
                $componentId = trim((string) ($component['acIdentChild'] ?? ''));
                $lineNo = (int) ($this->toFloatOrNull($component['anNo'] ?? null) ?? 0);

                if ($componentId === '') {
                    continue;
                }

                $selectedKeys[$this->bomSelectionKey($lineNo, $componentId)] = true;
            }

            if (empty($selectedKeys)) {
                return response()->json([
                    'message' => 'Nijedna komponenta nije odabrana.',
                ], 422);
            }

            $selectedRows = array_values(array_filter($bomRows, function (array $row) use ($selectedKeys) {
                $lineNo = (int) ($this->toFloatOrNull($row['anNo'] ?? null) ?? 0);
                $componentId = trim((string) ($row['acIdentChild'] ?? ''));

                if ($componentId === '') {
                    return false;
                }

                return array_key_exists($this->bomSelectionKey($lineNo, $componentId), $selectedKeys);
            }));

            if (empty($selectedRows)) {
                return response()->json([
                    'message' => 'Odabrane komponente nisu pronadjene u BOM strukturi.',
                ], 422);
            }

            $now = now();
            $userId = (int) (auth()->id() ?? 0);
            $variant = (int) ($this->toFloatOrNull($this->valueTrimmed($workOrderRow, ['anVariant'], 0)) ?? 0);
            $itemColumns = $this->itemTableColumns();
            $identityColumns = $this->itemTableIdentityColumns();
            $manualNoInsert = in_array('anNo', $itemColumns, true) && !in_array('anNo', $identityColumns, true);
            $manualQIdInsert = in_array('anQId', $itemColumns, true) && !in_array('anQId', $identityColumns, true);
            $hasNoteColumn = in_array('acNote', $itemColumns, true);
            $hasStatementColumn = in_array('acStatement', $itemColumns, true);

            Log::info('Planned consumption save started.', [
                'id' => $id,
                'work_order_key' => $workOrderKey,
                'product_id' => $productId,
                'quantity_factor' => $quantityFactor,
                'quantity_unit' => $quantityUnit,
                'description_present' => $userDescription !== '',
                'description_length' => strlen($userDescription),
                'requested_components_count' => count((array) ($validated['components'] ?? [])),
                'selected_components_count' => count($selectedRows),
                'variant' => $variant,
                'user_id' => $userId,
                'items_table' => $this->qualifiedItemTableName(),
                'identity_columns' => $identityColumns,
                'manual_anNo_insert' => $manualNoInsert,
                'manual_anQId_insert' => $manualQIdInsert,
                'has_note_column' => $hasNoteColumn,
                'has_statement_column' => $hasStatementColumn,
            ]);

            $insertedRows = DB::transaction(function () use (
                $selectedRows,
                $quantityFactor,
                $productId,
                $workOrderKey,
                $variant,
                $userId,
                $now,
                $quantityUnit,
                $userDescription,
                $hasNoteColumn,
                $hasStatementColumn,
                $manualNoInsert,
                $manualQIdInsert
            ) {
                $nextNo = null;
                $nextQId = null;

                if ($manualNoInsert) {
                    $nextNo = ((int) ($this->newItemTableQuery()->where('acKey', $workOrderKey)->max('anNo') ?? 0)) + 1;
                }

                if ($manualQIdInsert) {
                    $nextQId = ((int) ($this->newItemTableQuery()->where('acKey', $workOrderKey)->max('anQId') ?? 0)) + 1;
                }

                $saved = [];

                foreach ($selectedRows as $row) {
                    $componentId = trim((string) ($row['acIdentChild'] ?? ''));
                    $description = trim((string) ($row['acDescr'] ?? ''));
                    $operationType = substr(trim((string) ($row['acOperationType'] ?? '')), 0, 1);
                    $baseQty = $this->toFloatOrNull($row['anGrossQty'] ?? null) ?? 0.0;
                    $sourceUnit = strtoupper(substr(trim((string) ($row['acUM'] ?? '')), 0, 3));
                    $resolvedUnit = $quantityUnit === 'AUTO' ? $sourceUnit : $quantityUnit;
                    // Fallback to entered quantity when BOM gross qty is 0 to avoid saving 0 by default.
                    $plannedQty = abs($baseQty) > 0.000001 ? ($baseQty * $quantityFactor) : $quantityFactor;

                    if ($componentId === '') {
                        continue;
                    }

                    $insertPayload = [
                        'acKey' => $workOrderKey,
                        'anVariant' => $variant,
                        'acIdent' => substr($componentId, 0, 16),
                        'acDescr' => $description === '' ? null : substr($description, 0, 80),
                        'acOperationType' => $operationType === '' ? null : $operationType,
                        'anPlanQty' => $plannedQty,
                        'anQty' => $plannedQty,
                        'anQty1' => $plannedQty,
                        'anQtyBase' => 0,
                        'adTimeIns' => $now,
                        'adTimeChg' => $now,
                        'anUserIns' => $userId,
                        'anUserChg' => $userId,
                    ];

                    if ($hasNoteColumn) {
                        if ($hasStatementColumn) {
                            $insertPayload['acNote'] = $userDescription === '' ? null : substr($userDescription, 0, 4000);
                        } else {
                            $noteMarker = 'PLANNED_BOM|';
                            $maxDescriptionLength = max(0, 4000 - strlen($noteMarker));
                            $trimmedDescription = $userDescription === '' ? '' : substr($userDescription, 0, $maxDescriptionLength);
                            $insertPayload['acNote'] = $noteMarker . $trimmedDescription;
                        }
                    }

                    if ($hasStatementColumn) {
                        $insertPayload['acStatement'] = 'PLANNED_BOM';
                    }

                    if ($resolvedUnit !== '') {
                        $insertPayload['acUM'] = $resolvedUnit;
                    }

                    if ($manualNoInsert && $nextNo !== null) {
                        $insertPayload['anNo'] = $nextNo;
                    }

                    if ($manualQIdInsert && $nextQId !== null) {
                        $insertPayload['anQId'] = $nextQId;
                    }

                    $this->newItemTableQuery()->insert($insertPayload);

                    $saved[] = [
                        'anNo' => $nextNo,
                        'anQId' => $nextQId,
                        'acIdent' => $componentId,
                        'acDescr' => $description,
                        'acUM' => $resolvedUnit,
                        'acOperationType' => $operationType,
                        'anGrossQty' => $baseQty,
                        'anPlanQty' => $plannedQty,
                    ];

                    if ($manualNoInsert && $nextNo !== null) {
                        $nextNo++;
                    }

                    if ($manualQIdInsert && $nextQId !== null) {
                        $nextQId++;
                    }
                }

                return $saved;
            });

            if (empty($insertedRows)) {
                return response()->json([
                    'message' => 'Nema stavki za snimanje planirane potrosnje.',
                ], 422);
            }

            return response()->json([
                'message' => 'Planirana potrosnja je uspjesno sacuvana.',
                'data' => [
                    'work_order_id' => $id,
                    'work_order_key' => $workOrderKey,
                    'product_id' => $productId,
                    'quantity_factor' => $quantityFactor,
                    'description' => $userDescription,
                    'saved_count' => count($insertedRows),
                    'items' => $insertedRows,
                ],
            ], 201);
        } catch (Throwable $exception) {
            Log::error('Planned consumption save failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'items_table' => $this->qualifiedItemTableName(),
                'product_structure_table' => $this->qualifiedProductStructureTableName(),
                'message' => $exception->getMessage(),
                'exception_class' => get_class($exception),
                'exception_code' => (string) $exception->getCode(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
                'trace' => Str::limit($exception->getTraceAsString(), 2000),
                'request_product_id' => (string) $request->input('product_id', ''),
                'request_quantity' => $this->toFloatOrNull($request->input('quantity')),
                'request_quantity_unit' => strtoupper(trim((string) $request->input('quantity_unit', 'AUTO'))),
                'request_description' => trim((string) $request->input('description', '')),
                'request_components_count' => count((array) $request->input('components', [])),
            ]);

            return response()->json([
                'message' => 'Greska pri snimanju planirane potrosnje.',
            ], 500);
        }
    }

    public function removePlannedConsumptionItem(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'item_id' => ['nullable', 'numeric'],
            'item_no' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravni podaci za brisanje stavke.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $workOrderRow = $this->findWorkOrderRow($id);

            if ($workOrderRow === null) {
                return response()->json([
                    'message' => 'Radni nalog nije pronadjen.',
                ], 404);
            }

            $columns = $this->itemTableColumns();
            $workOrderKey = trim((string) $this->valueTrimmed($workOrderRow, ['acKey'], ''));

            if ($workOrderKey === '') {
                return response()->json([
                    'message' => 'RN kljuc nije pronadjen.',
                ], 422);
            }

            if (!in_array('acKey', $columns, true)) {
                return response()->json([
                    'message' => 'Tabela stavki nema RN kljuc.',
                ], 500);
            }

            $validated = $validator->validated();
            $itemId = $this->toFloatOrNull($validated['item_id'] ?? null);
            $itemNo = $this->toFloatOrNull($validated['item_no'] ?? null);
            $hasQId = in_array('anQId', $columns, true);
            $hasNo = in_array('anNo', $columns, true);

            if ($itemId === null && $itemNo === null) {
                return response()->json([
                    'message' => 'Nedostaje identifikator stavke za brisanje.',
                ], 422);
            }

            if ($itemId !== null && !$hasQId && $itemNo === null) {
                return response()->json([
                    'message' => 'Kolona anQId nije dostupna za brisanje.',
                ], 422);
            }

            if ($itemNo !== null && !$hasNo && $itemId === null) {
                return response()->json([
                    'message' => 'Kolona anNo nije dostupna za brisanje.',
                ], 422);
            }

            $deleteQuery = $this->newItemTableQuery()->where('acKey', $workOrderKey);

            if ($itemId !== null && $hasQId) {
                $deleteQuery->where('anQId', (int) $itemId);
            } elseif ($itemNo !== null && $hasNo) {
                $deleteQuery->where('anNo', (int) $itemNo);
            } else {
                return response()->json([
                    'message' => 'Nije moguce odrediti stavku za brisanje.',
                ], 422);
            }

            $row = (clone $deleteQuery)->first();

            if ($row === null) {
                return response()->json([
                    'message' => 'Stavka nije pronadjena.',
                ], 404);
            }

            $deletedCount = $deleteQuery->delete();

            if ($deletedCount < 1) {
                return response()->json([
                    'message' => 'Stavka nije obrisana.',
                ], 500);
            }

            Log::info('Work order item removed.', [
                'id' => $id,
                'work_order_key' => $workOrderKey,
                'item_id' => $hasQId ? (int) ($row->anQId ?? 0) : null,
                'item_no' => $hasNo ? (int) ($row->anNo ?? 0) : null,
                'ac_ident' => trim((string) ($row->acIdent ?? '')),
                'deleted_count' => $deletedCount,
                'user_id' => (int) (auth()->id() ?? 0),
            ]);

            return response()->json([
                'message' => 'Stavka je obrisana.',
                'data' => [
                    'work_order_id' => $id,
                    'work_order_key' => $workOrderKey,
                    'item_id' => $hasQId ? (int) ($row->anQId ?? 0) : null,
                    'item_no' => $hasNo ? (int) ($row->anNo ?? 0) : null,
                    'deleted_count' => $deletedCount,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order item remove failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'items_table' => $this->qualifiedItemTableName(),
                'message' => $exception->getMessage(),
                'exception_class' => get_class($exception),
                'exception_code' => (string) $exception->getCode(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
                'request_item_id' => $this->toFloatOrNull($request->input('item_id')),
                'request_item_no' => $this->toFloatOrNull($request->input('item_no')),
            ]);

            return response()->json([
                'message' => 'Greska pri brisanju stavke planirane potrosnje.',
            ], 500);
        }
    }

    private function resolveWorkOrderProducts(array $workOrderRow, string $search = ''): array
    {
        $limit = $this->bomLimit();
        $products = [];
        $seenKeys = [];
        $addProduct = function (string $ident, string $name, string $source) use (&$products, &$seenKeys) {
            $normalizedIdent = trim($ident);

            if ($normalizedIdent === '') {
                return;
            }

            $seenKey = strtolower($normalizedIdent);

            if (array_key_exists($seenKey, $seenKeys)) {
                return;
            }

            $displayLabel = $normalizedIdent;
            $normalizedName = trim($name);

            if ($normalizedName !== '') {
                $displayLabel .= ' - ' . $normalizedName;
            }

            $products[] = [
                'acIdent' => $ident,
                'acIdentTrimmed' => $normalizedIdent,
                'acName' => $normalizedName,
                'label' => $displayLabel,
                'source' => $source,
                'bom_count' => 0,
            ];
            $seenKeys[$seenKey] = true;
        };

        $search = trim($search);
        $headerProductIdent = (string) $this->value($workOrderRow, ['acIdent'], '');
        $headerProductName = trim((string) $this->value($workOrderRow, ['acName'], ''));
        if (
            $search === ''
            || stripos($headerProductIdent, $search) !== false
            || stripos($headerProductName, $search) !== false
        ) {
            $addProduct($headerProductIdent, $headerProductName, 'work_order');
        }

        if ($search !== '') {
            $masterProducts = $this->newProductStructureTableQuery()
                ->select('acIdent')
                ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) <> ''")
                ->where('acIdent', 'like', '%' . $search . '%')
                ->distinct()
                ->orderBy('acIdent')
                ->limit($limit)
                ->get();

            foreach ($masterProducts as $masterProduct) {
                $addProduct((string) ($masterProduct->acIdent ?? ''), '', 'master_structure');
            }
        }

        if (empty($products)) {
            return [];
        }

        usort($products, static function (array $left, array $right): int {
            return strcmp(
                strtolower((string) ($left['acIdentTrimmed'] ?? '')),
                strtolower((string) ($right['acIdentTrimmed'] ?? ''))
            );
        });

        return array_slice($products, 0, $limit);
    }

    private function fetchBomComponentsByProduct(string $productId, ?int $limit = null): array
    {
        $resolvedLimit = max(1, (int) ($limit ?? $this->bomLimit()));

        $query = $this->newProductStructureTableQuery()
            ->select([
                'acIdentChild',
                'acDescr',
                'acUM',
                'anGrossQty',
                'acOperationType',
                'anNo',
            ])
            ->where('acIdent', $productId)
            ->orderBy('anNo')
            ->limit($resolvedLimit);

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $trimmedProduct = trim($productId);

            if ($trimmedProduct !== '' && $trimmedProduct !== $productId) {
                $rows = $this->newProductStructureTableQuery()
                    ->select([
                        'acIdentChild',
                        'acDescr',
                        'acUM',
                        'anGrossQty',
                        'acOperationType',
                        'anNo',
                    ])
                    ->whereRaw('LTRIM(RTRIM(acIdent)) = ?', [$trimmedProduct])
                    ->orderBy('anNo')
                    ->limit($resolvedLimit)
                    ->get();
            }
        }

        return $rows
            ->map(function ($row) {
                return [
                    'acIdentChild' => trim((string) ($row->acIdentChild ?? '')),
                    'acDescr' => trim((string) ($row->acDescr ?? '')),
                    'acUM' => strtoupper(substr(trim((string) ($row->acUM ?? '')), 0, 3)),
                    'anGrossQty' => $this->toFloatOrNull($row->anGrossQty ?? null) ?? 0.0,
                    'acOperationType' => trim((string) ($row->acOperationType ?? '')),
                    'anNo' => (int) ($this->toFloatOrNull($row->anNo ?? null) ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function bomSelectionKey(int $lineNo, string $componentId): string
    {
        return $lineNo . '|' . strtolower(trim($componentId));
    }

    private function bomLimit(): int
    {
        $limit = (int) config('workorders.bom_limit', 100);

        if ($limit < 1) {
            return 100;
        }

        return min($limit, 100);
    }

    private function fetchWorkOrders(
        ?int $limit = null,
        ?int $page = null,
        array $filters = [],
        array $sort = []
    ): array
    {
        $columns = $this->tableColumns();
        $moneyColumns = $this->monetaryColumns($columns);
        $resolvedLimit = $this->resolveLimit($limit);
        $resolvedPage = max(1, (int) ($page ?? 1));

        $total = (clone $this->newTableQuery())->count();
        $query = $this->newTableQuery();

        $this->applyFilters($query, $columns, $filters);
        $filteredTotal = (clone $query)->count();
        $hasMoneyValues = $this->hasMonetaryValues($query, $moneyColumns);
        $hasCustomOrdering = $this->applyRequestedOrdering($query, $columns, $sort);

        if (!$hasCustomOrdering) {
            $this->applyDefaultOrdering($query, $columns);
        }

        $rows = $query
            ->forPage($resolvedPage, $resolvedLimit)
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();
        $linkedOrders = $this->resolveLinkedOrdersForRows($rows);
        $data = array_map(function (array $row) use ($linkedOrders) {
            return $this->mapRow($row, false, $linkedOrders);
        }, $rows);

        return [
            'data' => $data,
            'meta' => [
                'count' => count($data),
                'page' => $resolvedPage,
                'limit' => $resolvedLimit,
                'total' => (int) $total,
                'filtered_total' => (int) $filteredTotal,
                'last_page' => $resolvedLimit > 0 ? (int) ceil($filteredTotal / $resolvedLimit) : 1,
                'has_money_column' => !empty($moneyColumns),
                'has_money_values' => $hasMoneyValues,
            ],
        ];
    }

    private function fetchCalendarEvents(?string $startDate, ?string $endDate, array $statusBuckets, array $priorityCodes = []): array
    {
        $columns = $this->tableColumns();
        $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF']);
        $dateColumn = $this->firstExistingColumn($columns, ['adDate', 'adDateIns']);
        $query = $this->newTableQuery();

        if ($dateColumn !== null) {
            if ($startDate !== null) {
                $query->whereDate($dateColumn, '>=', $startDate);
            }

            if ($endDate !== null) {
                $query->whereDate($dateColumn, '<', $endDate);
            }
        }

        $this->applyStatusBucketsFilter($query, $statusColumn, $statusBuckets);
        $this->applyCalendarPriorityCodesFilter($query, $columns, $priorityCodes);
        $this->applyDefaultOrdering($query, $columns);

        return $query
            ->get()
            ->map(function ($row) {
                $mappedRow = $this->mapRow((array) $row);
                $workOrderId = trim((string) ($mappedRow['id'] ?? ''));
                $start = $mappedRow['datum_kreiranja'] ?? null;

                if ($workOrderId === '' || $start === null) {
                    return null;
                }

                $displayNumber = $this->formatWorkOrderNumberForCalendar(
                    (string) ($mappedRow['broj_naloga'] ?? $workOrderId)
                );
                $status = (string) ($mappedRow['status'] ?? '');
                $bucket = $this->statusBucket($status) ?? 'planiran';

                return [
                    'id' => $workOrderId,
                    'title' => $displayNumber,
                    'start' => $start,
                    'allDay' => true,
                    'extendedProps' => [
                        'calendar' => $bucket,
                        'status' => $status,
                        'priority' => (string) ($mappedRow['prioritet'] ?? ''),
                        'workOrderId' => $workOrderId,
                        'workOrderNumber' => (string) ($mappedRow['broj_naloga'] ?? $workOrderId),
                        'previewUrl' => route('app-invoice-preview', ['id' => $workOrderId]),
                    ],
                ];
            })
            ->filter(function ($event) {
                return $event !== null;
            })
            ->values()
            ->all();
    }

    private function fetchCalendarStatusStats(?string $startDate, ?string $endDate, array $priorityCodes = []): array
    {
        $stats = $this->emptyStatusStats();
        $columns = $this->tableColumns();
        $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF']);
        $dateColumn = $this->firstExistingColumn($columns, ['adDate', 'adDateIns']);

        if ($statusColumn === null) {
            return $stats;
        }

        $query = $this->newTableQuery();

        if ($dateColumn !== null) {
            if ($startDate !== null) {
                $query->whereDate($dateColumn, '>=', $startDate);
            }

            if ($endDate !== null) {
                $query->whereDate($dateColumn, '<', $endDate);
            }
        }

        $this->applyCalendarPriorityCodesFilter($query, $columns, $priorityCodes);
        $stats['svi'] = (int) (clone $query)->count();

        $rows = (clone $query)
            ->select($statusColumn, DB::raw('COUNT(*) as total'))
            ->groupBy($statusColumn)
            ->get();

        foreach ($rows as $row) {
            $resolvedStatus = $this->resolveStatus($row->{$statusColumn} ?? null);
            $bucket = $resolvedStatus['bucket'] ?? null;

            if ($bucket !== null && array_key_exists($bucket, $stats)) {
                $stats[$bucket] += (int) $row->total;
            }
        }

        return $stats;
    }

    private function fetchCalendarPriorityStats(?string $startDate, ?string $endDate, array $statusBuckets = []): array
    {
        $stats = $this->emptyPriorityStats();
        $columns = $this->tableColumns();
        $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF']);
        $dateColumn = $this->firstExistingColumn($columns, ['adDate', 'adDateIns']);
        $priorityColumns = $this->existingColumns($columns, ['anPriority', 'acPriority', 'acWayOfSale', 'priority']);

        if (empty($priorityColumns)) {
            return $stats;
        }

        $query = $this->newTableQuery();

        if ($dateColumn !== null) {
            if ($startDate !== null) {
                $query->whereDate($dateColumn, '>=', $startDate);
            }

            if ($endDate !== null) {
                $query->whereDate($dateColumn, '<', $endDate);
            }
        }

        $this->applyStatusBucketsFilter($query, $statusColumn, $statusBuckets);

        $rows = $query
            ->get($priorityColumns)
            ->map(function ($row) use ($priorityColumns) {
                $rowData = (array) $row;
                $priorityValue = null;

                foreach ($priorityColumns as $priorityColumn) {
                    if (!array_key_exists($priorityColumn, $rowData)) {
                        continue;
                    }

                    if ($rowData[$priorityColumn] === null || trim((string) $rowData[$priorityColumn]) === '') {
                        continue;
                    }

                    $priorityValue = $rowData[$priorityColumn];
                    break;
                }

                return $this->resolvePriorityCode($priorityValue);
            })
            ->all();

        $stats['svi'] = count($rows);

        foreach ($rows as $priorityCode) {
            if ($priorityCode === null) {
                continue;
            }

            $statKey = (string) $priorityCode;

            if (array_key_exists($statKey, $stats)) {
                $stats[$statKey]++;
            }
        }

        return $stats;
    }

    private function fetchYearlySummary(int $currentYear, int $compareYear): array
    {
        $columns = $this->tableColumns();
        $dateColumn = $this->firstExistingColumn($columns, ['adDate', 'adDateIns']);
        $currentMonthly = $this->emptyYearMonthlyCounts();
        $compareMonthly = $this->emptyYearMonthlyCounts();

        if ($dateColumn !== null) {
            $wrappedDateColumn = '[' . str_replace(']', ']]', $dateColumn) . ']';
            $rows = $this->newTableQuery()
                ->selectRaw("YEAR($wrappedDateColumn) as year_no")
                ->selectRaw("MONTH($wrappedDateColumn) as month_no")
                ->selectRaw('COUNT(*) as total')
                ->whereNotNull($dateColumn)
                ->where(function (Builder $yearQuery) use ($dateColumn, $currentYear, $compareYear) {
                    $yearQuery->whereYear($dateColumn, $currentYear);

                    if ($compareYear !== $currentYear) {
                        $yearQuery->orWhereYear($dateColumn, $compareYear);
                    }
                })
                ->groupByRaw("YEAR($wrappedDateColumn), MONTH($wrappedDateColumn)")
                ->get();

            foreach ($rows as $row) {
                $yearNo = (int) ($row->year_no ?? 0);
                $monthNo = (int) ($row->month_no ?? 0);
                $total = (int) ($row->total ?? 0);

                if ($monthNo < 1 || $monthNo > 12) {
                    continue;
                }

                if ($yearNo === $currentYear) {
                    $currentMonthly[$monthNo] = $total;
                }

                if ($yearNo === $compareYear) {
                    $compareMonthly[$monthNo] = $total;
                }
            }
        }

        $currentSeries = array_values($currentMonthly);
        $compareSeries = array_values($compareMonthly);
        $currentTotal = (int) array_sum($currentSeries);
        $compareTotal = (int) array_sum($compareSeries);
        $delta = $currentTotal - $compareTotal;
        $deltaPercent = $compareTotal > 0
            ? round(($delta / $compareTotal) * 100, 1)
            : null;

        return [
            'current_year' => $currentYear,
            'compare_year' => $compareYear,
            'months' => [
                'current' => $currentMonthly,
                'compare' => $compareMonthly,
            ],
            'series' => [
                'current' => $currentSeries,
                'compare' => $compareSeries,
            ],
            'totals' => [
                'current' => $currentTotal,
                'compare' => $compareTotal,
                'delta' => $delta,
                'delta_percent' => $deltaPercent,
            ],
        ];
    }

    private function emptyYearMonthlyCounts(): array
    {
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = 0;
        }

        return $months;
    }

    private function normalizeCalendarStatuses(mixed $statuses): array
    {
        $allowedBuckets = array_values(array_filter(array_keys($this->emptyStatusStats()), function ($bucket) {
            return $bucket !== 'svi';
        }));

        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }

        if (!is_array($statuses)) {
            return [];
        }

        $normalized = array_map(function ($status) {
            return strtolower(trim((string) $status));
        }, $statuses);

        return array_values(array_unique(array_filter($normalized, function ($status) use ($allowedBuckets) {
            return in_array($status, $allowedBuckets, true);
        })));
    }

    private function normalizeCalendarPriorities(mixed $priorities): array
    {
        $allowedCodes = $this->calendarPriorityCodes();

        if (is_string($priorities)) {
            $priorities = explode(',', $priorities);
        }

        if (!is_array($priorities) || empty($allowedCodes)) {
            return [];
        }

        $normalized = [];

        foreach ($priorities as $priority) {
            $priorityValue = trim((string) $priority);

            if ($priorityValue === '') {
                continue;
            }

            $matchedCodes = $this->priorityCodesForSearch($priorityValue);

            if (empty($matchedCodes) && ctype_digit($priorityValue)) {
                $matchedCodes = [(int) $priorityValue];
            }

            foreach ($matchedCodes as $matchedCode) {
                $priorityCode = (int) $matchedCode;

                if (in_array($priorityCode, $allowedCodes, true)) {
                    $normalized[] = $priorityCode;
                }
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    private function applyStatusBucketsFilter(Builder $query, ?string $statusColumn, array $statusBuckets): void
    {
        if ($statusColumn === null || empty($statusBuckets)) {
            return;
        }

        $statusAliases = [];

        foreach ($statusBuckets as $bucket) {
            $statusAliases = array_merge($statusAliases, $this->statusAliasesByBucket($bucket));
        }

        $statusAliases = array_values(array_unique($statusAliases));

        if (empty($statusAliases)) {
            return;
        }

        $query->whereIn($statusColumn, $statusAliases);
    }

    private function applyCalendarPriorityCodesFilter(Builder $query, array $columns, array $priorityCodes): void
    {
        if (empty($priorityCodes)) {
            return;
        }

        $priorityCodeColumn = $this->firstExistingColumn($columns, ['anPriority']);
        $priorityTextColumns = $this->existingColumns($columns, ['acPriority', 'acWayOfSale', 'priority']);
        $priorityCodes = array_values(array_unique(array_map(function ($priorityCode) {
            return (int) $priorityCode;
        }, $priorityCodes)));
        $priorityLabels = [];

        foreach ($priorityCodes as $priorityCode) {
            $priorityLabel = trim((string) ($this->deliveryPriorityMap()[$priorityCode] ?? ''));

            if ($priorityLabel !== '') {
                $priorityLabels[] = $priorityLabel;
            }
        }

        $priorityLabels = array_values(array_unique($priorityLabels));

        if ($priorityCodeColumn === null && empty($priorityTextColumns)) {
            return;
        }

        $query->where(function (Builder $priorityQuery) use ($priorityCodeColumn, $priorityCodes, $priorityTextColumns, $priorityLabels) {
            $hasAnyClause = false;

            if ($priorityCodeColumn !== null && !empty($priorityCodes)) {
                $priorityQuery->where(function (Builder $codeQuery) use ($priorityCodeColumn, $priorityCodes) {
                    $codeQuery->whereIn($priorityCodeColumn, $priorityCodes);

                    if (in_array(5, $priorityCodes, true)) {
                        $codeQuery->orWhereNull($priorityCodeColumn);
                    }
                });
                $hasAnyClause = true;
            }

            if (!empty($priorityTextColumns) && !empty($priorityLabels)) {
                foreach ($priorityTextColumns as $priorityTextColumn) {
                    foreach ($priorityLabels as $priorityLabel) {
                        if ($hasAnyClause) {
                            $priorityQuery->orWhere($priorityTextColumn, 'like', '%' . $priorityLabel . '%');
                        } else {
                            $priorityQuery->where($priorityTextColumn, 'like', '%' . $priorityLabel . '%');
                            $hasAnyClause = true;
                        }
                    }
                }
            }

            if (!$hasAnyClause) {
                $priorityQuery->whereRaw('1 = 0');
            }
        });
    }

    private function formatWorkOrderNumberForCalendar(string $workOrderNumber): string
    {
        $rawValue = trim($workOrderNumber);

        if ($rawValue === '') {
            return 'N/A';
        }

        if (str_contains($rawValue, '-')) {
            return $rawValue;
        }

        $digits = preg_replace('/\D+/', '', $rawValue);

        if (strlen($digits) === 13) {
            return substr($digits, 0, 2) . '-' . substr($digits, 2, 5) . '-' . substr($digits, 7);
        }

        return $rawValue;
    }

    private function fetchMappedWorkOrderItems(array $workOrderRow): array
    {
        $columns = $this->itemTableColumns();

        if (!in_array('acKey', $columns, true)) {
            return [];
        }

        $workOrderKey = trim((string) $this->value($workOrderRow, ['acKey'], ''));

        if ($workOrderKey === '') {
            return [];
        }

        $query = $this->newItemTableQuery()->where('acKey', $workOrderKey);

        foreach (['anNo', 'anVariant', 'adTimeIns'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy($column);
            }
        }

        return $query
            ->get()
            ->map(function ($row) {
                return $this->mapItemRow((array) $row);
            })
            ->values()
            ->all();
    }

    private function fetchMappedWorkOrderItemResources(array $workOrderRow): array
    {
        $columns = $this->itemResourcesTableColumns();
        $workOrderKey = trim((string) $this->value($workOrderRow, ['acKey'], ''));

        if ($workOrderKey === '') {
            return [];
        }

        $itemQIdColumn = $this->firstExistingColumn($columns, ['anWOExItemQId', 'anItemQId', 'anQIdItem']);
        $query = DB::table($this->qualifiedItemResourcesTableName() . ' as r')
            ->select('r.*');

        if ($itemQIdColumn !== null) {
            $itemQIdField = 'r.' . $itemQIdColumn;
            $itemQIds = $this->newItemTableQuery()
                ->where('acKey', $workOrderKey)
                ->pluck('anQId')
                ->map(function ($id) {
                    return is_numeric((string) $id) ? (int) $id : null;
                })
                ->filter(function ($id) {
                    return $id !== null;
                })
                ->values()
                ->all();

            if (empty($itemQIds)) {
                return [];
            }

            $query->whereIn($itemQIdField, $itemQIds)
                ->leftJoin($this->qualifiedItemTableName() . ' as i', 'i.anQId', '=', $itemQIdField)
                ->addSelect([
                    'i.anNo as __item_no',
                    'i.acIdent as __item_ident',
                    'i.acDescr as __item_descr',
                    'i.acUM as __item_um',
                    'i.anQty as __item_qty',
                    'i.anPlanQty as __item_plan_qty',
                ]);
        } else {
            $linkColumn = $this->firstExistingColumn($columns, ['acKey', 'acWOKey', 'acDocKey', 'acLnkKey']);

            if ($linkColumn === null) {
                return [];
            }

            $query->where('r.' . $linkColumn, $workOrderKey);
        }

        foreach (['anWOExItemQId', 'anNo', 'anLineNo', 'anResNo', 'anVariant', 'adTimeIns'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy('r.' . $column);
            }
        }

        return $query
            ->get()
            ->map(function ($row) {
                return $this->mapItemResourceRow((array) $row);
            })
            ->values()
            ->all();
    }

    private function fetchMappedWorkOrderRegOperations(array $workOrderRow): array
    {
        $columns = $this->regOperationsTableColumns();
        $linkColumn = $this->firstExistingColumn($columns, ['acKey', 'acWOKey', 'acDocKey', 'acLnkKey']);

        if ($linkColumn === null) {
            return [];
        }

        $workOrderKey = trim((string) $this->value($workOrderRow, ['acKey'], ''));

        if ($workOrderKey === '') {
            return [];
        }

        if ($linkColumn === null) {
            return $this->fetchMappedOperationsFromItems($workOrderKey);
        }

        $query = $this->newRegOperationsTableQuery()->where($linkColumn, $workOrderKey);

        foreach (['anNo', 'anVariant', 'adDate', 'adTimeIns'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy($column);
            }
        }

        $operations = $query
            ->get()
            ->map(function ($row) {
                return $this->mapRegOperationRow((array) $row);
            })
            ->values()
            ->all();

        if (!empty($operations)) {
            return $operations;
        }

        return $this->fetchMappedOperationsFromItems($workOrderKey);
    }

    private function fetchMappedOperationsFromItems(string $workOrderKey): array
    {
        $columns = $this->itemTableColumns();

        if (!in_array('acOperationType', $columns, true)) {
            return [];
        }

        $query = $this->newItemTableQuery()
            ->where('acKey', $workOrderKey)
            ->whereRaw("LTRIM(RTRIM(ISNULL(acOperationType, ''))) <> ''");

        foreach (['anNo', 'anVariant', 'adTimeIns'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy($column);
            }
        }

        return $query
            ->get()
            ->map(function ($row) {
                return $this->mapOperationFromItemRow((array) $row);
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

        return $this->mapRow($row, $includeRaw, $this->resolveLinkedOrdersForRows([$row]), true);
    }

    private function findWorkOrderRow(string $id): ?array
    {
        $id = trim($id);

        if ($id === '') {
            return null;
        }

        $orderLocator = $this->parseOrderLocatorIdentifier($id);

        if ($orderLocator !== null) {
            $rowFromOrderLocator = $this->findWorkOrderRowByOrderLocator(
                (string) ($orderLocator['order_number'] ?? ''),
                (int) ($orderLocator['order_position'] ?? 0),
                array_key_exists('product_code', $orderLocator)
                    ? (string) ($orderLocator['product_code'] ?? '')
                    : null
            );

            if ($rowFromOrderLocator !== null) {
                return $rowFromOrderLocator;
            }
        }

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

    private function parseOrderLocatorIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);

        if ($identifier === '' || !str_contains($identifier, ';')) {
            return null;
        }

        $parts = array_map(static function ($part) {
            return trim((string) $part);
        }, explode(';', $identifier));

        if (count($parts) < 2 || count($parts) > 3) {
            return null;
        }

        $orderNumberRaw = $parts[0] ?? '';
        $orderPositionRaw = $parts[1] ?? '';
        $productCodeRaw = $parts[2] ?? null;

        if ($orderNumberRaw === '' || $orderPositionRaw === '') {
            return null;
        }

        $positionValue = $this->toFloatOrNull($orderPositionRaw);

        if ($positionValue === null) {
            return null;
        }

        $orderPosition = (int) round($positionValue);

        if (abs($positionValue - $orderPosition) > 0.000001) {
            return null;
        }

        $normalizedOrderNumber = $this->normalizeComparableIdentifier($orderNumberRaw);

        if ($normalizedOrderNumber === '') {
            return null;
        }

        $parsed = [
            'order_number' => $normalizedOrderNumber,
            'order_position' => $orderPosition,
        ];

        if ($productCodeRaw !== null) {
            $normalizedProductCode = $this->normalizeComparableIdentifier($productCodeRaw);

            if ($normalizedProductCode === '') {
                return null;
            }

            $parsed['product_code'] = $normalizedProductCode;
        }

        return $parsed;
    }

    private function findWorkOrderRowByOrderLocator(
        string $normalizedOrderNumber,
        int $orderPosition,
        ?string $normalizedProductCode = null
    ): ?array
    {
        if ($normalizedOrderNumber === '') {
            return null;
        }

        try {
            $workOrderColumns = $this->tableColumns();
            $workOrderLinkColumn = $this->firstExistingColumn($workOrderColumns, ['acLnkKey']);
            $workOrderPositionColumn = $this->firstExistingColumn($workOrderColumns, ['anLnkNo']);
            $workOrderProductColumn = $this->firstExistingColumn($workOrderColumns, ['acIdent', 'product_code', 'acCode']);
            $orderColumns = $this->orderTableColumns();
            $orderKeyColumn = $this->firstExistingColumn($orderColumns, ['acKey']);
            $orderNumberColumns = $this->existingColumns($orderColumns, ['acKeyView', 'acRefNo1', 'acKey']);

            if (
                $workOrderLinkColumn === null
                || $workOrderPositionColumn === null
                || $orderKeyColumn === null
                || empty($orderNumberColumns)
            ) {
                return null;
            }

            $query = DB::table($this->qualifiedTableName() . ' as wo')
                ->join(
                    $this->qualifiedOrderTableName() . ' as ord',
                    'wo.' . $workOrderLinkColumn,
                    '=',
                    'ord.' . $orderKeyColumn
                )
                ->select('wo.*')
                ->where('wo.' . $workOrderPositionColumn, $orderPosition)
                ->where(function (Builder $orderNumberQuery) use ($orderNumberColumns, $normalizedOrderNumber) {
                    foreach ($orderNumberColumns as $index => $orderNumberColumn) {
                        $normalizedExpression = $this->normalizedIdentifierExpression($orderNumberQuery, 'ord.' . $orderNumberColumn);
                        $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                        $orderNumberQuery->{$method}("$normalizedExpression = ?", [$normalizedOrderNumber]);
                    }
                });

            if ($normalizedProductCode !== null && $normalizedProductCode !== '' && $workOrderProductColumn !== null) {
                $normalizedProductExpression = $this->normalizedIdentifierExpression($query, 'wo.' . $workOrderProductColumn);
                $query->whereRaw("$normalizedProductExpression = ?", [$normalizedProductCode]);
            }

            foreach (['adDate', 'adDateIns', 'adTimeIns', 'anNo'] as $orderByColumn) {
                if (in_array($orderByColumn, $workOrderColumns, true)) {
                    $query->orderByDesc('wo.' . $orderByColumn);
                }
            }

            $row = $query->first();

            return $row ? (array) $row : null;
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve work order by order number and position.', [
                'connection' => config('database.default'),
                'work_orders_table' => $this->qualifiedTableName(),
                'orders_table' => $this->qualifiedOrderTableName(),
                'order_number' => $normalizedOrderNumber,
                'order_position' => $orderPosition,
                'product_code' => $normalizedProductCode,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function normalizeComparableIdentifier(string $value): string
    {
        $normalized = preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value)));

        return is_string($normalized) ? $normalized : '';
    }

    private function normalizedIdentifierExpression(Builder $query, string $columnIdentifier): string
    {
        $wrappedColumn = $query->getGrammar()->wrap($columnIdentifier);

        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(CAST($wrappedColumn AS NVARCHAR(64)), ''), '-', ''), ' ', ''), '/', ''), '.', ''), '_', ''))";
    }

    private function mapRow(
        array $row,
        bool $includeRaw = false,
        array $linkedOrders = [],
        bool $loadLinkedOrderOnMiss = false
    ): array
    {
        $brojNaloga = (string) $this->value($row, ['acRefNo1', 'acKey', 'anNo', 'id'], 'N/A');
        $id = $this->value($row, ['acRefNo1', 'acKey'], null);

        if ($id === null || $id === '' || ((is_int($id) || is_float($id) || is_numeric((string) $id)) && (float) $id === 0.0)) {
            $id = $this->value($row, ['anNo', 'id'], $brojNaloga);
        }

        $status = $this->mapStatus($this->value($row, ['acStatusMF'], 'N/A'));
        $priority = $this->mapPriority($this->value($row, ['anPriority', 'acPriority', 'acWayOfSale', 'priority'], 5));
        $createdDate = $this->normalizeDate($this->value($row, ['adDate', 'adDateIns', 'created_at']));
        $endDate = $this->normalizeDate($this->value($row, ['adDeliveryDeadline', 'adDateOut', 'actual_end']));
        $rawAmount = $this->value($row, $this->moneyValueCandidates(), null);
        $amount = $this->normalizeNullableNumber($rawAmount);
        $orderLinkKey = $this->extractOrderLinkKey($row);
        $linkedOrder = $this->resolveLinkedOrder($orderLinkKey, $linkedOrders, $loadLinkedOrderOnMiss);
        $orderKey = trim((string) ($linkedOrder['order_key'] ?? $orderLinkKey));
        $orderNumber = trim((string) ($linkedOrder['order_number'] ?? $orderKey));
        $orderPosition = $this->valueTrimmed($row, ['anLnkNo'], null);

        $mapped = [
            'responsive_id' => '',
            'id' => $id,
            'broj_naloga' => $brojNaloga,
            'naziv' => (string) $this->value($row, ['acName', 'acDescr', 'title'], 'Radni nalog'),
            'sifra' => (string) $this->valueTrimmed($row, ['acIdent', 'product_code', 'acCode'], ''),
            'opis' => (string) $this->value($row, ['acNote', 'acStatement', 'acDescr', 'description'], ''),
            'status' => $status,
            'prioritet' => $priority,
            'datum_kreiranja' => $createdDate,
            'datum_zavrsetka' => $endDate,
            'dodeljen_korisnik' => (string) $this->value($row, ['anClerk', 'created_by', 'acUser'], ''),
            'klijent' => (string) $this->value($row, ['acConsignee', 'acReceiver', 'client_name', 'acPartner'], 'N/A'),
            'vrednost' => $amount,
            'valuta' => $amount === null ? '' : (string) $this->value($row, ['acCurrency', 'currency'], 'BAM'),
            'magacin' => (string) $this->value($row, ['acWarehouse', 'linked_document', 'acWarehouseFrom'], ''),
            'broj_narudzbe' => $orderNumber,
            'narudzba_kljuc' => $orderKey,
            'broj_pozicije_narudzbe' => $orderPosition,
        ];

        if ($includeRaw) {
            $mapped['raw'] = $row;
        }

        return $mapped;
    }

    private function buildWorkOrderMetadata(
        array $raw,
        array $workOrder,
        array $workOrderItems,
        array $workOrderItemResources,
        array $workOrderRegOperations
    ): array {
        $unit = (string) $this->valueTrimmed($raw, ['acUM'], '');
        $planQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anPlanQty'], null));
        $producedQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anProducedQty'], null));
        $seriesQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anQtySeries'], null));
        $planWasteQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anPlanWasteQty'], null));
        $wasteQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anWasteQty'], null));
        $planScrapQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anPlanScrapQty'], null));
        $scrapQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anScrapQty'], null));
        $workTime = $this->toFloatOrNull($this->valueTrimmed($raw, ['anWorkTime'], null));
        $throTime = $this->toFloatOrNull($this->valueTrimmed($raw, ['anThroTime'], null));
        $repairQty = $this->toFloatOrNull($this->valueTrimmed($raw, ['anRepairQty'], null));

        $completionPercent = null;
        if ($planQty !== null && $producedQty !== null && abs($planQty) > 0.000001) {
            $completionPercent = ($producedQty / $planQty) * 100;
        }

        $itemTotal = count($workOrderItems);
        $finishedItems = count(array_filter($workOrderItems, function (array $item) {
            return strtolower(trim((string) ($item['zavrseno'] ?? ''))) === 'da';
        }));
        $itemsCompletionPercent = $itemTotal > 0 ? ($finishedItems / $itemTotal) * 100 : null;

        $progressPercent = $completionPercent ?? $itemsCompletionPercent ?? 0.0;
        $progressPercent = max(0.0, min(100.0, $progressPercent));
        $progressLabel = $completionPercent !== null ? 'Realizacija po količini' : 'Realizacija po završenim stavkama';

        $statusBucket = $this->statusBucket((string) ($workOrder['status'] ?? ''));
        $statusToneMap = [
            'planiran' => 'primary',
            'otvoren' => 'success',
            'rezerviran' => 'warning',
            'raspisan' => 'info',
            'u_radu' => 'warning',
            'djelimicno_zakljucen' => 'orange',
            'zakljucen' => 'danger',
        ];
        $statusTone = $statusBucket !== null ? ($statusToneMap[$statusBucket] ?? 'secondary') : 'secondary';

        $priorityCode = (int) ($this->toFloatOrNull($this->valueTrimmed($raw, ['anPriority'], null)) ?? 0);
        $priorityTone = 'warning';
        if ($priorityCode === 1) {
            $priorityTone = 'danger';
        } elseif ($priorityCode >= 10) {
            $priorityTone = 'info';
        }

        $highlights = array_values(array_filter([
            ['label' => 'Status', 'value' => (string) ($workOrder['status'] ?? 'N/A'), 'tone' => $statusTone],
            ['label' => 'Prioritet', 'value' => (string) ($workOrder['prioritet'] ?? 'N/A'), 'tone' => $priorityTone],
            ['label' => 'Tip dokumenta', 'value' => (string) $this->valueTrimmed($raw, ['acDocTypeView', 'acDocType'], ''), 'tone' => 'slate'],
            ['label' => 'Šifra proizvoda', 'value' => (string) $this->valueTrimmed($raw, ['acIdent'], ''), 'tone' => 'slate'],
            ['label' => 'Naziv proizvoda', 'value' => (string) $this->valueTrimmed($raw, ['acName'], ''), 'tone' => 'slate'],
            ['label' => 'Varijanta', 'value' => (string) $this->valueTrimmed($raw, ['acProdVariant', 'anVariant'], ''), 'tone' => 'slate'],
            ['label' => 'Lokacija', 'value' => (string) $this->valueTrimmed($raw, ['acLocation', 'acDept'], ''), 'tone' => 'slate'],
            ['label' => 'Plan ID', 'value' => (string) $this->valueTrimmed($raw, ['acPlanIDView', 'acPlanID'], ''), 'tone' => 'slate'],
        ], function (array $entry) {
            return trim((string) ($entry['value'] ?? '')) !== '';
        }));

        $kpis = [
            ['label' => 'Planirana količina', 'value' => $this->formatMetaNumber($planQty), 'unit' => $unit],
            ['label' => 'Izrađena količina', 'value' => $this->formatMetaNumber($producedQty), 'unit' => $unit],
            ['label' => 'Serija', 'value' => $this->formatMetaNumber($seriesQty), 'unit' => $unit],
            ['label' => 'Popravka', 'value' => $this->formatMetaNumber($repairQty), 'unit' => $unit],
            ['label' => 'Plan otpad', 'value' => $this->formatMetaNumber($planWasteQty), 'unit' => $unit],
            ['label' => 'Otpad', 'value' => $this->formatMetaNumber($wasteQty), 'unit' => $unit],
            ['label' => 'Plan skart', 'value' => $this->formatMetaNumber($planScrapQty), 'unit' => $unit],
            ['label' => 'Skart', 'value' => $this->formatMetaNumber($scrapQty), 'unit' => $unit],
            ['label' => 'Vrijeme rada', 'value' => $this->formatMetaNumber($workTime), 'unit' => 'h'],
            ['label' => 'Vrijeme protoka', 'value' => $this->formatMetaNumber($throTime), 'unit' => 'h'],
            ['label' => 'Stavke', 'value' => (string) $itemTotal, 'unit' => ''],
            ['label' => 'Materijali', 'value' => (string) count($workOrderItemResources), 'unit' => ''],
            ['label' => 'Operacije', 'value' => (string) count($workOrderRegOperations), 'unit' => ''],
        ];

        $timelineRows = [
            ['label' => 'Datum naloga', 'raw' => $this->value($raw, ['adDate'], null)],
            ['label' => 'Planirani start', 'raw' => $this->value($raw, ['adSchedStartTime'], null)],
            ['label' => 'Planirani kraj', 'raw' => $this->value($raw, ['adSchedEndTime'], null)],
            ['label' => 'Zavrsetak WO', 'raw' => $this->value($raw, ['adWOFinishDate'], null)],
            ['label' => 'Datum veze', 'raw' => $this->value($raw, ['adLnkDate'], null)],
            ['label' => 'Vrijeme unosa', 'raw' => $this->value($raw, ['adTimeIns'], null)],
            ['label' => 'Vrijeme izmjene', 'raw' => $this->value($raw, ['adTimeChg'], null)],
        ];
        $timeline = $this->sortTimelineRowsChronologically($timelineRows);

        $traceability = [
            ['label' => 'RN ključ', 'value' => (string) $this->valueTrimmed($raw, ['acKeyView', 'acKey'], '-')],
            ['label' => 'Broj narudžbe', 'value' => (string) ($workOrder['broj_narudzbe'] ?? '-')],
            ['label' => 'Vezni dokument', 'value' => (string) $this->valueTrimmed($raw, ['acLnkKeyView', 'acLnkKey'], '-')],
            ['label' => 'Vezni broj', 'value' => (string) $this->valueTrimmed($raw, ['anLnkNo'], '-')],
            ['label' => 'Nadređeni RN', 'value' => (string) $this->valueTrimmed($raw, ['acParentWOView', 'acParentWO'], '-')],
            ['label' => 'Nadređena količina', 'value' => $this->formatMetaNumber($this->toFloatOrNull($this->valueTrimmed($raw, ['anParentWOQty'], null)))],
            ['label' => 'QID', 'value' => (string) $this->valueTrimmed($raw, ['anQId'], '-')],
            ['label' => 'QID CA', 'value' => (string) $this->valueTrimmed($raw, ['anQIdCA'], '-')],
            ['label' => 'Korisnik unosa', 'value' => (string) $this->valueTrimmed($raw, ['anUserIns'], '-')],
            ['label' => 'Korisnik izmjene', 'value' => (string) $this->valueTrimmed($raw, ['anUserChg'], '-')],
            ['label' => 'Nosilac troška', 'value' => (string) $this->valueTrimmed($raw, ['acCostDrv'], '-')],
            ['label' => 'Izvor kreiranja', 'value' => (string) $this->valueTrimmed($raw, ['acCreateFrom'], '-')],
            ['label' => 'Tip kroja', 'value' => (string) $this->valueTrimmed($raw, ['acCropType'], '-')],
        ];

        $flags = [
            ['label' => 'Povrat', 'value' => $this->formatMetaFlag($this->valueTrimmed($raw, ['acReversal'], null)), 'tone' => $this->flagTone($this->valueTrimmed($raw, ['acReversal'], null))],
            ['label' => 'Prijem završen', 'value' => $this->formatMetaFlag($this->valueTrimmed($raw, ['acReceiveFinished'], null)), 'tone' => $this->flagTone($this->valueTrimmed($raw, ['acReceiveFinished'], null))],
            ['label' => 'SN transfer', 'value' => $this->formatMetaFlag($this->valueTrimmed($raw, ['anSNTransfer'], null)), 'tone' => $this->flagTone($this->valueTrimmed($raw, ['anSNTransfer'], null))],
        ];

        return [
            'highlights' => $highlights,
            'kpis' => $kpis,
            'timeline' => $timeline,
            'traceability' => $traceability,
            'flags' => $flags,
            'progress' => [
                'label' => $progressLabel,
                'percent' => $progressPercent,
                'display' => $this->formatMetaNumber($progressPercent, 1) . ' %',
            ],
        ];
    }

    private function sortTimelineRowsChronologically(array $rows): array
    {
        $sortable = [];

        foreach ($rows as $index => $row) {
            $timestamp = $this->metaDateTimestamp($row['raw'] ?? null);
            $sortable[] = [
                'label' => (string) ($row['label'] ?? ''),
                'raw' => $row['raw'] ?? null,
                'timestamp' => $timestamp,
                'index' => $index,
            ];
        }

        usort($sortable, static function (array $a, array $b): int {
            $aTs = $a['timestamp'];
            $bTs = $b['timestamp'];

            if ($aTs === null && $bTs === null) {
                return $a['index'] <=> $b['index'];
            }

            if ($aTs === null) {
                return 1;
            }

            if ($bTs === null) {
                return -1;
            }

            if ($aTs === $bTs) {
                return $a['index'] <=> $b['index'];
            }

            return $aTs <=> $bTs;
        });

        return array_map(function (array $row): array {
            return [
                'label' => $row['label'],
                'value' => $this->formatMetaDateTime($row['raw'] ?? null),
            ];
        }, $sortable);
    }

    private function metaDateTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $dateTime = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);

            return $dateTime->getTimestamp();
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function mapItemRow(array $row): array
    {
        $taskState = strtoupper(trim((string) $this->value($row, ['acTaskState'], '')));
        $isFinished = in_array($taskState, ['F', 'Z', 'C', 'D'], true);
        $note = (string) $this->value($row, ['acNote'], '');
        $displayNote = $this->plannedConsumptionDisplayNote($note);
        $itemId = $this->value($row, ['anQId', 'anNo'], null);
        $itemQid = $this->value($row, ['anQId'], null);
        $itemNo = $this->value($row, ['anNo'], null);
        $canRemove = $itemQid !== null || $itemNo !== null;

        return [
            'id' => $itemId,
            'qid' => $itemQid,
            'no' => $itemNo,
            'ac_key' => trim((string) $this->value($row, ['acKey'], '')),
            'alternativa' => (string) $this->value($row, ['anVariant'], ''),
            'pozicija' => (string) $this->value($row, ['anNo'], ''),
            'artikal' => (string) $this->value($row, ['acIdent'], ''),
            'opis' => (string) $this->value($row, ['acDescr'], ''),
            'napomena' => $displayNote,
            'kolicina' => $this->normalizeNumber($this->value($row, ['anQty', 'anQty1', 'anPlanQty'], 0)),
            'mj' => (string) $this->value($row, ['acUM'], ''),
            'serija' => $this->normalizeNumber($this->value($row, ['anQtySE', 'anBatch'], 0)),
            'normativna_osnova' => $this->normalizeNumber($this->value($row, ['anQtyBase', 'anQtyBase3'], 0)),
            'aktivno' => ((int) $this->value($row, ['anActive'], 0)) === 1 ? 'Da' : 'Ne',
            'zavrseno' => $isFinished ? 'Da' : 'Ne',
            'va' => (string) $this->value($row, ['acFieldSA', 'acFieldSE'], ''),
            'prim_klas' => (string) $this->value($row, ['acFieldSB'], ''),
            'sek_klas' => (string) $this->value($row, ['acFieldSC'], ''),
            'can_remove' => $canRemove,
        ];
    }

    private function resolveLinkedOrdersForRows(array $rows): array
    {
        $linkKeys = array_values(array_unique(array_filter(array_map(function ($row) {
            return $this->extractOrderLinkKey(is_array($row) ? $row : (array) $row);
        }, $rows))));

        if (empty($linkKeys)) {
            return [];
        }

        $this->primeLinkedOrderCache($linkKeys);

        $resolved = [];

        foreach ($linkKeys as $linkKey) {
            if (!array_key_exists($linkKey, $this->linkedOrderCache) || empty($this->linkedOrderCache[$linkKey])) {
                continue;
            }

            $resolved[$linkKey] = $this->linkedOrderCache[$linkKey];
        }

        return $resolved;
    }

    private function extractOrderLinkKey(array $row): string
    {
        return trim((string) $this->valueTrimmed($row, ['acLnkKey'], ''));
    }

    private function resolveLinkedOrder(string $linkKey, array $linkedOrders = [], bool $loadOnMiss = false): array
    {
        if ($linkKey === '') {
            return [];
        }

        if (array_key_exists($linkKey, $linkedOrders) && !empty($linkedOrders[$linkKey])) {
            return $linkedOrders[$linkKey];
        }

        if (array_key_exists($linkKey, $this->linkedOrderCache) && !empty($this->linkedOrderCache[$linkKey])) {
            return $this->linkedOrderCache[$linkKey];
        }

        if (!$loadOnMiss) {
            return [];
        }

        $this->primeLinkedOrderCache([$linkKey]);

        return (array) ($this->linkedOrderCache[$linkKey] ?? []);
    }

    private function primeLinkedOrderCache(array $linkKeys): void
    {
        $linkKeys = array_values(array_unique(array_filter(array_map(function ($key) {
            return trim((string) $key);
        }, $linkKeys), function ($key) {
            return $key !== '';
        })));

        if (empty($linkKeys)) {
            return;
        }

        $missingLinkKeys = array_values(array_filter($linkKeys, function ($key) {
            return !array_key_exists($key, $this->linkedOrderCache);
        }));

        if (empty($missingLinkKeys)) {
            return;
        }

        $orderColumns = $this->orderTableColumns();
        $orderKeyColumn = $this->firstExistingColumn($orderColumns, ['acKey']);
        $orderNumberColumn = $this->firstExistingColumn($orderColumns, ['acKeyView', 'acRefNo1', 'acKey']);
        $orderQidColumn = $this->firstExistingColumn($orderColumns, ['anQId']);

        foreach ($missingLinkKeys as $linkKey) {
            $this->linkedOrderCache[$linkKey] = [];
        }

        if ($orderKeyColumn === null) {
            return;
        }

        $selectColumns = array_values(array_unique(array_filter([
            $orderKeyColumn,
            $orderNumberColumn,
            $orderQidColumn,
        ])));

        try {
            $rows = $this->newOrderTableQuery()
                ->whereIn($orderKeyColumn, $missingLinkKeys)
                ->get($selectColumns)
                ->map(function ($row) {
                    return (array) $row;
                })
                ->values()
                ->all();

            foreach ($rows as $row) {
                $orderKey = trim((string) ($row[$orderKeyColumn] ?? ''));

                if ($orderKey === '') {
                    continue;
                }

                $orderNumber = $orderNumberColumn !== null
                    ? trim((string) ($row[$orderNumberColumn] ?? ''))
                    : '';

                if ($orderNumber === '') {
                    $orderNumber = $orderKey;
                }

                $this->linkedOrderCache[$orderKey] = [
                    'order_key' => $orderKey,
                    'order_number' => $orderNumber,
                    'order_qid' => $orderQidColumn !== null
                        ? ($row[$orderQidColumn] ?? null)
                        : null,
                ];
            }
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve linked order numbers for work orders.', [
                'connection' => config('database.default'),
                'work_orders_table' => $this->qualifiedTableName(),
                'orders_table' => $this->qualifiedOrderTableName(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function plannedConsumptionDisplayNote(string $note): string
    {
        $trimmedNote = trim($note);

        if ($trimmedNote === '') {
            return '';
        }

        $markerPrefix = 'PLANNED_BOM|';
        if (Str::startsWith($trimmedNote, $markerPrefix)) {
            return trim(substr($trimmedNote, strlen($markerPrefix)));
        }

        $legacyPrefix = 'Planned BOM consumption from tHF_SetPrSt.';
        if (Str::startsWith($trimmedNote, $legacyPrefix)) {
            $legacyDescriptionTag = '| Description:';
            $descriptionOffset = stripos($trimmedNote, $legacyDescriptionTag);

            if ($descriptionOffset !== false) {
                return trim(substr($trimmedNote, $descriptionOffset + strlen($legacyDescriptionTag)));
            }

            return '';
        }

        return $trimmedNote;
    }

    private function mapItemResourceRow(array $row): array
    {
        return [
            'id' => $this->value($row, ['anQId', 'anNo', 'anLineNo'], null),
            'item_qid' => $this->value($row, ['anWOExItemQId'], null),
            'pozicija' => (string) $this->valueTrimmed($row, ['anNo', 'anLineNo', 'anResNo', '__item_no'], ''),
            'materijal' => (string) $this->valueTrimmed($row, ['acResursID', 'acIdent', 'acResIdent', 'acResource', 'acCode', '__item_ident'], ''),
            'naziv' => (string) $this->valueTrimmed($row, ['acResType', 'acDescr', 'acName', 'acResDescr', '__item_descr', '__item_ident'], ''),
            'kolicina' => $this->normalizeNumber($this->valueTrimmed($row, ['anQty', 'anPlanQty', 'anNormQty', '__item_qty', '__item_plan_qty'], 0)),
            'mj' => (string) $this->valueTrimmed($row, ['acUM', 'acUMRes', '__item_um'], ''),
            'napomena' => (string) $this->valueTrimmed($row, ['acNote'], ''),
        ];
    }

    private function mapRegOperationRow(array $row): array
    {
        return [
            'id' => $this->value($row, ['anQId', 'anNo', 'anRegNo'], null),
            'alternativa' => (string) $this->value($row, ['anVariant', 'anVariantSubLvl'], ''),
            'pozicija' => (string) $this->value($row, ['anNo', 'anItemNo', 'anRegNo'], ''),
            'operacija' => (string) $this->value($row, ['acOperation', 'acOper', 'acOperationType', 'acIdent'], ''),
            'naziv' => (string) $this->value($row, ['acName', 'acDescr', 'acOperationName'], ''),
            'napomena' => (string) $this->value($row, ['acNote'], ''),
            'mj' => (string) $this->value($row, ['acUM', 'acUMTime'], ''),
            'mj_vrij' => $this->normalizeNumber($this->value($row, ['anQty', 'anWorkTime', 'anTime', 'anDuration'], 0)),
            'normativna_osnova' => $this->normalizeNumber($this->value($row, ['anNormQty', 'anQtyBase', 'anPlanQty'], 0)),
            'va' => (string) $this->value($row, ['acFieldSA', 'acVA'], ''),
            'prim_klas' => (string) $this->value($row, ['acFieldSB'], ''),
            'sek_klas' => (string) $this->value($row, ['acFieldSC'], ''),
        ];
    }

    private function mapOperationFromItemRow(array $row): array
    {
        $timeUnit = strtoupper((string) $this->valueTrimmed($row, ['acUMTime'], ''));
        $mjVrij = $timeUnit === 'H' ? 'Sat' : (string) $this->valueTrimmed($row, ['acUMTime'], '');
        $normative = $this->normalizeNumber($this->valueTrimmed($row, ['anBatch', 'anQtyBase', 'anQtyBase3'], 0));
        $va = (string) $this->valueTrimmed($row, ['acFieldSE', 'acFieldSA', 'acFieldSB'], '');

        if ($va === '') {
            $va = 'OPR';
        }

        return [
            'id' => $this->value($row, ['anQId', 'anNo'], null),
            'alternativa' => (string) $this->valueTrimmed($row, ['anVariant'], ''),
            'pozicija' => (string) $this->valueTrimmed($row, ['anNo'], ''),
            'operacija' => (string) $this->valueTrimmed($row, ['acIdent'], ''),
            'naziv' => (string) $this->valueTrimmed($row, ['acDescr', 'acName', 'acIdent'], ''),
            'napomena' => (string) $this->valueTrimmed($row, ['acNote'], ''),
            'mj' => (string) $this->valueTrimmed($row, ['acUM'], ''),
            'mj_vrij' => $mjVrij,
            'normativna_osnova' => $normative,
            'va' => $va,
            'prim_klas' => (string) $this->valueTrimmed($row, ['acFieldSB'], ''),
            'sek_klas' => (string) $this->valueTrimmed($row, ['acFieldSC'], ''),
        ];
    }

    private function fetchStatusStats(array $filters = []): array
    {
        $stats = $this->emptyStatusStats();
        $columns = $this->tableColumns();
        $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF']);

        if ($statusColumn === null) {
            return $stats;
        }

        $query = $this->newTableQuery();
        $filtersWithoutStatus = $filters;
        unset($filtersWithoutStatus['status']);
        $this->applyFilters($query, $columns, $filtersWithoutStatus);

        $stats['svi'] = (int) (clone $query)->count();

        $rows = (clone $query)
            ->select($statusColumn, DB::raw('COUNT(*) as total'))
            ->groupBy($statusColumn)
            ->get();

        foreach ($rows as $row) {
            $resolvedStatus = $this->resolveStatus($row->{$statusColumn} ?? null);
            $bucket = $resolvedStatus['bucket'] ?? null;

            if ($bucket !== null && array_key_exists($bucket, $stats)) {
                $stats[$bucket] += (int) $row->total;
            }
        }

        return $stats;
    }

    private function extractFilters(Request $request): array
    {
        $rawSearch = $request->input('search.value');

        if ($rawSearch === null) {
            $rawSearch = $request->input('search');
        }

        return [
            'status' => trim((string) $request->input('status', '')),
            'kupac' => trim((string) $request->input('kupac', '')),
            'primatelj' => trim((string) $request->input('primatelj', '')),
            'proizvod' => trim((string) $request->input('proizvod', '')),
            'plan_pocetak_od' => trim((string) $request->input('plan_pocetak_od', '')),
            'plan_pocetak_do' => trim((string) $request->input('plan_pocetak_do', '')),
            'plan_kraj_od' => trim((string) $request->input('plan_kraj_od', '')),
            'plan_kraj_do' => trim((string) $request->input('plan_kraj_do', '')),
            'datum_od' => trim((string) $request->input('datum_od', '')),
            'datum_do' => trim((string) $request->input('datum_do', '')),
            'vezni_dok' => trim((string) $request->input('vezni_dok', '')),
            'prioritet' => trim((string) $request->input('prioritet', '')),
            'search' => is_string($rawSearch) ? trim($rawSearch) : '',
        ];
    }

    private function extractSort(Request $request): array
    {
        $sortBy = $this->normalizeSortColumnAlias((string) $request->input('sort_by', ''));
        $sortDir = strtolower(trim((string) $request->input('sort_dir', 'desc')));

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        if ($sortBy === '') {
            $orderColumnIndex = $request->input('order.0.column');
            $orderDirection = strtolower(trim((string) $request->input('order.0.dir', '')));

            if (in_array($orderDirection, ['asc', 'desc'], true)) {
                $sortDir = $orderDirection;
            }

            if (is_numeric($orderColumnIndex)) {
                $columnData = (string) $request->input('columns.' . ((int) $orderColumnIndex) . '.data', '');
                $sortBy = $this->normalizeSortColumnAlias($columnData);
            }
        }

        return [
            'by' => $sortBy,
            'dir' => $sortDir,
        ];
    }

    private function applyFilters(Builder $query, array $columns, array $filters): void
    {
        $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF']);

        if ($statusColumn !== null && !empty($filters['status'])) {
            $this->applyStatusFilter($query, $statusColumn, (string) $filters['status']);
        }

        $this->applyLikeAny(
            $query,
            $this->existingColumns($columns, ['acConsignee', 'acReceiver', 'acPartner']),
            (string) ($filters['kupac'] ?? '')
        );

        $this->applyLikeAny(
            $query,
            $this->existingColumns($columns, ['acReceiver', 'acConsignee', 'anClerk', 'acPartner']),
            (string) ($filters['primatelj'] ?? '')
        );

        $this->applyLikeAny(
            $query,
            $this->existingColumns($columns, ['acIdent', 'acNote', 'acStatement', 'acDescr', 'acName', 'acDocType']),
            (string) ($filters['proizvod'] ?? '')
        );

        $this->applyLikeAny(
            $query,
            $this->existingColumns($columns, ['acRefNo1', 'acKey', 'acLnkKey', 'acLnkKeyView']),
            (string) ($filters['vezni_dok'] ?? '')
        );

        $this->applyPriorityFilter(
            $query,
            $this->existingColumns($columns, ['anPriority', 'acPriority', 'acWayOfSale', 'priority']),
            (string) ($filters['prioritet'] ?? '')
        );

        $startDateColumn = $this->firstExistingColumn($columns, ['adDate', 'adDateIns']);
        $endDateColumn = $this->firstExistingColumn($columns, ['adDeliveryDeadline', 'adDateOut']);

        $this->applyDateRangeFilter(
            $query,
            $startDateColumn,
            (string) ($filters['plan_pocetak_od'] ?? ''),
            (string) ($filters['plan_pocetak_do'] ?? '')
        );
        $this->applyDateRangeFilter(
            $query,
            $endDateColumn,
            (string) ($filters['plan_kraj_od'] ?? ''),
            (string) ($filters['plan_kraj_do'] ?? '')
        );
        $this->applyDateRangeFilter(
            $query,
            $startDateColumn,
            (string) ($filters['datum_od'] ?? ''),
            (string) ($filters['datum_do'] ?? '')
        );

        $search = (string) ($filters['search'] ?? '');

        if ($search !== '') {
            $this->applyQuickSearchFilter($query, $columns, $search);
        }
    }

    private function applyStatusFilter(Builder $query, string $column, string $statusFilter): void
    {
        $normalized = strtolower(trim($statusFilter));
        $allowedStatuses = array_filter(array_keys($this->emptyStatusStats()), function ($status) {
            return $status !== 'svi';
        });

        if ($normalized === '' || $normalized === 'svi' || !in_array($normalized, $allowedStatuses, true)) {
            return;
        }

        $statusAliases = $this->statusAliasesByBucket($normalized);

        if (empty($statusAliases)) {
            return;
        }

        $query->where(function (Builder $statusQuery) use ($column, $statusAliases) {
            $statusQuery->whereIn($column, $statusAliases);
        });
    }

    private function applyPriorityFilter(Builder $query, array $columns, string $priorityFilter): void
    {
        $priorityFilter = trim($priorityFilter);

        if ($priorityFilter === '' || empty($columns)) {
            return;
        }

        $priorityCodes = $this->priorityCodesForSearch($priorityFilter);
        $priorityLabels = [];

        foreach ($priorityCodes as $priorityCode) {
            $priorityLabel = trim((string) ($this->deliveryPriorityMap()[$priorityCode] ?? ''));

            if ($priorityLabel !== '') {
                $priorityLabels[] = $priorityLabel;
            }
        }

        $isNumericFilter = ctype_digit($priorityFilter);
        $searchValues = array_values(array_unique(array_filter(
            array_merge(
                (!$isNumericFilter || empty($priorityLabels)) ? [$priorityFilter] : [],
                $priorityLabels
            ),
            function ($value) {
                return trim((string) $value) !== '';
            }
        )));

        $query->where(function (Builder $priorityQuery) use ($columns, $priorityCodes, $searchValues) {
            $hasAnyClause = false;

            foreach ($columns as $column) {
                if ($column === 'anPriority') {
                    if (!empty($priorityCodes)) {
                        if ($hasAnyClause) {
                            $priorityQuery->orWhereIn($column, $priorityCodes);
                        } else {
                            $priorityQuery->whereIn($column, $priorityCodes);
                            $hasAnyClause = true;
                        }
                    }

                    continue;
                }

                foreach ($searchValues as $searchValue) {
                    if ($hasAnyClause) {
                        $priorityQuery->orWhere($column, 'like', '%' . $searchValue . '%');
                        continue;
                    }

                    $priorityQuery->where($column, 'like', '%' . $searchValue . '%');
                    $hasAnyClause = true;
                }
            }

            if (!$hasAnyClause) {
                $priorityQuery->whereRaw('1 = 0');
            }
        });
    }

    private function applyDateRangeFilter(Builder $query, ?string $column, string $from, string $to): void
    {
        if ($column === null) {
            return;
        }

        $fromDate = $this->normalizeDateInput($from);
        $toDate = $this->normalizeDateInput($to);

        if ($fromDate !== null) {
            $query->whereDate($column, '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->whereDate($column, '<=', $toDate);
        }
    }

    private function applyLikeAny(Builder $query, array $columns, string $value): void
    {
        $value = trim($value);

        if ($value === '' || empty($columns)) {
            return;
        }

        $query->where(function (Builder $textQuery) use ($columns, $value) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $textQuery->where($column, 'like', '%' . $value . '%');
                    continue;
                }

                $textQuery->orWhere($column, 'like', '%' . $value . '%');
            }
        });
    }

    private function applyQuickSearchFilter(Builder $query, array $columns, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $searchColumns = $this->existingColumns($columns, [
            'acRefNo1',
            'acKey',
            'acLnkKey',
            'acLnkKeyView',
            'acConsignee',
            'acReceiver',
            'acPartner',
            'acIdent',
            'acNote',
            'acStatement',
            'acDescr',
            'acName',
            'title',
            'acStatusMF',
            'acStatus',
            'acPriority',
            'acWayOfSale',
            'priority',
        ]);
        $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF', 'acStatus']);
        $priorityCodeColumns = $this->existingColumns($columns, ['anPriority']);
        $statusAliases = $this->statusAliasesForSearch($search);
        $priorityCodes = $this->priorityCodesForSearch($search);
        $searchVariants = $this->searchVariants($search);

        $query->where(function (Builder $searchQuery) use (
            $searchColumns,
            $statusColumn,
            $statusAliases,
            $priorityCodeColumns,
            $priorityCodes,
            $searchVariants
        ) {
            $hasAnyClause = false;

            foreach ($searchVariants as $variant) {
                if ($variant === '') {
                    continue;
                }

                foreach ($searchColumns as $column) {
                    if ($hasAnyClause) {
                        $searchQuery->orWhere($column, 'like', '%' . $variant . '%');
                        continue;
                    }

                    $searchQuery->where($column, 'like', '%' . $variant . '%');
                    $hasAnyClause = true;
                }
            }

            if ($statusColumn !== null && !empty($statusAliases)) {
                if ($hasAnyClause) {
                    $searchQuery->orWhereIn($statusColumn, $statusAliases);
                } else {
                    $searchQuery->whereIn($statusColumn, $statusAliases);
                    $hasAnyClause = true;
                }
            }

            if (!empty($priorityCodes)) {
                foreach ($priorityCodeColumns as $priorityCodeColumn) {
                    if ($hasAnyClause) {
                        $searchQuery->orWhereIn($priorityCodeColumn, $priorityCodes);
                        continue;
                    }

                    $searchQuery->whereIn($priorityCodeColumn, $priorityCodes);
                    $hasAnyClause = true;
                }
            }

            if (!$hasAnyClause) {
                $searchQuery->whereRaw('1 = 0');
            }
        });
    }

    private function searchVariants(string $search): array
    {
        $variants = [trim($search)];
        $digits = preg_replace('/\D+/', '', $search);

        if (is_string($digits) && $digits !== '' && !in_array($digits, $variants, true)) {
            $variants[] = $digits;
        }

        if (is_string($digits) && $digits !== '') {
            $formatted = $this->formatWorkOrderNumberForCalendar($digits);

            if ($formatted !== 'N/A' && !in_array($formatted, $variants, true)) {
                $variants[] = $formatted;
            }
        }

        return $variants;
    }

    private function statusAliasesForSearch(string $search): array
    {
        $normalizedSearch = $this->normalizeSearchValue($search);

        if ($normalizedSearch === '') {
            return [];
        }

        $aliases = [];

        foreach ($this->statusCodeMap() as $statusCode => $statusMeta) {
            $normalizedCode = $this->normalizeSearchValue((string) $statusCode);
            $normalizedLabel = $this->normalizeSearchValue((string) ($statusMeta['label'] ?? ''));
            $normalizedBucket = str_replace('_', ' ', $this->normalizeSearchValue((string) ($statusMeta['bucket'] ?? '')));

            if (
                $normalizedSearch === $normalizedCode
                || ($normalizedLabel !== '' && str_contains($normalizedLabel, $normalizedSearch))
                || ($normalizedBucket !== '' && str_contains($normalizedBucket, $normalizedSearch))
            ) {
                $aliases[] = (string) $statusCode;
            }
        }

        return array_values(array_unique($aliases));
    }

    private function priorityCodesForSearch(string $search): array
    {
        $normalizedSearch = $this->normalizeSearchValue($search);

        if ($normalizedSearch === '') {
            return [];
        }

        $isNumericSearch = ctype_digit($normalizedSearch);
        $matchedCodes = [];

        foreach ($this->deliveryPriorityMap() as $priorityCode => $priorityLabel) {
            $code = (int) $priorityCode;
            $normalizedCode = (string) $code;
            $normalizedLabel = $this->normalizeSearchValue((string) $priorityLabel);

            if ($isNumericSearch) {
                if ($normalizedSearch === $normalizedCode) {
                    $matchedCodes[] = $code;
                }

                continue;
            }

            if (
                ($normalizedLabel !== '' && str_contains($normalizedLabel, $normalizedSearch))
                || str_contains($normalizedCode . ' - ' . $normalizedLabel, $normalizedSearch)
            ) {
                $matchedCodes[] = $code;
            }
        }

        return array_values(array_unique($matchedCodes));
    }

    private function resolvePriorityCode(mixed $priority): ?int
    {
        if ($priority === null || trim((string) $priority) === '') {
            return array_key_exists(5, $this->deliveryPriorityMap()) ? 5 : null;
        }

        if (is_int($priority) || is_float($priority) || is_numeric((string) $priority)) {
            return (int) $priority;
        }

        $priorityText = trim((string) $priority);

        if (preg_match('/^\s*(\d+)/', $priorityText, $matches) === 1) {
            return (int) ($matches[1] ?? 0);
        }

        $matchedCodes = $this->priorityCodesForSearch($priorityText);

        if (!empty($matchedCodes)) {
            return (int) ($matchedCodes[0] ?? 0);
        }

        return null;
    }

    private function normalizeSearchValue(string $value): string
    {
        $normalized = Str::ascii(trim($value));
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim((string) $normalized);
    }

    private function calculateStatusStats(array $workOrders): array
    {
        $stats = $this->emptyStatusStats();
        $stats['svi'] = count($workOrders);

        foreach ($workOrders as $workOrder) {
            $bucket = $this->statusBucket((string) ($workOrder['status'] ?? ''));

            if ($bucket !== null && array_key_exists($bucket, $stats)) {
                $stats[$bucket]++;
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

    private function emptyPriorityStats(): array
    {
        $stats = ['svi' => 0];

        foreach ($this->calendarPriorityCodes() as $priorityCode) {
            $stats[(string) $priorityCode] = 0;
        }

        return $stats;
    }

    private function calendarPriorityCodes(): array
    {
        $codes = array_map(function ($priorityCode) {
            return (int) $priorityCode;
        }, array_keys($this->deliveryPriorityMap()));

        $codes = array_values(array_unique(array_filter($codes, function ($priorityCode) {
            return $priorityCode > 0;
        })));
        sort($codes);

        return $codes;
    }

    private function statusCodeMap(): array
    {
        return [
            'F' => ['label' => "Zavr\u{0161}eno", 'bucket' => 'zakljucen'],
            'P' => ['label' => 'U toku', 'bucket' => 'u_radu'],
            'I' => ['label' => "Zavr\u{0161}eno", 'bucket' => 'zakljucen'],
            'N' => ['label' => 'Novo', 'bucket' => 'planiran'],
            'C' => ['label' => 'Otkazano', 'bucket' => null],
            'D' => ['label' => 'Raspisan', 'bucket' => 'raspisan'],
            'O' => ['label' => 'Otvoren', 'bucket' => 'otvoren'],
            'R' => ['label' => "Djelimi\u{010D}no zavr\u{0161}eno", 'bucket' => 'djelimicno_zakljucen'],
            'S' => ['label' => 'Raspisan', 'bucket' => 'raspisan'],
            'E' => ['label' => 'U radu', 'bucket' => 'u_radu'],
            'Z' => ['label' => "Zavr\u{0161}eno", 'bucket' => 'zakljucen'],
        ];
    }

    private function statusBucket(string $status): ?string
    {
        $statusString = trim($status);

        if ($statusString === '' || strtolower($statusString) === 'n/a') {
            return null;
        }

        $statusCode = strtoupper($statusString);
        $statusMeta = $this->statusCodeMap()[$statusCode] ?? null;

        if ($statusMeta !== null) {
            return $statusMeta['bucket'] ?? null;
        }

        $normalized = strtolower($statusString);

        if ($normalized === 'planiran' || $normalized === 'novo') {
            return 'planiran';
        }

        if ($normalized === 'otvoren') {
            return 'otvoren';
        }

        if ($normalized === 'rezerviran') {
            return 'rezerviran';
        }

        if ($normalized === 'raspisan') {
            return 'raspisan';
        }

        if ($normalized === 'u toku' || $normalized === 'u radu') {
            return 'u_radu';
        }

        if ($normalized === "djelimi\u{010D}no zavr\u{0161}eno" || $normalized === 'djelimicno zavrseno') {
            return 'djelimicno_zakljucen';
        }

        if ($normalized === "zavr\u{0161}eno" || $normalized === 'zavrseno') {
            return 'zakljucen';
        }

        return null;
    }

    private function mapStatus(mixed $status): string
    {
        if ($status === null || $status === '') {
            return 'N/A';
        }

        $statusString = trim((string) $status);
        $statusCode = strtoupper($statusString);
        $statusMeta = $this->statusCodeMap()[$statusCode] ?? null;

        if ($statusMeta === null) {
            return $statusString;
        }

        return (string) ($statusMeta['label'] ?? $statusString);
    }

    private function resolveStatus(mixed $status): array
    {
        if ($status === null || $status === '') {
            return [
                'label' => 'N/A',
                'bucket' => null,
            ];
        }

        $statusString = trim((string) $status);
        $statusCode = strtoupper($statusString);
        $statusMeta = $this->statusCodeMap()[$statusCode] ?? null;

        if ($statusMeta !== null) {
            return [
                'label' => (string) ($statusMeta['label'] ?? $statusString),
                'bucket' => $statusMeta['bucket'] ?? null,
            ];
        }

        $label = $this->mapStatus($statusString);

        return [
            'label' => $label,
            'bucket' => $this->statusBucket($label),
        ];
    }

    private function statusAliasesByBucket(string $bucket): array
    {
        return array_values(array_keys(array_filter($this->statusCodeMap(), function ($statusMeta) use ($bucket) {
            return ($statusMeta['bucket'] ?? null) === $bucket;
        })));
    }

    private function mapPriority(mixed $priority): string
    {
        $priorityMap = $this->deliveryPriorityMap();

        if ($priority === null || $priority === '') {
            return $priorityMap[5] ?? '5 - Uobičajeni prioritet';
        }

        if (is_int($priority) || is_float($priority) || is_numeric((string) $priority)) {
            $priorityCode = (int) $priority;
            return $priorityMap[$priorityCode] ?? (string) $priorityCode;
        }

        $priorityString = trim((string) $priority);
        $normalizedPriority = strtoupper($priorityString);

        if (preg_match('/^\d+\s*-\s*/', $priorityString) === 1) {
            return $priorityString;
        }

        $legacyMap = [
            'V' => 1,
            'Z' => 1,
            'VISOK' => 1,
            'HIGH' => 1,
            'S' => 5,
            'M' => 5,
            'SREDNJI' => 5,
            'MEDIUM' => 5,
            'D' => 10,
            'N' => 10,
            'NIZAK' => 10,
            'LOW' => 10,
        ];

        if (array_key_exists($normalizedPriority, $legacyMap)) {
            $priorityCode = $legacyMap[$normalizedPriority];
            return $priorityMap[$priorityCode] ?? (string) $priorityCode;
        }

        return $priorityString;
    }

    private function deliveryPriorityMap(): array
    {
        if ($this->deliveryPriorityMap !== null) {
            return $this->deliveryPriorityMap;
        }

        $fallbackMap = [
            1 => '1 - Visoki prioritet',
            5 => '5 - Uobičajeni prioritet',
            10 => '10 - Niski prioritet',
            15 => '15 - Uzorci',
        ];

        try {
            $rows = DB::table($this->tableSchema() . '.tHE_SetDeliveryPriority')
                ->select(['anPriority', 'acPriority', 'acName', 'abActive'])
                ->where('abActive', 1)
                ->orderBy('anPriority')
                ->get();

            $mapped = $rows
                ->mapWithKeys(function ($row) {
                    $code = (int) ($row->anPriority ?? 0);
                    $label = trim((string) ($row->acPriority ?? ''));

                    if ($label === '') {
                        $name = trim((string) ($row->acName ?? ''));
                        $label = $name !== '' ? ($code . ' - ' . $name) : (string) $code;
                    }

                    return [$code => $label];
                })
                ->all();

            $this->deliveryPriorityMap = !empty($mapped)
                ? array_replace($fallbackMap, $mapped)
                : $fallbackMap;
        } catch (Throwable $exception) {
            $this->deliveryPriorityMap = $fallbackMap;
        }

        return $this->deliveryPriorityMap;
    }

    private function applyRequestedOrdering(Builder $query, array $columns, array $sort): bool
    {
        $sortBy = $this->normalizeSortColumnAlias((string) ($sort['by'] ?? ''));
        $direction = strtolower((string) ($sort['dir'] ?? 'desc'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        if ($sortBy === '') {
            return false;
        }

        if ($sortBy === 'id') {
            return $this->applyWorkOrderIdentifierOrdering($query, $columns, $direction);
        }

        if ($sortBy === 'klijent') {
            return $this->applyOrderByFirstNonEmptyString(
                $query,
                $this->existingColumns($columns, ['acConsignee', 'acReceiver', 'client_name', 'acPartner']),
                $direction
            );
        }

        if ($sortBy === 'status') {
            $statusColumn = $this->firstExistingColumn($columns, ['acStatusMF', 'acStatus']);

            if ($statusColumn === null) {
                return false;
            }

            if ($statusColumn === 'acStatusMF') {
                $wrappedStatusColumn = $query->getGrammar()->wrap($statusColumn);
                $caseParts = [];

                foreach ($this->statusCodeMap() as $statusCode => $statusMeta) {
                    $escapedCode = str_replace("'", "''", strtoupper((string) $statusCode));
                    $escapedLabel = str_replace("'", "''", (string) ($statusMeta['label'] ?? $statusCode));
                    $caseParts[] = "WHEN '" . $escapedCode . "' THEN '" . $escapedLabel . "'";
                }

                $query->orderByRaw(
                    "CASE UPPER(COALESCE($wrappedStatusColumn, '')) " . implode(' ', $caseParts) . " ELSE COALESCE($wrappedStatusColumn, '') END $direction"
                );

                return true;
            }

            $query->orderBy($statusColumn, $direction);
            return true;
        }

        if ($sortBy === 'prioritet') {
            $priorityCodeColumn = $this->firstExistingColumn($columns, ['anPriority']);

            if ($priorityCodeColumn !== null) {
                $query->orderBy($priorityCodeColumn, $direction);
                return true;
            }

            return $this->applyOrderByFirstNonEmptyString(
                $query,
                $this->existingColumns($columns, ['acPriority', 'acWayOfSale', 'priority']),
                $direction
            );
        }

        return false;
    }

    private function applyOrderByFirstNonEmptyString(Builder $query, array $candidateColumns, string $direction): bool
    {
        if (empty($candidateColumns)) {
            return false;
        }

        $direction = strtolower($direction);
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
        $grammar = $query->getGrammar();
        $coalesceParts = array_map(function ($column) use ($grammar) {
            $wrappedColumn = $grammar->wrap((string) $column);
            return "NULLIF(LTRIM(RTRIM($wrappedColumn)), '')";
        }, $candidateColumns);

        $query->orderByRaw('COALESCE(' . implode(', ', $coalesceParts) . ') ' . $direction);

        return true;
    }

    private function applyWorkOrderIdentifierOrdering(Builder $query, array $columns, string $direction): bool
    {
        $identifierColumn = $this->firstExistingColumn($columns, ['acRefNo1', 'acKey', 'anNo', 'id']);

        if ($identifierColumn === null) {
            return false;
        }

        $direction = strtolower($direction);
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
        $wrappedColumn = $query->getGrammar()->wrap($identifierColumn);
        $normalizedIdentifier = "REPLACE(REPLACE(REPLACE(COALESCE(CAST($wrappedColumn AS NVARCHAR(64)), ''), '-', ''), ' ', ''), '/', '')";
        $prefixExpression = "TRY_CAST(CASE WHEN LEN($normalizedIdentifier) >= 2 THEN LEFT($normalizedIdentifier, 2) ELSE $normalizedIdentifier END AS INT)";
        $suffixExpression = "TRY_CAST(CASE WHEN LEN($normalizedIdentifier) >= 6 THEN RIGHT($normalizedIdentifier, 6) ELSE $normalizedIdentifier END AS INT)";

        // Hierarchical WO sorting: first prefix (e.g. 26), then sequence part (e.g. 001091).
        $query->orderByRaw("CASE WHEN $prefixExpression IS NULL THEN 1 ELSE 0 END ASC");
        $query->orderByRaw("$prefixExpression $direction");
        $query->orderByRaw("CASE WHEN $suffixExpression IS NULL THEN 1 ELSE 0 END ASC");
        $query->orderByRaw("$suffixExpression $direction");
        $query->orderByRaw("$normalizedIdentifier $direction");

        return true;
    }

    private function normalizeSortColumnAlias(string $sortBy): string
    {
        $normalized = strtolower(trim($sortBy));

        return match ($normalized) {
            'id', '#', 'broj', 'broj_naloga', 'work_order_number' => 'id',
            'klijent', 'kupac', 'client' => 'klijent',
            'status' => 'status',
            'prioritet', 'priority' => 'prioritet',
            default => '',
        };
    }

    private function applyDefaultOrdering(Builder $query, array $columns): void
    {
        foreach (['adDate', 'adDateIns', 'adTimeIns', 'anNo'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderByDesc($column);
            }
        }
    }

    private function normalizeStatusSelection(string $status): ?string
    {
        return match ($this->normalizeSearchValue($status)) {
            'planiran', 'novo' => 'Planiran',
            'otvoren' => 'Otvoren',
            'rezerviran' => 'Rezerviran',
            'raspisan' => 'Raspisan',
            'u radu', 'u toku' => 'U radu',
            'djelimicno zavrsen', 'djelimicno zavrseno' => 'Djelimično završen',
            'zavrsen', 'zavrseno' => 'Završen',
            default => null,
        };
    }

    private function resolveStatusStorageValue(string $statusSelection, array $row, string $statusColumn): string
    {
        $statusCode = $this->statusCodeFromSelection($statusSelection);
        $currentStatusValue = trim((string) ($row[$statusColumn] ?? ''));
        $currentStatusIsCode = $currentStatusValue !== ''
            && array_key_exists(strtoupper($currentStatusValue), $this->statusCodeMap());

        if ($statusCode !== null && ($statusColumn === 'acStatusMF' || $statusColumn === 'acStatus' || $currentStatusIsCode)) {
            return $statusCode;
        }

        return $statusSelection;
    }

    private function statusCodeFromSelection(string $statusSelection): ?string
    {
        return match ($this->normalizeSearchValue($statusSelection)) {
            'planiran', 'novo' => 'N',
            'otvoren' => 'O',
            'raspisan' => 'D',
            'u radu', 'u toku' => 'E',
            'djelimicno zavrsen', 'djelimicno zavrseno' => 'R',
            'zavrsen', 'zavrseno' => 'Z',
            default => null,
        };
    }

    private function rowAlreadyHasUpdates(array $row, array $updates): bool
    {
        foreach ($updates as $column => $value) {
            if (!array_key_exists($column, $row)) {
                return false;
            }

            if (!$this->updateValuesMatch($row[$column], $value)) {
                return false;
            }
        }

        return true;
    }

    private function updateValuesMatch(mixed $currentValue, mixed $newValue): bool
    {
        if ($currentValue === null && $newValue === null) {
            return true;
        }

        if (
            (is_int($currentValue) || is_float($currentValue) || is_numeric((string) $currentValue))
            && (is_int($newValue) || is_float($newValue) || is_numeric((string) $newValue))
        ) {
            return (float) $currentValue === (float) $newValue;
        }

        return trim((string) $currentValue) === trim((string) $newValue);
    }

    private function updateWorkOrderRow(array $row, array $updates): bool
    {
        if (empty($updates)) {
            return false;
        }

        $query = $this->newTableQuery();
        $hasIdentity = false;

        foreach (['acRefNo1', 'acKey', 'anNo', 'id'] as $identityColumn) {
            if (!array_key_exists($identityColumn, $row)) {
                continue;
            }

            $identityValue = $row[$identityColumn];

            if ($identityValue === null || (is_string($identityValue) && trim($identityValue) === '')) {
                continue;
            }

            $query->where($identityColumn, $identityValue);
            $hasIdentity = true;
        }

        if (!$hasIdentity) {
            return false;
        }

        return $query->update($updates) > 0;
    }

    private function tableColumns(): array
    {
        return DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->tableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();
    }

    private function itemTableColumns(): array
    {
        return DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->itemTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();
    }

    private function orderTableColumns(): array
    {
        if ($this->orderTableColumnsCache !== null) {
            return $this->orderTableColumnsCache;
        }

        $this->orderTableColumnsCache = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->orderTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();

        return $this->orderTableColumnsCache;
    }

    private function itemTableIdentityColumns(): array
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return [];
        }

        try {
            return DB::table('sys.columns as c')
                ->join('sys.tables as t', 'c.object_id', '=', 't.object_id')
                ->join('sys.schemas as s', 't.schema_id', '=', 's.schema_id')
                ->where('s.name', $this->tableSchema())
                ->where('t.name', $this->itemTableName())
                ->where('c.is_identity', 1)
                ->pluck('c.name')
                ->map(function ($columnName) {
                    return (string) $columnName;
                })
                ->values()
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve item table identity columns.', [
                'connection' => config('database.default'),
                'items_table' => $this->qualifiedItemTableName(),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function itemResourcesTableColumns(): array
    {
        return DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->itemResourcesTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();
    }

    private function regOperationsTableColumns(): array
    {
        return DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->regOperationsTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();
    }

    private function existingColumns(array $columns, array $candidates): array
    {
        return array_values(array_filter($candidates, function ($candidate) use ($columns) {
            return in_array($candidate, $columns, true);
        }));
    }

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
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

    private function valueTrimmed(array $row, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);

                if ($value === '') {
                    continue;
                }

                return $value;
            }

            return $value;
        }

        return $default;
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = trim(str_replace(',', '.', $value));

            if ($normalized === '' || !is_numeric($normalized)) {
                return null;
            }

            return (float) $normalized;
        }

        if (is_numeric((string) $value)) {
            return (float) $value;
        }

        return null;
    }

    private function formatMetaNumber(?float $value, int $precision = 3): string
    {
        if ($value === null) {
            return '-';
        }

        $formatted = number_format($value, $precision, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        if ($formatted === '-0') {
            return '0';
        }

        return $formatted;
    }

    private function formatMetaDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        try {
            $dateTime = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);

            if ($dateTime->format('H:i:s') === '00:00:00') {
                return $dateTime->format('d.m.Y');
            }

            return $dateTime->format('d.m.Y H:i');
        } catch (Throwable $exception) {
            return '-';
        }
    }

    private function formatMetaFlag(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $normalized = strtoupper(trim((string) $value));

        if (in_array($normalized, ['Y', 'D', '1', 'TRUE', 'T'], true)) {
            return 'Da';
        }

        if (in_array($normalized, ['N', '0', 'FALSE', 'F'], true)) {
            return 'Ne';
        }

        return (string) $value;
    }

    private function flagTone(mixed $value): string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));

        if (in_array($normalized, ['Y', 'D', '1', 'TRUE', 'T'], true)) {
            return 'success';
        }

        if (in_array($normalized, ['N', '0', 'FALSE', 'F'], true)) {
            return 'secondary';
        }

        return 'info';
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

    private function normalizeDateInput(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable $exception) {
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

    private function normalizeNullableNumber(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeNumber($value);
    }

    private function moneyValueCandidates(): array
    {
        return [
            'anValue',
            'anDocValue',
            'total',
            'anAmount',
            'anTotal',
            'anNetValue',
            'anGrossValue',
            'anPrcValue',
            'anPrice',
        ];
    }

    private function monetaryColumns(array $columns): array
    {
        return $this->existingColumns($columns, $this->moneyValueCandidates());
    }

    private function hasMonetaryValues(Builder $query, array $moneyColumns): bool
    {
        if (empty($moneyColumns)) {
            return false;
        }

        $valueQuery = clone $query;
        $valueQuery->where(function (Builder $amountQuery) use ($moneyColumns) {
            foreach ($moneyColumns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';

                $amountQuery->{$method}(function (Builder $singleColumnQuery) use ($column) {
                    $singleColumnQuery
                        ->whereNotNull($column)
                        ->where($column, '<>', 0);
                });
            }
        });

        return $valueQuery->exists();
    }

    private function newTableQuery(): Builder
    {
        return DB::table($this->qualifiedTableName());
    }

    private function newItemTableQuery(): Builder
    {
        return DB::table($this->qualifiedItemTableName());
    }

    private function newItemResourcesTableQuery(): Builder
    {
        return DB::table($this->qualifiedItemResourcesTableName());
    }

    private function newRegOperationsTableQuery(): Builder
    {
        return DB::table($this->qualifiedRegOperationsTableName());
    }

    private function newProductStructureTableQuery(): Builder
    {
        return DB::table($this->qualifiedProductStructureTableName());
    }

    private function newOrderTableQuery(): Builder
    {
        return DB::table($this->qualifiedOrderTableName());
    }

    private function resolveLimit(?int $requestedLimit = null): int
    {
        $maxLimit = max(1, (int) config('workorders.max_limit', 100));
        $defaultLimit = max(1, (int) config('workorders.default_limit', 10));
        $limit = $requestedLimit ?? $defaultLimit;

        if ($limit < 1) {
            return $defaultLimit;
        }

        return min($limit, $maxLimit);
    }

    private function tableSchema(): string
    {
        return (string) config('workorders.schema', 'dbo');
    }

    private function tableName(): string
    {
        return (string) config('workorders.table', 'tHF_WOEx');
    }

    private function itemTableName(): string
    {
        return (string) config('workorders.items_table', 'tHF_WOExItem');
    }

    private function itemResourcesTableName(): string
    {
        return (string) config('workorders.item_resources_table', 'tHF_WOExItemResources');
    }

    private function regOperationsTableName(): string
    {
        return (string) config('workorders.reg_operations_table', 'tHF_WOExRegOper');
    }

    private function productStructureTableName(): string
    {
        return (string) config('workorders.product_structure_table', 'tHF_SetPrSt');
    }

    private function orderTableName(): string
    {
        return (string) config('workorders.orders_table', 'tHE_Order');
    }

    private function qualifiedTableName(): string
    {
        return $this->tableSchema() . '.' . $this->tableName();
    }

    private function qualifiedItemTableName(): string
    {
        return $this->tableSchema() . '.' . $this->itemTableName();
    }

    private function qualifiedItemResourcesTableName(): string
    {
        return $this->tableSchema() . '.' . $this->itemResourcesTableName();
    }

    private function qualifiedRegOperationsTableName(): string
    {
        return $this->tableSchema() . '.' . $this->regOperationsTableName();
    }

    private function qualifiedProductStructureTableName(): string
    {
        return $this->tableSchema() . '.' . $this->productStructureTableName();
    }

    private function qualifiedOrderTableName(): string
    {
        return $this->tableSchema() . '.' . $this->orderTableName();
    }

    private function emptyInvoicePreviewResponse(array $pageConfigs, ?string $invoiceNumber = null)
    {
        return view('/content/apps/invoice/app-invoice-preview', [
            'pageConfigs' => $pageConfigs,
            'workOrder' => [],
            'workOrderItems' => [],
            'workOrderItemResources' => [],
            'workOrderRegOperations' => [],
            'workOrderMeta' => [],
            'sender' => [
                'name' => '',
                'address' => '',
                'phone' => '',
                'email' => '',
            ],
            'recipient' => [
                'name' => '',
                'address' => '',
                'phone' => '',
                'email' => '',
            ],
            'invoiceNumber' => (string) ($invoiceNumber ?? ''),
            'issueDate' => '',
            'plannedStartDate' => '',
            'dueDate' => '',
        ]);
    }
}
