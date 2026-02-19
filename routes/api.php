<?php

use App\Http\Controllers\WorkOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/work-orders', [WorkOrderController::class, 'index'])->name('api.work-orders.index');
Route::get('/work-orders/{id}', [WorkOrderController::class, 'show'])->name('api.work-orders.show');
Route::get('/work-orders-calendar', [WorkOrderController::class, 'calendar'])->name('api.work-orders.calendar');
