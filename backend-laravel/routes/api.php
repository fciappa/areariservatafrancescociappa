<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollaboratorsController;
use App\Http\Controllers\Api\ClientsController;
use App\Http\Controllers\Api\TariffsController;
use App\Http\Controllers\Api\ProjectsController;
use App\Http\Controllers\Api\HoursController;
use App\Http\Controllers\Api\InvoicesController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

// ── Auth (public) ─────────────────────────────────────────
Route::post('/auth/login',   [AuthController::class, 'login']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);
Route::post('/auth/logout',  [AuthController::class, 'logout']);

// ── Protected routes ──────────────────────────────────────
Route::middleware('auth.jwt')->group(function () {

    // Collaborators
    Route::get('/collaborators',         [CollaboratorsController::class, 'index']);
    Route::get('/collaborators/{id}',    [CollaboratorsController::class, 'show']);
    Route::middleware('admin')->group(function () {
        Route::post('/collaborators',           [CollaboratorsController::class, 'store']);
        Route::put('/collaborators/{id}',       [CollaboratorsController::class, 'update']);
        Route::delete('/collaborators/{id}',    [CollaboratorsController::class, 'destroy']);
    });

    // Clients (admin only)
    Route::middleware('admin')->group(function () {
        Route::get('/clients',          [ClientsController::class, 'index']);
        Route::get('/clients/{id}',     [ClientsController::class, 'show']);
        Route::post('/clients',         [ClientsController::class, 'store']);
        Route::put('/clients/{id}',     [ClientsController::class, 'update']);
        Route::delete('/clients/{id}',  [ClientsController::class, 'destroy']);
    });

    // Tariffs
    Route::get('/tariffs',      [TariffsController::class, 'index']);
    Route::get('/tariffs/{id}', [TariffsController::class, 'show']);
    Route::middleware('admin')->group(function () {
        Route::post('/tariffs',          [TariffsController::class, 'store']);
        Route::put('/tariffs/{id}',      [TariffsController::class, 'update']);
        Route::delete('/tariffs/{id}',   [TariffsController::class, 'destroy']);
    });

    // Projects — NOTE: specific routes BEFORE /{id} to avoid conflicts
    Route::get('/projects/tariff/resolve',    [ProjectsController::class, 'resolveTargetTariff']);
    Route::get('/projects',                   [ProjectsController::class, 'index']);
    Route::get('/projects/{id}',              [ProjectsController::class, 'show']);
    Route::middleware('admin')->group(function () {
        Route::post('/projects',                          [ProjectsController::class, 'store']);
        Route::put('/projects/{id}',                      [ProjectsController::class, 'update']);
        Route::post('/projects/{id}/assignments',         [ProjectsController::class, 'addAssignment']);
        Route::delete('/projects/assignments/{assignId}', [ProjectsController::class, 'removeAssignment']);
    });

    // Hours
    Route::get('/hours/collaborators',          [HoursController::class, 'indexCollaborators']);
    Route::post('/hours/collaborators',         [HoursController::class, 'storeCollaborator']);
    Route::put('/hours/collaborators/{id}',     [HoursController::class, 'updateCollaborator']);
    Route::delete('/hours/collaborators/{id}',  [HoursController::class, 'destroyCollaborator']);

    Route::middleware('admin')->group(function () {
        Route::get('/hours/my',           [HoursController::class, 'indexMy']);
        Route::post('/hours/my/bulk',     [HoursController::class, 'bulkStoreMy']);
        Route::post('/hours/my',          [HoursController::class, 'storeMy']);
        Route::put('/hours/my/{id}',      [HoursController::class, 'updateMy']);
        Route::delete('/hours/my/{id}',   [HoursController::class, 'destroyMy']);
    });

    // Invoices (admin only) — summary/monthly BEFORE /{id}
    Route::middleware('admin')->group(function () {
        Route::get('/invoices/summary/monthly',  [InvoicesController::class, 'monthlySummary']);
        Route::get('/invoices',                  [InvoicesController::class, 'index']);
        Route::get('/invoices/{id}',             [InvoicesController::class, 'show']);
        Route::post('/invoices/simulate',        [InvoicesController::class, 'simulate']);
        Route::post('/invoices',                 [InvoicesController::class, 'store']);
        Route::put('/invoices/{id}/status',      [InvoicesController::class, 'updateStatus']);
    });

    // Users (admin only)
    Route::middleware('admin')->group(function () {
        Route::get('/users',                [UsersController::class, 'index']);
        Route::post('/users',               [UsersController::class, 'store']);
        Route::put('/users/{id}/password',  [UsersController::class, 'changePassword']);
        Route::put('/users/{id}/toggle',    [UsersController::class, 'toggle']);
    });
});
