<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\Api\FreightTableController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\SystemLogController;

Route::prefix('v1')->group(function () {

    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware(['auth.token', 'tenant'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::apiResource('users', UserController::class);
        Route::apiResource('companies', CompanyController::class)->only(['show']);
        Route::apiResource('carriers', CarrierController::class);
        Route::apiResource('freight-tables', FreightTableController::class);

        Route::get('quotations', [QuotationController::class, 'index']);
        Route::post('quotations', [QuotationController::class, 'store']);
        Route::get('quotations/{id}', [QuotationController::class, 'show']);
        Route::post('quotations/{id}/cancel', [QuotationController::class, 'cancel']);
        Route::post('quotations/parse-xml', [QuotationController::class, 'parseXml']);

        Route::get('contracts', [ContractController::class, 'index']);
        Route::post('contracts', [ContractController::class, 'store']);
        Route::get('contracts/{id}/pdf', [ContractController::class, 'pdf']);
        Route::get('contracts/{id}', [ContractController::class, 'show']);
        Route::post('contracts/{id}/cancel', [ContractController::class, 'cancel']);
        Route::patch('contracts/{id}/cte', [ContractController::class, 'updateCte']);

        Route::get('tracking', [TrackingController::class, 'index']);
        Route::get('tracking/{contractId}', [TrackingController::class, 'show']);
        Route::post('tracking/{contractId}/events', [TrackingController::class, 'store']);

        Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('reports/detailed', [ReportController::class, 'detailed']);

        Route::get('carriers/{carrierId}/performance', [ReportController::class, 'carrierPerformance']);

        Route::get('audit-logs', [AuditLogController::class, 'index']);
        Route::post('audit-logs', [AuditLogController::class, 'store']);

        Route::get('system-logs', [SystemLogController::class, 'index']);
        Route::post('system-logs', [SystemLogController::class, 'store']);
    });
});
