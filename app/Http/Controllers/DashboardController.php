<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\WorkOrder;

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
    $latestOrders = WorkOrder::orderByDesc('id')->take(5)->get();

    return view('/content/dashboard/dashboard-ecommerce', [
      'pageConfigs' => $pageConfigs,
      'latestOrders' => $latestOrders
    ]);
  }
}
