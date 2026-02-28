<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardController extends Controller
{
  private ?array $orderTableColumnsCache = null;
  private array $linkedOrderCache = [];

  // Dashboard - Analytics
  public function dashboardAnalytics()
  {
    $pageConfigs = ['pageHeader' => false];

    return view('/content/dashboard/dashboard-analytics', ['pageConfigs' => $pageConfigs]);
  }

  // Dashboard - Home entrypoint (root)
  public function home()
  {
    if (Auth::check() && Auth::user()->hasRole('user')) {
      $pageConfigs = ['pageHeader' => false];

      return view('/content/apps/invoice/app-invoice-preview', ['pageConfigs' => $pageConfigs]);
    }

    return $this->dashboardEcommerce();
  }

  // Dashboard - Ecommerce
  public function dashboardEcommerce()
  {
    $pageConfigs = ['pageHeader' => false];
    $latestOrders = collect();
    $dashboardStats = [
      'work_orders_total' => 0,
      'customers_total' => 0,
      'products_total' => 0,
    ];

    try {
      $schema = config('workorders.schema', 'dbo');
      $tableName = config('workorders.table', 'tHF_WOEx');
      $table = $schema . '.' . $tableName;
      $columns = DB::table('INFORMATION_SCHEMA.COLUMNS')
        ->where('TABLE_SCHEMA', $schema)
        ->where('TABLE_NAME', $tableName)
        ->pluck('COLUMN_NAME')
        ->map(function ($column) {
          return (string) $column;
        })
        ->all();

      $dashboardStats['work_orders_total'] = (int) DB::table($table)->count();

      $customerColumn = $this->resolveFirstExistingColumn($columns, ['acConsignee', 'acReceiver', 'acPartner']);
      if ($customerColumn !== null) {
        $dashboardStats['customers_total'] = $this->countDistinctTrimmedValues($table, $customerColumn);
      }

      $productColumn = $this->resolveFirstExistingColumn($columns, ['acName', 'acDescr', 'acProduct', 'acItem']);
      if ($productColumn !== null) {
        $dashboardStats['products_total'] = $this->countDistinctTrimmedValues($table, $productColumn);
      }

      $query = DB::table($table);
      $hasIdentifierOrdering = $this->applyLatestOrdersIdentifierOrdering($query, $columns);

      if (!$hasIdentifierOrdering) {
        foreach (['adTimeIns', 'adDate', 'anNo'] as $orderColumn) {
          if (in_array($orderColumn, $columns, true)) {
            $query->orderByDesc($orderColumn);
          }
        }
      }

      $latestRows = $query
        ->limit(6)
        ->get()
        ->map(function ($row) {
          return (array) $row;
        })
        ->values()
        ->all();
      $linkedOrders = $this->resolveLinkedOrdersForRows($latestRows);

      $latestOrders = collect($latestRows)
        ->map(function (array $rowData) use ($linkedOrders) {
          $orderLinkKey = trim((string) ($rowData['acLnkKey'] ?? ''));
          $linkedOrder = $orderLinkKey !== '' && array_key_exists($orderLinkKey, $linkedOrders)
            ? (array) $linkedOrders[$orderLinkKey]
            : [];
          $orderKey = trim((string) ($linkedOrder['order_key'] ?? $orderLinkKey));
          $orderNumber = trim((string) ($linkedOrder['order_number'] ?? $orderKey));

          return (object) [
            'id' => $rowData['acRefNo1'] ?? $rowData['acKey'] ?? $rowData['anNo'] ?? null,
            'work_order_number' => $rowData['acRefNo1'] ?? $rowData['acKey'] ?? 'N/A',
            'order_number' => $orderNumber,
            'order_key' => $orderKey,
            'product_name' => $rowData['acName'] ?? $rowData['acDescr'] ?? 'Radni nalog',
            'product_code' => $rowData['acIdent'] ?? $rowData['acCode'] ?? '',
            'linked_document' => $rowData['acKey'] ?? '',
            'client_name' => $rowData['acConsignee'] ?? $rowData['acReceiver'] ?? 'N/A',
            'planned_start' => $this->parseDate($rowData['adDate'] ?? null),
            'status' => $this->mapStatus($rowData['acStatusMF'] ?? ($rowData['acStatus'] ?? null)),
          ];
        });
    } catch (Throwable $exception) {
      Log::error('Dashboard latest work orders query failed.', [
        'message' => $exception->getMessage(),
      ]);
    }

    return view('/content/dashboard/dashboard-ecommerce', [
      'pageConfigs' => $pageConfigs,
      'latestOrders' => $latestOrders,
      'dashboardStats' => $dashboardStats,
    ]);
  }

  private function resolveFirstExistingColumn(array $columns, array $candidates): ?string
  {
    foreach ($candidates as $candidate) {
      if (in_array($candidate, $columns, true)) {
        return $candidate;
      }
    }

    return null;
  }

  private function countDistinctTrimmedValues(string $table, string $column): int
  {
    $wrappedColumn = '[' . str_replace(']', ']]', $column) . ']';
    $expression = "NULLIF(LTRIM(RTRIM(CAST($wrappedColumn AS NVARCHAR(255)))), '')";

    return (int) DB::table($table)
      ->selectRaw("COUNT(DISTINCT $expression) AS aggregate")
      ->value('aggregate');
  }

  private function applyLatestOrdersIdentifierOrdering(Builder $query, array $columns): bool
  {
    $identifierColumn = $this->resolveFirstExistingColumn($columns, ['acRefNo1', 'acKey', 'anNo', 'id']);

    if ($identifierColumn === null) {
      return false;
    }

    $wrappedColumn = $query->getGrammar()->wrap($identifierColumn);
    $normalizedIdentifier = "REPLACE(REPLACE(REPLACE(COALESCE(CAST($wrappedColumn AS NVARCHAR(64)), ''), '-', ''), ' ', ''), '/', '')";
    $prefixExpression = "TRY_CAST(CASE WHEN LEN($normalizedIdentifier) >= 2 THEN LEFT($normalizedIdentifier, 2) ELSE $normalizedIdentifier END AS INT)";
    $suffixExpression = "TRY_CAST(CASE WHEN LEN($normalizedIdentifier) >= 6 THEN RIGHT($normalizedIdentifier, 6) ELSE $normalizedIdentifier END AS INT)";

    // Keep dashboard list aligned with invoice # hierarchy (prefix + last sequence part).
    $query->orderByRaw("CASE WHEN $prefixExpression IS NULL THEN 1 ELSE 0 END ASC");
    $query->orderByRaw("$prefixExpression DESC");
    $query->orderByRaw("CASE WHEN $suffixExpression IS NULL THEN 1 ELSE 0 END ASC");
    $query->orderByRaw("$suffixExpression DESC");
    $query->orderByRaw("$normalizedIdentifier DESC");

    return true;
  }

  private function parseDate(mixed $value): ?Carbon
  {
    if (!$value) {
      return null;
    }

    try {
      if ($value instanceof \DateTimeInterface) {
        return Carbon::instance($value);
      }

      return Carbon::parse((string) $value);
    } catch (Throwable $exception) {
      return null;
    }
  }

  private function mapStatus(mixed $status): string
  {
    if ($status === null || $status === '') {
      return 'N/A';
    }

    $code = strtoupper(trim((string) $status));

    return [
      'F' => 'Zakljucen',
      'I' => 'Zakljucen',
      'Z' => 'Zakljucen',
      'R' => 'Djelimicno zakljucen',
      'D' => 'U radu',
      'P' => 'Planiran',
      'S' => 'Rezerviran',
      'O' => 'Otvoren',
      'N' => 'Novo',
      'C' => 'Otkazano',
    ][$code] ?? (string) $status;
  }

  private function resolveLinkedOrdersForRows(array $rows): array
  {
    $linkKeys = array_values(array_unique(array_filter(array_map(function ($row) {
      $rowData = is_array($row) ? $row : (array) $row;
      return trim((string) ($rowData['acLnkKey'] ?? ''));
    }, $rows), function ($value) {
      return $value !== '';
    })));

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
    $orderKeyColumn = $this->resolveFirstExistingColumn($orderColumns, ['acKey']);
    $orderNumberColumn = $this->resolveFirstExistingColumn($orderColumns, ['acKeyView', 'acRefNo1', 'acKey']);
    $orderQidColumn = $this->resolveFirstExistingColumn($orderColumns, ['anQId']);

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
      $rows = DB::table($this->qualifiedOrderTableName())
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
      Log::warning('Dashboard linked order lookup failed.', [
        'work_orders_table' => config('workorders.schema', 'dbo') . '.' . config('workorders.table', 'tHF_WOEx'),
        'orders_table' => $this->qualifiedOrderTableName(),
        'message' => $exception->getMessage(),
      ]);
    }
  }

  private function orderTableColumns(): array
  {
    if ($this->orderTableColumnsCache !== null) {
      return $this->orderTableColumnsCache;
    }

    $schema = (string) config('workorders.schema', 'dbo');
    $tableName = (string) config('workorders.orders_table', 'tHE_Order');
    $this->orderTableColumnsCache = DB::table('INFORMATION_SCHEMA.COLUMNS')
      ->where('TABLE_SCHEMA', $schema)
      ->where('TABLE_NAME', $tableName)
      ->pluck('COLUMN_NAME')
      ->map(function ($columnName) {
        return (string) $columnName;
      })
      ->values()
      ->all();

    return $this->orderTableColumnsCache;
  }

  private function qualifiedOrderTableName(): string
  {
    return (string) config('workorders.schema', 'dbo') . '.' . (string) config('workorders.orders_table', 'tHE_Order');
  }
}
