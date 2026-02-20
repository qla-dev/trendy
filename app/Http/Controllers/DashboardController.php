<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardController extends Controller
{
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

      foreach (['adTimeIns', 'adDate', 'anNo'] as $orderColumn) {
        if (in_array($orderColumn, $columns, true)) {
          $query->orderByDesc($orderColumn);
        }
      }

      $latestOrders = $query
        ->limit(6)
        ->get()
        ->map(function ($row) {
          $rowData = (array) $row;

          return (object) [
            'id' => $rowData['acRefNo1'] ?? $rowData['acKey'] ?? $rowData['anNo'] ?? null,
            'work_order_number' => $rowData['acRefNo1'] ?? $rowData['acKey'] ?? 'N/A',
            'product_name' => $rowData['acName'] ?? $rowData['acDescr'] ?? 'Radni nalog',
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
}
