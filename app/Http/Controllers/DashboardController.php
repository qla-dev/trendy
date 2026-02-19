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

    try {
      $table = config('workorders.schema', 'dbo') . '.' . config('workorders.table', 'tHF_WOEx');
      $columns = DB::table('INFORMATION_SCHEMA.COLUMNS')
        ->where('TABLE_SCHEMA', config('workorders.schema', 'dbo'))
        ->where('TABLE_NAME', config('workorders.table', 'tHF_WOEx'))
        ->pluck('COLUMN_NAME')
        ->map(function ($column) {
          return (string) $column;
        })
        ->all();

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
      'latestOrders' => $latestOrders
    ]);
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
