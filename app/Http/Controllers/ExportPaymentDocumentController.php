<?php

namespace App\Http\Controllers;

use App\Models\OrderAiScan;
use App\Services\OrderAi\Support\OrderAiDocumentMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExportPaymentDocumentController extends Controller
{
    private const TOKEN_PRICE_BAM = 0.15;
    private const SELLER_INFO = [
        'name' => 'OD "qla.dev"',
        'address' => 'Bra&#263;e Muli&#263; 81, 71000 Sarajevo, BiH',
        'id_number' => '4304128180005',
        'bank_account' => '1861210311145263 ZiraatBank BH d.d.',
        'email' => 'info@qla.dev',
        'phone' => '+387 67 104 6240',
    ];
    private const BUYER_INFO = [
        'name' => 'Trendy d.o.o.',
        'address' => 'Bratstvo 11',
        'zip_code' => '72290',
        'city' => 'Novi Travnik, BiH',
        'ibk' => '236318900009',
        'pib' => '',
    ];
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

    public function __invoke(Request $request, string $document, OrderAiDocumentMetrics $documentMetrics): View
    {
        $this->authorizeModuleAccess($request);

        $documentType = $this->normalizeDocumentType($document);

        if ($documentType === '') {
            abort(404);
        }

        $period = $this->resolveBillingPeriod($request);
        $rows = $this->buildLineItems($period['start'], $period['end'], $documentMetrics);
        $invoice = $this->buildInvoiceMakerPayload($documentType, $period, $rows);
        $totals = $this->calculateInvoiceMakerTotals(
            $invoice['items'],
            (float) $invoice['discountPercent'],
            (float) $invoice['taxPercent'],
            (float) $invoice['advancePaidAmount'],
            (string) $invoice['invoiceType']
        );

        return view('content.apps.ai.app-ai-token-payment-document', [
            'documentTitle' => $invoice['documentTitle'],
            'invoice' => $invoice,
            'period' => $period,
            'printItemPages' => [$invoice['items']],
            'totals' => $totals,
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

    private function normalizeDocumentType(string $document): string
    {
        $document = strtolower(trim($document));

        if (in_array($document, ['predracun', 'a4-faktura'], true)) {
            return $document;
        }

        return '';
    }

    private function resolveBillingPeriod(Request $request): array
    {
        $today = Carbon::today();
        $month = (int) $request->query('month', $today->month);
        $year = (int) $request->query('year', $today->year);

        if ($month < 1 || $month > 12) {
            $month = (int) $today->month;
        }

        if ($year < 2000 || $year > ((int) $today->year + 1)) {
            $year = (int) $today->year;
        }

        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        return [
            'start' => $periodStart,
            'end' => $periodEnd,
            'label' => sprintf(
                '%s %d',
                self::MONTH_OPTIONS[(int) $periodStart->month] ?? $periodStart->format('m'),
                (int) $periodStart->year
            ),
        ];
    }

    private function buildLineItems(Carbon $periodStart, Carbon $periodEnd, OrderAiDocumentMetrics $documentMetrics): array
    {
        return $this->baseHistoryQuery()
            ->whereRaw($this->eventTimestampExpression() . ' >= ?', [$periodStart->toDateTimeString()])
            ->whereRaw($this->eventTimestampExpression() . ' <= ?', [$periodEnd->toDateTimeString()])
            ->orderByRaw($this->eventTimestampExpression() . ' ASC')
            ->orderBy('id')
            ->get([
                'id',
                'document_profile',
                'status',
                'processed_at',
                'completed_at',
                'created_at',
                'source_file_name',
                'source_email_subject',
                'page_count',
            ])
            ->map(function (OrderAiScan $scan) use ($documentMetrics): array {
                $quantity = $this->resolveBillableQuantity($scan, $documentMetrics);

                return [
                    'date_time' => $this->resolveEventTimestamp($scan)?->format('d.m.Y H:i') ?? '-',
                    'name' => $this->resolveLineItemName($scan),
                    'quantity' => $quantity,
                    'unit_price' => self::TOKEN_PRICE_BAM,
                    'total_amount' => round($quantity * self::TOKEN_PRICE_BAM, 2),
                ];
            })
            ->values()
            ->all();
    }

    private function buildInvoiceMakerPayload(string $documentType, array $period, array $rows): array
    {
        $periodStart = $period['start'] instanceof Carbon ? $period['start'] : Carbon::parse($period['start']);
        $periodEnd = $period['end'] instanceof Carbon ? $period['end'] : Carbon::parse($period['end']);
        $today = Carbon::today();
        $invoiceType = $documentType === 'predracun' ? 'predracun' : 'faktura';
        $documentTitle = $documentType === 'predracun'
            ? $this->decode('Predra&#269;un')
            : 'A4 faktura';
        $periodLabel = (string) ($period['label'] ?? $periodStart->format('m.Y'));
        $fiscalNumber = 'AI-' . $periodStart->format('Y-m');

        return [
            'id' => 'ai-payment-' . $periodStart->format('Y-m') . '-' . $invoiceType,
            'sourceBillId' => null,
            'invoiceType' => $invoiceType,
            'documentTitle' => $documentTitle,
            'currency' => 'BAM',
            'number' => $fiscalNumber,
            'fiscalNumber' => $fiscalNumber,
            'createdAt' => $today->toDateString(),
            'deliveryDate' => $periodEnd->toDateString(),
            'dueDate' => $today->copy()->addDays(7)->toDateString(),
            'conditions' => $this->decode('Pla&#263;anje po potro&#353;nji prikazanoj u AI History evidenciji.'),
            'discountPercent' => 0,
            'taxPercent' => 0,
            'advancePaidAmount' => 0,
            'notes' => $this->decode('Ra&#269;un za mjesec ') . $periodLabel . '.',
            'periodLabel' => $periodLabel,
            'periodStartDisplay' => $this->formatDateForDisplay($periodStart),
            'periodEndDisplay' => $this->formatDateForDisplay($periodEnd),
            'seller' => [
                'name' => self::SELLER_INFO['name'],
                'address' => $this->decode(self::SELLER_INFO['address']),
                'idNumber' => self::SELLER_INFO['id_number'],
                'bankAccount' => self::SELLER_INFO['bank_account'],
                'email' => self::SELLER_INFO['email'],
                'phone' => self::SELLER_INFO['phone'],
            ],
            'buyer' => [
                'name' => self::BUYER_INFO['name'],
                'address' => self::BUYER_INFO['address'],
                'zipCode' => self::BUYER_INFO['zip_code'],
                'city' => self::BUYER_INFO['city'],
                'ibk' => self::BUYER_INFO['ibk'],
                'pib' => self::BUYER_INFO['pib'],
            ],
            'items' => array_values(array_map(function (array $row, int $index): array {
                return $this->mapInvoiceMakerItem($row, $index);
            }, $rows, array_keys($rows))),
        ];
    }

    private function mapInvoiceMakerItem(array $row, int $index): array
    {
        $quantity = max(0, (float) ($row['quantity'] ?? 0));
        $price = (float) ($row['unit_price'] ?? self::TOKEN_PRICE_BAM);
        $description = trim((string) ($row['name'] ?? '-'));

        return [
            'id' => 'ai-token-line-' . ($index + 1),
            'dateTime' => trim((string) ($row['date_time'] ?? '-')) ?: '-',
            'description' => $description !== '' ? $description : 'AI scan #' . ($index + 1),
            'hoursOrQty' => $quantity,
            'price' => $price,
            'total' => round($quantity * $price, 2),
        ];
    }

    private function calculateInvoiceMakerTotals(
        array $items,
        float $discountPercent,
        float $taxPercent,
        float $advanceAmount,
        string $invoiceType
    ): array {
        $rawSubtotal = array_reduce($items, static function (float $sum, array $item): float {
            return $sum + ((float) ($item['hoursOrQty'] ?? 0) * (float) ($item['price'] ?? 0));
        }, 0.0);
        $discountAmount = $rawSubtotal * ($discountPercent / 100);
        $afterDiscount = $rawSubtotal - $discountAmount;
        $vatAmount = $afterDiscount * ($taxPercent / 100);
        $finalTotal = $afterDiscount + $vatAmount - ($invoiceType === 'avansna' ? $advanceAmount : 0);

        return [
            'subtotal' => round($rawSubtotal, 2),
            'discount' => round($discountAmount, 2),
            'vat' => round($vatAmount, 2),
            'total' => round(max(0, $finalTotal), 2),
        ];
    }

    private function resolveBillableQuantity(OrderAiScan $scan, OrderAiDocumentMetrics $documentMetrics): float
    {
        if ($scan->processed_at === null) {
            return 0.0;
        }

        return (float) $documentMetrics->calculateBilledTokens(max(0, (int) ($scan->page_count ?? 0)));
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

    private function eventTimestampExpression(): string
    {
        return 'COALESCE(processed_at, completed_at, created_at)';
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

    private function resolveLineItemName(OrderAiScan $scan): string
    {
        $name = trim((string) ($scan->source_file_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $subject = trim((string) ($scan->source_email_subject ?? ''));

        if ($subject !== '') {
            return $subject;
        }

        return 'AI scan #' . (int) $scan->id;
    }

    private function formatDateForDisplay(Carbon|string|null $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('d.m.Y');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        return Carbon::parse($value)->format('d.m.Y');
    }

    private function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }
}
