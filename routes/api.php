<?php

use App\Http\Controllers\Cnc\CncApiController;
use App\Http\Controllers\Cnc\CncAuthController;
use App\Http\Controllers\OrderController;
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

Route::middleware(['auth:sanctum', \App\Http\Middleware\RestrictSupplier::class])->get('/user', function (Request $request) {
    return $request->user();
});

if (app()->environment('local')) {
    Route::get('/__codex/orders/inspect', [OrderController::class, 'inspectMaintenance'])->middleware(['auth:sanctum', \App\Http\Middleware\RestrictSupplier::class])
        ->name('api.__codex.orders.inspect');
    Route::post('/__codex/orders/by-key-delete', [OrderController::class, 'destroyByKeyMaintenance'])->middleware(['auth:sanctum', \App\Http\Middleware\RestrictSupplier::class])
        ->name('api.__codex.orders.by-key-delete');
}

Route::middleware(['auth:sanctum', \App\Http\Middleware\RestrictSupplier::class])->group(function () {
    Route::post('/orders', [OrderController::class, 'store'])->name('api.orders.store');
    Route::delete('/orders', [OrderController::class, 'destroyLinkedOrder'])->name('api.orders.destroy');
    Route::get('/work-orders', [WorkOrderController::class, 'index'])->name('api.work-orders.index');
    Route::get('/work-orders/{id}', [WorkOrderController::class, 'show'])->name('api.work-orders.show');
    Route::get('/work-orders-calendar', [WorkOrderController::class, 'calendar'])->name('api.work-orders.calendar');
    Route::get('/work-orders-yearly-summary', [WorkOrderController::class, 'yearlySummary'])->name('api.work-orders.yearly-summary');
});

/*
|--------------------------------------------------------------------------
| CNC Quoting API
|--------------------------------------------------------------------------
*/

Route::prefix('cnc')->group(function () {
    Route::post('/auth/login', [CncAuthController::class, 'login']);

    Route::middleware('cnc.auth')->group(function () {
        Route::post('/auth/logout', [CncAuthController::class, 'logout']);
        Route::get('/auth/me', [CncAuthController::class, 'me']);

        // Customers
        Route::get('/customers', [CncApiController::class, 'customers']);
        Route::post('/customers', [CncApiController::class, 'storeCustomer']);
        Route::put('/customers/{id}', [CncApiController::class, 'updateCustomer']);
        Route::delete('/customers/{id}', [CncApiController::class, 'deleteCustomer']);

        // Materials
        Route::get('/materials', [CncApiController::class, 'materials']);
        Route::post('/materials', [CncApiController::class, 'storeMaterial']);
        Route::put('/materials/{id}', [CncApiController::class, 'updateMaterial']);

        // Machines
        Route::get('/machines', [CncApiController::class, 'machines']);
        Route::post('/machines', [CncApiController::class, 'storeMachine']);
        Route::put('/machines/{id}', [CncApiController::class, 'updateMachine']);

        // Surface Protections
        Route::get('/surface-protections', [CncApiController::class, 'surfaceProtections']);
        Route::post('/surface-protections', [CncApiController::class, 'storeSurfaceProtection']);
        Route::put('/surface-protections/{id}', [CncApiController::class, 'updateSurfaceProtection']);
        Route::delete('/surface-protections/{id}', [CncApiController::class, 'deleteSurfaceProtection']);

        // Heat Treatments
        Route::get('/heat-treatments', [CncApiController::class, 'heatTreatments']);
        Route::post('/heat-treatments', [CncApiController::class, 'storeHeatTreatment']);
        Route::put('/heat-treatments/{id}', [CncApiController::class, 'updateHeatTreatment']);
        Route::delete('/heat-treatments/{id}', [CncApiController::class, 'deleteHeatTreatment']);

        // Other Operations
        Route::get('/other-operations', [CncApiController::class, 'otherOperations']);
        Route::post('/other-operations', [CncApiController::class, 'storeOtherOperation']);
        Route::put('/other-operations/{id}', [CncApiController::class, 'updateOtherOperation']);
        Route::delete('/other-operations/{id}', [CncApiController::class, 'deleteOtherOperation']);

        // Positions
        Route::get('/positions', [CncApiController::class, 'positions']);
        Route::post('/positions', [CncApiController::class, 'storePosition']);
        Route::get('/positions/by-code', [CncApiController::class, 'findPositionByCode']);
        Route::get('/positions/{id}', [CncApiController::class, 'showPosition']);
        Route::put('/positions/{id}', [CncApiController::class, 'updatePosition']);
        Route::delete('/positions/{id}', [CncApiController::class, 'deletePosition']);

        // Quotes
        Route::get('/quotes', [CncApiController::class, 'quotes']);
        Route::post('/quotes', [CncApiController::class, 'storeQuote']);
        Route::get('/quotes/{id}', [CncApiController::class, 'showQuote']);
        Route::put('/quotes/{id}', [CncApiController::class, 'updateQuote']);
        Route::delete('/quotes/{id}', [CncApiController::class, 'deleteQuote']);
        Route::post('/quotes/{id}/contact-history', [CncApiController::class, 'addContactHistory']);

        // Settings
        Route::get('/settings', [CncApiController::class, 'settings']);
        Route::put('/settings', [CncApiController::class, 'updateSettings']);
        Route::get('/settings/{key}', [CncApiController::class, 'getSetting']);
        Route::put('/settings/{key}', [CncApiController::class, 'putSetting']);

        // Activity Logs
        Route::get('/activity-logs', [CncApiController::class, 'activityLogs']);

        // Quote Drafts
        Route::get('/drafts', [CncApiController::class, 'quoteDrafts']);
        Route::get('/drafts/{draftId}', [CncApiController::class, 'showQuoteDraft']);
        Route::post('/drafts', [CncApiController::class, 'saveQuoteDraft']);
        Route::delete('/drafts/{draftId}', [CncApiController::class, 'deleteQuoteDraft']);

        // Users (admin only)
        Route::middleware('cnc.admin')->group(function () {
            Route::get('/users', [CncApiController::class, 'users']);
            Route::post('/users', [CncApiController::class, 'storeUser']);
            Route::put('/users/{id}', [CncApiController::class, 'updateUser']);
            Route::post('/users/{id}/reset-password', [CncApiController::class, 'resetUserPassword']);
        });
    });
});
