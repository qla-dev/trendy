<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Product;
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
    private const DEFAULT_SCAN_CREATE_DOC_TYPE = '6000';
    private const SCAN_CREATE_DOC_TYPES = ['6000', '6001'];
    private const DEFAULT_SCAN_CREATE_SEQUENCE_LENGTH = 7;
    private const MATERIALS_SETS = [
        '011',
        '020',
        '021',
        '022',
        '023',
        '024',
        '025',
        '026',
        '101',
        '102',
        '103',
        '104',
        '10A',
        '111',
        '120',
        '134',
        '13S',
        '13X',
        'H1',
    ];
    private const OPERATIONS_SET = 'OPR';

    private ?array $deliveryPriorityMap = null;
    private ?array $orderTableColumnsCache = null;
    private ?array $orderItemTableColumnsCache = null;
    private ?array $workOrderOrderItemLinkTableColumnsCache = null;
    private ?array $workOrderOrderItemLinkInsertTableColumnsCache = null;
    private ?array $tableNonInsertableColumnsCache = null;
    private ?array $tableStringLengthMapCache = null;
    private ?string $workOrderOrderItemLinkTableCache = null;
    private ?string $workOrderOrderItemLinkInsertTableCache = null;
    private array $linkedOrderCache = [];

    public function invoiceList()
    {
        $pageConfigs = ['pageHeader' => false];
        $canDeleteWorkOrders = $this->canDeleteWorkOrders(auth()->user());
        $destroyWorkOrderUrlTemplate = route('app-invoice-destroy', ['id' => '__WORK_ORDER__']);

        try {
            return view('/content/apps/invoice/app-invoice-list', [
                'pageConfigs' => $pageConfigs,
                'radniNalozi' => [],
                'statusStats' => $this->fetchStatusStats(),
                'canDeleteWorkOrders' => $canDeleteWorkOrders,
                'destroyWorkOrderUrlTemplate' => $destroyWorkOrderUrlTemplate,
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
                'canDeleteWorkOrders' => $canDeleteWorkOrders,
                'destroyWorkOrderUrlTemplate' => $destroyWorkOrderUrlTemplate,
                'error' => 'Greška pri učitavanju radnih naloga iz baze.',
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
                    'text' => 'Radni nalog je uspješno učitan iz QR koda',
                ];
            } elseif (in_array($normalizedSource, ['invoice_list', 'upravljanje_nalozima', 'lista_naloga'], true)) {
                $successNotice = [
                    'icon' => 'success',
                    'title' => 'Nalog učitan',
                    'text' => 'Radni nalog je učitan kroz administraciju',
                ];
            } elseif (in_array($normalizedSource, ['dashboard_home', 'home_table', 'kontrolna_ploca'], true)) {
                $successNotice = [
                    'icon' => 'success',
                    'title' => 'Nalog učitan',
                    'text' => 'Radni nalog je učitan kroz administraciju',
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
                'invoiceNumber' => $this->formatWorkOrderNumberForCalendar((string) ($workOrder['broj_naloga'] ?? '')),
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
                    ->with('error', 'Radni nalog nije pronađen.');
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
                ->with('error', 'Greška pri učitavanju print prikaza radnog naloga.');
        }
    }

    public function scanLookup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravan QR sadrzaj.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $identifier = trim((string) $validator->validated()['identifier']);

        try {
            $workOrder = $this->findMappedWorkOrder($identifier, true);

            if ($workOrder !== null) {
                $routeId = trim((string) ($workOrder['id'] ?? $workOrder['broj_naloga'] ?? $identifier));
                $workOrderNumber = $this->formatWorkOrderNumberForCalendar((string) ($workOrder['broj_naloga'] ?? $routeId));

                return response()->json([
                    'data' => [
                        'status' => 'existing',
                        'message' => 'Da li želite otvoriti RN broj ' . $workOrderNumber . '?',
                        'work_order' => [
                            'id' => $routeId,
                            'number' => $workOrderNumber,
                            'broj_narudzbe' => (string) ($workOrder['broj_narudzbe'] ?? ''),
                            'poz' => (string) ($workOrder['broj_pozicije_narudzbe'] ?? ''),
                            'sifra' => (string) ($workOrder['sifra'] ?? ''),
                            'naziv' => (string) ($workOrder['naziv'] ?? ''),
                            'preview_url' => route('app-invoice-preview', [
                                'id' => $routeId,
                                'scan' => 1,
                            ]),
                        ],
                    ],
                ]);
            }

            $orderLocator = $this->parseOrderLocatorIdentifier($identifier);

            if ($orderLocator === null) {
                return response()->json([
                    'message' => 'Radni nalog nije pronađen za skenirani QR kod.',
                ], 404);
            }

            $orderRow = $this->findOrderRowByLocator(
                (string) ($orderLocator['order_number'] ?? ''),
                (int) ($orderLocator['order_position'] ?? 0),
                array_key_exists('product_code', $orderLocator)
                    ? (string) ($orderLocator['product_code'] ?? '')
                    : null
            );

            if ($orderRow === null) {
                return response()->json([
                    'message' => 'Ne postoji ni radni nalog ni narudžba za skenirane parametre.',
                ], 404);
            }

            $orderContext = $this->buildOrderLocatorContext($orderRow, $orderLocator);
            $docTypeOptions = $this->buildScanCreateDocTypeOptions(Carbon::now());
            $defaultDocType = $this->resolveRequestedScanCreateDocType(self::DEFAULT_SCAN_CREATE_DOC_TYPE);
            $defaultDocTypeOption = $docTypeOptions[$defaultDocType] ?? reset($docTypeOptions) ?: [];
            $defaultLastWorkOrder = is_array($defaultDocTypeOption['last_work_order'] ?? null)
                ? $defaultDocTypeOption['last_work_order']
                : [];
            $defaultNextWorkOrder = is_array($defaultDocTypeOption['next_work_order'] ?? null)
                ? $defaultDocTypeOption['next_work_order']
                : [];
            $numberContext = [
                'last_display' => (string) ($defaultLastWorkOrder['number'] ?? ''),
                'last_raw' => (string) ($defaultLastWorkOrder['number_raw'] ?? ''),
                'next_display' => (string) ($defaultNextWorkOrder['number'] ?? ''),
                'next_raw' => (string) ($defaultNextWorkOrder['number_raw'] ?? ''),
            ];

            return response()->json([
                'data' => [
                    'status' => 'create_available',
                    'doc_type' => $defaultDocType,
                    'doc_type_options' => $docTypeOptions,
                    'message' => 'Da li želite kreirati RN broj ' . $numberContext['next_display'] . '?',
                    'order' => [
                        'narudzba_kljuc' => (string) ($orderContext['order_key'] ?? ''),
                        'broj_narudzbe' => (string) ($orderContext['order_number'] ?? ''),
                        'poz' => (string) ($orderContext['order_position'] ?? ''),
                        'sifra' => (string) ($orderContext['product_code'] ?? ''),
                        'naziv' => (string) ($orderContext['product_name'] ?? ''),
                        'kupac' => (string) ($orderContext['client_name'] ?? ''),
                        'kolicina' => $this->normalizeNullableNumber($orderContext['quantity'] ?? null),
                        'mj' => (string) ($orderContext['unit'] ?? ''),
                        'datum_isporuke' => (string) ($orderContext['delivery_date'] ?? ''),
                        'datum_isporuke_display' => (string) ($orderContext['delivery_date_display'] ?? ''),
                        'datum_izrade_rn' => (string) ($orderContext['projected_issue_date'] ?? ''),
                        'datum_izrade_rn_display' => (string) ($orderContext['projected_issue_date_display'] ?? ''),
                        'catalog_item_missing' => (bool) ($orderContext['catalog_item_missing'] ?? false),
                        'catalog_item_notice' => (string) ($orderContext['catalog_item_notice'] ?? ''),
                    ],
                    'last_work_order' => [
                        'number' => (string) ($numberContext['last_display'] ?? ''),
                        'number_raw' => (string) ($numberContext['last_raw'] ?? ''),
                    ],
                    'next_work_order' => [
                        'number' => (string) ($numberContext['next_display'] ?? ''),
                        'number_raw' => (string) ($numberContext['next_raw'] ?? ''),
                    ],
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order scan lookup failed.', [
                'identifier' => $identifier,
                'connection' => config('database.default'),
                'work_orders_table' => $this->qualifiedTableName(),
                'orders_table' => $this->qualifiedOrderTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greška pri provjeri skeniranog QR koda.',
            ], 500);
        }
    }

    public function createFromScan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string', 'max:255'],
            'doc_type' => ['nullable', 'string', 'max:4'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravan QR sadrzaj.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $identifier = trim((string) $validator->validated()['identifier']);
        $requestedDocType = trim((string) ($validator->validated()['doc_type'] ?? ''));
        $requestedQuantity = $this->toFloatOrNull($validator->validated()['quantity'] ?? null);

        if ($requestedDocType !== '' && !in_array($requestedDocType, self::SCAN_CREATE_DOC_TYPES, true)) {
            return response()->json([
                'message' => 'Neispravna vrsta dokumenta za kreiranje RN.',
            ], 422);
        }

        $selectedDocType = $this->resolveRequestedScanCreateDocType($requestedDocType);
        $orderLocator = $this->parseOrderLocatorIdentifier($identifier);
        $debugContext = [
            'identifier' => $identifier,
            'doc_type' => $selectedDocType,
            'requested_quantity' => $requestedQuantity,
            'order_locator' => $orderLocator,
        ];

        if ($orderLocator === null) {
            return response()->json([
                'message' => 'Kreiranje RN je moguće samo za QR sa narudžbom, pozicijom i šifrom.',
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $orderLocator, $identifier, $selectedDocType, $requestedQuantity, &$debugContext) {
                $existingRow = $this->findWorkOrderRowByOrderLocator(
                    (string) ($orderLocator['order_number'] ?? ''),
                    (int) ($orderLocator['order_position'] ?? 0),
                    array_key_exists('product_code', $orderLocator)
                        ? (string) ($orderLocator['product_code'] ?? '')
                        : null
                );

                if ($existingRow !== null) {
                    $debugContext['existing_row_identifier'] = [
                        'acRefNo1' => $existingRow['acRefNo1'] ?? null,
                        'acKey' => $existingRow['acKey'] ?? null,
                        'anNo' => $existingRow['anNo'] ?? null,
                    ];

                    return [
                        'status' => 'existing',
                        'row' => $existingRow,
                    ];
                }

                $orderRow = $this->findOrderRowByLocator(
                    (string) ($orderLocator['order_number'] ?? ''),
                    (int) ($orderLocator['order_position'] ?? 0),
                    array_key_exists('product_code', $orderLocator)
                        ? (string) ($orderLocator['product_code'] ?? '')
                        : null
                );

                if ($orderRow === null) {
                    throw new \RuntimeException('Narudžba za skenirane parametre nije pronađena.');
                }

                $orderContext = $this->buildOrderLocatorContext($orderRow, $orderLocator);
                $numberContext = $this->generateNextWorkOrderNumber(
                    Carbon::now(),
                    $selectedDocType
                );
                $catalogEnsureResult = $this->ensureCatalogItemForScanCreate(
                    $orderContext,
                    $orderRow,
                    $request->user()
                );
                $orderContext = is_array($catalogEnsureResult['order_context'] ?? null)
                    ? $catalogEnsureResult['order_context']
                    : $orderContext;
                $debugContext['resolved_order_context'] = $orderContext;
                $debugContext['resolved_order_row'] = $orderRow;
                $debugContext['number_context'] = $numberContext;
                $debugContext['catalog_item_created'] = (bool) ($catalogEnsureResult['created'] ?? false);
                $payload = $this->buildWorkOrderInsertPayloadFromOrder(
                    $orderRow,
                    $orderContext,
                    $numberContext,
                    $request->user(),
                    $requestedQuantity
                );
                $debugContext['insert_payload'] = $payload;

                if (empty($payload)) {
                    throw new \RuntimeException('Nije pripremljen payload za kreiranje RN.');
                }

                Log::info('Prepared work order insert payload from scan.', [
                    'identifier' => $identifier,
                    'order_locator' => $orderLocator,
                    'resolved_order_context' => $orderContext,
                    'number_context' => $numberContext,
                    'insert_payload' => $payload,
                ]);

                $this->newTableQuery()->insert($payload);

                // Create link between WorkOrder and OrderItem
                $linkPayload = $this->buildWorkOrderOrderItemLinkPayload(
                    (string) ($payload['acKey'] ?? ''),
                    $orderContext,
                    $request->user()
                );
                if (!empty($linkPayload)) {
                    $this->newWorkOrderOrderItemLinkInsertTableQuery()->insert($linkPayload);
                }

                $lookupIdentifier = (string) ($payload['acRefNo1'] ?? ($payload['acKey'] ?? $numberContext['next_display']));
                $debugContext['lookup_identifier_after_insert'] = $lookupIdentifier;
                $createdRow = $this->findWorkOrderRow($lookupIdentifier);

                if ($createdRow === null) {
                    $createdRow = $this->findWorkOrderRowByOrderLocator(
                        (string) ($orderLocator['order_number'] ?? ''),
                        (int) ($orderLocator['order_position'] ?? 0),
                        array_key_exists('product_code', $orderLocator)
                            ? (string) ($orderLocator['product_code'] ?? '')
                            : null
                    );
                }

                if ($createdRow === null) {
                    throw new \RuntimeException('RN je kreiran, ali ga nije moguće ponovo učitati.');
                }

                return [
                    'status' => 'created',
                    'row' => $createdRow,
                ];
            }, 3);

            $mapped = $this->mapRow((array) $result['row'], false, [], true);
            $routeId = trim((string) ($mapped['id'] ?? $mapped['broj_naloga'] ?? ''));
            $workOrderNumber = $this->formatWorkOrderNumberForCalendar((string) ($mapped['broj_naloga'] ?? $routeId));
            $created = (string) ($result['status'] ?? '') === 'created';

            if ($created) {
                $this->attachSastavnicaToWorkOrder(
                    $routeId,
                    trim((string) ($mapped['sifra'] ?? $mapped['acIdent'] ?? $mapped['product_code'] ?? '')),
                    true,
                    $this->toFloatOrNull($mapped['kolicina'] ?? null)
                );
            }

            return response()->json([
                'message' => $created
                    ? 'Radni nalog je uspješno kreiran.'
                    : 'Radni nalog već postoji i bit će otvoren.',
                'data' => [
                    'status' => $created ? 'created' : 'existing',
                    'created' => $created,
                    'catalog_item_created' => (bool) ($debugContext['catalog_item_created'] ?? false),
                    'work_order' => [
                        'id' => $routeId,
                        'number' => $workOrderNumber,
                        'broj_narudzbe' => (string) ($mapped['broj_narudzbe'] ?? ''),
                        'poz' => (string) ($mapped['broj_pozicije_narudzbe'] ?? ''),
                        'sifra' => (string) ($mapped['sifra'] ?? ''),
                        'naziv' => (string) ($mapped['naziv'] ?? ''),
                        'preview_url' => route('app-invoice-preview', [
                            'id' => $routeId,
                            'scan' => 1,
                        ]),
                    ],
                ],
            ]);
        } catch (Throwable $exception) {
            $exceptionMessages = [];
            $currentException = $exception;

            while ($currentException !== null && count($exceptionMessages) < 5) {
                $exceptionMessages[] = $currentException->getMessage();
                $currentException = $currentException->getPrevious();
            }

            Log::error('Work order create from scan failed.', [
                'identifier' => $identifier,
                'connection' => config('database.default'),
                'work_orders_table' => $this->qualifiedTableName(),
                'orders_table' => $this->qualifiedOrderTableName(),
                'exception_class' => get_class($exception),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
                'message' => $exception->getMessage(),
                'exception_messages' => $exceptionMessages,
                'debug_context' => $debugContext,
            ]);

            $normalizedExceptionMessage = strtolower(Str::ascii($exception->getMessage()));
            $statusCode = str_contains($normalizedExceptionMessage, 'narudzba') ? 404 : 500;
            $response = [
                'message' => $statusCode === 404
                    ? 'Narudžba za skenirane parametre nije pronađena.'
                    : 'Greška pri kreiranju radnog naloga iz narudžbe.',
            ];

            if (config('app.debug')) {
                $response['debug'] = [
                    'messages' => $exceptionMessages,
                    'context' => $debugContext,
                ];
            }

            return response()->json($response, $statusCode);
        }
    }

    private function attachSastavnicaToWorkOrder(
        string $workOrderId,
        string $productCode,
        bool $suppressStatusTransition = false,
        ?float $quantityFactor = null
    ): void
    {
        $workOrderId = trim($workOrderId);
        $productCode = trim($productCode);

        if ($workOrderId === '' || $productCode === '') {
            return;
        }

        try {
            $bomRows = $this->fetchBomComponentsByProduct($productCode, $this->bomLimit());

            if (empty($bomRows)) {
                return;
            }

            $resolvedQuantityFactor = $quantityFactor !== null && $quantityFactor > 0
                ? $quantityFactor
                : 1.0;

            if ($resolvedQuantityFactor > 0) {
                $this->syncWorkOrderHeaderQuantityFromSastavnica($workOrderId, $resolvedQuantityFactor);
            }

            $components = array_values(array_filter(array_map(function ($bomRow) {
                $componentId = trim((string) ($bomRow['acIdentChild'] ?? ''));

                if ($componentId === '') {
                    return null;
                }

                return [
                    'acIdentChild' => $componentId,
                    'anNo' => (int) ($bomRow['anNo'] ?? 0),
                    'acDescr' => trim((string) ($bomRow['acDescr'] ?? '')),
                    'napomena' => trim((string) ($bomRow['napomena'] ?? '')),
                    'acUM' => trim((string) ($bomRow['acUM'] ?? '')),
                    'acOperationType' => trim((string) ($bomRow['acOperationType'] ?? '')),
                ];
            }, $bomRows)));

            if (empty($components)) {
                return;
            }

            $request = Request::create('', 'POST', [
                'product_id' => $productCode,
                'quantity' => $resolvedQuantityFactor,
                'quantity_unit' => 'AUTO',
                'description' => 'Automatski preuzeta sastavnica',
                'save_mode' => 'manual',
                'components' => $components,
            ]);
            $request->attributes->set('suppress_status_transition', $suppressStatusTransition);

            $this->storePlannedConsumption($request, $workOrderId);
        } catch (Throwable $exception) {
            Log::warning('Nije uspjelo formiranje sastavnice za RN.', [
                'work_order_id' => $workOrderId,
                'product_code' => $productCode,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function sumSastavnicaHeaderQuantity(array $bomRows): ?float
    {
        $totalQty = 0.0;
        $hasQty = false;

        foreach ($bomRows as $bomRow) {
            if (!is_array($bomRow)) {
                continue;
            }

            $componentId = trim((string) ($bomRow['acIdentChild'] ?? ''));
            if ($componentId === '') {
                continue;
            }

            $itemKind = $this->resolveItemKindForPreview(
                (string) ($bomRow['acOperationType'] ?? ''),
                ''
            );

            if ($itemKind === 'operations') {
                continue;
            }

            $componentQty = (float) ($this->toFloatOrNull($bomRow['anGrossQty'] ?? null) ?? 0.0);
            if ($componentQty <= 0) {
                continue;
            }

            $totalQty += $componentQty;
            $hasQty = true;
        }

        return $hasQty ? $totalQty : null;
    }

    private function syncWorkOrderHeaderQuantityFromSastavnica(string $workOrderId, float $plannedQty): void
    {
        $workOrderRow = $this->findWorkOrderRow($workOrderId);
        if ($workOrderRow === null) {
            return;
        }

        $columns = $this->tableColumns();
        $updates = [];

        foreach (['anPlanQty', 'anQty', 'anQty1'] as $quantityColumn) {
            if (in_array($quantityColumn, $columns, true)) {
                $updates[$quantityColumn] = $plannedQty;
            }
        }

        if (empty($updates) || $this->rowAlreadyHasUpdates($workOrderRow, $updates)) {
            return;
        }

        $this->updateWorkOrderRow($workOrderRow, $updates);
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

            // Do not override planned start / entry time when updating status
            unset($updates['adSchedStartTime'], $updates['adTimeIns']);

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

    public function destroyInvoice(Request $request, string $id): JsonResponse
    {
        if (!$this->canDeleteWorkOrders($request->user())) {
            return response()->json([
                'message' => 'Nemate dozvolu za brisanje radnog naloga.',
            ], 403);
        }

        try {
            $workOrderRow = $this->findWorkOrderRow($id);

            if ($workOrderRow === null) {
                return response()->json([
                    'message' => 'Radni nalog nije pronadjen.',
                ], 404);
            }

            $workOrderKey = trim((string) $this->valueTrimmed($workOrderRow, ['acKey'], ''));

            if ($workOrderKey === '') {
                return response()->json([
                    'message' => 'RN kljuc nije pronadjen.',
                ], 422);
            }

            $deletedCounts = DB::transaction(function () use ($workOrderRow, $workOrderKey) {
                $deleted = [
                    'item_resources' => 0,
                    'reg_operations' => 0,
                    'items' => 0,
                    'work_orders' => 0,
                ];

                $itemColumns = $this->itemTableColumns();
                $itemRows = [];
                $itemQIds = [];

                if (in_array('acKey', $itemColumns, true)) {
                    $itemRows = $this->newItemTableQuery()
                        ->where('acKey', $workOrderKey)
                        ->get()
                        ->map(function ($row) {
                            return (array) $row;
                        })
                        ->values()
                        ->all();

                    $itemQIds = array_values(array_filter(array_map(function (array $row) {
                        $itemQId = $row['anQId'] ?? null;

                        return is_numeric((string) $itemQId) ? (int) $itemQId : null;
                    }, $itemRows), function ($itemQId) {
                        return $itemQId !== null;
                    }));
                }

                $itemResourcesColumns = $this->itemResourcesTableColumns();
                $itemResourceQIdColumn = $this->firstExistingColumn($itemResourcesColumns, ['anWOExItemQId', 'anItemQId', 'anQIdItem']);
                $itemResourceLinkColumn = $this->firstExistingColumn($itemResourcesColumns, ['acKey', 'acWOKey', 'acDocKey', 'acLnkKey']);

                if ($itemResourceQIdColumn !== null && !empty($itemQIds)) {
                    $deleted['item_resources'] = $this->newItemResourcesTableQuery()
                        ->whereIn($itemResourceQIdColumn, $itemQIds)
                        ->delete();
                } elseif ($itemResourceLinkColumn !== null) {
                    $deleted['item_resources'] = $this->newItemResourcesTableQuery()
                        ->where($itemResourceLinkColumn, $workOrderKey)
                        ->delete();
                }

                $regOperationsColumns = $this->regOperationsTableColumns();
                $regOperationsLinkColumn = $this->firstExistingColumn($regOperationsColumns, ['acKey', 'acWOKey', 'acDocKey', 'acLnkKey']);

                if ($regOperationsLinkColumn !== null) {
                    $deleted['reg_operations'] = $this->newRegOperationsTableQuery()
                        ->where($regOperationsLinkColumn, $workOrderKey)
                        ->delete();
                }

                if (in_array('acKey', $itemColumns, true)) {
                    $deleted['items'] = $this->newItemTableQuery()
                        ->where('acKey', $workOrderKey)
                        ->delete();
                }

                $workOrderDeleteColumn = $this->firstExistingColumn($this->tableColumns(), ['acKey', 'acRefNo1']);

                if ($workOrderDeleteColumn === null) {
                    throw new \RuntimeException('Kolona za brisanje RN nije pronadjena.');
                }

                $workOrderDeleteValue = trim((string) ($workOrderRow[$workOrderDeleteColumn] ?? $workOrderKey));
                $deleted['work_orders'] = $this->newTableQuery()
                    ->where($workOrderDeleteColumn, $workOrderDeleteValue)
                    ->delete();

                if ($deleted['work_orders'] < 1) {
                    throw new \RuntimeException('Radni nalog nije obrisan.');
                }

                return $deleted;
            }, 3);

            Log::info('Work order deleted from invoice list.', [
                'id' => $id,
                'work_order_key' => $workOrderKey,
                'deleted_counts' => $deletedCounts,
                'user_id' => (int) ($request->user()->id ?? 0),
                'username' => (string) ($request->user()->username ?? ''),
            ]);

            return response()->json([
                'message' => 'Radni nalog je obrisan.',
                'data' => [
                    'id' => $id,
                    'work_order_key' => $workOrderKey,
                    'deleted_counts' => $deletedCounts,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order delete failed.', [
                'id' => $id,
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'items_table' => $this->qualifiedItemTableName(),
                'item_resources_table' => $this->qualifiedItemResourcesTableName(),
                'reg_operations_table' => $this->qualifiedRegOperationsTableName(),
                'message' => $exception->getMessage(),
                'exception_class' => get_class($exception),
                'exception_code' => (string) $exception->getCode(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            ]);

            return response()->json([
                'message' => 'Greska pri brisanju radnog naloga.',
            ], 500);
        }
    }

    protected function ordersLinkageIndex(Request $request)
    {
        if (!$this->canAccessOrderLinkage($request->user())) {
            return redirect()->route('misc-not-authorized');
        }

        $pageConfigs = ['pageHeader' => false];

        return view('/content/apps/orders/app-orders', [
            'pageConfigs' => $pageConfigs,
            'ordersLinkageDataUrl' => route('app-orders-data'),
            'ordersLinkagePositionsUrl' => route('app-orders-positions'),
            'ordersLinkageWorkOrdersUrl' => route('app-orders-work-orders'),
            'ordersLinkageWorkOrdersApiUrl' => route('app-orders-radni-nalozi'),
            'ordersLinkageDeleteUrl' => route('app-orders-destroy'),
            'canDeleteLinkedOrders' => $this->canDeleteWorkOrders($request->user()),
        ]);
    }

    protected function ordersLinkageData(Request $request): JsonResponse
    {
        if (!$this->canAccessOrderLinkage($request->user())) {
            return $this->orderLinkageForbiddenJsonResponse();
        }

        try {
            $requestedLimit = (int) $request->input('limit', $request->input('length', config('workorders.default_limit', 10)));
            $requestedPage = (int) $request->input('page', 0);
            $requestedStart = (int) $request->input('start', 0);

            if ($requestedPage < 1) {
                $resolvedLength = max(1, (int) $request->input('length', $requestedLimit));
                $requestedPage = (int) floor(max(0, $requestedStart) / $resolvedLength) + 1;
            }

            $filters = $this->extractOrderLinkageFilters($request);
            $sort = $this->extractOrderLinkageSort($request);
            $result = $this->fetchOrdersLinkagePage($requestedLimit, $requestedPage, $filters, $sort);

            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'data' => $result['data'] ?? [],
                'meta' => $result['meta'] ?? [
                    'count' => 0,
                    'page' => 1,
                    'limit' => $this->resolveLimit($requestedLimit),
                    'total' => 0,
                    'filtered_total' => 0,
                    'last_page' => 1,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Order linkage list failed.', [
                'connection' => config('database.default'),
                'orders_table' => $this->qualifiedOrderTableName(),
                'order_items_table' => $this->qualifiedOrderItemTableName(),
                'work_orders_table' => $this->qualifiedTableName(),
                'filters' => $request->except(['_token']),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greška pri učitavanju narudžbi.',
                'data' => [],
                'meta' => [
                    'count' => 0,
                    'page' => 1,
                    'limit' => $this->resolveLimit((int) $request->input('limit', $request->input('length', config('workorders.default_limit', 10)))),
                    'total' => 0,
                    'filtered_total' => 0,
                    'last_page' => 1,
                ],
            ], 500);
        }
    }

    protected function ordersLinkagePositions(Request $request)
    {
        if (!$this->canAccessOrderLinkage($request->user())) {
            return $this->orderLinkageForbiddenHtmlResponse();
        }

        $validator = Validator::make($request->all(), [
            'order_number' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response(
                '<div class="alert alert-danger mb-0">' . e('Broj narudžbe je obavezan.') . '</div>',
                422
            );
        }

        $normalizedOrderNumber = $this->normalizeComparableIdentifier(
            (string) ($validator->validated()['order_number'] ?? '')
        );

        if ($normalizedOrderNumber === '') {
            return response(
                '<div class="alert alert-danger mb-0">' . e('Neispravan broj narudžbe.') . '</div>',
                422
            );
        }

        try {
            $details = $this->buildOrdersLinkageDetails($normalizedOrderNumber);

            if (empty($details)) {
                return response(
                    '<div class="alert alert-warning mb-0">' . e('Narudžba nije pronađena.') . '</div>',
                    404
                );
            }

            return view('content.apps.orders.partials.order-positions-modal-content', [
                'orderSummary' => $details['summary'],
                'links' => $details['links'],
            ]);
        } catch (Throwable $exception) {
            Log::error('Order linkage relations modal failed.', [
                'connection' => config('database.default'),
                'orders_table' => $this->qualifiedOrderTableName(),
                'order_items_table' => $this->qualifiedOrderItemTableName(),
                'links_table' => $this->qualifiedWorkOrderOrderItemLinkTableName(),
                'order_number' => $normalizedOrderNumber,
                'message' => $exception->getMessage(),
            ]);

            return response(
                '<div class="alert alert-danger mb-0">' . e('Greška pri učitavanju veza narudžbe.') . '</div>',
                500
            );
        }
    }

    protected function ordersLinkageWorkOrders(Request $request)
    {
        if (!$this->canAccessOrderLinkage($request->user())) {
            return $this->orderLinkageForbiddenHtmlResponse();
        }

        $validator = Validator::make($request->all(), [
            'order_number' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response(
                '<div class="alert alert-danger mb-0">' . e('Broj narudžbe je obavezan.') . '</div>',
                422
            );
        }

        $normalizedOrderNumber = $this->normalizeComparableIdentifier(
            (string) ($validator->validated()['order_number'] ?? '')
        );

        if ($normalizedOrderNumber === '') {
            return response(
                '<div class="alert alert-danger mb-0">' . e('Neispravan broj narudžbe.') . '</div>',
                422
            );
        }

        try {
            $details = $this->buildOrdersLinkageDetails($normalizedOrderNumber);

            if (empty($details)) {
                return response(
                    '<div class="alert alert-warning mb-0">' . e('Narudžba nije pronađena.') . '</div>',
                    404
                );
            }

            return view('content.apps.orders.partials.order-work-orders-modal-content', [
                'orderSummary' => $details['summary'],
                'workOrders' => $details['work_orders'],
            ]);
        } catch (Throwable $exception) {
            Log::error('Order linkage work orders modal failed.', [
                'connection' => config('database.default'),
                'orders_table' => $this->qualifiedOrderTableName(),
                'work_orders_table' => $this->qualifiedTableName(),
                'order_number' => $normalizedOrderNumber,
                'message' => $exception->getMessage(),
            ]);

            return response(
                '<div class="alert alert-danger mb-0">' . e('Greška pri učitavanju radnih naloga narudžbe.') . '</div>',
                500
            );
        }
    }

    protected function ordersLinkageWorkOrdersApi(Request $request): JsonResponse
    {
        if (!$this->canAccessOrderLinkage($request->user())) {
            return $this->orderLinkageForbiddenJsonResponse();
        }

        $orderNumber = trim((string) $request->query('narudzba', $request->query('order_number', '')));
        $normalizedOrderNumber = $this->normalizeComparableIdentifier($orderNumber);

        if ($normalizedOrderNumber === '') {
            return response()->json([
                'message' => 'Broj narudžbe je obavezan.',
                'data' => [],
            ], 422);
        }

        try {
            return response()->json($this->fetchLinkedWorkOrdersByOrderNumber($normalizedOrderNumber));
        } catch (Throwable $exception) {
            Log::error('Order linkage RN API failed.', [
                'connection' => config('database.default'),
                'work_orders_table' => $this->qualifiedTableName(),
                'orders_table' => $this->qualifiedOrderTableName(),
                'order_number' => $normalizedOrderNumber,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greška pri učitavanju radnih naloga za odabranu narudžbu.',
                'data' => [],
            ], 500);
        }
    }

    protected function destroyLinkedOrder(Request $request): JsonResponse
    {
        if (!$this->canDeleteWorkOrders($request->user())) {
            return response()->json([
                'message' => 'Nemate dozvolu za brisanje narudžbe.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'order_number' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Broj narudžbe je obavezan.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $normalizedOrderNumber = $this->normalizeComparableIdentifier(
            (string) ($validator->validated()['order_number'] ?? '')
        );

        if ($normalizedOrderNumber === '') {
            return response()->json([
                'message' => 'Neispravan broj narudžbe.',
            ], 422);
        }

        try {
            $orderRows = $this->fetchOrderHeadersForLinkage($normalizedOrderNumber);

            if (empty($orderRows)) {
                return response()->json([
                    'message' => 'Narudžba nije pronađena.',
                ], 404);
            }

            $details = $this->buildOrdersLinkageDetails($normalizedOrderNumber);
            $linkedWorkOrders = (array) ($details['work_orders'] ?? []);

            if (!empty($linkedWorkOrders)) {
                return response()->json([
                    'message' => 'Narudžba ima povezane radne naloge i ne može biti obrisana sa ove stranice.',
                ], 422);
            }

            $orderColumns = $this->orderTableColumns();
            $orderKeyColumn = $this->firstExistingColumn($orderColumns, ['acKey']);
            $orderKeys = array_values(array_unique(array_filter(array_map(function (array $row) {
                return trim((string) $this->valueTrimmed($row, ['acKey'], ''));
            }, $orderRows))));

            if ($orderKeyColumn === null || empty($orderKeys)) {
                return response()->json([
                    'message' => 'Narudžba nema raspoloživ ključ za sigurno brisanje.',
                ], 422);
            }

            $deletedCounts = DB::transaction(function () use ($orderKeys, $orderKeyColumn) {
                $deleted = [
                    'order_items' => 0,
                    'orders' => 0,
                ];

                $orderItemColumns = $this->orderItemTableColumns();
                $orderItemKeyColumns = $this->existingColumns(
                    $orderItemColumns,
                    ['acKey', 'acLnkKey', 'acOrderKey', 'order_key']
                );

                if (!empty($orderItemKeyColumns)) {
                    $deleted['order_items'] = $this->newOrderItemTableQuery()
                        ->where(function (Builder $query) use ($orderItemKeyColumns, $orderKeys) {
                            foreach ($orderItemKeyColumns as $index => $orderItemKeyColumn) {
                                $method = $index === 0 ? 'whereIn' : 'orWhereIn';
                                $query->{$method}($orderItemKeyColumn, $orderKeys);
                            }
                        })
                        ->delete();
                }

                $deleted['orders'] = $this->newOrderTableQuery()
                    ->whereIn($orderKeyColumn, $orderKeys)
                    ->delete();

                if ($deleted['orders'] < 1) {
                    throw new \RuntimeException('Narudžba nije obrisana.');
                }

                return $deleted;
            }, 3);

            Log::info('Linked order deleted from order linkage page.', [
                'order_number' => $normalizedOrderNumber,
                'order_keys' => $orderKeys,
                'deleted_counts' => $deletedCounts,
                'user_id' => (int) ($request->user()->id ?? 0),
                'username' => (string) ($request->user()->username ?? ''),
            ]);

            return response()->json([
                'message' => 'Narudžba je obrisana.',
                'data' => [
                    'order_number' => $normalizedOrderNumber,
                    'order_keys' => $orderKeys,
                    'deleted_counts' => $deletedCounts,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Linked order delete failed from order linkage page.', [
                'connection' => config('database.default'),
                'orders_table' => $this->qualifiedOrderTableName(),
                'order_items_table' => $this->qualifiedOrderItemTableName(),
                'order_number' => $normalizedOrderNumber,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greška pri brisanju narudžbe.',
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
                    'message' => 'Radni nalog nije pronađen.',
                ], 404);
            }

            $search = trim((string) $request->query('q', ''));
            $selected = trim((string) $request->query('selected', ''));
            $products = $this->resolveWorkOrderProducts($workOrderRow, $search, $selected);

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
                'message' => 'Greška pri učitavanju proizvoda.',
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
                    'message' => 'Radni nalog nije pronađen.',
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
                'message' => 'Greška pri učitavanju BOM strukture.',
            ], 500);
        }
    }

    public function destroyWorkOrderBom(Request $request, string $id): JsonResponse
    {
        if (!$this->canDeleteWorkOrders(auth()->user())) {
            return response()->json([
                'message' => 'Nemate dozvolu za brisanje sastavnice proizvoda.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravan proizvod.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $productId = trim((string) $validator->validated()['product_id']);

        if ($productId === '') {
            return response()->json([
                'message' => 'Product ID (acIdent) je obavezan.',
            ], 422);
        }

        try {
            $workOrderRow = $this->findWorkOrderRow($id);

            if ($workOrderRow === null) {
                return response()->json([
                    'message' => 'Radni nalog nije pronađen.',
                ], 404);
            }

            $deleteResult = Product::deleteCatalogProductStructure($productId);
            $deletedCount = (int) ($deleteResult['count'] ?? 0);

            if ($deletedCount < 1) {
                return response()->json([
                    'message' => 'Sastavnica za odabrani proizvod nije pronađena.',
                ], 404);
            }

            return response()->json([
                'message' => 'Sastavnica proizvoda je uspješno izbrisana.',
                'meta' => [
                    'product_id' => $productId,
                    'deleted_count' => $deletedCount,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order BOM delete failed.', [
                'id' => $id,
                'product_id' => $productId,
                'connection' => config('database.default'),
                'product_structure_table' => $this->qualifiedProductStructureTableName(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greška pri brisanju BOM strukture.',
            ], 500);
        }
    }

    public function barcodeMaterialLookup(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode' => ['required', 'string', 'max:128'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravan barcode materijala.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $workOrderRow = $this->findWorkOrderRow($id);

            if ($workOrderRow === null) {
                return response()->json([
                    'message' => 'Radni nalog nije pronađen.',
                ], 404);
            }

            $barcode = trim((string) $validator->validated()['barcode']);
            $workOrderKey = trim((string) $this->valueTrimmed($workOrderRow, ['acKey'], ''));

            if ($workOrderKey === '') {
                return response()->json([
                    'message' => 'RN ključ nije pronađen.',
                ], 422);
            }

            $material = Material::scannerFindByBarcode($barcode, self::MATERIALS_SETS);

            if ($material === null) {
                return response()->json([
                    'message' => 'Materijal za skenirani barcode nije pronađen.',
                ], 404);
            }

            $existingItem = $this->findExistingMaterialItemForWorkOrder(
                $workOrderKey,
                (string) ($material['material_code'] ?? ''),
                (string) ($material['material_set'] ?? '')
            );

            return response()->json([
                'data' => [
                    'barcode' => (string) ($material['barcode'] ?? ''),
                    'barcode_field' => (string) ($material['barcode_field'] ?? 'acIdent'),
                    'material_code' => (string) ($material['material_code'] ?? ''),
                    'material_name' => (string) ($material['material_name'] ?? ''),
                    'material_um' => (string) ($material['material_um'] ?? ''),
                    'material_code_alt' => (string) ($material['material_code_alt'] ?? ''),
                    'material_set' => (string) ($material['material_set'] ?? ''),
                    'material_supplier' => (string) ($material['material_supplier'] ?? ''),
                    'material_qid' => $material['material_qid'] ?? null,
                    'material_buy_price' => $material['material_buy_price'] ?? null,
                    'material_buy_price_display' => $this->formatMetaMoney($this->toFloatOrNull($material['material_buy_price'] ?? null)),
                    'material_price' => $material['material_price'] ?? null,
                    'material_price_display' => $this->formatMetaMoney($this->toFloatOrNull($material['material_price'] ?? null)),
                    'material_vat_rate' => $material['material_vat_rate'] ?? null,
                    'material_vat_display' => $this->formatMetaPercent($this->toFloatOrNull($material['material_vat_rate'] ?? null)),
                    'material_delivery_deadline' => $material['material_delivery_deadline'] ?? null,
                    'material_delivery_deadline_display' => $this->formatMetaDays($material['material_delivery_deadline'] ?? null),
                    'material_changed_at' => (string) ($material['material_changed_at'] ?? ''),
                    'material_changed_display' => $this->formatMetaDate($material['material_changed_at'] ?? null),
                    'stock_qty' => (float) ($material['material_qty'] ?? 0),
                    'action' => $existingItem === null ? 'insert' : 'update',
                    'exists_on_work_order' => $existingItem !== null,
                    'existing_item' => $existingItem === null ? null : [
                        'qid' => $existingItem['anQId'] ?? null,
                        'no' => $existingItem['anNo'] ?? null,
                        'qty' => $this->workOrderItemQuantity($existingItem),
                        'um' => trim((string) ($existingItem['acUM'] ?? '')),
                    ],
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Barcode material lookup failed.', [
                'id' => $id,
                'barcode' => (string) $request->input('barcode', ''),
                'connection' => config('database.default'),
                'items_table' => $this->qualifiedItemTableName(),
                'catalog_items_table' => Material::scannerSourceTable(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Greška pri provjeri barcode materijala.',
            ], 500);
        }
    }

    public function storePlannedConsumption(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'quantity_unit' => ['nullable', 'string', 'in:AUTO,KG,MJ,RDS'],
            'description' => ['nullable', 'string', 'max:500'],
            'save_mode' => ['nullable', 'string', 'in:manual,barcode'],
            'trigger_status_transition' => ['sometimes', 'boolean'],
            'components' => ['required', 'array', 'min:1', 'max:100'],
            'components.*.acIdentChild' => ['required', 'string', 'max:64'],
            'components.*.anNo' => ['nullable', 'numeric'],
            'components.*.acDescr' => ['nullable', 'string', 'max:200'],
            'components.*.napomena' => ['nullable', 'string', 'max:4000'],
            'components.*.acUM' => ['nullable', 'string', 'max:8'],
            'components.*.acUMSource' => ['nullable', 'string', 'max:8'],
            'components.*.acOperationType' => ['nullable', 'string', 'max:8'],
            'components.*.row_uid' => ['nullable', 'string', 'max:64'],
            'components.*.anPlanQty' => ['nullable', 'numeric', 'min:0'],
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
                    'message' => 'Radni nalog nije pronađen.',
                ], 404);
            }

            $validated = $validator->validated();
            $productId = (string) ($validated['product_id'] ?? '');
            $quantityFactor = (float) ($validated['quantity'] ?? 0);
            $quantityUnit = strtoupper(trim((string) ($validated['quantity_unit'] ?? 'AUTO')));
            $userDescription = trim((string) ($validated['description'] ?? ''));
            $saveMode = strtolower(trim((string) ($validated['save_mode'] ?? 'manual')));
            $productStructureCreated = false;
            $productStructureCreatedCount = 0;
            $suppressStatusTransition = (bool) $request->attributes->get('suppress_status_transition', false);
            $triggerStatusTransition = array_key_exists('trigger_status_transition', $validated)
                ? (bool) $validated['trigger_status_transition']
                : true;
            $shouldTransitionStatus = $triggerStatusTransition && !$suppressStatusTransition;

            if (trim($productId) === '') {
                return response()->json([
                    'message' => 'Product ID (acIdent) je obavezan.',
                ], 422);
            }

            if ($quantityFactor <= 0) {
                return response()->json([
                    'message' => 'Količina mora biti veca od 0.',
                ], 422);
            }

            $workOrderKey = trim((string) $this->valueTrimmed($workOrderRow, ['acKey'], ''));
            $preferredWarehouse = $this->resolveWorkOrderWarehouse($workOrderRow);

            if ($workOrderKey === '') {
                return response()->json([
                    'message' => 'RN kljuc nije pronađen.',
                ], 422);
            }

            $bomRows = $this->fetchBomComponentsByProduct($productId, $this->bomLimit());
            $normalizedComponents = [];
            $normalizedSeen = [];

            foreach ((array) ($validated['components'] ?? []) as $component) {
                $componentId = trim((string) ($component['acIdentChild'] ?? ''));
                $lineNo = (int) ($this->toFloatOrNull($component['anNo'] ?? null) ?? 0);
                $rowUid = trim((string) ($component['row_uid'] ?? ''));

                if ($componentId === '') {
                    continue;
                }

                $selectionKey = $this->plannedConsumptionComponentSelectionKey($lineNo, $componentId, $rowUid);
                if (array_key_exists($selectionKey, $normalizedSeen)) {
                    continue;
                }

                $normalizedSeen[$selectionKey] = true;
                $manualOperationType = strtoupper(substr(trim((string) ($component['acOperationType'] ?? '')), 0, 1));
                $manualUnitRaw = strtoupper(trim((string) ($component['acUM'] ?? '')));
                $manualUnit = $manualUnitRaw === 'AUTO' ? '' : strtoupper(substr($manualUnitRaw, 0, 3));
                $manualUnitSourceRaw = strtoupper(trim((string) ($component['acUMSource'] ?? '')));
                $manualUnitSource = $manualUnitSourceRaw === 'AUTO' ? '' : strtoupper(substr($manualUnitSourceRaw, 0, 3));
                $componentNote = trim((string) ($component['napomena'] ?? ''));

                Log::info('normalize- planned consumption component', [
                    'componentId' => $componentId,
                    'lineNo' => $lineNo,
                    'napomena' => $componentNote,
                    'origin' => 'storePlannedConsumption',
                ]);

                $normalizedComponents[] = [
                    'row_uid' => $rowUid,
                    'acIdentChild' => $componentId,
                    'anNo' => $lineNo,
                    'acDescr' => trim((string) ($component['acDescr'] ?? '')),
                    'napomena' => $componentNote,
                    'acUM' => $manualUnit,
                    'acUMSource' => $manualUnitSource,
                    'acOperationType' => $manualOperationType,
                    'anPlanQty' => $this->toFloatOrNull($component['anPlanQty'] ?? null),
                ];
            }

            if (empty($normalizedComponents)) {
                return response()->json([
                    'message' => 'Nijedna komponenta nije odabrana.',
                ], 422);
            }

            $catalogMetaByIdent = $this->fetchCatalogMetaForComponentIds(array_map(function (array $component) {
                return (string) ($component['acIdentChild'] ?? '');
            }, $normalizedComponents));

            $normalizedComponents = array_map(function (array $component) use ($catalogMetaByIdent) {
                $componentId = trim((string) ($component['acIdentChild'] ?? ''));
                $catalogMeta = $catalogMetaByIdent[strtolower($componentId)] ?? [];
                $catalogName = trim((string) ($catalogMeta['name'] ?? ''));
                $catalogUnit = strtoupper(substr(trim((string) ($catalogMeta['um'] ?? '')), 0, 3));
                $catalogSet = strtoupper(trim((string) ($catalogMeta['set'] ?? '')));
                $componentDescription = trim((string) ($component['acDescr'] ?? ''));
                $componentUnit = strtoupper(substr(trim((string) ($component['acUM'] ?? '')), 0, 3));
                $componentUnitSource = strtoupper(substr(trim((string) ($component['acUMSource'] ?? '')), 0, 3));

                if ($componentDescription === '' && $catalogName !== '') {
                    $componentDescription = $catalogName;
                }

                if ($componentUnit === '') {
                    if ($componentUnitSource !== '') {
                        $componentUnit = $componentUnitSource;
                    } elseif ($catalogUnit !== '') {
                        $componentUnit = $catalogUnit;
                    }
                }

                return [
                    'row_uid' => trim((string) ($component['row_uid'] ?? '')),
                    'acIdentChild' => $componentId,
                    'anNo' => (int) ($component['anNo'] ?? 0),
                    'acDescr' => $componentDescription,
                    'napomena' => trim((string) ($component['napomena'] ?? '')),
                    'acUM' => $componentUnit,
                    'acOperationType' => $this->resolveOperationTypeForSave(
                        (string) ($component['acOperationType'] ?? ''),
                        $catalogSet
                    ),
                    'catalog_set' => $catalogSet,
                    'anPlanQty' => $this->toFloatOrNull($component['anPlanQty'] ?? null),
                ];
            }, $normalizedComponents);

            if ($saveMode === 'manual') {
                $duplicatePositions = $this->findDuplicatePlannedConsumptionPositions($normalizedComponents);

                if (!empty($duplicatePositions)) {
                    return response()->json([
                        'message' => 'Nije moguće nastaviti jer postoje duple pozicije. Duple pozicije: ' . implode(', ', $duplicatePositions) . '.',
                    ], 422);
                }
            }

            if ($saveMode === 'manual' && empty($bomRows)) {
                $structureEnsureResult = Product::ensureCatalogProductStructure(
                    $productId,
                    $normalizedComponents,
                    $quantityFactor,
                    (int) (auth()->id() ?? 0)
                );
                $productStructureCreated = (bool) ($structureEnsureResult['created'] ?? false);
                $productStructureCreatedCount = (int) ($structureEnsureResult['count'] ?? 0);

                if ($productStructureCreated) {
                    $bomRows = $this->fetchBomComponentsByProduct($productId, $this->bomLimit());
                }
            }

            $bomRowsByKey = [];
            foreach ($bomRows as $row) {
                $lineNo = (int) ($this->toFloatOrNull($row['anNo'] ?? null) ?? 0);
                $componentId = trim((string) ($row['acIdentChild'] ?? ''));

                if ($componentId === '') {
                    continue;
                }

                $key = $this->bomSelectionKey($lineNo, $componentId);
                if (!array_key_exists($key, $bomRowsByKey)) {
                    $bomRowsByKey[$key] = $row;
                }
            }

            $resolvedRows = [];
            $bomMatchedCount = 0;
            $manualRawCount = 0;

            foreach ($normalizedComponents as $component) {
                $key = $this->bomSelectionKey((int) ($component['anNo'] ?? 0), (string) ($component['acIdentChild'] ?? ''));

                if (array_key_exists($key, $bomRowsByKey)) {
                    $bomMatchedCount++;
                    $resolvedRows[] = [
                        'source' => 'bom',
                        'requested' => $component,
                        'bom' => $bomRowsByKey[$key],
                    ];
                    continue;
                }

                $manualRawCount++;
                $resolvedRows[] = [
                    'source' => 'raw',
                    'requested' => $component,
                    'bom' => null,
                ];
            }

            if (empty($resolvedRows)) {
                return response()->json([
                    'message' => 'Nijedna komponenta nije spremna za snimanje.',
                ], 422);
            }

            $now = now();
            $userId = (int) (auth()->id() ?? 0);
            $variant = (int) ($this->toFloatOrNull($this->valueTrimmed($workOrderRow, ['anVariant'], 0)) ?? 0);
            $itemColumns = $this->itemTableColumns();
            $identityColumns = $this->itemTableIdentityColumns();
            $manualNoInsert = in_array('anNo', $itemColumns, true) && !in_array('anNo', $identityColumns, true);
            $manualQIdInsert = in_array('anQId', $itemColumns, true) && !in_array('anQId', $identityColumns, true);
            $itemNoteColumn = $this->workOrderItemNoteColumn($itemColumns);
            $hasStatementColumn = in_array('acStatement', $itemColumns, true);

            Log::info('Planned consumption save started.', [
                'id' => $id,
                'work_order_key' => $workOrderKey,
                'product_id' => $productId,
                'quantity_factor' => $quantityFactor,
                'quantity_unit' => $quantityUnit,
                'save_mode' => $saveMode,
                'trigger_status_transition' => $triggerStatusTransition,
                'effective_status_transition' => $shouldTransitionStatus,
                'suppressed_status_transition' => $suppressStatusTransition,
                'description_present' => $userDescription !== '',
                'description_length' => strlen($userDescription),
                'requested_components_count' => count((array) ($validated['components'] ?? [])),
                'selected_components_count' => count($resolvedRows),
                'bom_matched_components_count' => $bomMatchedCount,
                'raw_manual_components_count' => $manualRawCount,
                'product_structure_created' => $productStructureCreated,
                'product_structure_created_count' => $productStructureCreatedCount,
                'variant' => $variant,
                'user_id' => $userId,
                'items_table' => $this->qualifiedItemTableName(),
                'identity_columns' => $identityColumns,
                'manual_anNo_insert' => $manualNoInsert,
                'manual_anQId_insert' => $manualQIdInsert,
                'item_note_column' => $itemNoteColumn,
                'has_statement_column' => $hasStatementColumn,
            ]);

            $workOrderColumns = $this->tableColumns();
            $saveResult = DB::transaction(function () use (
                $resolvedRows,
                $quantityFactor,
                $workOrderKey,
                $preferredWarehouse,
                $workOrderRow,
                $workOrderColumns,
                $variant,
                $userId,
                $now,
                $quantityUnit,
                $saveMode,
                $userDescription,
                $itemNoteColumn,
                $hasStatementColumn,
                $manualNoInsert,
                $manualQIdInsert,
                $suppressStatusTransition,
                $shouldTransitionStatus
            ) {
                $nextNo = null;
                $nextQId = null;

                if ($manualNoInsert) {
                    $nextNo = ((int) ($this->newItemTableQuery()->where('acKey', $workOrderKey)->max('anNo') ?? 0)) + 1;
                }

                if ($manualQIdInsert) {
                    $nextQId = ((int) ($this->newItemTableQuery()->where('acKey', $workOrderKey)->max('anQId') ?? 0)) + 1;
                }

                $existingMaterialItemsByIdent = $saveMode === 'barcode'
                    ? $this->findExistingMaterialItemsForBarcodeSave($workOrderKey, $resolvedRows)
                    : [];

                $saved = [];

                foreach ($resolvedRows as $resolvedRow) {
                    $source = (string) ($resolvedRow['source'] ?? 'raw');
                    $requestedRow = is_array($resolvedRow['requested'] ?? null) ? $resolvedRow['requested'] : [];
                    $bomRow = is_array($resolvedRow['bom'] ?? null) ? $resolvedRow['bom'] : [];

                    $componentId = trim((string) ($requestedRow['acIdentChild'] ?? ($bomRow['acIdentChild'] ?? '')));
                    $lineNo = (int) (($requestedRow['anNo'] ?? $bomRow['anNo'] ?? 0));
                    $descriptionFromRequested = trim((string) ($requestedRow['acDescr'] ?? ''));
                    $descriptionFromBom = trim((string) ($bomRow['acDescr'] ?? ''));
                    $componentNote = trim((string) ($requestedRow['napomena'] ?? ''));
                    $bomNote = trim((string) ($bomRow['napomena'] ?? ''));
                    $description = $descriptionFromRequested !== '' ? $descriptionFromRequested : $descriptionFromBom;
                    $resolvedNote = $componentNote !== ''
                        ? $componentNote
                        : ($bomNote !== '' ? $bomNote : $userDescription);

                    Log::info('save-planned-consumption resolved component note', [
                        'componentId' => $componentId,
                        'lineNo' => $lineNo,
                        'componentNote' => $componentNote,
                        'bomNote' => $bomNote,
                        'resolvedNote' => $resolvedNote,
                        'source' => $source,
                    ]);
                    $operationType = strtoupper(substr(trim((string) ($requestedRow['acOperationType'] ?? '')), 0, 1));
                    if ($operationType === '') {
                        $operationType = strtoupper(substr(trim((string) ($bomRow['acOperationType'] ?? '')), 0, 1));
                    }

                    if ($operationType === '') {
                        $operationType = $this->resolveOperationTypeForSave('', (string) ($requestedRow['catalog_set'] ?? ''));
                    }

                    $baseQty = $this->toFloatOrNull($bomRow['anGrossQty'] ?? null) ?? 0.0;
                    $manualPlanQty = $this->toFloatOrNull($requestedRow['anPlanQty'] ?? null);
                    $requestedUnitRaw = strtoupper(trim((string) ($requestedRow['acUM'] ?? '')));
                    $requestedUnit = $requestedUnitRaw === 'AUTO' ? '' : strtoupper(substr($requestedUnitRaw, 0, 3));
                    $bomUnit = strtoupper(substr(trim((string) ($bomRow['acUM'] ?? '')), 0, 3));
                    $sourceUnit = $requestedUnit !== '' ? $requestedUnit : $bomUnit;
                    $resolvedUnit = $quantityUnit === 'AUTO' ? $sourceUnit : $quantityUnit;

                    if ($resolvedUnit === '' && $quantityUnit !== 'AUTO') {
                        $resolvedUnit = $quantityUnit;
                    }

                    if ($manualPlanQty !== null) {
                        $plannedQty = max(0, $manualPlanQty);
                    } elseif ($saveMode === 'barcode') {
                        // Barcode/weight-confirm mode always treats entered quantity as the absolute value,
                        // not as a BOM multiplier.
                        $plannedQty = $quantityFactor;
                    } elseif ($source === 'bom') {
                        // Fallback to entered quantity when BOM gross qty is 0 to avoid saving 0 by default.
                        $plannedQty = abs($baseQty) > 0.000001 ? ($baseQty * $quantityFactor) : $quantityFactor;
                    } else {
                        $plannedQty = $quantityFactor;
                    }

                    if ($componentId === '') {
                        continue;
                    }

                    $componentKey = strtolower($componentId);
                    $itemKind = $this->resolveItemKindForPreview(
                        $operationType,
                        (string) ($requestedRow['catalog_set'] ?? '')
                    );

                    if ($saveMode === 'barcode' && $itemKind === 'materials' && array_key_exists($componentKey, $existingMaterialItemsByIdent)) {
                        $existingRow = $existingMaterialItemsByIdent[$componentKey];
                        $existingQty = $this->workOrderItemQuantity($existingRow);
                        $updatedQty = $plannedQty;
                        $updatePayload = [
                            'anPlanQty' => $updatedQty,
                            'anQty' => $updatedQty,
                            'anQty1' => $updatedQty,
                            'adTimeChg' => $now,
                            'anUserChg' => $userId,
                        ];

                        if ($resolvedUnit !== '' && trim((string) ($existingRow['acUM'] ?? '')) === '') {
                            $updatePayload['acUM'] = $resolvedUnit;
                        }

                        if ($description !== '' && trim((string) ($existingRow['acDescr'] ?? '')) === '') {
                            $updatePayload['acDescr'] = substr($description, 0, 80);
                        }

                        if ($itemNoteColumn !== null && $resolvedNote !== '') {
                            $resolvedSavedNote = $this->resolveWorkOrderItemNoteForSave(
                                $resolvedNote,
                                $itemNoteColumn,
                                $existingRow
                            );

                            if ($resolvedSavedNote !== null) {
                                $updatePayload[$itemNoteColumn] = $resolvedSavedNote;
                            }
                        }

                        if ($hasStatementColumn) {
                            $updatePayload['acStatement'] = 'PLANNED_BARCODE_UPDATE';
                        }

                        $updateQuery = $this->newItemTableQuery()->where('acKey', $workOrderKey);

                        if (array_key_exists('anQId', $existingRow) && $existingRow['anQId'] !== null) {
                            $updateQuery->where('anQId', (int) $existingRow['anQId']);
                        } elseif (array_key_exists('anNo', $existingRow) && $existingRow['anNo'] !== null) {
                            $updateQuery->where('anNo', (int) $existingRow['anNo']);
                        }

                        $updateQuery->update($updatePayload);

                        $existingMaterialItemsByIdent[$componentKey] = array_merge($existingRow, [
                            'anPlanQty' => $updatedQty,
                            'anQty' => $updatedQty,
                            'anQty1' => $updatedQty,
                            'acUM' => $updatePayload['acUM'] ?? ($existingRow['acUM'] ?? null),
                            'acDescr' => $updatePayload['acDescr'] ?? ($existingRow['acDescr'] ?? null),
                        ]);

                        $saved[] = [
                            'action' => 'updated',
                            'source' => $source,
                            'anNo' => $existingRow['anNo'] ?? null,
                            'anQId' => $existingRow['anQId'] ?? null,
                            'acIdent' => $componentId,
                            'acDescr' => $description,
                            'acNote' => $resolvedNote,
                            'acUM' => $updatePayload['acUM'] ?? (trim((string) ($existingRow['acUM'] ?? '')) !== '' ? trim((string) ($existingRow['acUM'] ?? '')) : $resolvedUnit),
                            'acOperationType' => $operationType,
                            'item_kind' => $itemKind,
                            'anGrossQty' => $baseQty,
                            'previous_anPlanQty' => $existingQty,
                            'stock_consumed_qty' => $plannedQty,
                            'anPlanQty' => $updatedQty,
                        ];

                        continue;
                    }

                    $statementMarker = $saveMode === 'barcode'
                        ? 'PLANNED_BARCODE'
                        : ($source === 'bom' ? 'PLANNED_BOM_PENDING' : 'PLANNED_RAW_PENDING');
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

                    if ($itemNoteColumn !== null) {
                        $insertPayload[$itemNoteColumn] = $this->resolveWorkOrderItemNoteForSave(
                            $resolvedNote,
                            $itemNoteColumn
                        );
                    }

                    if ($hasStatementColumn) {
                        $insertPayload['acStatement'] = $statementMarker;
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
                        'action' => 'inserted',
                        'source' => $source,
                        'anNo' => $nextNo,
                        'anQId' => $nextQId,
                        'acIdent' => $componentId,
                        'acDescr' => $description,
                        'acNote' => $resolvedNote,
                        'acUM' => $resolvedUnit,
                        'acOperationType' => $operationType,
                        'item_kind' => $itemKind,
                        'anGrossQty' => $baseQty,
                        'stock_consumed_qty' => $saveMode === 'barcode' ? $plannedQty : 0.0,
                        'anPlanQty' => $plannedQty,
                    ];

                    if ($manualNoInsert && $nextNo !== null) {
                        $nextNo++;
                    }

                    if ($manualQIdInsert && $nextQId !== null) {
                        $nextQId++;
                    }
                }

                $stockAdjustmentResults = [];
                $stockAdjustments = $this->buildPlannedConsumptionStockAdjustments($saved);

                if ($saveMode === 'barcode' && !empty($stockAdjustments)) {
                    $stockAdjustmentResults = Material::bulkAdjustStock(
                        $stockAdjustments,
                        $userId,
                        $preferredWarehouse
                    );
                }

                $statusTransition = [
                    'changed' => false,
                ];

                if ($this->savedRowsContainMaterialConsumption($saved) && $shouldTransitionStatus) {
                    $statusTransition = $this->transitionWorkOrderStatusAfterConsumption(
                        $workOrderRow,
                        $workOrderColumns,
                        $userId,
                        $now
                    );
                } elseif ($this->savedRowsContainMaterialConsumption($saved) && !$shouldTransitionStatus) {
                    $statusTransition = [
                        'changed' => false,
                        'reason' => $suppressStatusTransition
                            ? 'suppressed_for_fresh_create'
                            : 'disabled_for_request_context',
                    ];
                }

                return [
                    'saved' => $saved,
                    'stock_adjustments' => $stockAdjustmentResults,
                    'status_transition' => $statusTransition,
                ];
            });

            $insertedRows = is_array($saveResult['saved'] ?? null)
                ? $saveResult['saved']
                : [];
            $stockAdjustmentResults = is_array($saveResult['stock_adjustments'] ?? null)
                ? $saveResult['stock_adjustments']
                : [];
            $statusTransition = is_array($saveResult['status_transition'] ?? null)
                ? $saveResult['status_transition']
                : ['changed' => false];

            foreach ($stockAdjustmentResults as &$stockAdjustmentResult) {
                if (!is_array($stockAdjustmentResult)) {
                    continue;
                }

                $consumedQty = $this->toFloatOrNull($stockAdjustmentResult['value'] ?? null);
                if ($consumedQty !== null) {
                    $stockAdjustmentResult['consumed_qty'] = abs($consumedQty);
                }
            }
            unset($stockAdjustmentResult);

            if (empty($insertedRows)) {
                return response()->json([
                    'message' => 'Nema stavki za snimanje planirane potrosnje.',
                ], 422);
            }

            $updatedCount = count(array_filter($insertedRows, function (array $row) {
                return (string) ($row['action'] ?? '') === 'updated';
            }));
            $insertedCount = count(array_filter($insertedRows, function (array $row) {
                return (string) ($row['action'] ?? '') !== 'updated';
            }));
            $responseMessage = 'Planirana potrosnja je uspjesno sačuvana.';

            if ($saveMode === 'barcode') {
                if ($updatedCount > 0 && $insertedCount > 0) {
                    $responseMessage = 'Barcode materijal je obrađen. Ažurirano: ' . $updatedCount . ', dodano: ' . $insertedCount . '.';
                } elseif ($updatedCount > 0) {
                    $responseMessage = 'Barcode materijal je uspjesno ažuriran na radnom nalogu.';
                } elseif ($insertedCount > 0) {
                    $responseMessage = 'Barcode materijal je uspješno dodan na radni nalog.';
                }
            }

            return response()->json([
                'message' => 'Planirana potrošnja je uspjesno sačuvana.',
                'message' => $responseMessage,
                'data' => [
                    'work_order_id' => $id,
                    'work_order_key' => $workOrderKey,
                    'product_id' => $productId,
                    'quantity_factor' => $quantityFactor,
                    'save_mode' => $saveMode,
                    'trigger_status_transition' => $triggerStatusTransition,
                    'effective_status_transition' => $shouldTransitionStatus,
                    'description' => $userDescription,
                    'saved_count' => count($insertedRows),
                    'updated_count' => $updatedCount,
                    'inserted_count' => $insertedCount,
                    'product_structure_created' => $productStructureCreated,
                    'product_structure_created_count' => $productStructureCreatedCount,
                    'stock_adjusted_count' => count($stockAdjustmentResults),
                    'stock_adjustments' => $stockAdjustmentResults,
                    'status_transition' => $statusTransition,
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
                'request_save_mode' => strtolower(trim((string) $request->input('save_mode', 'manual'))),
                'request_trigger_status_transition' => $request->input('trigger_status_transition'),
                'request_description' => trim((string) $request->input('description', '')),
                'request_components_count' => count((array) $request->input('components', [])),
            ]);

            $errorDetail = $this->clientFacingExceptionDetail($exception);

            return response()->json([
                'message' => 'Greška pri snimanju planirane potrošnje.',
                'detail' => $errorDetail !== '' ? $errorDetail : null,
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
                    'message' => 'Radni nalog nije pronađen.',
                ], 404);
            }

            $columns = $this->itemTableColumns();
            $workOrderKey = trim((string) $this->valueTrimmed($workOrderRow, ['acKey'], ''));

            if ($workOrderKey === '') {
                return response()->json([
                    'message' => 'RN kljuc nije pronađen.',
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

            $preferredWarehouse = $this->resolveWorkOrderWarehouse($workOrderRow);
            $userId = (int) (auth()->id() ?? 0);
            $hasStatementColumn = in_array('acStatement', $columns, true);

            $deleteResult = DB::transaction(function () use ($deleteQuery, $preferredWarehouse, $userId, $hasStatementColumn) {
                $row = (clone $deleteQuery)->lockForUpdate()->first();

                if ($row === null) {
                    return null;
                }

                $rowData = (array) $row;
                $deletedCount = $deleteQuery->delete();

                if ($deletedCount < 1) {
                    throw new RuntimeException('Stavka nije obrisana.');
                }

                $stockAdjustments = [];
                $reverseAdjustment = null;

                if ($this->workOrderItemRowShouldRestoreStockOnRemove($rowData, $hasStatementColumn)) {
                    $reverseAdjustment = $this->buildRemovedPlannedConsumptionStockAdjustment($rowData, $preferredWarehouse);

                    if ($reverseAdjustment !== null) {
                        $stockAdjustments = Material::bulkAdjustStock(
                            [$reverseAdjustment],
                            $userId,
                            $preferredWarehouse
                        );
                    }
                }

                return [
                    'row' => $rowData,
                    'deleted_count' => $deletedCount,
                    'stock_adjustments' => $stockAdjustments,
                    'restored_qty' => $reverseAdjustment !== null
                        ? abs((float) ($reverseAdjustment['value'] ?? 0))
                        : 0.0,
                ];
            });

            if ($deleteResult === null) {
                return response()->json([
                    'message' => 'Stavka nije pronađena.',
                ], 404);
            }

            $rowData = is_array($deleteResult['row'] ?? null) ? $deleteResult['row'] : [];
            $deletedCount = (int) ($deleteResult['deleted_count'] ?? 0);
            $stockAdjustmentResults = is_array($deleteResult['stock_adjustments'] ?? null)
                ? $deleteResult['stock_adjustments']
                : [];
            $restoredQty = (float) ($deleteResult['restored_qty'] ?? 0);

            Log::info('Work order item removed.', [
                'id' => $id,
                'work_order_key' => $workOrderKey,
                'item_id' => $hasQId ? (int) ($rowData['anQId'] ?? 0) : null,
                'item_no' => $hasNo ? (int) ($rowData['anNo'] ?? 0) : null,
                'ac_ident' => trim((string) ($rowData['acIdent'] ?? '')),
                'deleted_count' => $deletedCount,
                'restored_qty' => $restoredQty,
                'stock_adjustments' => $stockAdjustmentResults,
                'user_id' => $userId,
            ]);

            return response()->json([
                'message' => 'Stavka je uklonjena sa sastavnice ovog radnog naloga',
                'data' => [
                    'work_order_id' => $id,
                    'work_order_key' => $workOrderKey,
                    'item_id' => $hasQId ? (int) ($rowData['anQId'] ?? 0) : null,
                    'item_no' => $hasNo ? (int) ($rowData['anNo'] ?? 0) : null,
                    'deleted_count' => $deletedCount,
                    'restored_qty' => $restoredQty,
                    'stock_adjustments' => $stockAdjustmentResults,
                ],
            ]);

            $row = (clone $deleteQuery)->first();

            if ($row === null) {
                return response()->json([
                    'message' => 'Stavka nije pronađena.',
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
                'message' => 'Stavka je uklonjena sa sastavnice ovog radnog naloga',
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

    public function updatePlannedConsumptionItem(Request $request, string $id): JsonResponse
    {
        $hasDescriptionInput = $request->exists('opis');
        $hasNoteInput = $request->exists('napomena');
        $hasQuantityInput = $request->exists('kolicina');
        $hasUnitInput = $request->exists('mj');

        if (!$hasDescriptionInput && !$hasNoteInput && !$hasQuantityInput && !$hasUnitInput) {
            return response()->json([
                'message' => 'Nije dostavljeno nijedno polje za ažuriranje stavke.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'item_id' => ['nullable', 'numeric'],
            'item_no' => ['nullable', 'numeric'],
            'opis' => ['nullable', 'string', 'max:80'],
            'napomena' => ['nullable', 'string', 'max:4000'],
            'kolicina' => ['nullable', 'numeric', 'min:0'],
            'mj' => ['nullable', 'string', 'max:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Neispravni podaci za ažuriranje stavke.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $workOrderRow = $this->findWorkOrderRow($id);

            if ($workOrderRow === null) {
                return response()->json([
                    'message' => 'Radni nalog nije pronađen.',
                ], 404);
            }

            $columns = $this->itemTableColumns();
            $workOrderKey = trim((string) $this->valueTrimmed($workOrderRow, ['acKey'], ''));

            if ($workOrderKey === '') {
                return response()->json([
                    'message' => 'RN ključ nije pronađen.',
                ], 422);
            }

            if (!in_array('acKey', $columns, true)) {
                return response()->json([
                    'message' => 'Tabela stavki nema RN ključ.',
                ], 500);
            }

            $validated = $validator->validated();
            $itemId = $this->toFloatOrNull($validated['item_id'] ?? null);
            $itemNo = $this->toFloatOrNull($validated['item_no'] ?? null);
            $hasQId = in_array('anQId', $columns, true);
            $hasNo = in_array('anNo', $columns, true);

            if ($itemId === null && $itemNo === null) {
                return response()->json([
                    'message' => 'Nedostaje identifikator stavke za ažuriranje.',
                ], 422);
            }

            $userId = (int) (auth()->id() ?? 0);
            $preferredWarehouse = $this->resolveWorkOrderWarehouse($workOrderRow);
            $hasStatementColumn = in_array('acStatement', $columns, true);
            $now = Carbon::now();

            $updateQuery = $this->newItemTableQuery()->where('acKey', $workOrderKey);

            if ($itemId !== null && $hasQId) {
                $updateQuery->where('anQId', (int) $itemId);
            } elseif ($itemNo !== null && $hasNo) {
                $updateQuery->where('anNo', (int) $itemNo);
            } else {
                return response()->json([
                    'message' => 'Nije moguće odrediti stavku za ažuriranje.',
                ], 422);
            }

            $updateResult = DB::transaction(function () use (
                $updateQuery,
                $columns,
                $validated,
                $hasDescriptionInput,
                $hasNoteInput,
                $hasQuantityInput,
                $hasUnitInput,
                $now,
                $userId,
                $preferredWarehouse,
                $hasStatementColumn
            ) {
                $row = (clone $updateQuery)->lockForUpdate()->first();

                if ($row === null) {
                    return [
                        'row' => null,
                    ];
                }

                $itemRow = (array) $row;
                $fieldUpdates = [];
                $requestedQuantity = null;

                if ($hasDescriptionInput && in_array('acDescr', $columns, true)) {
                    $description = trim((string) ($validated['opis'] ?? ''));
                    $fieldUpdates['acDescr'] = $description === '' ? null : substr($description, 0, 80);
                }

                $itemNoteColumn = $this->workOrderItemNoteColumn($columns);

                if ($hasNoteInput && $itemNoteColumn !== null) {
                    $fieldUpdates[$itemNoteColumn] = $this->resolveWorkOrderItemNoteForSave(
                        (string) ($validated['napomena'] ?? ''),
                        $itemNoteColumn,
                        $itemRow
                    );
                }

                if ($hasQuantityInput) {
                    $quantityColumns = $this->existingColumns($columns, ['anPlanQty', 'anQty', 'anQty1']);
                    if (!empty($quantityColumns)) {
                        $requestedQuantity = max(0, (float) ($this->toFloatOrNull($validated['kolicina'] ?? 0) ?? 0));

                        foreach ($quantityColumns as $quantityColumn) {
                            $fieldUpdates[$quantityColumn] = $requestedQuantity;
                        }
                    }
                }

                if ($hasUnitInput && in_array('acUM', $columns, true)) {
                    $unit = strtoupper(substr(trim((string) ($validated['mj'] ?? '')), 0, 3));
                    $fieldUpdates['acUM'] = $unit === '' ? null : $unit;
                }

                if (empty($fieldUpdates)) {
                    return [
                        'row' => $itemRow,
                        'missing_fields' => true,
                    ];
                }

                if ($this->rowAlreadyHasUpdates($itemRow, $fieldUpdates)) {
                    return [
                        'row' => $itemRow,
                        'changed' => false,
                        'item' => $this->mapItemRow($itemRow),
                        'updated_fields' => [],
                    ];
                }

                $updates = $fieldUpdates;

                if (in_array('adTimeChg', $columns, true)) {
                    $updates['adTimeChg'] = $now;
                }

                if ($userId > 0 && in_array('anUserChg', $columns, true)) {
                    $updates['anUserChg'] = $userId;
                }

                $stockAdjustmentResults = [];
                $quantityDelta = null;
                $stockAdjustment = null;

                if ($requestedQuantity !== null) {
                    $previousQuantity = $this->workOrderItemQuantity($itemRow);
                    $quantityDelta = $requestedQuantity - $previousQuantity;

                    if (
                        abs($quantityDelta) > 0.000001
                        && $this->workOrderItemRowShouldRestoreStockOnRemove($itemRow, $hasStatementColumn)
                    ) {
                        $stockAdjustment = $this->buildRemovedPlannedConsumptionStockAdjustment(
                            $itemRow,
                            $preferredWarehouse,
                            $quantityDelta
                        );
                    }
                }

                $updated = $this->updateWorkOrderItemRow($itemRow, $updates);

                if (!$updated) {
                    throw new RuntimeException('Stavka nije ažurirana.');
                }

                if ($stockAdjustment !== null) {
                    $stockAdjustmentResults = Material::bulkAdjustStock(
                        [$stockAdjustment],
                        $userId,
                        $preferredWarehouse
                    );
                }

                $updatedRow = (clone $updateQuery)->first();
                $resolvedRow = $updatedRow !== null ? (array) $updatedRow : array_merge($itemRow, $fieldUpdates);

                return [
                    'row' => $resolvedRow,
                    'changed' => true,
                    'item' => $this->mapItemRow($resolvedRow),
                    'stock_adjustments' => $stockAdjustmentResults,
                    'quantity_delta' => $quantityDelta,
                    'updated_fields' => array_keys($fieldUpdates),
                ];
            });

            if (!is_array($updateResult) || !array_key_exists('row', $updateResult)) {
                return response()->json([
                    'message' => 'Stavka nije ažurirana.',
                ], 500);
            }

            if ($updateResult['row'] === null) {
                return response()->json([
                    'message' => 'Stavka nije pronađena.',
                ], 404);
            }

            if (!empty($updateResult['missing_fields'])) {
                return response()->json([
                    'message' => 'Nijedno traženo polje nije dostupno za ažuriranje stavke.',
                ], 422);
            }

            $mappedItem = is_array($updateResult['item'] ?? null) ? $updateResult['item'] : $this->mapItemRow((array) $updateResult['row']);
            $stockAdjustmentResults = is_array($updateResult['stock_adjustments'] ?? null)
                ? $updateResult['stock_adjustments']
                : [];
            $changed = (bool) ($updateResult['changed'] ?? false);
            $updatedFields = is_array($updateResult['updated_fields'] ?? null) ? $updateResult['updated_fields'] : [];

            if (!$changed) {
                return response()->json([
                    'message' => 'Stavka je već na odabranim vrijednostima.',
                    'data' => [
                        'work_order_id' => $id,
                        'work_order_key' => $workOrderKey,
                        'item' => $mappedItem,
                        'changed' => false,
                    ],
                ]);
            }

            Log::info('Work order item updated.', [
                'id' => $id,
                'work_order_key' => $workOrderKey,
                'item_id' => $mappedItem['qid'] ?? $itemId,
                'item_no' => $mappedItem['no'] ?? $itemNo,
                'ac_ident' => $mappedItem['artikal'] ?? '',
                'updated_fields' => $updatedFields,
                'stock_adjusted' => !empty($stockAdjustmentResults),
                'user_id' => $userId,
            ]);

            return response()->json([
                'message' => 'Stavka sastavnice je uspješno ažurirana.',
                'data' => [
                    'work_order_id' => $id,
                    'work_order_key' => $workOrderKey,
                    'item' => $mappedItem,
                    'changed' => $changed,
                    'stock_adjustments' => $stockAdjustmentResults,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Work order item update failed.', [
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
                'message' => 'Greška pri ažuriranju stavke planirane potrošnje.',
            ], 500);
        }
    }

    private function resolveWorkOrderProducts(array $workOrderRow, string $search = '', string $selectedIdent = ''): array
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
            $normalizedName = trim($name);

            if (array_key_exists($seenKey, $seenKeys)) {
                $existingIndex = (int) $seenKeys[$seenKey];
                $existingName = trim((string) ($products[$existingIndex]['acName'] ?? ''));

                if ($normalizedName !== '' && $existingName === '') {
                    $displayLabel = $normalizedIdent . ' - ' . $normalizedName;
                    $products[$existingIndex]['acName'] = $normalizedName;
                    $products[$existingIndex]['label'] = $displayLabel;
                }

                return;
            }

            $displayLabel = $normalizedIdent;

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
            $seenKeys[$seenKey] = count($products) - 1;
        };

        $search = trim($search);
        $selectedIdent = trim($selectedIdent);

        if ($selectedIdent !== '') {
            $selectedName = trim((string) (
                $this->newTableQuery()
                    ->whereRaw('LTRIM(RTRIM(ISNULL(acIdent, \'\'))) = ?', [$selectedIdent])
                    ->whereRaw("LTRIM(RTRIM(ISNULL(acName, ''))) <> ''")
                    ->value('acName')
            ));
            $addProduct($selectedIdent, $selectedName, 'selected');
        }

        $headerProductIdent = (string) $this->value($workOrderRow, ['acIdent'], '');
        $headerProductName = trim((string) $this->value($workOrderRow, ['acName'], ''));
        if (
            $search === ''
            || stripos($headerProductIdent, $search) !== false
            || stripos($headerProductName, $search) !== false
        ) {
            $addProduct($headerProductIdent, $headerProductName, 'work_order');
        }

        $workOrderProductsQuery = $this->newTableQuery()
            ->select([
                'acIdent',
                DB::raw('MAX(acName) as acName'),
            ])
            ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) <> ''");

        if ($search !== '') {
            $workOrderProductsQuery->where(function (Builder $query) use ($search): void {
                $query
                    ->where('acIdent', 'like', '%' . $search . '%')
                    ->orWhere('acName', 'like', '%' . $search . '%');
            });
        }

        $workOrderProducts = $workOrderProductsQuery
            ->groupBy('acIdent')
            ->orderBy('acIdent')
            ->limit($limit)
            ->get();

        foreach ($workOrderProducts as $workOrderProduct) {
            $addProduct(
                (string) ($workOrderProduct->acIdent ?? ''),
                (string) ($workOrderProduct->acName ?? ''),
                'work_order_history'
            );
        }

        $masterProductsQuery = $this->newProductStructureTableQuery()
            ->select('acIdent')
            ->whereRaw("LTRIM(RTRIM(ISNULL(acIdent, ''))) <> ''");

        if ($search !== '') {
            $masterProductsQuery->where('acIdent', 'like', '%' . $search . '%');
        }

        $masterProducts = $masterProductsQuery
            ->distinct()
            ->orderBy('acIdent')
            ->limit($limit)
            ->get();

        foreach ($masterProducts as $masterProduct) {
            $addProduct((string) ($masterProduct->acIdent ?? ''), '', 'master_structure');
        }

        if (empty($products)) {
            return [];
        }

        $missingNameIdents = [];
        foreach ($products as $product) {
            $ident = trim((string) ($product['acIdentTrimmed'] ?? ''));
            $name = trim((string) ($product['acName'] ?? ''));

            if ($ident !== '' && $name === '') {
                $missingNameIdents[] = $ident;
            }
        }

        $missingNameIdents = array_values(array_unique($missingNameIdents));

        if (!empty($missingNameIdents)) {
            $nameByIdent = [];

            $workOrderNameRows = $this->newTableQuery()
                ->select([
                    'acIdent',
                    DB::raw('MAX(acName) as acName'),
                ])
                ->whereIn('acIdent', $missingNameIdents)
                ->whereRaw("LTRIM(RTRIM(ISNULL(acName, ''))) <> ''")
                ->groupBy('acIdent')
                ->get();

            foreach ($workOrderNameRows as $row) {
                $ident = strtolower(trim((string) ($row->acIdent ?? '')));
                $name = trim((string) ($row->acName ?? ''));

                if ($ident !== '' && $name !== '') {
                    $nameByIdent[$ident] = $name;
                }
            }

            $itemNameRows = $this->newItemTableQuery()
                ->select([
                    'acIdent',
                    DB::raw('MAX(acDescr) as acDescr'),
                ])
                ->whereIn('acIdent', $missingNameIdents)
                ->whereRaw("LTRIM(RTRIM(ISNULL(acDescr, ''))) <> ''")
                ->groupBy('acIdent')
                ->get();

            foreach ($itemNameRows as $row) {
                $ident = strtolower(trim((string) ($row->acIdent ?? '')));
                $name = trim((string) ($row->acDescr ?? ''));

                if ($ident !== '' && $name !== '' && !array_key_exists($ident, $nameByIdent)) {
                    $nameByIdent[$ident] = $name;
                }
            }

            foreach ($products as &$product) {
                $ident = trim((string) ($product['acIdentTrimmed'] ?? ''));
                $name = trim((string) ($product['acName'] ?? ''));

                if ($ident === '' || $name !== '') {
                    continue;
                }

                $resolvedName = (string) ($nameByIdent[strtolower($ident)] ?? '');

                if ($resolvedName === '') {
                    continue;
                }

                $product['acName'] = $resolvedName;
                $product['label'] = $ident . ' - ' . $resolvedName;
            }
            unset($product);
        }

        usort($products, static function (array $left, array $right): int {
            return strcmp(
                strtolower((string) ($left['acIdentTrimmed'] ?? '')),
                strtolower((string) ($right['acIdentTrimmed'] ?? ''))
            );
        });

        if ($selectedIdent !== '') {
            $selectedKey = strtolower($selectedIdent);
            $selectedProducts = [];
            $otherProducts = [];

            foreach ($products as $product) {
                $productKey = strtolower((string) ($product['acIdentTrimmed'] ?? ''));

                if ($productKey === $selectedKey) {
                    $selectedProducts[] = $product;
                    continue;
                }

                $otherProducts[] = $product;
            }

            $products = array_merge($selectedProducts, $otherProducts);
        }

        return array_slice($products, 0, $limit);
    }

    private function fetchBomComponentsByProduct(string $productId, ?int $limit = null): array
    {
        $resolvedLimit = max(1, (int) ($limit ?? $this->bomLimit()));
        $structureColumns = $this->productStructureTableColumns();
        $selectColumns = $this->existingColumns($structureColumns, [
            'acIdentChild',
            'acDescr',
            'acUM',
            'anGrossQty',
            'acOperationType',
            'anNo',
            'acFieldSE',
            'acNote',
        ]);

        if (empty($selectColumns)) {
            $selectColumns = [
                'acIdentChild',
                'acDescr',
                'acUM',
                'anGrossQty',
                'acOperationType',
                'anNo',
            ];
        }

        $query = $this->newProductStructureTableQuery()
            ->select($selectColumns)
            ->where('acIdent', $productId)
            ->orderBy('anNo')
            ->limit($resolvedLimit);

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $trimmedProduct = trim($productId);

            if ($trimmedProduct !== '' && $trimmedProduct !== $productId) {
                $rows = $this->newProductStructureTableQuery()
                    ->select($selectColumns)
                    ->whereRaw('LTRIM(RTRIM(acIdent)) = ?', [$trimmedProduct])
                    ->orderBy('anNo')
                    ->limit($resolvedLimit)
                    ->get();
            }
        }

        return $rows
            ->map(function ($row) {
                $rowData = (array) $row;

                return [
                    'acIdentChild' => trim((string) ($rowData['acIdentChild'] ?? '')),
                    'acDescr' => trim((string) ($rowData['acDescr'] ?? '')),
                    'napomena' => $this->plannedConsumptionDisplayNote(
                        (string) $this->valueTrimmed($rowData, ['acFieldSE', 'acNote'], '')
                    ),
                    'acUM' => strtoupper(substr(trim((string) ($rowData['acUM'] ?? '')), 0, 3)),
                    'anGrossQty' => $this->toFloatOrNull($rowData['anGrossQty'] ?? null) ?? 0.0,
                    'acOperationType' => trim((string) ($rowData['acOperationType'] ?? '')),
                    'anNo' => (int) ($this->toFloatOrNull($rowData['anNo'] ?? null) ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function bomSelectionKey(int $lineNo, string $componentId): string
    {
        return $lineNo . '|' . strtolower(trim($componentId));
    }

    private function plannedConsumptionComponentSelectionKey(int $lineNo, string $componentId, string $rowUid = ''): string
    {
        $normalizedRowUid = strtolower(trim($rowUid));

        if ($normalizedRowUid !== '') {
            return 'row|' . $normalizedRowUid;
        }

        return $this->bomSelectionKey($lineNo, $componentId);
    }

    private function normalizePlannedConsumptionPosition(mixed $value): ?int
    {
        $position = $this->toFloatOrNull($value);

        if ($position === null || $position < 1) {
            return null;
        }

        $integerPosition = (int) $position;
        if (abs($position - $integerPosition) > 0.000001) {
            return null;
        }

        return $integerPosition;
    }

    private function findDuplicatePlannedConsumptionPositions(array $components): array
    {
        $counts = [];

        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }

            $position = $this->normalizePlannedConsumptionPosition($component['anNo'] ?? null);
            if ($position === null) {
                continue;
            }

            $counts[$position] = ($counts[$position] ?? 0) + 1;
        }

        $duplicates = [];

        foreach ($counts as $position => $count) {
            if ($count > 1) {
                $duplicates[] = (int) $position;
            }
        }

        sort($duplicates);

        return $duplicates;
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

    private function fetchOrdersLinkagePage(
        ?int $limit = null,
        ?int $page = null,
        array $filters = [],
        array $sort = []
    ): array {
        $resolvedLimit = $this->resolveLimit($limit);
        $resolvedPage = max(1, (int) ($page ?? 1));
        $totalQuery = $this->newOrderLinkageGroupQuery([]);
        $filteredQuery = $this->newOrderLinkageGroupQuery($filters);
        $total = $totalQuery === null ? 0 : (int) DB::query()->fromSub($totalQuery, 'order_linkage_total')->count();
        $filteredTotal = $filteredQuery === null ? 0 : (int) DB::query()->fromSub($filteredQuery, 'order_linkage_filtered')->count();
        $lastPage = $resolvedLimit > 0 ? max(1, (int) ceil($filteredTotal / $resolvedLimit)) : 1;

        if ($filteredQuery === null || $filteredTotal < 1) {
            return [
                'data' => [],
                'meta' => [
                    'count' => 0,
                    'page' => $resolvedPage,
                    'limit' => $resolvedLimit,
                    'total' => $total,
                    'filtered_total' => $filteredTotal,
                    'last_page' => $lastPage,
                ],
            ];
        }

        $pageGroupsQuery = DB::query()->fromSub($filteredQuery, 'order_linkage_page');
        $this->applyOrderLinkageGroupingOrdering($pageGroupsQuery, $sort);

        $pageGroups = $pageGroupsQuery
            ->forPage($resolvedPage, $resolvedLimit)
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();
        $data = array_map(function (array $row): array {
            return $this->mapOrderLinkageGroupRow($row);
        }, $pageGroups);

        return [
            'data' => $data,
            'meta' => [
                'count' => count($data),
                'page' => $resolvedPage,
                'limit' => $resolvedLimit,
                'total' => $total,
                'filtered_total' => $filteredTotal,
                'last_page' => $lastPage,
            ],
        ];
    }

    private function extractOrderLinkageFilters(Request $request): array
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

    private function extractOrderLinkageSort(Request $request): array
    {
        $sortBy = $this->normalizeOrderLinkageSortColumnAlias((string) $request->input('sort_by', ''));
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
                $sortBy = $this->normalizeOrderLinkageSortColumnAlias($columnData);
            }
        }

        return [
            'by' => $sortBy,
            'dir' => $sortDir,
        ];
    }

    private function normalizeOrderLinkageSortColumnAlias(string $sortBy): string
    {
        $normalized = strtolower(trim($sortBy));

        return match ($normalized) {
            'order_number', 'narudzba', 'broj_narudzbe' => 'narudzba',
            'naziv', 'name' => 'naziv',
            'sifra', 'code' => 'sifra',
            'customer', 'kupac', 'klijent', 'narucitelj', 'prijevoznik', 'posiljatelj' => 'klijent',
            'date', 'datum' => 'datum',
            'quantity', 'qty', 'kolicina', 'totalkolicina' => 'kolicina',
            'position_count', 'broj_pozicija' => 'broj_pozicija',
            'work_order_count', 'broj_rn', 'broj_radnih_naloga' => 'broj_rn',
            default => '',
        };
    }

    private function newOrderLinkageGroupQuery(array $filters = []): ?Builder
    {
        $baseQuery = $this->newOrderLinkageBaseQuery($filters);

        if ($baseQuery === null) {
            return null;
        }

        // SQL Server is much more stable when the normalized order number is aliased once in a derived table.
        $query = DB::query()->fromSub($baseQuery, 'order_linkage_source');
        $query->where('normalized_order_number', '<>', '');
        $this->applyOrderLinkageSourceQuickSearchFilter($query, (string) ($filters['search'] ?? ''));
        $query->select('normalized_order_number');
        $query->selectRaw('MAX(resolved_order_number) as narudzba');
        $query->selectRaw('MAX(naziv_source) as naziv_sort');
        $query->selectRaw('MAX(sifra_source) as sifra_sort');
        $query->selectRaw('MAX(klijent_source) as klijent_sort');
        $query->selectRaw('MIN(datum_source) as datum_sort');
        $query->selectRaw('SUM(COALESCE(quantity_source, 0)) as total_kolicina_sort');
        $query->selectRaw('SUM(CASE WHEN position_source IS NULL THEN 0 ELSE 1 END) as broj_pozicija_sort');
        $query->selectRaw('COUNT(*) as broj_rn_sort');
        $query->selectRaw('MAX(jedinica_source) as jedinica_sort');
        $query->groupBy('normalized_order_number');

        return $query;
    }

    private function newOrderLinkageBaseQuery(array $filters = []): ?Builder
    {
        $workOrderColumns = $this->tableColumns();
        $workOrderQuery = $this->newTableQuery();
        $baseFilters = $filters;
        $baseFilters['search'] = '';
        $this->applyFilters($workOrderQuery, $workOrderColumns, $baseFilters);

        $query = DB::query()->fromSub($workOrderQuery, 'wo');
        $orderColumns = $this->orderTableColumns();
        $workOrderLinkColumn = $this->firstExistingColumn($workOrderColumns, ['acLnkKey']);
        $orderKeyColumn = $this->firstExistingColumn($orderColumns, ['acKey']);
        $hasOrderJoin = $workOrderLinkColumn !== null && $orderKeyColumn !== null;

        if ($hasOrderJoin) {
            $query->leftJoin(
                $this->qualifiedOrderTableName() . ' as ord',
                'wo.' . $workOrderLinkColumn,
                '=',
                'ord.' . $orderKeyColumn
            );
        }

        $orderNumberExpression = $this->buildOrderLinkageResolvedOrderNumberExpression(
            $query,
            $workOrderColumns,
            $orderColumns,
            $hasOrderJoin
        );

        if ($orderNumberExpression === null) {
            return null;
        }

        $normalizedOrderExpression = $this->orderLinkageDisplaySqlExpression($orderNumberExpression);
        $senderColumns = $hasOrderJoin
            ? array_merge(
                $this->qualifyColumns($this->existingColumns($orderColumns, ['acConsignee', 'acReceiver', 'acPartner']), 'ord'),
                $this->qualifyColumns($this->existingColumns($workOrderColumns, ['acConsignee', 'acReceiver', 'acPartner']), 'wo')
            )
            : $this->qualifyColumns($this->existingColumns($workOrderColumns, ['acConsignee', 'acReceiver', 'acPartner']), 'wo');
        $customerExpression = $this->firstNonEmptyStringExpression($query, $senderColumns) ?? "''";
        $nameExpression = $this->firstNonEmptyStringExpression(
            $query,
            $this->qualifyColumns($this->existingColumns($workOrderColumns, ['acName', 'acDescr']), 'wo')
        ) ?? "''";
        $codeExpression = $this->firstNonEmptyStringExpression(
            $query,
            $this->qualifyColumns($this->existingColumns($workOrderColumns, ['acIdent', 'acCode']), 'wo')
        ) ?? "''";
        $unitExpression = $this->firstNonEmptyStringExpression(
            $query,
            $this->qualifyColumns($this->existingColumns($workOrderColumns, ['acUM']), 'wo'),
            32
        ) ?? "''";
        $createdDateColumn = $this->firstExistingColumn($workOrderColumns, ['adDate', 'adDateIns']);
        $quantityExpression = $this->firstNonNullNumericExpression(
            $query,
            $this->qualifyColumns($this->existingColumns($workOrderColumns, ['anPlanQty', 'anQty', 'anQty1']), 'wo')
        );
        $positionColumn = $this->firstExistingColumn($workOrderColumns, ['anLnkNo']);
        $statusExpression = $this->firstNonEmptyStringExpression(
            $query,
            $this->qualifyColumns($this->existingColumns($workOrderColumns, ['acStatusMF', 'acStatus']), 'wo'),
            64
        ) ?? "''";
        $identifierExpression = $this->firstNonEmptyStringExpression(
            $query,
            $this->qualifyColumns($this->existingColumns($workOrderColumns, ['acRefNo1', 'acKey', 'anNo', 'id']), 'wo')
        ) ?? "''";

        $query->selectRaw($normalizedOrderExpression . ' as normalized_order_number');
        $query->selectRaw($orderNumberExpression . ' as resolved_order_number');
        $query->selectRaw($nameExpression . ' as naziv_source');
        $query->selectRaw($codeExpression . ' as sifra_source');
        $query->selectRaw($customerExpression . ' as klijent_source');

        if ($createdDateColumn !== null) {
            $query->selectRaw('CAST(' . $query->getGrammar()->wrap('wo.' . $createdDateColumn) . ' AS DATE) as datum_source');
        } else {
            $query->selectRaw('NULL as datum_source');
        }

        if ($quantityExpression !== null) {
            $query->selectRaw($quantityExpression . ' as quantity_source');
        } else {
            $query->selectRaw('NULL as quantity_source');
        }

        if ($positionColumn !== null) {
            $query->selectRaw(
                "NULLIF(LTRIM(RTRIM(CAST(" . $query->getGrammar()->wrap('wo.' . $positionColumn) . " AS NVARCHAR(64)))), '') as position_source"
            );
        } else {
            $query->selectRaw('NULL as position_source');
        }

        $query->selectRaw($unitExpression . ' as jedinica_source');
        $query->selectRaw($statusExpression . ' as status_source');
        $query->selectRaw($identifierExpression . ' as work_order_id_source');

        return $query;
    }

    private function applyOrderLinkageGroupingOrdering(Builder $query, array $sort): void
    {
        $sortBy = $this->normalizeOrderLinkageSortColumnAlias((string) ($sort['by'] ?? ''));
        $direction = strtolower(trim((string) ($sort['dir'] ?? 'desc')));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        switch ($sortBy) {
            case 'narudzba':
                $query->orderBy('narudzba', $direction);
                return;
            case 'naziv':
                $query->orderByRaw("CASE WHEN NULLIF(LTRIM(RTRIM(COALESCE(naziv_sort, ''))), '') IS NULL THEN 1 ELSE 0 END ASC");
                $query->orderBy('naziv_sort', $direction);
                $query->orderBy('narudzba', $direction);
                return;
            case 'sifra':
                $query->orderByRaw("CASE WHEN NULLIF(LTRIM(RTRIM(COALESCE(sifra_sort, ''))), '') IS NULL THEN 1 ELSE 0 END ASC");
                $query->orderBy('sifra_sort', $direction);
                $query->orderBy('narudzba', $direction);
                return;
            case 'klijent':
                $query->orderByRaw("CASE WHEN NULLIF(LTRIM(RTRIM(COALESCE(klijent_sort, ''))), '') IS NULL THEN 1 ELSE 0 END ASC");
                $query->orderBy('klijent_sort', $direction);
                $query->orderBy('narudzba', $direction);
                return;
            case 'datum':
                $query->orderByRaw('CASE WHEN datum_sort IS NULL THEN 1 ELSE 0 END ASC');
                $query->orderBy('datum_sort', $direction);
                $query->orderBy('narudzba', $direction);
                return;
            case 'kolicina':
                $query->orderBy('total_kolicina_sort', $direction);
                $query->orderBy('narudzba', $direction);
                return;
            case 'broj_pozicija':
                $query->orderBy('broj_pozicija_sort', $direction);
                $query->orderBy('narudzba', $direction);
                return;
            case 'broj_rn':
                $query->orderBy('broj_rn_sort', $direction);
                $query->orderBy('narudzba', $direction);
                return;
            default:
                $query->orderByRaw('CASE WHEN datum_sort IS NULL THEN 1 ELSE 0 END ASC');
                $query->orderBy('datum_sort', 'desc');
                $query->orderBy('narudzba', 'desc');
                return;
        }
    }

    private function applyOrderLinkageSourceQuickSearchFilter(Builder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $orderNumberVariants = $this->normalizeComparableIdentifiers(
            $this->orderLinkageOrderNumberSearchVariants($search)
        );
        $tailSearch = $this->orderLinkageOrderNumberTailSearch($search);
        $allowFullOrderNumberMatch = $tailSearch === ''
            || preg_match('/\D/', $search) === 1;

        $query->where(function (Builder $searchQuery) use (
            $search,
            $orderNumberVariants,
            $tailSearch,
            $allowFullOrderNumberMatch
        ) {
            $hasAnyClause = false;

            if ($tailSearch !== '') {
                $tailLength = strlen($tailSearch);

                $searchQuery->whereRaw(
                    "LEN(normalized_order_number) = 12 AND SUBSTRING(normalized_order_number, 7, $tailLength) = ?",
                    [$tailSearch]
                );
                $hasAnyClause = true;
            }

            if ($allowFullOrderNumberMatch) {
                foreach ($orderNumberVariants as $orderNumberVariant) {
                    if ($hasAnyClause) {
                        $searchQuery->orWhere('normalized_order_number', 'like', '%' . $orderNumberVariant . '%');
                    } else {
                        $searchQuery->where('normalized_order_number', 'like', '%' . $orderNumberVariant . '%');
                        $hasAnyClause = true;
                    }
                }
            }

            if ($hasAnyClause) {
                $searchQuery->orWhere('klijent_source', 'like', '%' . $search . '%');
            } else {
                $searchQuery->where('klijent_source', 'like', '%' . $search . '%');
                $hasAnyClause = true;
            }

            if (!$hasAnyClause) {
                $searchQuery->whereRaw('1 = 0');
            }
        });
    }

    private function applyOrderLinkageFilters(
        Builder $query,
        array $orderColumns,
        array $filters,
        string $orderAlias = 'ord'
    ): void {
        $statusColumn = $this->firstExistingColumn($orderColumns, ['acStatusMF', 'acStatus', 'status']);

        if ($statusColumn !== null && !empty($filters['status'])) {
            $this->applyStatusFilter($query, $this->qualifyColumn($statusColumn, $orderAlias), (string) $filters['status']);
        }

        $this->applyLikeAny(
            $query,
            $this->qualifyColumns($this->existingColumns($orderColumns, ['acConsignee', 'acReceiver', 'acPartner']), $orderAlias),
            (string) ($filters['kupac'] ?? '')
        );

        $this->applyLikeAny(
            $query,
            $this->qualifyColumns($this->existingColumns($orderColumns, ['acReceiver', 'acConsignee', 'acPartner']), $orderAlias),
            (string) ($filters['primatelj'] ?? '')
        );

        $this->applyLikeAny(
            $query,
            $this->qualifyColumns($this->existingColumns($orderColumns, ['acKeyView', 'acRefNo1', 'acKey']), $orderAlias),
            (string) ($filters['vezni_dok'] ?? '')
        );

        $this->applyPriorityFilter(
            $query,
            $this->qualifyColumns($this->existingColumns($orderColumns, ['anPriority', 'acPriority', 'acWayOfSale', 'priority']), $orderAlias),
            (string) ($filters['prioritet'] ?? '')
        );

        $createdDateColumn = $this->firstExistingColumn($orderColumns, ['adDate', 'adDateIns']);
        $dueDateColumn = $this->firstExistingColumn($orderColumns, ['adDeliveryDeadline', 'adDateValid', 'adDateOut', 'adDateDoc']);

        $this->applyDateRangeFilter(
            $query,
            $createdDateColumn !== null ? $this->qualifyColumn($createdDateColumn, $orderAlias) : null,
            (string) ($filters['plan_pocetak_od'] ?? ''),
            (string) ($filters['plan_pocetak_do'] ?? '')
        );
        $this->applyDateRangeFilter(
            $query,
            $dueDateColumn !== null ? $this->qualifyColumn($dueDateColumn, $orderAlias) : null,
            (string) ($filters['plan_kraj_od'] ?? ''),
            (string) ($filters['plan_kraj_do'] ?? '')
        );
        $this->applyDateRangeFilter(
            $query,
            $createdDateColumn !== null ? $this->qualifyColumn($createdDateColumn, $orderAlias) : null,
            (string) ($filters['datum_od'] ?? ''),
            (string) ($filters['datum_do'] ?? '')
        );

        $productFilter = trim((string) ($filters['proizvod'] ?? ''));

        if ($productFilter !== '') {
            $this->applyOrderLinkageItemSearchExists($query, $orderColumns, $this->searchVariants($productFilter), $orderAlias);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $this->applyOrderLinkageQuickSearchFilter($query, $orderColumns, $search, $orderAlias);
        }
    }

    private function applyOrderLinkageQuickSearchFilter(
        Builder $query,
        array $orderColumns,
        string $search,
        string $orderAlias = 'ord'
    ): void {
        $searchVariants = array_values(array_filter($this->searchVariants($search), function ($value) {
            return trim((string) $value) !== '';
        }));

        if (empty($searchVariants)) {
            return;
        }

        $searchColumns = $this->qualifyColumns(
            $this->existingColumns($orderColumns, [
                'acConsignee',
                'acReceiver',
                'acPartner',
                'acStatusMF',
                'acStatus',
                'acPriority',
                'acWayOfSale',
                'priority',
            ]),
            $orderAlias
        );
        $identifierSearchColumns = $this->qualifyColumns(
            $this->existingColumns($orderColumns, ['acKeyView', 'acRefNo1', 'acKey']),
            $orderAlias
        );
        $statusColumn = $this->firstExistingColumn($orderColumns, ['acStatusMF', 'acStatus']);
        $priorityCodeColumns = $this->qualifyColumns($this->existingColumns($orderColumns, ['anPriority']), $orderAlias);
        $statusAliases = $this->statusAliasesForSearch($search);
        $priorityCodes = $this->priorityCodesForSearch($search);
        $orderNumberSearchVariants = $this->orderLinkageOrderNumberSearchVariants($search);
        $normalizedOrderNumberSearchVariants = $this->normalizeComparableIdentifiers($orderNumberSearchVariants);
        $orderNumberSuffixSearch = $this->orderLinkageOrderNumberSuffixSearch($search);

        $query->where(function (Builder $searchQuery) use (
            $searchColumns,
            $identifierSearchColumns,
            $statusColumn,
            $priorityCodeColumns,
            $statusAliases,
            $priorityCodes,
            $searchVariants,
            $normalizedOrderNumberSearchVariants,
            $orderNumberSuffixSearch,
            $orderColumns,
            $orderAlias
        ) {
            $hasAnyClause = false;

            foreach ($searchVariants as $variant) {
                foreach ($searchColumns as $column) {
                    if ($hasAnyClause) {
                        $searchQuery->orWhere($column, 'like', '%' . $variant . '%');
                    } else {
                        $searchQuery->where($column, 'like', '%' . $variant . '%');
                        $hasAnyClause = true;
                    }
                }
            }

            if ($orderNumberSuffixSearch !== '') {
                foreach ($identifierSearchColumns as $column) {
                    $displayExpression = $this->orderLinkageDisplayIdentifierExpression($searchQuery, $column);
                    $suffixLength = strlen($orderNumberSuffixSearch);

                    if ($hasAnyClause) {
                        $searchQuery->orWhereRaw(
                            "LEN($displayExpression) = 12 AND SUBSTRING($displayExpression, 7, $suffixLength) = ?",
                            [$orderNumberSuffixSearch]
                        );
                    } else {
                        $searchQuery->whereRaw(
                            "LEN($displayExpression) = 12 AND SUBSTRING($displayExpression, 7, $suffixLength) = ?",
                            [$orderNumberSuffixSearch]
                        );
                        $hasAnyClause = true;
                    }
                }
            }

            if ($orderNumberSuffixSearch === '') {
                foreach ($normalizedOrderNumberSearchVariants as $normalizedVariant) {
                    foreach ($identifierSearchColumns as $column) {
                        $displayExpression = $this->orderLinkageDisplayIdentifierExpression($searchQuery, $column);

                        if ($hasAnyClause) {
                            $searchQuery->orWhereRaw(
                                $displayExpression . ' like ?',
                                ['%' . $normalizedVariant . '%']
                            );
                        } else {
                            $searchQuery->whereRaw(
                                $displayExpression . ' like ?',
                                ['%' . $normalizedVariant . '%']
                            );
                            $hasAnyClause = true;
                        }
                    }
                }
            }

            if ($statusColumn !== null && !empty($statusAliases)) {
                $qualifiedStatusColumn = $this->qualifyColumn($statusColumn, $orderAlias);

                if ($hasAnyClause) {
                    $searchQuery->orWhereIn($qualifiedStatusColumn, $statusAliases);
                } else {
                    $searchQuery->whereIn($qualifiedStatusColumn, $statusAliases);
                    $hasAnyClause = true;
                }
            }

            if (!empty($priorityCodes)) {
                foreach ($priorityCodeColumns as $priorityCodeColumn) {
                    if ($hasAnyClause) {
                        $searchQuery->orWhereIn($priorityCodeColumn, $priorityCodes);
                    } else {
                        $searchQuery->whereIn($priorityCodeColumn, $priorityCodes);
                        $hasAnyClause = true;
                    }
                }
            }

            if ($orderNumberSuffixSearch === '' && $this->applyOrderLinkageItemSearchExists($searchQuery, $orderColumns, $searchVariants, $orderAlias, $hasAnyClause)) {
                $hasAnyClause = true;
            }

            if (!$hasAnyClause) {
                $searchQuery->whereRaw('1 = 0');
            }
        });
    }

    private function applyOrderLinkageItemSearchExists(
        Builder $query,
        array $orderColumns,
        array $searchVariants,
        string $orderAlias = 'ord',
        bool $useOrWhere = false
    ): bool {
        $searchVariants = array_values(array_unique(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $searchVariants), function ($value) {
            return $value !== '';
        })));

        if (empty($searchVariants)) {
            return false;
        }

        $itemColumns = $this->orderItemTableColumns();
        $itemSearchColumns = $this->qualifyColumns(
            $this->existingColumns($itemColumns, ['acIdent', 'acName', 'acDescr', 'acCode', 'product_code']),
            'oi'
        );
        $itemIdentifierColumns = $this->qualifyColumns(
            $this->existingColumns($itemColumns, ['acIdent', 'acCode', 'product_code']),
            'oi'
        );
        $normalizedSearchVariants = $this->normalizeComparableIdentifiers($searchVariants);

        if (empty($itemSearchColumns)) {
            return false;
        }

        $outerOrderKeyColumn = $this->firstExistingColumn($orderColumns, ['acKey']);
        $outerOrderNumberExpression = $this->firstNonEmptyStringExpression(
            $query,
            $this->qualifyColumns($this->existingColumns($orderColumns, ['acKeyView', 'acRefNo1', 'acKey']), $orderAlias)
        );
        $itemKeyColumns = $this->qualifyColumns(
            $this->existingColumns($itemColumns, ['acKey', 'acLnkKey', 'acOrderKey', 'order_key']),
            'oi'
        );
        $itemNumberColumns = $this->qualifyColumns(
            $this->existingColumns($itemColumns, ['acKeyView', 'acRefNo1', 'acOrderNo', 'order_number']),
            'oi'
        );
        $method = $useOrWhere ? 'orWhereExists' : 'whereExists';

        $query->{$method}(function (Builder $itemQuery) use (
            $searchVariants,
            $itemSearchColumns,
            $itemIdentifierColumns,
            $normalizedSearchVariants,
            $outerOrderKeyColumn,
            $orderAlias,
            $outerOrderNumberExpression,
            $itemKeyColumns,
            $itemNumberColumns
        ) {
            $itemQuery->selectRaw('1')->from($this->qualifiedOrderItemTableName() . ' as oi');
            $innerOrderNumberExpression = $this->firstNonEmptyStringExpression($itemQuery, $itemNumberColumns);

            $itemQuery->where(function (Builder $relationQuery) use (
                $outerOrderKeyColumn,
                $orderAlias,
                $outerOrderNumberExpression,
                $itemKeyColumns,
                $innerOrderNumberExpression
            ) {
                $hasRelation = false;

                if ($outerOrderKeyColumn !== null && !empty($itemKeyColumns)) {
                    foreach ($itemKeyColumns as $itemKeyColumn) {
                        if ($hasRelation) {
                            $relationQuery->orWhereColumn($itemKeyColumn, $this->qualifyColumn($outerOrderKeyColumn, $orderAlias));
                        } else {
                            $relationQuery->whereColumn($itemKeyColumn, $this->qualifyColumn($outerOrderKeyColumn, $orderAlias));
                            $hasRelation = true;
                        }
                    }
                }

                if ($outerOrderNumberExpression !== null && $innerOrderNumberExpression !== null) {
                    $comparison = $this->normalizedSqlExpression($innerOrderNumberExpression) . ' = ' . $this->normalizedSqlExpression($outerOrderNumberExpression);

                    if ($hasRelation) {
                        $relationQuery->orWhereRaw($comparison);
                    } else {
                        $relationQuery->whereRaw($comparison);
                        $hasRelation = true;
                    }
                }

                if (!$hasRelation) {
                    $relationQuery->whereRaw('1 = 0');
                }
            });

            $this->buildOrderLinkageItemSearchTextGroup(
                $itemQuery,
                $itemSearchColumns,
                $itemIdentifierColumns,
                $searchVariants,
                $normalizedSearchVariants
            );
        });

        return true;
    }

    private function buildOrderLinkageItemSearchTextGroup(
        Builder $query,
        array $itemSearchColumns,
        array $itemIdentifierColumns,
        array $searchVariants,
        array $normalizedSearchVariants
    ): void {
        $query->where(function (Builder $textQuery) use ($itemSearchColumns, $itemIdentifierColumns, $searchVariants, $normalizedSearchVariants) {
            $hasSearchClause = false;

            foreach ($searchVariants as $variant) {
                foreach ($itemSearchColumns as $itemSearchColumn) {
                    if ($hasSearchClause) {
                        $textQuery->orWhere($itemSearchColumn, 'like', '%' . $variant . '%');
                    } else {
                        $textQuery->where($itemSearchColumn, 'like', '%' . $variant . '%');
                        $hasSearchClause = true;
                    }
                }
            }

            foreach ($normalizedSearchVariants as $normalizedVariant) {
                foreach ($itemIdentifierColumns as $itemIdentifierColumn) {
                    if ($hasSearchClause) {
                        $textQuery->orWhereRaw(
                            $this->normalizedIdentifierExpression($textQuery, $itemIdentifierColumn) . ' like ?',
                            ['%' . $normalizedVariant . '%']
                        );
                    } else {
                        $textQuery->whereRaw(
                            $this->normalizedIdentifierExpression($textQuery, $itemIdentifierColumn) . ' like ?',
                            ['%' . $normalizedVariant . '%']
                        );
                        $hasSearchClause = true;
                    }
                }
            }

            if (!$hasSearchClause) {
                $textQuery->whereRaw('1 = 0');
            }
        });
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

        $digits = preg_replace('/\D+/', '', $rawValue);

        if (strlen($digits) === 13) {
            return substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6);
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
                return $this->fetchMappedMaterialsFromItems($workOrderKey);
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
                return $this->fetchMappedMaterialsFromItems($workOrderKey);
            }

            $query->where('r.' . $linkColumn, $workOrderKey);
        }

        foreach (['anWOExItemQId', 'anNo', 'anLineNo', 'anResNo', 'anVariant', 'adTimeIns'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy('r.' . $column);
            }
        }

        $resources = $query
            ->get()
            ->map(function ($row) {
                return $this->mapItemResourceRow((array) $row);
            })
            ->values()
            ->all();

        if (!empty($resources)) {
            $catalogMetaByIdent = $this->fetchCatalogMetaForComponentIds(array_map(function (array $resource) {
                return (string) ($resource['materijal'] ?? '');
            }, $resources));

            $filteredResources = array_values(array_filter($resources, function (array $resource) use ($catalogMetaByIdent) {
                $ident = strtolower(trim((string) ($resource['materijal'] ?? '')));
                if ($ident === '') {
                    return false;
                }

                $catalogSet = $catalogMetaByIdent[$ident]['set'] ?? '';
                $itemKind = $this->resolveItemKindForPreview('', (string) $catalogSet);

                return $itemKind === 'materials' || $itemKind === 'unknown';
            }));

            if (!empty($filteredResources)) {
                return $filteredResources;
            }
        }

        return $this->fetchMappedMaterialsFromItems($workOrderKey);
    }

    private function fetchMappedWorkOrderRegOperations(array $workOrderRow): array
    {
        $columns = $this->regOperationsTableColumns();
        $linkColumn = $this->firstExistingColumn($columns, ['acKey', 'acWOKey', 'acDocKey', 'acLnkKey']);

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
            $catalogMetaByIdent = $this->fetchCatalogMetaForComponentIds(array_map(function (array $operation) {
                return (string) ($operation['operacija'] ?? '');
            }, $operations));

            $filteredOperations = array_values(array_filter($operations, function (array $operation) use ($catalogMetaByIdent) {
                $ident = strtolower(trim((string) ($operation['operacija'] ?? '')));
                if ($ident === '') {
                    return false;
                }

                $catalogSet = $catalogMetaByIdent[$ident]['set'] ?? '';
                $itemKind = $this->resolveItemKindForPreview('O', (string) $catalogSet);

                return $itemKind === 'operations' || $itemKind === 'unknown';
            }));

            if (!empty($filteredOperations)) {
                return $filteredOperations;
            }
        }

        return $this->fetchMappedOperationsFromItems($workOrderKey);
    }

    private function fetchMappedOperationsFromItems(string $workOrderKey): array
    {
        $itemRows = $this->fetchWorkOrderItemRows($workOrderKey);
        if (empty($itemRows)) {
            return [];
        }

        $catalogMetaByIdent = $this->fetchCatalogMetaForComponentIds(array_map(function (array $row) {
            return (string) ($row['acIdent'] ?? '');
        }, $itemRows));

        $operations = [];
        foreach ($itemRows as $row) {
            $ident = trim((string) ($row['acIdent'] ?? ''));
            $catalogSet = $catalogMetaByIdent[strtolower($ident)]['set'] ?? '';
            $itemKind = $this->resolveItemKindForPreview(
                (string) ($row['acOperationType'] ?? ''),
                (string) $catalogSet
            );

            if ($itemKind !== 'operations') {
                continue;
            }

            $operations[] = $this->mapOperationFromItemRow($row);
        }

        return array_values($operations);
    }

    private function fetchMappedMaterialsFromItems(string $workOrderKey): array
    {
        $itemRows = $this->fetchWorkOrderItemRows($workOrderKey);
        if (empty($itemRows)) {
            return [];
        }

        $catalogMetaByIdent = $this->fetchCatalogMetaForComponentIds(array_map(function (array $row) {
            return (string) ($row['acIdent'] ?? '');
        }, $itemRows));

        $materials = [];
        foreach ($itemRows as $row) {
            $ident = trim((string) ($row['acIdent'] ?? ''));
            $catalogSet = $catalogMetaByIdent[strtolower($ident)]['set'] ?? '';
            $itemKind = $this->resolveItemKindForPreview(
                (string) ($row['acOperationType'] ?? ''),
                (string) $catalogSet
            );

            if ($itemKind !== 'materials') {
                continue;
            }

            $materials[] = $this->mapMaterialFromItemRow($row);
        }

        return array_values($materials);
    }

    private function findExistingMaterialItemForWorkOrder(
        string $workOrderKey,
        string $materialCode,
        string $catalogSet = ''
    ): ?array {
        $matches = $this->findExistingMaterialItemsByCodes(
            $workOrderKey,
            [$materialCode],
            [strtolower(trim($materialCode)) => strtoupper(trim($catalogSet))]
        );

        $key = strtolower(trim($materialCode));
        return $matches[$key] ?? null;
    }

    private function findExistingMaterialItemsForBarcodeSave(string $workOrderKey, array $resolvedRows): array
    {
        $materialCodes = [];
        $catalogSetsByCode = [];

        foreach ($resolvedRows as $resolvedRow) {
            $requestedRow = is_array($resolvedRow['requested'] ?? null) ? $resolvedRow['requested'] : [];
            $materialCode = trim((string) ($requestedRow['acIdentChild'] ?? ''));
            $catalogSet = strtoupper(trim((string) ($requestedRow['catalog_set'] ?? '')));

            if ($materialCode === '') {
                continue;
            }

            if ($this->resolveItemKindForPreview(
                (string) ($requestedRow['acOperationType'] ?? ''),
                $catalogSet
            ) !== 'materials') {
                continue;
            }

            $materialCodes[] = $materialCode;
            $catalogSetsByCode[strtolower($materialCode)] = $catalogSet;
        }

        return $this->findExistingMaterialItemsByCodes($workOrderKey, $materialCodes, $catalogSetsByCode);
    }

    private function findExistingMaterialItemsByCodes(
        string $workOrderKey,
        array $materialCodes,
        array $catalogSetsByCode = []
    ): array {
        $normalizedCodes = array_values(array_unique(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $materialCodes), function ($value) {
            return $value !== '';
        })));

        if ($workOrderKey === '' || empty($normalizedCodes)) {
            return [];
        }

        $columns = $this->itemTableColumns();
        if (!in_array('acKey', $columns, true) || !in_array('acIdent', $columns, true)) {
            return [];
        }

        $query = $this->newItemTableQuery()
            ->where('acKey', $workOrderKey)
            ->whereIn('acIdent', $normalizedCodes);

        foreach (['anNo', 'anQId', 'adTimeIns'] as $column) {
            if (in_array($column, $columns, true)) {
                $query->orderBy($column);
            }
        }

        $rows = $query
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();

        if (empty($rows)) {
            return [];
        }

        $matches = [];
        foreach ($rows as $row) {
            $materialCode = trim((string) ($row['acIdent'] ?? ''));
            $materialKey = strtolower($materialCode);

            if ($materialKey === '' || array_key_exists($materialKey, $matches)) {
                continue;
            }

            $catalogSet = strtoupper(trim((string) ($catalogSetsByCode[$materialKey] ?? '')));
            $itemKind = $this->resolveItemKindForPreview((string) ($row['acOperationType'] ?? ''), $catalogSet);

            if ($itemKind !== 'materials') {
                continue;
            }

            $matches[$materialKey] = $row;
        }

        return $matches;
    }

    private function workOrderItemQuantity(array $row): float
    {
        return (float) ($this->toFloatOrNull(
            $row['anPlanQty'] ?? $row['anQty'] ?? $row['anQty1'] ?? 0
        ) ?? 0);
    }

    private function buildRemovedPlannedConsumptionStockAdjustment(
        array $row,
        string $preferredWarehouse = '',
        ?float $valueOverride = null
    ): ?array
    {
        $materialCode = trim((string) ($row['acIdent'] ?? ''));
        if ($materialCode === '') {
            return null;
        }

        $itemKind = $this->resolveItemKindForPreview(
            (string) ($row['acOperationType'] ?? ''),
            ''
        );

        if ($itemKind !== 'materials') {
            return null;
        }

        $value = $valueOverride;

        if ($value === null) {
            $removedQty = $this->workOrderItemQuantity($row);
            if ($removedQty <= 0) {
                return null;
            }

            $value = -1 * $removedQty;
        }

        if (abs($value) <= 0.000001) {
            return null;
        }

        return [
            'material_code' => $materialCode,
            'value' => $value,
            'warehouse' => trim($preferredWarehouse),
        ];
    }

    private function resolveWorkOrderWarehouse(array $workOrderRow): string
    {
        return trim((string) $this->valueTrimmed(
            $workOrderRow,
            ['acWarehouse', 'linked_document', 'acWarehouseFrom'],
            ''
        ));
    }

    private function buildPlannedConsumptionStockAdjustments(array $savedRows): array
    {
        $adjustmentsByCode = [];

        foreach ($savedRows as $savedRow) {
            $itemKind = strtolower(trim((string) ($savedRow['item_kind'] ?? '')));
            if ($itemKind !== 'materials') {
                continue;
            }

            $materialCode = trim((string) ($savedRow['acIdent'] ?? ''));
            if ($materialCode === '') {
                continue;
            }

            $consumedQty = (float) ($this->toFloatOrNull(
                $savedRow['stock_consumed_qty'] ?? null
            ) ?? 0);

            if (abs($consumedQty) <= 0.000001) {
                continue;
            }

            $materialKey = strtolower($materialCode);
            if (!array_key_exists($materialKey, $adjustmentsByCode)) {
                $adjustmentsByCode[$materialKey] = [
                    'material_code' => $materialCode,
                    'value' => 0.0,
                ];
            }

            $adjustmentsByCode[$materialKey]['value'] += $consumedQty;
        }

        return array_values($adjustmentsByCode);
    }

    private function workOrderItemStatementAdjustsStock(string $statement): bool
    {
        $normalized = strtoupper(trim($statement));

        if ($normalized === '') {
            return true;
        }

        return in_array($normalized, [
            'PLANNED_BOM',
            'PLANNED_RAW',
            'PLANNED_BARCODE',
            'PLANNED_BARCODE_UPDATE',
        ], true);
    }

    private function workOrderItemRowShouldRestoreStockOnRemove(array $row, bool $hasStatementColumn): bool
    {
        if (!$hasStatementColumn) {
            return true;
        }

        $statement = strtoupper(trim((string) ($row['acStatement'] ?? '')));

        if ($statement === '') {
            return true;
        }

        if (in_array($statement, ['PLANNED_BOM_PENDING', 'PLANNED_RAW_PENDING'], true)) {
            return false;
        }

        return $this->workOrderItemStatementAdjustsStock($statement);
    }

    private function fetchWorkOrderItemRows(string $workOrderKey): array
    {
        $columns = $this->itemTableColumns();

        if (!in_array('acKey', $columns, true)) {
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
                return (array) $row;
            })
            ->values()
            ->all();
    }

    private function findWorkOrderItemRow(string $workOrderKey, array $columns, ?float $itemId, ?float $itemNo): ?array
    {
        if ($workOrderKey === '' || !in_array('acKey', $columns, true)) {
            return null;
        }

        $query = $this->newItemTableQuery()->where('acKey', $workOrderKey);
        $hasQId = in_array('anQId', $columns, true);
        $hasNo = in_array('anNo', $columns, true);

        if ($itemId !== null && $hasQId) {
            $query->where('anQId', (int) $itemId);
        } elseif ($itemNo !== null && $hasNo) {
            $query->where('anNo', (int) $itemNo);
        } else {
            return null;
        }

        $row = $query->first();

        return $row ? (array) $row : null;
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
            $productCode = trim((string) $productCodeRaw);

            if ($productCode === '') {
                return null;
            }

            $parsed['product_code'] = $productCode;
        }

        return $parsed;
    }

    private function findWorkOrderRowByOrderLocator(
        string $normalizedOrderNumber,
        int $orderPosition,
        ?string $productCode = null
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

            if ($productCode !== null && trim($productCode) !== '' && $workOrderProductColumn !== null) {
                $this->applyProductCodeMatchFilter($query, ['wo.' . $workOrderProductColumn], $productCode);
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
                'product_code' => $productCode,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function findOrderRowByLocator(
        string $normalizedOrderNumber,
        int $orderPosition,
        ?string $productCode = null
    ): ?array {
        if ($normalizedOrderNumber === '') {
            return null;
        }

        try {
            $orderColumns = $this->orderTableColumns();
            $orderNumberColumns = $this->existingColumns($orderColumns, ['acKeyView', 'acRefNo1', 'acKey']);
            $orderPositionColumns = $this->existingColumns($orderColumns, ['anLnkNo', 'anLineNo', 'anItemNo', 'anPosition', 'anPos', 'anPosNo']);
            $orderProductColumns = $this->existingColumns($orderColumns, ['acIdent', 'product_code', 'acCode']);

            if (empty($orderNumberColumns)) {
                return null;
            }

            $query = $this->newOrderTableQuery()
                ->where(function (Builder $orderNumberQuery) use ($orderNumberColumns, $normalizedOrderNumber) {
                    foreach ($orderNumberColumns as $index => $orderNumberColumn) {
                        $normalizedExpression = $this->normalizedIdentifierExpression($orderNumberQuery, $orderNumberColumn);
                        $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                        $orderNumberQuery->{$method}("$normalizedExpression = ?", [$normalizedOrderNumber]);
                    }
                });

            if (!empty($orderPositionColumns)) {
                $query->where(function (Builder $positionQuery) use ($orderPositionColumns, $orderPosition) {
                    foreach ($orderPositionColumns as $index => $orderPositionColumn) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $positionQuery->{$method}($orderPositionColumn, $orderPosition);
                    }
                });
            }

            if ($productCode !== null && trim($productCode) !== '' && !empty($orderProductColumns)) {
                $this->applyProductCodeMatchFilter($query, $orderProductColumns, $productCode);
            }

            foreach (['adDate', 'adDateIns', 'adTimeIns', 'anNo', 'anQId'] as $orderByColumn) {
                if (in_array($orderByColumn, $orderColumns, true)) {
                    $query->orderByDesc($orderByColumn);
                }
            }

            $row = $query->first();

            return $row ? (array) $row : null;
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve order row for scanned work order QR.', [
                'connection' => config('database.default'),
                'orders_table' => $this->qualifiedOrderTableName(),
                'order_number' => $normalizedOrderNumber,
                'order_position' => $orderPosition,
                'product_code' => $productCode,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function findOrderItemRowByOrderContext(array $orderContext): ?array
    {
        $normalizedOrderKey = $this->normalizeComparableIdentifier((string) ($orderContext['order_key'] ?? ''));

        if ($normalizedOrderKey === '') {
            $normalizedOrderKey = $this->normalizeComparableIdentifier((string) ($orderContext['order_number'] ?? ''));
        }

        if ($normalizedOrderKey === '') {
            return null;
        }

        $orderPosition = (int) ($orderContext['order_position'] ?? 0);
        $productCode = trim((string) ($orderContext['product_code'] ?? ''));

        try {
            $orderItemColumns = $this->orderItemTableColumns();
            $orderKeyColumns = $this->existingColumns($orderItemColumns, ['acKey', 'acLnkKey', 'acOrderKey', 'order_key']);
            $orderPositionColumns = $this->existingColumns($orderItemColumns, ['anNo', 'anLineNo', 'anItemNo', 'anPosition', 'anPos', 'anPosNo']);
            $orderProductColumns = $this->existingColumns($orderItemColumns, ['acIdent', 'product_code', 'acCode']);

            if (empty($orderKeyColumns)) {
                return null;
            }

            $query = $this->newOrderItemTableQuery()
                ->where(function (Builder $orderKeyQuery) use ($orderKeyColumns, $normalizedOrderKey) {
                    foreach ($orderKeyColumns as $index => $orderKeyColumn) {
                        $normalizedExpression = $this->normalizedIdentifierExpression($orderKeyQuery, $orderKeyColumn);
                        $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                        $orderKeyQuery->{$method}("$normalizedExpression = ?", [$normalizedOrderKey]);
                    }
                });

            if ($orderPosition > 0 && !empty($orderPositionColumns)) {
                $query->where(function (Builder $positionQuery) use ($orderPositionColumns, $orderPosition) {
                    foreach ($orderPositionColumns as $index => $orderPositionColumn) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $positionQuery->{$method}($orderPositionColumn, $orderPosition);
                    }
                });
            }

            if ($productCode !== '' && !empty($orderProductColumns)) {
                $this->applyProductCodeMatchFilter($query, $orderProductColumns, $productCode);
            }

            foreach (['adDate', 'adDateIns', 'adTimeIns', 'anNo', 'anQId'] as $orderByColumn) {
                if (in_array($orderByColumn, $orderItemColumns, true)) {
                    $query->orderByDesc($orderByColumn);
                }
            }

            $row = $query->first();

            return $row ? (array) $row : null;
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve order item row for scanned work order QR.', [
                'connection' => config('database.default'),
                'order_items_table' => $this->qualifiedOrderItemTableName(),
                'order_key' => $normalizedOrderKey,
                'order_position' => $orderPosition,
                'product_code' => $productCode,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function applyProductCodeMatchFilter(Builder $query, array $columnIdentifiers, string $productCode): void
    {
        $productCode = trim($productCode);

        if ($productCode === '' || empty($columnIdentifiers)) {
            return;
        }

        $exactProductCode = $this->normalizeProductCodeExact($productCode);

        $query->where(function (Builder $productQuery) use ($columnIdentifiers, $exactProductCode): void {
            $hasClause = false;

            foreach ($columnIdentifiers as $columnIdentifier) {
                $exactExpression = $this->productCodeExactExpression($productQuery, $columnIdentifier);

                if ($hasClause) {
                    $productQuery->orWhereRaw("$exactExpression = ?", [$exactProductCode]);
                } else {
                    $productQuery->whereRaw("$exactExpression = ?", [$exactProductCode]);
                    $hasClause = true;
                }
            }

            if (!$hasClause) {
                $productQuery->whereRaw('1 = 0');
            }
        });
    }

    private function buildOrderLocatorContext(array $orderRow, array $orderLocator): array
    {
        $scannedProductCode = trim((string) ($orderLocator['product_code'] ?? ''));
        $catalogRow = $scannedProductCode !== ''
            ? $this->findCatalogItemByProductCode($scannedProductCode)
            : [];
        $productCode = $scannedProductCode;

        if ($productCode === '') {
            $productCode = (string) $this->valueTrimmed($orderRow, ['acIdent', 'product_code', 'acCode'], '');
        }

        $productName = (string) $this->valueTrimmed($orderRow, ['acName', 'acDescr', 'title'], '');

        if ($productName === '') {
            $productName = trim((string) ($catalogRow['acName'] ?? ''));
        }

        $context = [
            'order_key' => trim((string) $this->valueTrimmed($orderRow, ['acKey'], '')),
            'order_number' => $this->resolveOrderDisplayNumber($orderRow, (string) ($orderLocator['order_number'] ?? '')),
            'order_position' => (int) ($orderLocator['order_position'] ?? 0),
            'product_code' => $productCode,
            'product_name' => $productName,
            'client_name' => (string) $this->valueTrimmed($orderRow, ['acConsignee', 'acReceiver', 'acPartner', 'client_name'], ''),
            'catalog' => $catalogRow,
            'catalog_item_missing' => $productCode !== '' && empty($catalogRow),
        ];
        $dateContext = $this->resolveScanCreateDateContext($orderRow, $context);
        $context['delivery_date'] = $dateContext['delivery_date'];
        $context['delivery_date_display'] = $dateContext['delivery_date_display'];
        $context['projected_issue_date'] = $dateContext['projected_issue_date'];
        $context['projected_issue_date_display'] = $dateContext['projected_issue_date_display'];
        $context['unit'] = $this->resolveScanCreateWorkOrderUnit($orderRow, $context);
        $context['quantity'] = $this->resolveScanCreateWorkOrderQuantity($orderRow, $context);
        $context['catalog_item_notice'] = $context['catalog_item_missing']
            ? 'Šifra artikla nije pronađena u šifrarniku. Prilikom kreiranja RN bit će automatski kreirana osnovna stavka artikla.'
            : '';

        return $context;
    }

    private function resolveScanCreateDateContext(array $orderRow, array $orderContext): array
    {
        $datePlan = $this->resolveScanCreateDatePlan($orderRow, $orderContext);
        $deliveryDate = $datePlan['delivery_date'] instanceof Carbon
            ? $datePlan['delivery_date']->copy()->startOfDay()
            : null;
        $projectedIssueDate = $deliveryDate !== null && $datePlan['projected_issue_date'] instanceof Carbon
            ? $datePlan['projected_issue_date']->copy()->startOfDay()
            : null;

        return [
            'delivery_date' => $deliveryDate?->format('Y-m-d'),
            'delivery_date_display' => $deliveryDate?->format('d.m.Y') ?? '',
            'projected_issue_date' => $projectedIssueDate?->format('Y-m-d'),
            'projected_issue_date_display' => $projectedIssueDate?->format('d.m.Y') ?? '',
        ];
    }

    private function resolveScanCreateDatePlan(array $orderRow, array $orderContext): array
    {
        $now = Carbon::now();
        $deliveryDate = $this->resolveScanCreateDeliveryDateCarbon($orderRow, $orderContext);
        $deliveryDay = $deliveryDate?->copy()->startOfDay();
        $projectedIssueDate = $deliveryDay !== null
            ? $deliveryDay->copy()->subDays(14)->startOfDay()
            : $now->copy()->startOfDay();

        return [
            'delivery_date' => $deliveryDay,
            'projected_issue_date' => $projectedIssueDate,
            'scheduled_start' => $projectedIssueDate->copy()->setTime(8, 0, 0),
            'scheduled_end' => $deliveryDay !== null
                ? $deliveryDay->copy()->endOfDay()
                : $now->copy()->endOfDay(),
        ];
    }

    private function resolveScanCreateDeliveryDateCarbon(array $orderRow, array $orderContext): ?Carbon
    {
        $dateColumns = [
            'adDeliveryDeadline',
            'adDateOut',
            'adDueDate',
            'adDeliveryDate',
            'adShipDate',
            'adDateShip',
            'delivery_date',
            'due_date',
        ];

        $resolvedOrderDate = $this->resolveFirstAvailableDateFromRow($orderRow, $dateColumns);
        if ($resolvedOrderDate !== null) {
            return $resolvedOrderDate;
        }

        $orderItemRow = $this->findOrderItemRowByOrderContext($orderContext);
        if (!is_array($orderItemRow)) {
            return null;
        }

        return $this->resolveFirstAvailableDateFromRow($orderItemRow, $dateColumns);
    }

    private function resolveFirstAvailableDateFromRow(array $row, array $columns): ?Carbon
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            $normalizedDate = $this->normalizeDate($row[$column]);
            if ($normalizedDate === null) {
                continue;
            }

            try {
                return Carbon::parse($normalizedDate)->startOfDay();
            } catch (Throwable $exception) {
                continue;
            }
        }

        return null;
    }

    private function ensureCatalogItemForScanCreate(
        array $orderContext,
        array $orderRow,
        mixed $user = null
    ): array {
        $productCode = trim((string) ($orderContext['product_code'] ?? ''));

        if ($productCode === '') {
            return [
                'created' => false,
                'order_context' => $orderContext,
            ];
        }

        $catalogRow = $productCode !== ''
            ? $this->findCatalogItemByProductCode($productCode)
            : [];

        if (!empty($catalogRow)) {
            $orderContext['catalog'] = $catalogRow;
            $orderContext['catalog_item_missing'] = false;
            $orderContext['catalog_item_notice'] = '';

            if (trim((string) ($orderContext['product_name'] ?? '')) === '') {
                $orderContext['product_name'] = trim((string) ($catalogRow['acName'] ?? $productCode));
            }

            $orderContext['unit'] = $this->resolveScanCreateWorkOrderUnit($orderRow, $orderContext);

            return [
                'created' => false,
                'order_context' => $orderContext,
            ];
        }

        $userId = is_object($user) ? (int) ($user->id ?? 0) : 0;
        $ensureResult = Product::ensureCatalogProduct([
            'product_code' => $productCode,
            'product_name' => trim((string) ($orderContext['product_name'] ?? '')),
            'product_um' => $this->resolveScanCreateWorkOrderUnit($orderRow, $orderContext),
            'product_set' => trim((string) ($orderContext['catalog']['acSetOfItem'] ?? $this->valueTrimmed($orderRow, ['acSetOfItem'], ''))),
        ], $userId);

        $catalogRow = is_array($ensureResult['row'] ?? null)
            ? (array) ($ensureResult['row'] ?? [])
            : [];

        if (empty($catalogRow) && $productCode !== '') {
            $catalogRow = $this->findCatalogItemByProductCode($productCode);
        }

        $orderContext['catalog'] = $catalogRow;
        $orderContext['catalog_item_missing'] = empty($catalogRow);
        $orderContext['catalog_item_notice'] = '';
        $orderContext['unit'] = $this->resolveScanCreateWorkOrderUnit($orderRow, $orderContext);

        if (trim((string) ($orderContext['product_name'] ?? '')) === '') {
            $orderContext['product_name'] = trim((string) ($catalogRow['acName'] ?? $productCode));
        }

        return [
            'created' => (bool) ($ensureResult['created'] ?? false),
            'order_context' => $orderContext,
        ];
    }

    private function resolveRequestedScanCreateDocType(mixed $value): string
    {
        $docType = trim((string) $value);

        if ($docType === '') {
            return self::DEFAULT_SCAN_CREATE_DOC_TYPE;
        }

        return in_array($docType, self::SCAN_CREATE_DOC_TYPES, true)
            ? $docType
            : self::DEFAULT_SCAN_CREATE_DOC_TYPE;
    }

    private function buildScanCreateDocTypeOptions(Carbon $now): array
    {
        $options = [];

        foreach (self::SCAN_CREATE_DOC_TYPES as $docType) {
            $numberContext = $this->generateNextWorkOrderNumber($now, $docType);
            $options[$docType] = [
                'value' => $docType,
                'label' => $docType,
                'last_work_order' => [
                    'number' => (string) ($numberContext['last_display'] ?? ''),
                    'number_raw' => (string) ($numberContext['last_raw'] ?? ''),
                ],
                'next_work_order' => [
                    'number' => (string) ($numberContext['next_display'] ?? ''),
                    'number_raw' => (string) ($numberContext['next_raw'] ?? ''),
                ],
            ];
        }

        return $options;
    }

    private function resolveOrderDisplayNumber(array $orderRow, string $fallbackNormalizedOrderNumber): string
    {
        foreach (['acKeyView', 'acRefNo1', 'acKey'] as $candidateColumn) {
            if (!array_key_exists($candidateColumn, $orderRow)) {
                continue;
            }

            $candidateValue = trim((string) ($orderRow[$candidateColumn] ?? ''));

            if ($candidateValue === '') {
                continue;
            }

            if ($this->normalizeComparableIdentifier($candidateValue) === $fallbackNormalizedOrderNumber) {
                return $candidateValue;
            }
        }

        $displayNumber = trim((string) $this->valueTrimmed($orderRow, ['acKeyView', 'acRefNo1', 'acKey'], ''));

        return $displayNumber !== '' ? $displayNumber : $fallbackNormalizedOrderNumber;
    }

    private function findCatalogItemByProductCode(string $productCode): array
    {
        $productCode = trim($productCode);

        if ($productCode === '') {
            return [];
        }

        try {
            $query = $this->newCatalogItemsTableQuery();
            $exactProductCode = $this->normalizeProductCodeExact($productCode);
            $exactExpression = $this->productCodeExactExpression($query, 'acIdent');
            $row = $query
                ->whereRaw("$exactExpression = ?", [$exactProductCode])
                ->first();
            $catalogRow = $row === null ? [] : (array) $row;
            $catalogProduct = Product::findCatalogProduct($productCode);

            if ($catalogProduct === null) {
                return $catalogRow;
            }

            if (empty($catalogRow)) {
                return $catalogProduct;
            }

            return array_merge($catalogProduct, $catalogRow);
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve catalog item for scanned work order QR.', [
                'connection' => config('database.default'),
                'catalog_items_table' => $this->qualifiedCatalogItemsTableName(),
                'product_code' => $productCode,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function generateNextWorkOrderNumber(Carbon $now, string $docType = ''): array
    {
        $columns = $this->tableColumns();
        $identifierColumn = $this->firstExistingColumn($columns, ['acRefNo1', 'acKey']);
        $yearPrefix = $now->format('y');
        $resolvedDocType = $this->resolveRequestedScanCreateDocType($docType);
        $prefix = $yearPrefix . $resolvedDocType;
        $sequenceLength = $this->resolveScanCreateSequenceLength($yearPrefix, $resolvedDocType);
        $lastRaw = '';
        $lastDisplay = '';
        $lastSequence = 0;

        if ($identifierColumn !== null) {
            $query = $this->newTableQuery()->select([$identifierColumn]);
            $normalizedExpression = $this->normalizedIdentifierExpression($query, $identifierColumn);
            $query->whereRaw("$normalizedExpression LIKE ?", [$prefix . '%']);
            $this->applyWorkOrderIdentifierOrdering($query, $columns, 'desc');
            $lastRow = $query->first();

            if ($lastRow !== null) {
                $lastRaw = trim((string) ($lastRow->{$identifierColumn} ?? ''));
                $lastDigits = preg_replace('/\D+/', '', $lastRaw);
                $lastDigits = is_string($lastDigits) ? $lastDigits : '';

                if ($lastDigits !== '' && str_starts_with($lastDigits, $prefix)) {
                    $suffixDigits = substr($lastDigits, strlen($prefix));
                    $suffixDigits = is_string($suffixDigits) ? $suffixDigits : '';
                    $lastSequence = (int) substr($suffixDigits, 0, $sequenceLength);
                    $lastDisplay = $this->formatWorkOrderNumberForCalendar($lastRaw);
                }
            }
        }

        $nextSequence = $lastSequence + 1;
        $nextRaw = $prefix . str_pad((string) $nextSequence, $sequenceLength, '0', STR_PAD_LEFT);

        return [
            'year_prefix' => $yearPrefix,
            'doc_type' => $resolvedDocType,
            'last_raw' => $lastRaw,
            'last_display' => $lastDisplay,
            'next_raw' => $nextRaw,
            'next_display' => $this->formatWorkOrderNumberForCalendar($nextRaw),
            'next_sequence' => $nextSequence,
        ];
    }

    private function resolveScanCreateSequenceLength(string $yearPrefix, string $docType): int
    {
        $identifierLength = $this->tableStringLength('acKey');

        if ($identifierLength !== null) {
            $calculatedLength = $identifierLength - strlen($yearPrefix) - strlen($docType);

            if ($calculatedLength > 0) {
                return $calculatedLength;
            }
        }

        return self::DEFAULT_SCAN_CREATE_SEQUENCE_LENGTH;
    }

    private function buildWorkOrderInsertPayloadFromOrder(
        array $orderRow,
        array $orderContext,
        array $numberContext,
        mixed $user = null,
        ?float $requestedQuantity = null
    ): array {
        $columns = $this->tableColumns();
        $nonInsertableColumns = $this->tableNonInsertableColumns();
        $excludedCopyColumns = [
            'id',
            'acKey',
            'acKeyView',
            'acRefNo1',
            'acLnkKey',
            'acLnkKeyView',
            'anLnkNo',
            'anNo',
            'anQId',
            'acDocType',
            'acDocTypeView',
            'acIdent',
            'acCode',
            'product_code',
            'acName',
            'acDescr',
            'title',
            'acStatusMF',
            'acStatus',
            'status',
            'acCreateFrom',
            'adDate',
            'adLnkDate',
            'adDateIns',
            'adTimeIns',
            'adTimeChg',
            'adSchedStartTime',
            'adSchedEndTime',
            'adDeliveryDeadline',
            'adDateOut',
            'anUserIns',
            'anUserChg',
            'created_at',
            'updated_at',
        ];
        $payload = [];

        foreach ($columns as $column) {
            if (!array_key_exists($column, $orderRow)) {
                continue;
            }

            if (in_array($column, $nonInsertableColumns, true) || in_array($column, $excludedCopyColumns, true)) {
                continue;
            }

            $payload[$column] = $orderRow[$column];
        }

        $now = Carbon::now();
        $priorityLabel = (string) ($this->deliveryPriorityMap()[5] ?? '5 - Uobicajeni prioritet');
        $productCode = trim((string) ($orderContext['product_code'] ?? ''));
        $productName = trim((string) ($orderContext['product_name'] ?? ''));
        $orderNumber = trim((string) ($orderContext['order_number'] ?? ''));
        $orderKey = trim((string) ($orderContext['order_key'] ?? ''));
        $docType = $this->resolveRequestedScanCreateDocType($numberContext['doc_type'] ?? self::DEFAULT_SCAN_CREATE_DOC_TYPE);
        $username = is_object($user) ? trim((string) ($user->username ?? $user->name ?? '')) : '';
        $userId = is_object($user) ? (int) ($user->id ?? 0) : 0;
        $resolvedUnit = $this->resolveScanCreateWorkOrderUnit($orderRow, $orderContext);
        $resolvedQuantity = $requestedQuantity !== null && $requestedQuantity > 0
            ? $requestedQuantity
            : $this->resolveScanCreateWorkOrderQuantity($orderRow, $orderContext);
        $datePlan = $this->resolveScanCreateDatePlan($orderRow, $orderContext);
        $dayStart = $now->copy()->startOfDay();
        $plannedStart = $datePlan['scheduled_start'] instanceof Carbon
            ? $datePlan['scheduled_start']->copy()
            : $dayStart->copy();
        $plannedEnd = $datePlan['scheduled_end'] instanceof Carbon
            ? $datePlan['scheduled_end']->copy()
            : $now->copy()->endOfDay();

        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acKey', (string) ($numberContext['next_raw'] ?? ''));
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acKeyView', (string) ($numberContext['next_display'] ?? ''));
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acRefNo1', (string) ($numberContext['next_display'] ?? ''));
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acDocType', $docType);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acDocTypeView', $docType);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acLnkKey', $orderKey);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acLnkKeyView', $orderNumber !== '' ? $orderNumber : $orderKey);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'anLnkNo', (int) ($orderContext['order_position'] ?? 0));
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acIdent', $productCode);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acCode', $productCode);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'product_code', $productCode);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acName', $productName);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acDescr', $productName, false);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'title', $productName, false);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'adDate', $dayStart);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'adLnkDate', $dayStart);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'adDateIns', $now);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'adTimeIns', $now);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'adTimeChg', $now);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'adSchedStartTime', $plannedStart);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'adSchedEndTime', $plannedEnd);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'adDeliveryDeadline', $plannedEnd);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'adDateOut', $plannedEnd);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'created_at', $now);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'updated_at', $now);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acStatusMF', 'O');
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acStatus', 'N');
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'status', 'Otvoren');
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acCreateFrom', 'N');
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'anPriority', 5, false);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acPriority', $priorityLabel, false);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'priority', $priorityLabel, false);
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'abActive', 1, false);

        if ($resolvedUnit !== '') {
            $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acUM', $resolvedUnit);
        }

        if ($resolvedQuantity !== null) {
            $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'anPlanQty', $resolvedQuantity);
            $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'anQty', $resolvedQuantity, false);
            $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'anQty1', $resolvedQuantity, false);
        }

        if ($userId > 0) {
            $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'anUserIns', $userId);
            $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'anUserChg', $userId);
            $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'created_by', $userId, false);
            $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'updated_by', $userId, false);
        }

        if ($username !== '') {
            $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acUser', $username, false);
            $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'user_name', $username, false);
        }

        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'anNo', 0);

        $nextQId = $this->nextTableIntegerValue('anQId');
        if ($nextQId !== null) {
            $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'anQId', $nextQId);
        }

        $note = 'Kreirano iz preko eNalog.app preko QR skena narudžbe ' . ($orderNumber !== '' ? $orderNumber : $orderKey);
        if (!empty($orderContext['order_position'])) {
            $note .= ' / poz ' . $orderContext['order_position'];
        }
        if ($productCode !== '') {
            $note .= ' / sifra ' . $productCode;
        }
        $this->setInsertColumnValue($payload, $columns, $nonInsertableColumns, 'acNote', $note, false);

        return $payload;
    }

    private function buildWorkOrderOrderItemLinkPayload(
        string $workOrderKey,
        array $orderContext,
        mixed $user = null
    ): array {
        $columns = $this->workOrderOrderItemLinkInsertTableColumns();
        $payload = [];

        $orderKey = trim((string) ($orderContext['order_key'] ?? ''));
        $orderPosition = (int) ($orderContext['order_position'] ?? 0);

        if ($workOrderKey === '' || $orderKey === '') {
            return [];
        }

        if (empty($columns)) {
            return [];
        }

        $now = Carbon::now();
        $username = is_object($user) ? trim((string) ($user->username ?? $user->name ?? '')) : '';
        $userId = is_object($user) ? (int) ($user->id ?? 0) : 0;
        $orderItemRow = $this->findOrderItemRowByOrderContext($orderContext);
        $orderItemQIdValue = $this->toFloatOrNull($orderItemRow['anQId'] ?? null);
        $orderItemQId = $orderItemQIdValue === null ? null : (int) $orderItemQIdValue;

        if (in_array('acKey', $columns, true)) {
            $payload['acKey'] = $workOrderKey;
        }

        if (in_array('acLnkKey', $columns, true)) {
            $payload['acLnkKey'] = $orderKey;
        }
        if (in_array('anLnkNo', $columns, true)) {
            $payload['anLnkNo'] = $orderPosition;
        }
        if ($orderItemQId !== null && in_array('anOrderItemQId', $columns, true)) {
            $payload['anOrderItemQId'] = $orderItemQId;
        }

        if (in_array('anOrderItemQId', $columns, true) && !array_key_exists('anOrderItemQId', $payload)) {
            Log::warning('Skipping work order order item link insert because order item QID could not be resolved.', [
                'work_order_key' => $workOrderKey,
                'order_key' => $orderKey,
                'order_position' => $orderPosition,
                'product_code' => (string) ($orderContext['product_code'] ?? ''),
                'table' => $this->qualifiedWorkOrderOrderItemLinkInsertTableName(),
            ]);

            return [];
        }

        if (in_array('acType', $columns, true)) {
            $payload['acType'] = 'DK';
        }

        if (in_array('adTimeIns', $columns, true)) {
            $payload['adTimeIns'] = $now;
        }
        if (in_array('adTimeChg', $columns, true)) {
            $payload['adTimeChg'] = $now;
        }

        if ($userId > 0) {
            if (in_array('anUserId', $columns, true)) {
                $payload['anUserId'] = $userId;
            }
            if (in_array('anUserIns', $columns, true)) {
                $payload['anUserIns'] = $userId;
            }
            if (in_array('anUserChg', $columns, true)) {
                $payload['anUserChg'] = $userId;
            }
        }

        if ($username !== '' && in_array('acValue', $columns, true)) {
            $payload['acValue'] = substr($username, 0, 35);
        }

        if (in_array('anNo', $columns, true)) {
            $payload['anNo'] = 0;
        }

        return $payload;
    }

    private function resolveScanCreateWorkOrderUnit(array $orderRow, array $orderContext): string
    {
        $orderUnit = strtoupper(substr((string) $this->valueTrimmed($orderRow, ['acUM', 'acUM1', 'acUnit'], ''), 0, 3));

        if ($orderUnit !== '') {
            return $orderUnit;
        }

        $catalogUnit = strtoupper(substr(trim((string) ($orderContext['catalog']['acUM'] ?? '')), 0, 3));

        return $catalogUnit;
    }

    private function resolveScanCreateWorkOrderQuantity(array $orderRow, array $orderContext = []): ?float
    {
        $orderItemRow = $this->findOrderItemRowByOrderContext($orderContext);

        if (is_array($orderItemRow)) {
            foreach (['anQty', 'anQtyDispDoc', 'anQtyConverted', 'anQty1', 'anPlanQty'] as $column) {
                $resolvedValue = $this->toFloatOrNull($orderItemRow[$column] ?? null);

                if ($resolvedValue === null) {
                    continue;
                }

                if (abs($resolvedValue) > 0.000001) {
                    return $resolvedValue;
                }
            }
        }

        $productCode = trim((string) ($orderContext['product_code'] ?? ''));

        if ($productCode !== '') {
            $catalogRow = $this->findCatalogItemByProductCode($productCode);

            if (!empty($catalogRow)) {
                foreach (['anQty', 'anQty1', 'anPlanQty', 'anOrdQty', 'anQuantity', 'anDefQty', 'anDefaultQty', 'anStock'] as $column) {
                    $resolvedValue = $this->toFloatOrNull($catalogRow[$column] ?? null);

                    if ($resolvedValue === null) {
                        continue;
                    }

                    if (abs($resolvedValue) > 0.000001) {
                        return $resolvedValue;
                    }
                }
            }
        }

        return null;
    }

    private function nextTableIntegerValue(string $column): ?int
    {
        $columns = $this->tableColumns();

        if (!in_array($column, $columns, true) || in_array($column, $this->tableNonInsertableColumns(), true)) {
            return null;
        }

        try {
            return ((int) ($this->newTableQuery()->max($column) ?? 0)) + 1;
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve next integer value for work order column.', [
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'column' => $column,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function setInsertColumnValue(
        array &$payload,
        array $columns,
        array $nonInsertableColumns,
        string $column,
        mixed $value,
        bool $overwrite = true
    ): void {
        if ($value === null || !in_array($column, $columns, true) || in_array($column, $nonInsertableColumns, true)) {
            return;
        }

        if (is_string($value) && trim($value) === '') {
            return;
        }

        if (!$overwrite && $this->payloadHasValue($payload, $column)) {
            return;
        }

        if (is_string($value)) {
            $value = $this->fitStringToWorkOrderColumn($column, $value);

            if (trim($value) === '') {
                return;
            }
        }

        $payload[$column] = $value;
    }

    private function payloadHasValue(array $payload, string $column): bool
    {
        if (!array_key_exists($column, $payload)) {
            return false;
        }

        $value = $payload[$column];

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    private function fitStringToWorkOrderColumn(string $column, string $value): string
    {
        $maxLength = $this->tableStringLength($column);

        if ($maxLength === null || $maxLength < 1) {
            return $value;
        }

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        $trimmed = substr($value, 0, $maxLength);

        Log::info('Trimmed work order insert value to fit column length.', [
            'table' => $this->qualifiedTableName(),
            'column' => $column,
            'original_length' => strlen($value),
            'max_length' => $maxLength,
            'original_value' => $value,
            'trimmed_value' => $trimmed,
        ]);

        return $trimmed;
    }

    private function normalizeComparableIdentifier(string $value): string
    {
        $normalized = preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value)));

        return is_string($normalized) ? $normalized : '';
    }

    private function normalizeProductCodeExact(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function normalizeComparableIdentifiers(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(function ($value) {
            return $this->normalizeComparableIdentifier((string) $value);
        }, $values), function ($value) {
            return $value !== '';
        })));
    }

    private function normalizedIdentifierExpression(Builder $query, string $columnIdentifier): string
    {
        $wrappedColumn = $query->getGrammar()->wrap($columnIdentifier);

        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(CAST($wrappedColumn AS NVARCHAR(64)), ''), '-', ''), ' ', ''), '/', ''), '.', ''), '_', ''))";
    }

    private function productCodeExactExpression(Builder $query, string $columnIdentifier): string
    {
        $wrappedColumn = $query->getGrammar()->wrap($columnIdentifier);

        return "UPPER(LTRIM(RTRIM(COALESCE(CAST($wrappedColumn AS NVARCHAR(64)), ''))))";
    }

    private function orderLinkageDisplayIdentifierExpression(Builder $query, string $columnIdentifier): string
    {
        $normalizedExpression = $this->normalizedIdentifierExpression($query, $columnIdentifier);

        return "CASE WHEN LEN($normalizedExpression) = 13 AND SUBSTRING($normalizedExpression, 7, 1) = '0' THEN STUFF($normalizedExpression, 7, 1, '') ELSE $normalizedExpression END";
    }

    private function orderLinkageDisplaySqlExpression(string $sqlExpression): string
    {
        $normalizedExpression = $this->normalizedSqlExpression($sqlExpression);

        return "CASE WHEN LEN($normalizedExpression) = 13 AND SUBSTRING($normalizedExpression, 7, 1) = '0' THEN STUFF($normalizedExpression, 7, 1, '') ELSE $normalizedExpression END";
    }

    private function normalizedSqlExpression(string $sqlExpression): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(CAST(($sqlExpression) AS NVARCHAR(64)), ''), '-', ''), ' ', ''), '/', ''), '.', ''), '_', ''))";
    }

    private function qualifyColumn(string $column, string $alias): string
    {
        return strpos($column, '.') !== false ? $column : $alias . '.' . $column;
    }

    private function qualifyColumns(array $columns, string $alias): array
    {
        return array_values(array_map(function ($column) use ($alias) {
            return $this->qualifyColumn((string) $column, $alias);
        }, $columns));
    }

    private function firstNonEmptyStringExpression(Builder $query, array $columnIdentifiers, int $length = 255): ?string
    {
        $columnIdentifiers = array_values(array_filter(array_map(function ($columnIdentifier) {
            return trim((string) $columnIdentifier);
        }, $columnIdentifiers), function ($columnIdentifier) {
            return $columnIdentifier !== '';
        }));

        if (empty($columnIdentifiers)) {
            return null;
        }

        $parts = array_map(function ($columnIdentifier) use ($query, $length) {
            $wrappedColumn = $query->getGrammar()->wrap((string) $columnIdentifier);

            return "NULLIF(LTRIM(RTRIM(CAST($wrappedColumn AS NVARCHAR($length)))), '')";
        }, $columnIdentifiers);

        if (count($parts) === 1) {
            return $parts[0];
        }

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    private function firstNonNullNumericExpression(
        Builder $query,
        array $columnIdentifiers,
        int $precision = 18,
        int $scale = 3
    ): ?string {
        $columnIdentifiers = array_values(array_filter(array_map(function ($columnIdentifier) {
            return trim((string) $columnIdentifier);
        }, $columnIdentifiers), function ($columnIdentifier) {
            return $columnIdentifier !== '';
        }));

        if (empty($columnIdentifiers)) {
            return null;
        }

        $parts = array_map(function ($columnIdentifier) use ($query, $precision, $scale) {
            $wrappedColumn = $query->getGrammar()->wrap((string) $columnIdentifier);

            return "TRY_CONVERT(DECIMAL($precision,$scale), $wrappedColumn)";
        }, $columnIdentifiers);

        if (count($parts) === 1) {
            return $parts[0];
        }

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    private function buildOrderLinkageResolvedOrderNumberExpression(
        Builder $query,
        array $workOrderColumns,
        array $orderColumns,
        bool $hasOrderJoin
    ): ?string {
        $orderNumberColumns = [];

        if ($hasOrderJoin) {
            $orderNumberColumns = array_merge(
                $orderNumberColumns,
                $this->qualifyColumns($this->existingColumns($orderColumns, ['acKeyView', 'acRefNo1', 'acKey']), 'ord')
            );
        }

        // Pantheon veza nije uvijek dvosmjerna, pa kao rezervu koristimo broj narudžbe upisan direktno na RN.
        $orderNumberColumns = array_merge(
            $orderNumberColumns,
            $this->qualifyColumns($this->existingColumns($workOrderColumns, ['acLnkKeyView', 'acLnkKey']), 'wo')
        );

        return $this->firstNonEmptyStringExpression($query, $orderNumberColumns);
    }

    private function mapOrderLinkageGroupRow(array $row): array
    {
        $orderNumber = trim((string) ($row['narudzba'] ?? ''));
        $quantity = $this->normalizeNullableNumber($row['total_kolicina_sort'] ?? null);
        $unit = trim((string) ($row['jedinica_sort'] ?? ''));
        $workOrderCount = max(0, (int) ($row['broj_rn_sort'] ?? 0));
        $positionCount = max(0, (int) ($row['broj_pozicija_sort'] ?? 0));
        $sender = trim((string) ($row['klijent_sort'] ?? ''));

        return [
            'narudzba' => $orderNumber,
            'order_number' => $orderNumber,
            'klijent' => $sender,
            'narucitelj' => $sender,
            'prijevoznik' => $sender,
            'datum' => $this->normalizeDate($row['datum_sort'] ?? null),
            'totalKolicina' => $quantity,
            'jedinica' => $unit,
            'brojRN' => $workOrderCount,
            'brojPozicija' => $positionCount,
            'linkage_state' => $workOrderCount > 0 ? 'linked' : 'missing',
            'linkage_label' => $workOrderCount > 0 ? 'Povezano' : 'Bez RN',
            'linkage_tone' => $workOrderCount > 0 ? 'success' : 'secondary',
        ];
    }

    private function fetchLinkedWorkOrdersByOrderNumber(string $normalizedOrderNumber): array
    {
        if ($normalizedOrderNumber === '') {
            return [];
        }

        $details = $this->buildOrdersLinkageDetails($normalizedOrderNumber);
        $workOrders = (array) ($details['work_orders'] ?? []);

        if (empty($workOrders)) {
            return [];
        }

        return array_values(array_filter(array_map(function (array $row): array {
            return [
                'id' => trim((string) ($row['id'] ?? $row['rn_number'] ?? '')),
                'status' => trim((string) ($row['status'] ?? 'N/A')),
                'status_tone' => trim((string) ($row['status_tone'] ?? 'secondary')),
                'veza' => trim((string) ($row['veza'] ?? 'Sumnjiva veza')),
                'veza_tone' => trim((string) ($row['veza_tone'] ?? 'secondary')),
                'pozicije' => trim((string) ($row['pozicije'] ?? '')),
            ];
        }, $workOrders), function (array $row): bool {
                return $row['id'] !== '';
        }));
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
        $quantity = $this->normalizeNullableNumber($this->value($row, ['anPlanQty', 'anQty', 'anQty1'], null));
        $unit = (string) $this->valueTrimmed($row, ['acUM'], '');
        $note = $this->resolveWorkOrderNote($row);

        $mapped = [
            'responsive_id' => '',
            'id' => $id,
            'broj_naloga' => $brojNaloga,
            'naziv' => (string) $this->value($row, ['acName', 'acDescr', 'title'], 'Radni nalog'),
            'sifra' => (string) $this->valueTrimmed($row, ['acIdent', 'product_code', 'acCode'], ''),
            'opis' => (string) $this->value($row, ['acNote', 'acStatement', 'acDescr', 'description'], ''),
            'napomena_rn' => $note,
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
            'kolicina' => $quantity,
            'mj' => $unit,
        ];

        if ($includeRaw) {
            $mapped['raw'] = $row;
        }

        return $mapped;
    }

    private function resolveWorkOrderNote(array $row): string
    {
        return (string) $this->valueTrimmed($row, ['acNote', 'description'], '');
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
        $displayNote = $this->workOrderItemDisplayNote($row);
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
            'va' => (string) $this->value($row, ['acFieldSA'], ''),
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

        foreach (['PLANNED_BOM|', 'PLANNED_RAW|'] as $markerPrefix) {
            if (Str::startsWith($trimmedNote, $markerPrefix)) {
                return trim(substr($trimmedNote, strlen($markerPrefix)));
            }
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

    private function resolvePlannedConsumptionNoteForSave(string $note, string $existingRawNote = ''): ?string
    {
        $trimmedNote = trim($note);

        if ($trimmedNote === '') {
            return null;
        }

        $trimmedExistingNote = trim($existingRawNote);

        foreach (['PLANNED_BOM|', 'PLANNED_RAW|'] as $markerPrefix) {
            if (Str::startsWith($trimmedExistingNote, $markerPrefix)) {
                return substr($markerPrefix . $trimmedNote, 0, 4000);
            }
        }

        return substr($trimmedNote, 0, 4000);
    }

    private function workOrderItemNoteColumn(array $columns): ?string
    {
        return $this->firstExistingColumn($columns, ['acFieldSE', 'acNote']);
    }

    private function workOrderItemDisplayNote(array $row): string
    {
        return $this->plannedConsumptionDisplayNote(
            (string) $this->valueTrimmed($row, ['acFieldSE', 'acNote'], '')
        );
    }

    private function resolveWorkOrderItemNoteForSave(
        string $note,
        ?string $noteColumn,
        array $existingRow = []
    ): ?string {
        if ($noteColumn === null) {
            return null;
        }

        if ($noteColumn === 'acFieldSE') {
            $trimmedNote = trim($note);

            if ($trimmedNote === '') {
                return null;
            }

            return substr($trimmedNote, 0, 2000);
        }

        return $this->resolvePlannedConsumptionNoteForSave(
            $note,
            (string) ($existingRow['acNote'] ?? '')
        );
    }

    private function clientFacingExceptionDetail(Throwable $exception): string
    {
        $details = [];
        $current = $exception;

        while ($current !== null && count($details) < 3) {
            $message = $this->sanitizeClientFacingExceptionMessage($current->getMessage());

            if ($message !== '' && !in_array($message, $details, true)) {
                $details[] = $message;
            }

            $current = $current->getPrevious();
        }

        return implode("\n", $details);
    }

    private function sanitizeClientFacingExceptionMessage(string $message): string
    {
        $normalized = trim($message);

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+\(SQL:.*$/s', '', $normalized) ?? $normalized;

        if (preg_match('/\[SQL Server\](.+)$/s', $normalized, $matches)) {
            $normalized = trim((string) ($matches[1] ?? ''));
        }

        $normalized = preg_replace('/^SQLSTATE\[[^\]]+\]:\s*/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return Str::limit(trim($normalized), 600);
    }

    private function mapItemResourceRow(array $row): array
    {
        return [
            'id' => $this->value($row, ['anQId', 'anNo', 'anLineNo'], null),
            'item_qid' => $this->value($row, ['anWOExItemQId'], null),
            'alternativa' => (string) $this->valueTrimmed($row, ['anVariant', 'anVariantSubLvl'], ''),
            'pozicija' => (string) $this->valueTrimmed($row, ['anNo', 'anLineNo', 'anResNo', '__item_no'], ''),
            'materijal' => (string) $this->valueTrimmed($row, ['acResursID', 'acIdent', 'acResIdent', 'acResource', 'acCode', '__item_ident'], ''),
            'naziv' => (string) $this->valueTrimmed($row, ['acResType', 'acDescr', 'acName', 'acResDescr', '__item_descr', '__item_ident'], ''),
            'kolicina' => $this->normalizeNumber($this->valueTrimmed($row, ['anQty', 'anPlanQty', 'anNormQty', '__item_qty', '__item_plan_qty'], 0)),
            'mj' => (string) $this->valueTrimmed($row, ['acUM', 'acUMRes', '__item_um'], ''),
            'napomena' => $this->plannedConsumptionDisplayNote((string) $this->valueTrimmed($row, ['acNote'], '')),
        ];
    }

    private function mapMaterialFromItemRow(array $row): array
    {
        return [
            'id' => $this->value($row, ['anQId', 'anNo'], null),
            'item_qid' => $this->value($row, ['anQId'], null),
            'pozicija' => (string) $this->valueTrimmed($row, ['anNo'], ''),
            'materijal' => (string) $this->valueTrimmed($row, ['acIdent'], ''),
            'naziv' => (string) $this->valueTrimmed($row, ['acDescr', 'acName', 'acIdent'], ''),
            'kolicina' => $this->normalizeNumber($this->valueTrimmed($row, ['anQty', 'anPlanQty', 'anNormQty'], 0)),
            'mj' => (string) $this->valueTrimmed($row, ['acUM'], ''),
            'napomena' => $this->workOrderItemDisplayNote($row),
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
            'napomena' => $this->plannedConsumptionDisplayNote((string) $this->value($row, ['acNote'], '')),
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
        $va = (string) $this->valueTrimmed($row, ['acFieldSA'], '');

        if ($va === '') {
            $va = 'OPR';
        }

        return [
            'id' => $this->value($row, ['anQId', 'anNo'], null),
            'alternativa' => (string) $this->valueTrimmed($row, ['anVariant'], ''),
            'pozicija' => (string) $this->valueTrimmed($row, ['anNo'], ''),
            'operacija' => (string) $this->valueTrimmed($row, ['acIdent'], ''),
            'naziv' => (string) $this->valueTrimmed($row, ['acDescr', 'acName', 'acIdent'], ''),
            'napomena' => $this->workOrderItemDisplayNote($row),
            'mj' => (string) $this->valueTrimmed($row, ['acUM'], ''),
            'mj_vrij' => $mjVrij,
            'normativna_osnova' => $normative,
            'va' => $va,
            'prim_klas' => (string) $this->valueTrimmed($row, ['acFieldSB'], ''),
            'sek_klas' => (string) $this->valueTrimmed($row, ['acFieldSC'], ''),
        ];
    }

    private function fetchCatalogMetaForComponentIds(array $componentIds): array
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $componentIds), function ($value) {
            return $value !== '';
        })));

        if (empty($normalizedIds)) {
            return [];
        }

        try {
            return $this->newCatalogItemsTableQuery()
                ->select(['acIdent', 'acName', 'acUM', 'acSetOfItem'])
                ->whereIn('acIdent', $normalizedIds)
                ->get()
                ->mapWithKeys(function ($row) {
                    $ident = strtolower(trim((string) ($row->acIdent ?? '')));

                    if ($ident === '') {
                        return [];
                    }

                    return [
                        $ident => [
                            'name' => trim((string) ($row->acName ?? '')),
                            'um' => strtoupper(substr(trim((string) ($row->acUM ?? '')), 0, 3)),
                            'set' => strtoupper(trim((string) ($row->acSetOfItem ?? ''))),
                        ],
                    ];
                })
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve catalog metadata for selected components.', [
                'connection' => config('database.default'),
                'catalog_items_table' => $this->qualifiedCatalogItemsTableName(),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function resolveOperationTypeForSave(string $operationType, string $catalogSet): string
    {
        $normalizedSet = strtoupper(trim($catalogSet));
        if ($normalizedSet === self::OPERATIONS_SET) {
            return 'O';
        }

        if (in_array($normalizedSet, self::MATERIALS_SETS, true)) {
            return 'M';
        }

        $normalizedType = strtoupper(substr(trim($operationType), 0, 1));
        if ($normalizedType !== '') {
            return $normalizedType;
        }

        return '';
    }

    private function resolveItemKindForPreview(string $operationType, string $catalogSet): string
    {
        $normalizedSet = strtoupper(trim($catalogSet));

        if ($normalizedSet === self::OPERATIONS_SET) {
            return 'operations';
        }

        if (in_array($normalizedSet, self::MATERIALS_SETS, true)) {
            return 'materials';
        }

        $normalizedType = strtoupper(substr(trim($operationType), 0, 1));
        if ($normalizedType === 'M') {
            return 'materials';
        }

        if ($normalizedType !== '') {
            return 'operations';
        }

        return 'unknown';
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

    private function orderLinkageOrderNumberSearchVariants(string $search): array
    {
        $rawSearch = trim($search);
        $digits = preg_replace('/\D+/', '', $rawSearch);
        $variants = [];

        if (is_string($digits) && strlen($digits) === 13 && substr($digits, 6, 1) === '0') {
            $displayDigits = $this->canonicalOrderNumberDigits($digits);
            $variants[] = $displayDigits;
            $variants[] = $this->formatOrderNumberDigitsForDisplay($displayDigits);
        } else {
            if ($rawSearch !== '') {
                $variants[] = $rawSearch;
            }

            if (is_string($digits) && $digits !== '') {
                $variants[] = $digits;
                $variants[] = $this->formatOrderNumberDigitsForDisplay($digits);
            }
        }

        return array_values(array_unique(array_filter($variants, function ($variant) {
            return trim((string) $variant) !== '';
        })));
    }

    private function orderLinkageOrderNumberSuffixSearch(string $search): string
    {
        $rawSearch = trim($search);

        if (!preg_match('/^\d{4}$/', $rawSearch)) {
            return '';
        }

        return $rawSearch;
    }

    private function orderLinkageOrderNumberTailSearch(string $search): string
    {
        $rawSearch = trim($search);

        if (!preg_match('/^\d{1,6}$/', $rawSearch)) {
            return '';
        }

        return $rawSearch;
    }

    private function canonicalOrderNumberDigits(string $digits): string
    {
        if (strlen($digits) === 13 && substr($digits, 6, 1) === '0') {
            return substr($digits, 0, 6) . substr($digits, 7);
        }

        return $digits;
    }

    private function formatOrderNumberDigitsForDisplay(string $digits): string
    {
        if (strlen($digits) !== 12) {
            return '';
        }

        return substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6);
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

            if (strlen($digits) === 6) {
                $formattedProductCode = substr($digits, 0, 2) . '-' . substr($digits, 2);

                if (!in_array($formattedProductCode, $variants, true)) {
                    $variants[] = $formattedProductCode;
                }
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

        if ($sortBy === 'kolicina') {
            return $this->applyOrderByFirstNonEmptyNumeric(
                $query,
                $this->existingColumns($columns, ['anPlanQty', 'anQty', 'anQty1']),
                $direction
            );
        }

        if ($sortBy === 'datum_kreiranja') {
            $hasDateOrdering = false;

            foreach (['adDate', 'adDateIns', 'adTimeIns', 'anNo'] as $column) {
                if (!in_array($column, $columns, true)) {
                    continue;
                }

                $query->orderBy($column, $direction);
                $hasDateOrdering = true;
            }

            return $hasDateOrdering;
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

    private function applyOrderByFirstNonEmptyNumeric(Builder $query, array $candidateColumns, string $direction): bool
    {
        if (empty($candidateColumns)) {
            return false;
        }

        $direction = strtolower($direction);
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
        $grammar = $query->getGrammar();
        $coalesceParts = array_map(function ($column) use ($grammar) {
            $wrappedColumn = $grammar->wrap((string) $column);
            return "TRY_CAST($wrappedColumn AS FLOAT)";
        }, $candidateColumns);
        $numericExpression = 'COALESCE(' . implode(', ', $coalesceParts) . ')';

        $query->orderByRaw("CASE WHEN $numericExpression IS NULL THEN 1 ELSE 0 END ASC");
        $query->orderByRaw("$numericExpression $direction");

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
            'datum_kreiranja', 'datum', 'datum_kreiranja_rn', 'created_at', 'created_date' => 'datum_kreiranja',
            'klijent', 'kupac', 'client' => 'klijent',
            'status' => 'status',
            'prioritet', 'priority' => 'prioritet',
            'kolicina', 'količina', 'qty', 'quantity' => 'kolicina',
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

    private function savedRowsContainMaterialConsumption(array $savedRows): bool
    {
        foreach ($savedRows as $savedRow) {
            if (!is_array($savedRow)) {
                continue;
            }

            $itemKind = strtolower(trim((string) ($savedRow['item_kind'] ?? '')));
            $consumedQty = $this->toFloatOrNull($savedRow['stock_consumed_qty'] ?? null) ?? 0.0;

            if ($itemKind === 'materials' && abs($consumedQty) > 0.000001) {
                return true;
            }
        }

        return false;
    }

    private function transitionWorkOrderStatusAfterConsumption(array &$row, array $columns, int $userId, Carbon $now): array
    {
        $statusColumns = $this->existingColumns($columns, ['acStatusMF', 'acStatus', 'status']);

        if (empty($statusColumns)) {
            return [
                'changed' => false,
                'reason' => 'missing_status_columns',
            ];
        }

        $currentStatusValue = '';
        foreach ($statusColumns as $statusColumn) {
            if (!array_key_exists($statusColumn, $row)) {
                continue;
            }

            $candidateValue = trim((string) ($row[$statusColumn] ?? ''));
            if ($candidateValue === '') {
                continue;
            }

            $currentStatusValue = $candidateValue;
            break;
        }

        $currentResolvedStatus = $this->resolveStatus($currentStatusValue);
        $currentBucket = (string) ($currentResolvedStatus['bucket'] ?? '');
        $currentLabel = (string) ($currentResolvedStatus['label'] ?? $currentStatusValue);

        if ($currentBucket !== 'otvoren') {
            return [
                'changed' => false,
                'reason' => 'status_not_otvoren',
                'from' => $currentLabel,
                'to' => 'U radu',
            ];
        }

        $updates = [];
        foreach ($statusColumns as $statusColumn) {
            $updates[$statusColumn] = $this->resolveStatusStorageValue('U radu', $row, $statusColumn);
        }

        if (in_array('adTimeChg', $columns, true)) {
            $updates['adTimeChg'] = $now;
        }

        if (in_array('anUserChg', $columns, true)) {
            $updates['anUserChg'] = $userId;
        }

        if ($this->rowAlreadyHasUpdates($row, $updates)) {
            return [
                'changed' => false,
                'reason' => 'already_in_target_state',
                'from' => $currentLabel,
                'to' => 'U radu',
            ];
        }

        $changed = $this->updateWorkOrderRow($row, $updates);

        if ($changed) {
            $row = array_merge($row, $updates);
        }

        return [
            'changed' => $changed,
            'reason' => $changed ? 'updated' : 'update_failed',
            'from' => $currentLabel,
            'to' => 'U radu',
        ];
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

    private function updateWorkOrderItemRow(array $row, array $updates): bool
    {
        if (empty($updates)) {
            return false;
        }

        $workOrderKey = trim((string) ($row['acKey'] ?? ''));

        if ($workOrderKey === '') {
            return false;
        }

        $query = $this->newItemTableQuery()->where('acKey', $workOrderKey);
        $hasIdentity = false;

        foreach (['anQId', 'anNo', 'id'] as $identityColumn) {
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

    private function tableStringLength(string $column): ?int
    {
        $map = $this->tableStringLengthMap();

        return array_key_exists($column, $map) ? $map[$column] : null;
    }

    private function tableStringLengthMap(): array
    {
        if ($this->tableStringLengthMapCache !== null) {
            return $this->tableStringLengthMapCache;
        }

        try {
            $this->tableStringLengthMapCache = DB::table('INFORMATION_SCHEMA.COLUMNS')
                ->where('TABLE_SCHEMA', $this->tableSchema())
                ->where('TABLE_NAME', $this->tableName())
                ->whereNotNull('CHARACTER_MAXIMUM_LENGTH')
                ->pluck('CHARACTER_MAXIMUM_LENGTH', 'COLUMN_NAME')
                ->mapWithKeys(function ($length, $columnName) {
                    $normalizedLength = is_numeric($length) ? (int) $length : null;

                    if ($normalizedLength === null || $normalizedLength < 1) {
                        return [];
                    }

                    return [
                        (string) $columnName => $normalizedLength,
                    ];
                })
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve work order string column lengths.', [
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            $this->tableStringLengthMapCache = [];
        }

        return $this->tableStringLengthMapCache;
    }

    private function tableNonInsertableColumns(): array
    {
        if ($this->tableNonInsertableColumnsCache !== null) {
            return $this->tableNonInsertableColumnsCache;
        }

        if (DB::getDriverName() !== 'sqlsrv') {
            $this->tableNonInsertableColumnsCache = [];

            return $this->tableNonInsertableColumnsCache;
        }

        try {
            $this->tableNonInsertableColumnsCache = DB::table('sys.columns as c')
                ->join('sys.tables as t', 'c.object_id', '=', 't.object_id')
                ->join('sys.schemas as s', 't.schema_id', '=', 's.schema_id')
                ->where('s.name', $this->tableSchema())
                ->where('t.name', $this->tableName())
                ->where(function (Builder $query) {
                    $query->where('c.is_identity', 1)
                        ->orWhere('c.is_computed', 1);
                })
                ->pluck('c.name')
                ->map(function ($columnName) {
                    return (string) $columnName;
                })
                ->values()
                ->all();
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve work order non-insertable columns.', [
                'connection' => config('database.default'),
                'table' => $this->qualifiedTableName(),
                'message' => $exception->getMessage(),
            ]);

            $this->tableNonInsertableColumnsCache = [];
        }

        return $this->tableNonInsertableColumnsCache;
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

    private function productStructureTableColumns(): array
    {
        return DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->productStructureTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();
    }

    protected function orderItemTableColumns(): array
    {
        if ($this->orderItemTableColumnsCache !== null) {
            return $this->orderItemTableColumnsCache;
        }

        $this->orderItemTableColumnsCache = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->orderItemTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();

        return $this->orderItemTableColumnsCache;
    }

    protected function workOrderOrderItemLinkTableColumns(): array
    {
        if ($this->workOrderOrderItemLinkTableColumnsCache !== null) {
            return $this->workOrderOrderItemLinkTableColumnsCache;
        }

        $this->workOrderOrderItemLinkTableColumnsCache = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->workOrderOrderItemLinkTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();

        return $this->workOrderOrderItemLinkTableColumnsCache;
    }

    protected function workOrderOrderItemLinkInsertTableColumns(): array
    {
        if ($this->workOrderOrderItemLinkInsertTableColumnsCache !== null) {
            return $this->workOrderOrderItemLinkInsertTableColumnsCache;
        }

        $this->workOrderOrderItemLinkInsertTableColumnsCache = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', $this->tableSchema())
            ->where('TABLE_NAME', $this->workOrderOrderItemLinkInsertTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();

        return $this->workOrderOrderItemLinkInsertTableColumnsCache;
    }

    protected function orderTableColumns(): array
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

    private function formatMetaMoney(?float $value, string $currency = 'KM'): string
    {
        if ($value === null) {
            return '-';
        }

        return $this->formatMetaNumber($value, 2) . ' ' . strtoupper(trim($currency));
    }

    private function formatMetaPercent(?float $value): string
    {
        if ($value === null) {
            return '-';
        }

        return $this->formatMetaNumber($value, 2) . '%';
    }

    private function formatMetaDays(mixed $value): string
    {
        $days = (int) ($this->toFloatOrNull($value) ?? 0);

        if ($days <= 0) {
            return '-';
        }

        return $days . ' dana';
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

    private function formatMetaDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        try {
            $dateTime = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);

            return $dateTime->format('d.m.Y');
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
            return 'danger';
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

    protected function newOrderTableQuery(): Builder
    {
        return DB::table($this->qualifiedOrderTableName());
    }

    protected function newOrderItemTableQuery(): Builder
    {
        return DB::table($this->qualifiedOrderItemTableName());
    }

    protected function newWorkOrderOrderItemLinkTableQuery(): Builder
    {
        return DB::table($this->qualifiedWorkOrderOrderItemLinkTableName());
    }

    private function newWorkOrderOrderItemLinkInsertTableQuery(): Builder
    {
        return DB::table($this->qualifiedWorkOrderOrderItemLinkInsertTableName());
    }

    private function newCatalogItemsTableQuery(): Builder
    {
        return DB::table($this->qualifiedCatalogItemsTableName());
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

    protected function orderTableName(): string
    {
        return (string) config('workorders.orders_table', 'tHE_Order');
    }

    protected function orderItemTableName(): string
    {
        return (string) config('workorders.order_items_table', 'tHE_OrderItem');
    }

    protected function workOrderOrderItemLinkTableName(): string
    {
        if ($this->workOrderOrderItemLinkTableCache !== null) {
            return $this->workOrderOrderItemLinkTableCache;
        }

        $configuredTable = trim((string) config('workorders.work_order_order_item_link_table', 'vHF_LinkWOExOrderItem'));
        $candidates = array_values(array_unique(array_filter([
            $configuredTable,
            'vHF_LinkWOExOrderItem',
            'tHF_LinkWOExOrderItem',
        ])));

        foreach ($candidates as $candidate) {
            $exists = DB::table('INFORMATION_SCHEMA.COLUMNS')
                ->where('TABLE_SCHEMA', $this->tableSchema())
                ->where('TABLE_NAME', $candidate)
                ->exists();

            if ($exists) {
                $this->workOrderOrderItemLinkTableCache = (string) $candidate;

                return $this->workOrderOrderItemLinkTableCache;
            }
        }

        $this->workOrderOrderItemLinkTableCache = $configuredTable !== '' ? $configuredTable : 'tHF_LinkWOExOrderItem';

        return $this->workOrderOrderItemLinkTableCache;
    }

    private function workOrderOrderItemLinkInsertTableName(): string
    {
        if ($this->workOrderOrderItemLinkInsertTableCache !== null) {
            return $this->workOrderOrderItemLinkInsertTableCache;
        }

        $configuredTable = trim((string) config('workorders.work_order_order_item_link_insert_table', 'tHF_LinkWOExOrderItem'));
        $readTable = trim((string) config('workorders.work_order_order_item_link_table', 'vHF_LinkWOExOrderItem'));
        $derivedTable = preg_match('/^v(.+)$/', $readTable, $matches) === 1 ? 't' . $matches[1] : '';
        $candidates = array_values(array_unique(array_filter([
            $configuredTable,
            $derivedTable,
            'tHF_LinkWOExOrderItem',
            $readTable,
        ])));

        foreach ($candidates as $candidate) {
            $exists = DB::table('INFORMATION_SCHEMA.TABLES')
                ->where('TABLE_SCHEMA', $this->tableSchema())
                ->where('TABLE_NAME', $candidate)
                ->where('TABLE_TYPE', 'BASE TABLE')
                ->exists();

            if ($exists) {
                $this->workOrderOrderItemLinkInsertTableCache = (string) $candidate;

                return $this->workOrderOrderItemLinkInsertTableCache;
            }
        }

        $this->workOrderOrderItemLinkInsertTableCache = $configuredTable !== '' ? $configuredTable : 'tHF_LinkWOExOrderItem';

        return $this->workOrderOrderItemLinkInsertTableCache;
    }

    private function catalogItemsTableName(): string
    {
        return (string) config('workorders.catalog_items_table', 'tHE_SetItem');
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

    protected function qualifiedOrderTableName(): string
    {
        return $this->tableSchema() . '.' . $this->orderTableName();
    }

    protected function qualifiedOrderItemTableName(): string
    {
        return $this->tableSchema() . '.' . $this->orderItemTableName();
    }

    protected function qualifiedWorkOrderOrderItemLinkTableName(): string
    {
        return $this->tableSchema() . '.' . $this->workOrderOrderItemLinkTableName();
    }

    private function qualifiedWorkOrderOrderItemLinkInsertTableName(): string
    {
        return $this->tableSchema() . '.' . $this->workOrderOrderItemLinkInsertTableName();
    }

    private function qualifiedCatalogItemsTableName(): string
    {
        return $this->tableSchema() . '.' . $this->catalogItemsTableName();
    }

    private function buildOrdersLinkageSummary(?string $normalizedOrderNumber = null): array
    {
        if ($normalizedOrderNumber !== null && $normalizedOrderNumber !== '') {
            return $this->buildOrdersLinkageSummaryForOrderNumbers([$normalizedOrderNumber]);
        }

        return $this->buildOrdersLinkageSummaryForOrderNumbers(null);
    }

    private function buildOrdersLinkageSummaryForOrderNumbers(?array $normalizedOrderNumbers = null): array
    {
        $normalizedOrderNumbers = $normalizedOrderNumbers === null
            ? null
            : $this->normalizeComparableIdentifiers($normalizedOrderNumbers);

        if ($normalizedOrderNumbers !== null && empty($normalizedOrderNumbers)) {
            return [];
        }

        $headerRows = $this->fetchOrderHeadersForLinkage($normalizedOrderNumbers);

        if (empty($headerRows)) {
            return [];
        }

        $groups = [];
        $displayNumbers = [];
        $orderKeysToNumbers = [];
        $rawOrderKeys = [];

        foreach ($headerRows as $row) {
            $displayNumber = $this->resolveOrderDisplayNumber(
                $row,
                $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acKeyView', 'acRefNo1', 'acKey'], ''))
            );
            $normalizedDisplayNumber = $this->normalizeComparableIdentifier($displayNumber);

            if ($normalizedDisplayNumber === '') {
                continue;
            }

            $this->primeOrderLinkageGroup($groups, $normalizedDisplayNumber, $displayNumber);
            $displayNumbers[$normalizedDisplayNumber] = (string) ($groups[$normalizedDisplayNumber]['order_number'] ?? $displayNumber);

            $customer = trim((string) $this->valueTrimmed($row, ['acConsignee', 'acReceiver', 'acPartner', 'client_name'], ''));
            if ($customer !== '' && trim((string) ($groups[$normalizedDisplayNumber]['customer'] ?? '')) === '') {
                $groups[$normalizedDisplayNumber]['customer'] = $customer;
            }

            $createdDate = $this->normalizeDate($this->value($row, ['adDate', 'adDateIns'], null));
            if ($createdDate !== null) {
                $groups[$normalizedDisplayNumber]['_date_candidates'][$createdDate] = $createdDate;
            }

            $dueDate = $this->normalizeDate($this->value($row, ['adDeliveryDeadline', 'adDateValid', 'adDateOut', 'adDateDoc'], null));
            if ($dueDate !== null) {
                $groups[$normalizedDisplayNumber]['_due_date_candidates'][$dueDate] = $dueDate;
            }

            $statusMeta = $this->resolveStatus($this->value($row, ['acStatusMF', 'acStatus', 'status'], ''));
            $statusLabel = trim((string) ($statusMeta['label'] ?? ''));
            $statusBucket = $statusMeta['bucket'] ?? null;

            if ($statusLabel !== '' && $statusLabel !== 'N/A') {
                $groups[$normalizedDisplayNumber]['_status_labels'][$statusLabel] = true;
            }

            if ($statusBucket !== null && $statusBucket !== '') {
                $groups[$normalizedDisplayNumber]['_status_buckets'][(string) $statusBucket] = true;
            }

            $rawOrderKey = trim((string) $this->valueTrimmed($row, ['acKey'], ''));
            $normalizedOrderKey = $this->normalizeComparableIdentifier($rawOrderKey);

            if ($rawOrderKey !== '') {
                $groups[$normalizedDisplayNumber]['_raw_order_keys'][$rawOrderKey] = true;
                $rawOrderKeys[$rawOrderKey] = true;
            }

            if ($normalizedOrderKey !== '') {
                $orderKeysToNumbers[$normalizedOrderKey] = $normalizedDisplayNumber;
            }
        }

        $itemRows = $this->fetchOrderItemsForLinkage($normalizedOrderNumbers, array_values(array_keys($rawOrderKeys)));

        foreach ($itemRows as $index => $row) {
            $resolvedOrder = $this->resolveOrderLinkageNumberFromOrderItem($row, $orderKeysToNumbers, $displayNumbers);
            $normalizedItemOrderNumber = (string) ($resolvedOrder['normalized'] ?? '');
            $displayItemOrderNumber = (string) ($resolvedOrder['display'] ?? '');

            if ($normalizedItemOrderNumber === '') {
                continue;
            }

            $this->primeOrderLinkageGroup($groups, $normalizedItemOrderNumber, $displayItemOrderNumber);
            $displayNumbers[$normalizedItemOrderNumber] = (string) ($groups[$normalizedItemOrderNumber]['order_number'] ?? $displayItemOrderNumber);

            $positionValue = trim((string) $this->valueTrimmed($row, ['anNo', 'anLineNo', 'anItemNo', 'anPosition', 'anPos', 'anPosNo'], ''));
            $positionKey = $positionValue !== '' ? $positionValue : '__row_' . $index;
            $groups[$normalizedItemOrderNumber]['_position_keys'][$positionKey] = true;

            $quantity = $this->normalizeNullableNumber($this->value($row, ['anQty', 'anQty1', 'anPlanQty'], null));
            if (is_int($quantity) || is_float($quantity)) {
                $groups[$normalizedItemOrderNumber]['quantity_total'] += (float) $quantity;
                $groups[$normalizedItemOrderNumber]['has_quantity_total'] = true;
            }
        }

        $workOrderRows = $this->fetchWorkOrdersForLinkage($normalizedOrderNumbers, array_values(array_keys($rawOrderKeys)));
        $linkedOrders = $this->resolveLinkedOrdersForRows($workOrderRows);

        foreach ($workOrderRows as $row) {
            $mappedRow = $this->mapRow($row, false, $linkedOrders);
            $resolvedOrder = $this->resolveOrderLinkageNumberFromWorkOrder(
                $row,
                $mappedRow,
                $orderKeysToNumbers,
                $displayNumbers
            );
            $normalizedWorkOrderNumber = (string) ($resolvedOrder['normalized'] ?? '');
            $displayWorkOrderNumber = (string) ($resolvedOrder['display'] ?? '');

            if ($normalizedWorkOrderNumber === '') {
                continue;
            }

            $this->primeOrderLinkageGroup($groups, $normalizedWorkOrderNumber, $displayWorkOrderNumber);
            $displayNumbers[$normalizedWorkOrderNumber] = (string) ($groups[$normalizedWorkOrderNumber]['order_number'] ?? $displayWorkOrderNumber);
            $groups[$normalizedWorkOrderNumber]['work_order_count']++;

            $linkedPosition = trim((string) ($mappedRow['broj_pozicije_narudzbe'] ?? ''));
            if ($linkedPosition !== '') {
                $groups[$normalizedWorkOrderNumber]['_linked_position_keys'][$linkedPosition] = true;
            }

            $workOrderQuantity = $mappedRow['kolicina'] ?? null;
            if (is_int($workOrderQuantity) || is_float($workOrderQuantity)) {
                $groups[$normalizedWorkOrderNumber]['work_order_qty_total'] += (float) $workOrderQuantity;
                $groups[$normalizedWorkOrderNumber]['has_work_order_qty_total'] = true;
            }
        }

        $summaryRows = [];

        foreach ($groups as $normalizedKey => $group) {
            $positionCount = count((array) ($group['_position_keys'] ?? []));
            $linkedPositionCount = count((array) ($group['_linked_position_keys'] ?? []));
            $workOrderCount = (int) ($group['work_order_count'] ?? 0);
            $statusLabels = array_keys((array) ($group['_status_labels'] ?? []));
            $statusBuckets = array_keys((array) ($group['_status_buckets'] ?? []));
            $state = $this->determineOrderLinkageState(
                $positionCount,
                $linkedPositionCount,
                $workOrderCount,
                (bool) ($group['has_quantity_total'] ?? false),
                (float) ($group['quantity_total'] ?? 0),
                (bool) ($group['has_work_order_qty_total'] ?? false),
                (float) ($group['work_order_qty_total'] ?? 0)
            );
            $stateMeta = $this->orderLinkageStateMeta($state);

            $summaryRows[] = [
                'order_number' => (string) ($group['order_number'] ?? $displayNumbers[$normalizedKey] ?? $normalizedKey),
                'customer' => (string) ($group['customer'] ?? ''),
                'date' => $this->orderLinkagePickDate((array) ($group['_date_candidates'] ?? []), true),
                'due_date' => $this->orderLinkagePickDate((array) ($group['_due_date_candidates'] ?? []), false),
                'status' => count($statusLabels) === 1
                    ? $statusLabels[0]
                    : (count($statusLabels) > 1 ? 'Više statusa' : 'N/A'),
                'status_bucket' => count($statusBuckets) === 1 ? $statusBuckets[0] : ($statusBuckets[0] ?? null),
                'quantity' => ($group['has_quantity_total'] ?? false)
                    ? round((float) ($group['quantity_total'] ?? 0), 3)
                    : null,
                'position_count' => $positionCount,
                'work_order_count' => $workOrderCount,
                'linkage_state' => $state,
                'linkage_label' => $stateMeta['label'],
                'linkage_tone' => $stateMeta['tone'],
            ];
        }

        usort($summaryRows, function (array $firstRow, array $secondRow): int {
            $firstDate = trim((string) ($firstRow['date'] ?? ''));
            $secondDate = trim((string) ($secondRow['date'] ?? ''));

            if ($firstDate !== $secondDate) {
                return strcmp($secondDate, $firstDate);
            }

            return strnatcasecmp(
                (string) ($secondRow['order_number'] ?? ''),
                (string) ($firstRow['order_number'] ?? '')
            );
        });

        return $summaryRows;
    }

    private function buildOrdersLinkageDetails(string $normalizedOrderNumber): array
    {
        $summaryRows = $this->buildOrdersLinkageSummary($normalizedOrderNumber);
        $summaryRow = $summaryRows[0] ?? null;

        if ($summaryRow === null) {
            return [];
        }

        $headerRows = $this->fetchOrderHeadersForLinkage($normalizedOrderNumber);
        $orderKeysToNumbers = [];
        $rawOrderKeys = [];
        $displayNumbers = [
            $normalizedOrderNumber => (string) ($summaryRow['order_number'] ?? $normalizedOrderNumber),
        ];

        foreach ($headerRows as $row) {
            $rawOrderKey = trim((string) $this->valueTrimmed($row, ['acKey'], ''));
            $normalizedOrderKey = $this->normalizeComparableIdentifier($rawOrderKey);

            if ($rawOrderKey !== '') {
                $rawOrderKeys[$rawOrderKey] = true;
            }

            if ($normalizedOrderKey !== '') {
                $orderKeysToNumbers[$normalizedOrderKey] = $normalizedOrderNumber;
            }
        }

        $workOrderRows = $this->fetchWorkOrdersForLinkage(
            $normalizedOrderNumber,
            array_values(array_keys($rawOrderKeys))
        );
        $orderItemRows = $this->fetchOrderItemsForLinkage(
            $normalizedOrderNumber,
            array_values(array_keys($rawOrderKeys))
        );
        $orderPositionKeys = [];

        foreach ($orderItemRows as $index => $row) {
            $resolvedOrder = $this->resolveOrderLinkageNumberFromOrderItem($row, $orderKeysToNumbers, $displayNumbers);

            if (($resolvedOrder['normalized'] ?? '') !== $normalizedOrderNumber) {
                continue;
            }

            $positionValue = trim((string) $this->valueTrimmed($row, ['anNo', 'anLineNo', 'anItemNo', 'anPosition', 'anPos', 'anPosNo'], ''));
            $positionKey = $positionValue !== '' ? $positionValue : '__row_' . $index;
            $orderPositionKeys[$positionKey] = true;
        }

        $linkPreview = $this->buildOrderLinkPreviewData(
            $workOrderRows,
            $normalizedOrderNumber,
            array_values(array_keys($rawOrderKeys)),
            $orderKeysToNumbers,
            $displayNumbers
        );
        $summaryRow['position_count'] = count($orderPositionKeys);
        $summaryRow['link_count'] = count((array) ($linkPreview['links'] ?? []));

        return [
            'summary' => $summaryRow,
            'links' => (array) ($linkPreview['links'] ?? []),
            'work_orders' => $this->buildOrderWorkOrderPreviewRows(
                $workOrderRows,
                $normalizedOrderNumber,
                $orderKeysToNumbers,
                $displayNumbers,
                (array) ($linkPreview['work_order_position_ids'] ?? [])
            ),
        ];
    }

    private function fetchOrderHeadersForLinkage(array|string|null $normalizedOrderNumbers = null): array
    {
        if (is_string($normalizedOrderNumbers)) {
            $normalizedOrderNumbers = $normalizedOrderNumbers === ''
                ? null
                : [$normalizedOrderNumbers];
        }

        if ($normalizedOrderNumbers !== null) {
            $normalizedOrderNumbers = $this->normalizeComparableIdentifiers($normalizedOrderNumbers);

            if (empty($normalizedOrderNumbers)) {
                return [];
            }
        }

        $orderColumns = $this->orderTableColumns();
        $selectColumns = $this->existingColumns($orderColumns, [
            'acKey',
            'acKeyView',
            'acRefNo1',
            'acConsignee',
            'acReceiver',
            'acPartner',
            'adDate',
            'adDateIns',
            'adDeliveryDeadline',
            'adDateOut',
            'adDateDoc',
            'adDateValid',
            'acStatusMF',
            'acStatus',
            'status',
        ]);
        $query = $this->newOrderTableQuery();

        if ($normalizedOrderNumbers !== null) {
            $orderNumberColumns = $this->existingColumns($orderColumns, ['acKeyView', 'acRefNo1', 'acKey']);

            if (empty($orderNumberColumns)) {
                return [];
            }

            $query->where(function (Builder $numberQuery) use ($orderNumberColumns, $normalizedOrderNumbers) {
                $placeholders = implode(', ', array_fill(0, count($normalizedOrderNumbers), '?'));

                foreach ($orderNumberColumns as $index => $orderNumberColumn) {
                    $normalizedExpression = $this->normalizedIdentifierExpression($numberQuery, $orderNumberColumn);
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $numberQuery->{$method}("$normalizedExpression IN ($placeholders)", $normalizedOrderNumbers);
                }
            });
        }

        foreach (['adDate', 'adDateIns', 'acKey'] as $orderByColumn) {
            if (in_array($orderByColumn, $orderColumns, true)) {
                $query->orderByDesc($orderByColumn);
            }
        }

        return (empty($selectColumns) ? $query->get() : $query->get($selectColumns))
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();
    }

    private function fetchOrderItemsForLinkage(array|string|null $normalizedOrderNumbers = null, array $rawOrderKeys = []): array
    {
        if (is_string($normalizedOrderNumbers)) {
            $normalizedOrderNumbers = $normalizedOrderNumbers === ''
                ? null
                : [$normalizedOrderNumbers];
        }

        if ($normalizedOrderNumbers !== null) {
            $normalizedOrderNumbers = $this->normalizeComparableIdentifiers($normalizedOrderNumbers);

            if (empty($normalizedOrderNumbers)) {
                return [];
            }
        }

        $orderItemColumns = $this->orderItemTableColumns();
        $selectColumns = $this->existingColumns($orderItemColumns, [
            'acKey',
            'acLnkKey',
            'acOrderKey',
            'order_key',
            'acKeyView',
            'acRefNo1',
            'acOrderNo',
            'order_number',
            'anNo',
            'anLineNo',
            'anItemNo',
            'anPosition',
            'anPos',
            'anPosNo',
            'acIdent',
            'acName',
            'acDescr',
            'anQty',
            'anQtyDispDoc',
            'anQty1',
            'anPlanQty',
            'acUM',
            'anPrice',
            'anRebate',
            'acVATCode',
            'anVAT',
            'acNote',
            'anSalePrice',
            'anPackQty',
            'adDate',
            'adDateOut',
            'adDeliveryDeadline',
            'adDeliveryDate',
            'adDateValid',
            'acDept',
            'acCostDrv',
            'anVariant',
            'anDimVolume',
            'anDimWeight',
            'anDimWeightBrutto',
            'anRebate1',
            'anRebate2',
            'anRebate3',
            'anRetailPrice',
            'anPriceCurrency',
            'anPVValue',
            'anPVDiscount',
            'anPVExcise',
            'anPVVATBase',
            'anPVVAT',
            'anPVForPay',
            'anPVOCValue',
            'anPVOCDiscount',
            'anPVOCExcise',
            'anPVOCVATBase',
            'anPVOCVAT',
            'anPVOCForPay',
            'anRTPrice',
            'anReserved',
            'anQId',
            'anQtyConverted',
            'acUMConverted',
            'anRTPriceConverted',
            'acPackNum',
            'acWeighed',
            'acStatusMF',
            'acStatus',
            'status',
        ]);
        $query = $this->newOrderItemTableQuery();

        if ($normalizedOrderNumbers !== null) {
            $orderItemKeyColumns = $this->existingColumns($orderItemColumns, ['acKey', 'acLnkKey', 'acOrderKey', 'order_key']);
            $orderItemNumberColumns = $this->existingColumns($orderItemColumns, ['acKeyView', 'acRefNo1', 'acOrderNo', 'order_number']);
            $normalizedRawOrderKeys = $this->normalizeComparableIdentifiers($rawOrderKeys);

            if ((empty($normalizedRawOrderKeys) || empty($orderItemKeyColumns)) && empty($orderItemNumberColumns)) {
                return [];
            }

            $query->where(function (Builder $filterQuery) use ($orderItemKeyColumns, $orderItemNumberColumns, $normalizedOrderNumbers, $normalizedRawOrderKeys) {
                $hasCondition = false;
                $placeholders = implode(', ', array_fill(0, count($normalizedOrderNumbers), '?'));

                if (!empty($normalizedRawOrderKeys) && !empty($orderItemKeyColumns)) {
                    $normalizedKeyPlaceholders = implode(', ', array_fill(0, count($normalizedRawOrderKeys), '?'));

                    foreach ($orderItemKeyColumns as $index => $orderItemKeyColumn) {
                        $normalizedExpression = $this->normalizedIdentifierExpression($filterQuery, $orderItemKeyColumn);
                        $method = $hasCondition || $index > 0 ? 'orWhereRaw' : 'whereRaw';
                        $filterQuery->{$method}("$normalizedExpression IN ($normalizedKeyPlaceholders)", $normalizedRawOrderKeys);
                        $hasCondition = true;
                    }
                }

                if (!empty($orderItemNumberColumns)) {
                    foreach ($orderItemNumberColumns as $index => $orderItemNumberColumn) {
                        $normalizedExpression = $this->normalizedIdentifierExpression($filterQuery, $orderItemNumberColumn);
                        $method = $hasCondition || $index > 0 ? 'orWhereRaw' : 'whereRaw';
                        $filterQuery->{$method}("$normalizedExpression IN ($placeholders)", $normalizedOrderNumbers);
                        $hasCondition = true;
                    }
                }
            });
        }

        foreach (['anNo', 'anLineNo', 'anItemNo'] as $orderByColumn) {
            if (in_array($orderByColumn, $orderItemColumns, true)) {
                $query->orderBy($orderByColumn);
            }
        }

        return (empty($selectColumns) ? $query->get() : $query->get($selectColumns))
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();
    }

    private function fetchWorkOrdersForLinkage(array|string|null $normalizedOrderNumbers = null, array $rawOrderKeys = []): array
    {
        if (is_string($normalizedOrderNumbers)) {
            $normalizedOrderNumbers = $normalizedOrderNumbers === ''
                ? null
                : [$normalizedOrderNumbers];
        }

        if ($normalizedOrderNumbers !== null) {
            $normalizedOrderNumbers = $this->normalizeComparableIdentifiers($normalizedOrderNumbers);

            if (empty($normalizedOrderNumbers)) {
                return [];
            }
        }

        $workOrderColumns = $this->tableColumns();
        $selectColumns = $this->existingColumns($workOrderColumns, [
            'acRefNo1',
            'acKey',
            'anNo',
            'id',
            'acStatusMF',
            'anPriority',
            'acPriority',
            'acWayOfSale',
            'adDate',
            'adDateIns',
            'adDeliveryDeadline',
            'adDateOut',
            'anValue',
            'acCurrency',
            'acLnkKey',
            'acLnkKeyView',
            'anLnkNo',
            'anPlanQty',
            'anQty',
            'anQty1',
            'acUM',
            'acName',
            'acDescr',
            'acIdent',
            'acCode',
            'acNote',
            'acStatement',
            'acConsignee',
            'acReceiver',
            'acPartner',
            'acWarehouse',
            'acWarehouseFrom',
            'adSchedStartTime',
        ]);
        $query = $this->newTableQuery();

        if ($normalizedOrderNumbers !== null) {
            $linkKeyColumn = $this->firstExistingColumn($workOrderColumns, ['acLnkKey']);
            $linkDisplayColumns = $this->existingColumns($workOrderColumns, ['acLnkKeyView']);
            $normalizedRawOrderKeys = $this->normalizeComparableIdentifiers($rawOrderKeys);

            if (($linkKeyColumn === null || empty($normalizedRawOrderKeys)) && empty($linkDisplayColumns)) {
                return [];
            }

            $query->where(function (Builder $filterQuery) use ($linkKeyColumn, $linkDisplayColumns, $normalizedOrderNumbers, $normalizedRawOrderKeys) {
                $hasCondition = false;
                $placeholders = implode(', ', array_fill(0, count($normalizedOrderNumbers), '?'));

                if ($linkKeyColumn !== null && !empty($normalizedRawOrderKeys)) {
                    $normalizedKeyPlaceholders = implode(', ', array_fill(0, count($normalizedRawOrderKeys), '?'));
                    $normalizedExpression = $this->normalizedIdentifierExpression($filterQuery, $linkKeyColumn);
                    $filterQuery->whereRaw("$normalizedExpression IN ($normalizedKeyPlaceholders)", $normalizedRawOrderKeys);
                    $hasCondition = true;
                }

                foreach ($linkDisplayColumns as $index => $linkDisplayColumn) {
                    $normalizedExpression = $this->normalizedIdentifierExpression($filterQuery, $linkDisplayColumn);
                    $method = $hasCondition || $index > 0 ? 'orWhereRaw' : 'whereRaw';
                    $filterQuery->{$method}("$normalizedExpression IN ($placeholders)", $normalizedOrderNumbers);
                    $hasCondition = true;
                }
            });
        }

        foreach (['adDate', 'adDateIns', 'adTimeIns', 'anNo'] as $orderByColumn) {
            if (in_array($orderByColumn, $workOrderColumns, true)) {
                $query->orderByDesc($orderByColumn);
            }
        }

        return (empty($selectColumns) ? $query->get() : $query->get($selectColumns))
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();
    }

    private function fetchWorkOrderOrderItemLinksForLinkage(array|string|null $normalizedOrderNumbers = null, array $rawOrderKeys = []): array
    {
        if (is_string($normalizedOrderNumbers)) {
            $normalizedOrderNumbers = $normalizedOrderNumbers === ''
                ? null
                : [$normalizedOrderNumbers];
        }

        if ($normalizedOrderNumbers !== null) {
            $normalizedOrderNumbers = $this->normalizeComparableIdentifiers($normalizedOrderNumbers);

            if (empty($normalizedOrderNumbers)) {
                return [];
            }
        }

        $linkColumns = $this->workOrderOrderItemLinkTableColumns();

        if (empty($linkColumns)) {
            return [];
        }

        $selectColumns = $this->existingColumns($linkColumns, [
            'acKey',
            'acKeyView',
            'anNo',
            'acLnkKey',
            'acLnkKeyView',
            'anLnkNo',
            'acType',
            'acTypeA',
            'acTypeB',
            'anFieldNA',
            'anFieldNB',
            'adDate',
            'acValue',
            'adTimeIns',
        ]);
        $query = $this->newWorkOrderOrderItemLinkTableQuery();

        if ($normalizedOrderNumbers !== null) {
            $linkKeyColumns = $this->existingColumns($linkColumns, ['acLnkKey']);
            $linkDisplayColumns = $this->existingColumns($linkColumns, ['acLnkKeyView']);
            $normalizedRawOrderKeys = $this->normalizeComparableIdentifiers($rawOrderKeys);

            if ((empty($normalizedRawOrderKeys) || empty($linkKeyColumns)) && empty($linkDisplayColumns)) {
                return [];
            }

            $query->where(function (Builder $filterQuery) use ($linkKeyColumns, $linkDisplayColumns, $normalizedOrderNumbers, $normalizedRawOrderKeys) {
                $hasCondition = false;
                $placeholders = implode(', ', array_fill(0, count($normalizedOrderNumbers), '?'));

                if (!empty($normalizedRawOrderKeys) && !empty($linkKeyColumns)) {
                    $normalizedKeyPlaceholders = implode(', ', array_fill(0, count($normalizedRawOrderKeys), '?'));

                    foreach ($linkKeyColumns as $index => $linkKeyColumn) {
                        $normalizedExpression = $this->normalizedIdentifierExpression($filterQuery, $linkKeyColumn);
                        $method = $hasCondition || $index > 0 ? 'orWhereRaw' : 'whereRaw';
                        $filterQuery->{$method}("$normalizedExpression IN ($normalizedKeyPlaceholders)", $normalizedRawOrderKeys);
                        $hasCondition = true;
                    }
                }

                foreach ($linkDisplayColumns as $index => $linkDisplayColumn) {
                    $normalizedExpression = $this->normalizedIdentifierExpression($filterQuery, $linkDisplayColumn);
                    $method = $hasCondition || $index > 0 ? 'orWhereRaw' : 'whereRaw';
                    $filterQuery->{$method}("$normalizedExpression IN ($placeholders)", $normalizedOrderNumbers);
                    $hasCondition = true;
                }
            });
        }

        foreach (['adTimeIns', 'adDate', 'acKey', 'anNo'] as $orderByColumn) {
            if (in_array($orderByColumn, $linkColumns, true)) {
                $query->orderByDesc($orderByColumn);
            }
        }

        return (empty($selectColumns) ? $query->get() : $query->get($selectColumns))
            ->map(function ($row) {
                return (array) $row;
            })
            ->values()
            ->all();
    }

    private function primeOrderLinkageGroup(array &$groups, string $normalizedOrderNumber, string $displayOrderNumber): void
    {
        if (isset($groups[$normalizedOrderNumber])) {
            if ($displayOrderNumber !== '' && trim((string) ($groups[$normalizedOrderNumber]['order_number'] ?? '')) === '') {
                $groups[$normalizedOrderNumber]['order_number'] = $displayOrderNumber;
            }

            return;
        }

        $groups[$normalizedOrderNumber] = [
            'order_number' => $displayOrderNumber,
            'customer' => '',
            'quantity_total' => 0.0,
            'has_quantity_total' => false,
            'work_order_count' => 0,
            'work_order_qty_total' => 0.0,
            'has_work_order_qty_total' => false,
            '_date_candidates' => [],
            '_due_date_candidates' => [],
            '_status_labels' => [],
            '_status_buckets' => [],
            '_raw_order_keys' => [],
            '_position_keys' => [],
            '_linked_position_keys' => [],
        ];
    }

    private function resolveOrderLinkageNumberFromOrderItem(
        array $row,
        array $orderKeysToNumbers,
        array $displayNumbers
    ): array {
        foreach (['acKey', 'acLnkKey', 'acOrderKey', 'order_key'] as $candidateKeyColumn) {
            $candidateKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, [$candidateKeyColumn], ''));

            if ($candidateKey === '' || !array_key_exists($candidateKey, $orderKeysToNumbers)) {
                continue;
            }

            $normalizedOrderNumber = (string) $orderKeysToNumbers[$candidateKey];

            return [
                'normalized' => $normalizedOrderNumber,
                'display' => (string) ($displayNumbers[$normalizedOrderNumber] ?? $normalizedOrderNumber),
            ];
        }

        $displayOrderNumber = trim((string) $this->valueTrimmed($row, ['acKeyView', 'acRefNo1', 'acOrderNo', 'order_number'], ''));
        $normalizedOrderNumber = $this->normalizeComparableIdentifier($displayOrderNumber);

        if ($normalizedOrderNumber === '') {
            return [
                'normalized' => '',
                'display' => '',
            ];
        }

        return [
            'normalized' => $normalizedOrderNumber,
            'display' => (string) ($displayNumbers[$normalizedOrderNumber] ?? $displayOrderNumber),
        ];
    }

    private function resolveOrderLinkageNumberFromLinkRow(
        array $row,
        array $orderKeysToNumbers,
        array $displayNumbers
    ): array {
        $linkedOrderKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acLnkKey'], ''));

        if ($linkedOrderKey !== '' && array_key_exists($linkedOrderKey, $orderKeysToNumbers)) {
            $normalizedOrderNumber = (string) $orderKeysToNumbers[$linkedOrderKey];

            return [
                'normalized' => $normalizedOrderNumber,
                'display' => (string) ($displayNumbers[$normalizedOrderNumber] ?? $normalizedOrderNumber),
            ];
        }

        $displayOrderNumber = trim((string) $this->valueTrimmed($row, ['acLnkKeyView'], ''));
        $normalizedOrderNumber = $this->normalizeComparableIdentifier($displayOrderNumber);

        if ($normalizedOrderNumber === '') {
            return [
                'normalized' => '',
                'display' => '',
            ];
        }

        return [
            'normalized' => $normalizedOrderNumber,
            'display' => (string) ($displayNumbers[$normalizedOrderNumber] ?? $displayOrderNumber),
        ];
    }

    private function resolveOrderLinkageNumberFromWorkOrder(
        array $row,
        array $mappedRow,
        array $orderKeysToNumbers,
        array $displayNumbers
    ): array {
        $linkedOrderKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acLnkKey'], ''));

        if ($linkedOrderKey !== '' && array_key_exists($linkedOrderKey, $orderKeysToNumbers)) {
            $normalizedOrderNumber = (string) $orderKeysToNumbers[$linkedOrderKey];

            return [
                'normalized' => $normalizedOrderNumber,
                'display' => (string) ($displayNumbers[$normalizedOrderNumber] ?? $normalizedOrderNumber),
            ];
        }

        $mappedOrderNumber = trim((string) ($mappedRow['broj_narudzbe'] ?? ''));
        $normalizedMappedOrderNumber = $this->normalizeComparableIdentifier($mappedOrderNumber);

        if ($normalizedMappedOrderNumber !== '' && array_key_exists($normalizedMappedOrderNumber, $displayNumbers)) {
            return [
                'normalized' => $normalizedMappedOrderNumber,
                'display' => (string) ($displayNumbers[$normalizedMappedOrderNumber] ?? $mappedOrderNumber),
            ];
        }

        // Pantheon veza nije uvijek dvosmjerna, pa kao rezervu koristimo vidljivi broj narudžbe upisan na RN.
        $displayOrderNumber = trim((string) $this->valueTrimmed($row, ['acLnkKeyView'], ''));
        $normalizedDisplayOrderNumber = $this->normalizeComparableIdentifier($displayOrderNumber);

        if ($normalizedDisplayOrderNumber !== '') {
            return [
                'normalized' => $normalizedDisplayOrderNumber,
                'display' => (string) ($displayNumbers[$normalizedDisplayOrderNumber] ?? $displayOrderNumber),
            ];
        }

        if ($normalizedMappedOrderNumber !== '') {
            return [
                'normalized' => $normalizedMappedOrderNumber,
                'display' => $mappedOrderNumber !== ''
                    ? $mappedOrderNumber
                    : (string) ($displayNumbers[$normalizedMappedOrderNumber] ?? $normalizedMappedOrderNumber),
            ];
        }

        return [
            'normalized' => '',
            'display' => '',
        ];
    }

    private function resolveOrderLinkageMatchMetaFromWorkOrder(
        array $row,
        array $mappedRow,
        array $orderKeysToNumbers,
        array $displayNumbers
    ): array {
        $positionLink = trim((string) ($mappedRow['broj_pozicije_narudzbe'] ?? $this->valueTrimmed($row, ['anLnkNo'], '')));
        $hasPositionLink = $positionLink !== '';
        $linkedOrderKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acLnkKey'], ''));

        if ($linkedOrderKey !== '' && array_key_exists($linkedOrderKey, $orderKeysToNumbers)) {
            $normalizedOrderNumber = (string) $orderKeysToNumbers[$linkedOrderKey];

            return [
                'normalized' => $normalizedOrderNumber,
                'display' => (string) ($displayNumbers[$normalizedOrderNumber] ?? $normalizedOrderNumber),
                'match_type' => 'direct',
                'has_position_link' => $hasPositionLink,
            ];
        }

        $mappedOrderNumber = trim((string) ($mappedRow['broj_narudzbe'] ?? ''));
        $normalizedMappedOrderNumber = $this->normalizeComparableIdentifier($mappedOrderNumber);

        if ($normalizedMappedOrderNumber !== '' && array_key_exists($normalizedMappedOrderNumber, $displayNumbers)) {
            return [
                'normalized' => $normalizedMappedOrderNumber,
                'display' => (string) ($displayNumbers[$normalizedMappedOrderNumber] ?? $mappedOrderNumber),
                'match_type' => 'mapped',
                'has_position_link' => $hasPositionLink,
            ];
        }

        // Pantheon veza nije uvijek dvosmjerna, pa kao rezervu koristimo vidljivi broj narudzbe upisan na RN.
        $displayOrderNumber = trim((string) $this->valueTrimmed($row, ['acLnkKeyView'], ''));
        $normalizedDisplayOrderNumber = $this->normalizeComparableIdentifier($displayOrderNumber);

        if ($normalizedDisplayOrderNumber !== '') {
            return [
                'normalized' => $normalizedDisplayOrderNumber,
                'display' => (string) ($displayNumbers[$normalizedDisplayOrderNumber] ?? $displayOrderNumber),
                'match_type' => 'visible',
                'has_position_link' => $hasPositionLink,
            ];
        }

        if ($normalizedMappedOrderNumber !== '') {
            return [
                'normalized' => $normalizedMappedOrderNumber,
                'display' => $mappedOrderNumber !== ''
                    ? $mappedOrderNumber
                    : (string) ($displayNumbers[$normalizedMappedOrderNumber] ?? $normalizedMappedOrderNumber),
                'match_type' => 'fallback',
                'has_position_link' => $hasPositionLink,
            ];
        }

        return [
            'normalized' => '',
            'display' => '',
            'match_type' => 'none',
            'has_position_link' => $hasPositionLink,
        ];
    }

    private function orderWorkOrderLinkageMeta(array $resolvedOrder): array
    {
        $matchType = trim((string) ($resolvedOrder['match_type'] ?? 'none'));
        $hasPositionLink = (bool) ($resolvedOrder['has_position_link'] ?? false);

        if (in_array($matchType, ['direct', 'mapped'], true)) {
            return $hasPositionLink
                ? ['label' => 'Puna veza', 'tone' => 'success']
                : ['label' => 'Djelimicna veza', 'tone' => 'warning'];
        }

        return [
            'label' => 'Sumnjiva veza',
            'tone' => 'secondary',
        ];
    }

    private function buildOrderLinkPreviewData(
        array $workOrderRows,
        string $normalizedOrderNumber,
        array $rawOrderKeys,
        array $orderKeysToNumbers,
        array $displayNumbers
    ): array {
        $linkRows = $this->fetchWorkOrderOrderItemLinksForLinkage($normalizedOrderNumber, $rawOrderKeys);
        $orderItemRows = $this->fetchOrderItemsForLinkage($normalizedOrderNumber, $rawOrderKeys);
        $orderItemsByKeyAndPosition = [];

        foreach ($orderItemRows as $row) {
            $resolvedOrder = $this->resolveOrderLinkageNumberFromOrderItem($row, $orderKeysToNumbers, $displayNumbers);

            if (($resolvedOrder['normalized'] ?? '') !== $normalizedOrderNumber) {
                continue;
            }

            $orderKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acKey', 'acLnkKey', 'acOrderKey', 'order_key'], ''));
            $position = trim((string) $this->valueTrimmed($row, ['anNo', 'anLineNo', 'anItemNo', 'anPosition', 'anPos', 'anPosNo'], ''));

            if ($orderKey === '' || $position === '') {
                continue;
            }

            $orderItemsByKeyAndPosition[$orderKey . '#' . $position] = $row;
        }

        $linkedOrders = $this->resolveLinkedOrdersForRows($workOrderRows);
        $mappedWorkOrdersByKey = [];
        $rawWorkOrdersByKey = [];

        foreach ($workOrderRows as $row) {
            $workOrderKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acKey'], ''));

            if ($workOrderKey === '') {
                continue;
            }

            $mappedWorkOrdersByKey[$workOrderKey] = $this->mapRow($row, false, $linkedOrders);
            $rawWorkOrdersByKey[$workOrderKey] = $row;
        }

        $links = [];
        $workOrderPositionIds = [];
        $seenLinks = [];

        foreach ($linkRows as $index => $row) {
            $resolvedOrder = $this->resolveOrderLinkageNumberFromLinkRow($row, $orderKeysToNumbers, $displayNumbers);

            if (($resolvedOrder['normalized'] ?? '') !== $normalizedOrderNumber) {
                continue;
            }

            $workOrderKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acKey'], ''));
            $orderKey = $this->normalizeComparableIdentifier((string) $this->valueTrimmed($row, ['acLnkKey'], ''));
            $position = trim((string) $this->valueTrimmed($row, ['anLnkNo'], ''));
            $linkType = trim((string) $this->valueTrimmed($row, ['acType'], ''));
            $seenKey = strtolower($workOrderKey . '#' . $orderKey . '#' . $position . '#' . $linkType);

            if (isset($seenLinks[$seenKey])) {
                continue;
            }

            $seenLinks[$seenKey] = true;

            $mappedWorkOrder = (array) ($mappedWorkOrdersByKey[$workOrderKey] ?? []);
            $rawWorkOrder = (array) ($rawWorkOrdersByKey[$workOrderKey] ?? []);
            $workOrderNumber = trim((string) ($mappedWorkOrder['broj_naloga'] ?? $this->valueTrimmed($row, ['acKeyView'], '')));
            $orderItem = (array) ($orderItemsByKeyAndPosition[$orderKey . '#' . $position] ?? []);

            if ($workOrderNumber !== '' && $position !== '') {
                $workOrderPositionIds[$workOrderNumber][$position] = $position;
            }

            $orderedQty = $this->toFloatOrNull($this->value(
                $orderItem,
                ['anQty', 'anQty1', 'anPlanQty'],
                $this->value($rawWorkOrder, ['anPlanQty', 'anQty', 'anQty1'], $mappedWorkOrder['kolicina'] ?? null)
            ));
            $producedQty = $this->toFloatOrNull($this->value($rawWorkOrder, ['anProducedQty'], null));

            if ($producedQty === null && $orderedQty !== null) {
                $producedQty = 0.0;
            }

            $remainingQty = null;
            if ($orderedQty !== null) {
                $remainingQty = max($orderedQty - (float) ($producedQty ?? 0.0), 0.0);
            }

            $orderItemQuantity = $this->toFloatOrNull($this->value($orderItem, ['anQty', 'anQty1', 'anPlanQty'], $orderedQty));
            $dispatchedQty = $this->toFloatOrNull($this->value($orderItem, ['anQtyDispDoc'], null));
            $packageQty = $this->toFloatOrNull($this->value($orderItem, ['anPackQty'], null));
            $unitNetWeight = $this->toFloatOrNull($this->value($orderItem, ['anDimWeight'], null));
            $unitGrossWeight = $this->toFloatOrNull($this->value($orderItem, ['anDimWeightBrutto'], null));
            $unitVolume = $this->toFloatOrNull($this->value($orderItem, ['anDimVolume'], null));
            $dimensionMultiplier = $orderItemQuantity ?? $orderedQty;
            $netWeight = $unitNetWeight !== null && $dimensionMultiplier !== null ? $unitNetWeight * $dimensionMultiplier : $unitNetWeight;
            $grossWeight = $unitGrossWeight !== null && $dimensionMultiplier !== null ? $unitGrossWeight * $dimensionMultiplier : $unitGrossWeight;
            $volume = $unitVolume !== null && $dimensionMultiplier !== null ? $unitVolume * $dimensionMultiplier : $unitVolume;
            $deliveryDate = $this->normalizeDate($this->value(
                $orderItem,
                ['adDeliveryDeadline', 'adDeliveryDate', 'adDateOut', 'adDateValid'],
                null
            ));
            $dateValue = $this->normalizeDate($this->value(
                $rawWorkOrder,
                ['adDate', 'adTimeIns'],
                $this->value($row, ['adDate', 'adTimeIns'], null)
            ));
            $status = trim((string) ($mappedWorkOrder['status'] ?? 'N/A'));
            $statusBucket = $this->statusBucket($status);
            $statusToneMap = [
                'planiran' => 'primary',
                'otvoren' => 'success',
                'rezerviran' => 'warning',
                'raspisan' => 'info',
                'u_radu' => 'warning',
                'djelimicno_zakljucen' => 'warning',
                'zakljucen' => 'danger',
            ];

            $links[] = [
                'id' => $workOrderNumber !== '' && $position !== ''
                    ? $workOrderNumber . '-' . $position
                    : ($workOrderNumber !== '' ? $workOrderNumber : '__link_' . $index),
                'dokument' => $workOrderNumber !== '' ? $workOrderNumber : trim((string) $this->valueTrimmed($row, ['acKeyView', 'acKey'], '-')),
                'datum' => $this->displayDate($dateValue),
                'rn_number' => $workOrderNumber,
                'pozicija' => $position,
                'artikal' => trim((string) ($orderItem['acIdent'] ?? $mappedWorkOrder['sifra'] ?? '')),
                'opis' => trim((string) ($orderItem['acName'] ?? $mappedWorkOrder['naziv'] ?? '')),
                'napomena' => trim((string) $this->valueTrimmed($orderItem, ['acNote', 'acDescr'], '')),
                'status' => $status,
                'status_tone' => $statusBucket !== null ? ($statusToneMap[$statusBucket] ?? 'secondary') : 'secondary',
                'alt' => $this->normalizeNullableNumber($this->value($orderItem, ['anVariant'], null)),
                'tip' => $linkType,
                'naruceno' => $orderedQty,
                'kolicina' => $orderItemQuantity,
                'otpremljeno' => $dispatchedQty,
                'izradjeno' => $producedQty,
                'neizradjeno' => $remainingQty,
                'jm' => trim((string) ($orderItem['acUM'] ?? $mappedWorkOrder['mj'] ?? $this->valueTrimmed($rawWorkOrder, ['acUM'], ''))),
                'cijena' => $this->normalizeNullableNumber($this->value($orderItem, ['anPrice'], null)),
                'r1' => $this->normalizeNullableNumber($this->value($orderItem, ['anRebate1'], null)),
                'r2' => $this->normalizeNullableNumber($this->value($orderItem, ['anRebate2'], null)),
                'sr' => $this->normalizeNullableNumber($this->value($orderItem, ['anRebate3'], null)),
                'popust' => $this->normalizeNullableNumber($this->value($orderItem, ['anRebate'], null)),
                'vrijednost' => $this->normalizeNullableNumber($this->value($orderItem, ['anPVValue'], null)),
                'pdv' => trim((string) $this->valueTrimmed($orderItem, ['acVATCode'], '')),
                'pdv_stopa' => $this->normalizeNullableNumber($this->value($orderItem, ['anVAT'], null)),
                'za_platiti' => $this->normalizeNullableNumber($this->value($orderItem, ['anPVForPay'], null)),
                'paketa' => $packageQty,
                'neto_tezina' => $netWeight,
                'bruto_tezina' => $grossWeight,
                'volumen' => $volume,
                'rok_otpreme' => $this->displayDate($deliveryDate),
                'odjel' => trim((string) $this->valueTrimmed($orderItem, ['acDept'], '')),
                'nos_tr' => trim((string) $this->valueTrimmed($orderItem, ['acCostDrv'], '')),
                'cijena_s_rabatom' => $this->normalizeNullableNumber($this->value($orderItem, ['anRTPrice', 'anSalePrice', 'anPrice'], null)),
                'order_item_qid' => $this->normalizeNullableNumber($this->value($orderItem, ['anQId'], null)),
                '_sort_rn' => $workOrderNumber,
                '_sort_position' => $this->toFloatOrNull($position !== '' ? $position : null),
                '_sort_index' => $index,
            ];
        }

        usort($links, static function (array $firstRow, array $secondRow): int {
            $firstWorkOrder = trim((string) ($firstRow['_sort_rn'] ?? ''));
            $secondWorkOrder = trim((string) ($secondRow['_sort_rn'] ?? ''));

            if ($firstWorkOrder !== $secondWorkOrder) {
                return strnatcasecmp($firstWorkOrder, $secondWorkOrder);
            }

            $firstPosition = $firstRow['_sort_position'] ?? null;
            $secondPosition = $secondRow['_sort_position'] ?? null;

            if ($firstPosition !== null && $secondPosition !== null && $firstPosition !== $secondPosition) {
                return $firstPosition <=> $secondPosition;
            }

            if ($firstPosition !== null && $secondPosition === null) {
                return -1;
            }

            if ($firstPosition === null && $secondPosition !== null) {
                return 1;
            }

            return ($firstRow['_sort_index'] ?? 0) <=> ($secondRow['_sort_index'] ?? 0);
        });

        return [
            'links' => array_map(static function (array $row): array {
                unset($row['_sort_rn'], $row['_sort_position'], $row['_sort_index']);

                return $row;
            }, $links),
            'work_order_position_ids' => array_map(static function (array $ids): array {
                return array_values($ids);
            }, $workOrderPositionIds),
        ];
    }

    private function buildOrderPositionPreviewData(
        array $rows,
        string $normalizedOrderNumber,
        array $orderKeysToNumbers,
        array $displayNumbers
    ): array {
        $linkedOrders = $this->resolveLinkedOrdersForRows($rows);
        $positions = [];
        $workOrderPositionIds = [];

        foreach ($rows as $index => $row) {
            $mappedRow = $this->mapRow($row, false, $linkedOrders);
            $resolvedOrder = $this->resolveOrderLinkageMatchMetaFromWorkOrder($row, $mappedRow, $orderKeysToNumbers, $displayNumbers);

            if (($resolvedOrder['normalized'] ?? '') !== $normalizedOrderNumber) {
                continue;
            }

            $workOrderNumber = trim((string) ($mappedRow['broj_naloga'] ?? ''));
            $resourceRows = $this->fetchMappedWorkOrderItemResources($row);

            foreach ($resourceRows as $resourceIndex => $resourceRow) {
                $positionId = trim((string) ($resourceRow['id'] ?? ''));
                $positionNumber = trim((string) ($resourceRow['pozicija'] ?? ''));
                $positionReference = $positionId !== '' ? $positionId : $positionNumber;

                if ($workOrderNumber !== '' && $positionReference !== '') {
                    $workOrderPositionIds[$workOrderNumber][$positionReference] = $positionReference;
                }

                $positions[] = [
                    'id' => $positionReference,
                    'rn_number' => $workOrderNumber,
                    'alternativa' => (string) ($resourceRow['alternativa'] ?? ''),
                    'pozicija' => $positionNumber,
                    'sifra' => (string) ($resourceRow['materijal'] ?? ''),
                    'naziv' => (string) ($resourceRow['naziv'] ?? ''),
                    'napomena' => (string) ($resourceRow['napomena'] ?? ''),
                    'kolicina' => $this->normalizeNullableNumber($resourceRow['kolicina'] ?? null),
                    'mj' => (string) ($resourceRow['mj'] ?? ''),
                    '_sort_work_order' => $workOrderNumber,
                    '_sort_position' => $this->toFloatOrNull($positionNumber !== '' ? $positionNumber : null),
                    '_sort_index' => ($index * 1000) + $resourceIndex,
                ];
            }
        }

        usort($positions, static function (array $firstRow, array $secondRow): int {
            $firstWorkOrder = trim((string) ($firstRow['_sort_work_order'] ?? ''));
            $secondWorkOrder = trim((string) ($secondRow['_sort_work_order'] ?? ''));

            if ($firstWorkOrder !== $secondWorkOrder) {
                return strnatcasecmp($firstWorkOrder, $secondWorkOrder);
            }

            $firstPosition = $firstRow['_sort_position'] ?? null;
            $secondPosition = $secondRow['_sort_position'] ?? null;

            if ($firstPosition !== null && $secondPosition !== null && $firstPosition !== $secondPosition) {
                return $firstPosition <=> $secondPosition;
            }

            if ($firstPosition !== null && $secondPosition === null) {
                return -1;
            }

            if ($firstPosition === null && $secondPosition !== null) {
                return 1;
            }

            return ($firstRow['_sort_index'] ?? 0) <=> ($secondRow['_sort_index'] ?? 0);
        });

        return [
            'positions' => array_map(static function (array $row): array {
                unset($row['_sort_work_order'], $row['_sort_position'], $row['_sort_index']);

                return $row;
            }, $positions),
            'work_order_position_ids' => array_map(static function (array $ids): array {
                return array_values($ids);
            }, $workOrderPositionIds),
        ];
    }

    private function buildOrderWorkOrderPreviewRows(
        array $rows,
        string $normalizedOrderNumber,
        array $orderKeysToNumbers,
        array $displayNumbers,
        array $workOrderPositionIds = []
    ): array {
        $linkedOrders = $this->resolveLinkedOrdersForRows($rows);
        $workOrders = [];

        foreach ($rows as $index => $row) {
            $mappedRow = $this->mapRow($row, false, $linkedOrders);
            $resolvedOrder = $this->resolveOrderLinkageMatchMetaFromWorkOrder($row, $mappedRow, $orderKeysToNumbers, $displayNumbers);
            $linkageMeta = $this->orderWorkOrderLinkageMeta($resolvedOrder);

            if (($resolvedOrder['normalized'] ?? '') !== $normalizedOrderNumber) {
                continue;
            }

            $workOrderNumber = (string) ($mappedRow['broj_naloga'] ?? '');
            $positionIds = array_values((array) ($workOrderPositionIds[$workOrderNumber] ?? []));
            $status = (string) ($mappedRow['status'] ?? 'N/A');
            $statusBucket = $this->statusBucket($status);
            $statusToneMap = [
                'planiran' => 'primary',
                'otvoren' => 'success',
                'rezerviran' => 'warning',
                'raspisan' => 'info',
                'u_radu' => 'warning',
                'djelimicno_zakljucen' => 'warning',
                'zakljucen' => 'danger',
            ];

            $workOrders[] = [
                'id' => $workOrderNumber,
                'rn_number' => $workOrderNumber,
                'order_number' => (string) ($resolvedOrder['display'] ?? $mappedRow['broj_narudzbe'] ?? ''),
                'issue_date' => $this->displayDate($mappedRow['datum_kreiranja'] ?? null),
                'planned_start' => $this->formatMetaDateTime($this->value($row, ['adSchedStartTime'], null)),
                'status' => $status,
                'status_tone' => $statusBucket !== null ? ($statusToneMap[$statusBucket] ?? 'secondary') : 'secondary',
                'sifra' => (string) ($mappedRow['sifra'] ?? ''),
                'naziv' => (string) ($mappedRow['naziv'] ?? ''),
                'kolicina' => $mappedRow['kolicina'] ?? null,
                'mj' => (string) ($mappedRow['mj'] ?? ''),
                'link_position' => (string) ($mappedRow['broj_pozicije_narudzbe'] ?? ''),
                'veza' => (string) ($linkageMeta['label'] ?? 'Sumnjiva veza'),
                'veza_tone' => (string) ($linkageMeta['tone'] ?? 'secondary'),
                'pozicije' => !empty($positionIds) ? implode(', ', $positionIds) : '',
                '_sort_date' => (string) ($mappedRow['datum_kreiranja'] ?? ''),
                '_sort_index' => $index,
            ];
        }

        usort($workOrders, static function (array $firstRow, array $secondRow): int {
            $firstDate = trim((string) ($firstRow['_sort_date'] ?? ''));
            $secondDate = trim((string) ($secondRow['_sort_date'] ?? ''));

            if ($firstDate !== $secondDate) {
                return strcmp($secondDate, $firstDate);
            }

            return ($firstRow['_sort_index'] ?? 0) <=> ($secondRow['_sort_index'] ?? 0);
        });

        return array_map(static function (array $row): array {
            unset($row['_sort_date'], $row['_sort_index']);

            return $row;
        }, $workOrders);
    }

    private function orderLinkagePickDate(array $dateCandidates, bool $preferEarliest): ?string
    {
        $dates = array_values(array_filter(array_map(function ($value) {
            return $this->normalizeDate($value);
        }, $dateCandidates)));

        if (empty($dates)) {
            return null;
        }

        sort($dates);

        return $preferEarliest ? $dates[0] : $dates[count($dates) - 1];
    }

    private function determineOrderLinkageState(
        int $positionCount,
        int $linkedPositionCount,
        int $workOrderCount,
        bool $hasOrderQuantity,
        float $orderQuantity,
        bool $hasWorkOrderQuantity,
        float $workOrderQuantity
    ): string {
        if ($workOrderCount < 1) {
            return 'none';
        }

        // Pantheon veza nije uvijek dvosmjerna, pa "djelimično" procjenjujemo preko pokrivenih pozicija i količina.
        if ($positionCount > 0) {
            if ($linkedPositionCount > 0 && $linkedPositionCount < $positionCount) {
                return 'partial';
            }

            if ($linkedPositionCount === 0 && $workOrderCount < $positionCount) {
                return 'partial';
            }
        }

        if ($hasOrderQuantity && $hasWorkOrderQuantity && ($workOrderQuantity + 0.000001) < $orderQuantity) {
            return 'partial';
        }

        return 'linked';
    }

    private function orderLinkageStateMeta(string $state): array
    {
        return match ($state) {
            'none' => [
                'label' => 'Bez RN',
                'tone' => 'danger',
            ],
            'partial' => [
                'label' => 'Djelimično',
                'tone' => 'warning',
            ],
            default => [
                'label' => 'Povezano',
                'tone' => 'success',
            ],
        };
    }

    private function canDeleteWorkOrders(mixed $user = null): bool
    {
        if (!is_object($user)) {
            return false;
        }

        if (method_exists($user, 'isAdmin')) {
            return (bool) $user->isAdmin();
        }

        return strtolower(trim((string) ($user->role ?? ''))) === 'admin';
    }

    private function canAccessOrderLinkage(mixed $user = null): bool
    {
        return $this->canDeleteWorkOrders($user);
    }

    private function orderLinkageForbiddenJsonResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Nemate dozvolu za pristup upravljanju narudžbama.',
            'data' => [],
        ], 403);
    }

    private function orderLinkageForbiddenHtmlResponse()
    {
        return response(
            '<div class="alert alert-danger mb-0">' . e('Nemate dozvolu za pristup upravljanju narudžbama.') . '</div>',
            403
        );
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
