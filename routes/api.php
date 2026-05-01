<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\InvoiceController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/login', [AuthController::class, 'login']);

// Authenticated
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Empresas (Companies / Certificate configs)
    Route::get('/empresas', [CompanyController::class, 'index']);
    Route::post('/empresas', [CompanyController::class, 'store']);
    Route::delete('/empresas/{cnpj}', [CompanyController::class, 'destroy']);

    // Lotes (Batches)
    Route::get('/lotes', [BatchController::class, 'index']);
    Route::get('/lotes/{batch}', [BatchController::class, 'show']);
    Route::post('/lotes/preview', [BatchController::class, 'preview']);
    Route::post('/lotes/transmitir', [BatchController::class, 'transmitir']);
    Route::post('/lotes/{batch}/sincronizar', [BatchController::class, 'sincronizar']);
    Route::post('/lotes/{batch}/reenviar', [BatchController::class, 'reenviar']);
    Route::delete('/lotes/{batch}', [BatchController::class, 'destroy']);

    // Notas (Invoices)
    Route::get('/notas', [InvoiceController::class, 'index']);
    Route::post('/notas/{invoice}/cancelar', [InvoiceController::class, 'cancelar']);

    // Tomadores (Customers)
    Route::get('/tomadores', [CustomerController::class, 'index']);
    Route::put('/tomadores/{customer}', [CustomerController::class, 'update']);
    Route::delete('/tomadores/{customer}', [CustomerController::class, 'destroy']);
    Route::post('/tomadores/importar', [CustomerController::class, 'importar']);

    // Downloads
    Route::get('/download/xml/{batch}', [DownloadController::class, 'xml']);

    // Auditoria (admin only)
    Route::middleware('admin')->group(function () {
        Route::get('/auditoria', [AuditController::class, 'index']);
    });
});
