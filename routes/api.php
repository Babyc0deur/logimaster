<?php

use App\Http\Controllers\Api;
use App\Http\Middleware\AuditApiWrites;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [Api\AuthController::class, 'login'])->middleware('throttle:login');

// Application mobile du convoyeur (PWA) : jeton à capacité « mobile » uniquement
Route::post('mobile/login', [Api\Mobile\MobileAuthController::class, 'login'])->middleware('throttle:login');
Route::prefix('mobile')->middleware(['auth:sanctum', 'throttle:api', AuditApiWrites::class])->group(function () {
    Route::middleware('mobile.access:allow-temporary-password')->group(function () {
        Route::get('me', [Api\Mobile\MobileAuthController::class, 'me']);
        Route::post('logout', [Api\Mobile\MobileAuthController::class, 'logout']);
        Route::post('password', [Api\Mobile\MobileAuthController::class, 'changePassword']);
    });
    Route::middleware('mobile.access')->group(function () {
        Route::get('config', [Api\Mobile\MobileController::class, 'config']);
        Route::get('sorties', [Api\Mobile\MobileController::class, 'sorties']);
        Route::get('sorties/{id}', [Api\Mobile\MobileController::class, 'sortie']);
        Route::post('sorties/{id}/start', [Api\Mobile\MobileController::class, 'start']);
        Route::post('sorties/{id}/finish', [Api\Mobile\MobileController::class, 'finish']);
        Route::post('sorties/{id}/ravitaillements', [Api\Mobile\MobileController::class, 'ravitaillement']);
        Route::post('livraisons/{id}', [Api\Mobile\MobileController::class, 'livraison']);
        Route::get('planning', [Api\Mobile\FleetController::class, 'planning']);
        Route::get('vehicules', [Api\Mobile\FleetController::class, 'vehicules']);
        Route::post('vehicules/{id}/signalements', [Api\Mobile\FleetController::class, 'signalement']);
        Route::post('push-test', [Api\Mobile\FleetController::class, 'pushTest']);
        Route::get('notifications', [Api\Mobile\MobileController::class, 'notifications']);
        Route::post('notifications/read', [Api\Mobile\MobileController::class, 'readNotifications']);
        Route::post('push-subscriptions', [Api\Mobile\MobileController::class, 'subscribe']);
        Route::delete('push-subscriptions', [Api\Mobile\MobileController::class, 'unsubscribe']);
    });
});

Route::middleware(['auth:sanctum', EnsureActiveUser::class, 'throttle:api', AuditApiWrites::class])->group(function () {
    Route::get('auth/me', [Api\AuthController::class, 'me']);
    Route::post('auth/logout', [Api\AuthController::class, 'logout']);

    // Référentiels
    Route::apiResource('vehicles', Api\VehicleController::class)->parameters(['vehicles' => 'id']);
    Route::get('vehicles/{id}/documents', [Api\VehicleController::class, 'documents']);
    Route::get('vehicles/{id}/outings', [Api\VehicleController::class, 'outings']);
    Route::get('vehicles/{id}/maintenance', [Api\VehicleController::class, 'maintenance']);
    Route::get('vehicles/{id}/stats', [Api\VehicleController::class, 'stats']);

    Route::apiResource('drivers', Api\DriverController::class)->parameters(['drivers' => 'id']);
    Route::get('drivers/{id}/license-status', [Api\DriverController::class, 'licenseStatus']);
    Route::get('drivers/{id}/stats', [Api\DriverController::class, 'stats']);
    Route::get('personnels/{id}/stats', [Api\PersonnelController::class, 'stats']);
    Route::apiResource('personnels', Api\PersonnelController::class)->parameters(['personnels' => 'id']);

    Route::apiResource('circuits', Api\CircuitController::class)->parameters(['circuits' => 'id']);
    Route::get('circuits/{id}/compliance', [Api\CircuitController::class, 'compliance']);

    Route::apiResource('espc', Api\EspcController::class)->parameters(['espc' => 'id']);
    Route::get('espc/{id}/deliveries', [Api\EspcController::class, 'deliveries']);

    // Opérations
    Route::get('livraisons/summary', [Api\LivraisonController::class, 'summary']);
    Route::get('livraisons', [Api\LivraisonController::class, 'index']);
    Route::patch('livraisons/{id}', [Api\LivraisonController::class, 'update']);
    Route::post('chronogrammes/submit', [Api\ChronogrammeController::class, 'submit']);
    Route::post('chronogrammes/validate', [Api\ChronogrammeController::class, 'validatePlanning']);
    Route::post('chronogrammes/refuse', [Api\ChronogrammeController::class, 'refuse']);
    Route::post('chronogrammes/reopen', [Api\ChronogrammeController::class, 'reopen']);
    Route::apiResource('chronogrammes', Api\ChronogrammeController::class)->parameters(['chronogrammes' => 'id']);
    Route::post('chronogrammes/{id}/start', [Api\ChronogrammeController::class, 'start']);
    Route::post('sorties/{id}/validate', [Api\SortieController::class, 'validateSortie']);
    Route::post('sorties/{id}/duplicate', [Api\SortieController::class, 'duplicate']);
    Route::apiResource('sorties', Api\SortieController::class)->parameters(['sorties' => 'id']);
    Route::post('ravitaillements/{id}/validate', [Api\RavitaillementController::class, 'validateRavitaillement']);
    Route::apiResource('ravitaillements', Api\RavitaillementController::class)->parameters(['ravitaillements' => 'id']);
    Route::apiResource('immobilisations', Api\ImmobilisationController::class)->parameters(['immobilisations' => 'id']);
    Route::apiResource('vidanges', Api\VidangeController::class)->parameters(['vidanges' => 'id']);

    // Finance & documents
    Route::apiResource('expenses', Api\ExpenseController::class)->parameters(['expenses' => 'id']);
    Route::middleware('module:finance')->group(function () {
        Route::apiResource('budgets', Api\BudgetController::class)->parameters(['budgets' => 'id']);
        Route::get('finance/summary', [Api\FinanceController::class, 'summary']);
        Route::post('factures/{id}/submit', [Api\FactureController::class, 'submit']);
        Route::post('factures/{id}/validate', [Api\FactureController::class, 'validateFacture']);
        Route::post('factures/{id}/reject', [Api\FactureController::class, 'reject']);
        Route::post('factures/{id}/pay', [Api\FactureController::class, 'pay']);
        Route::post('factures/{id}/archive', [Api\FactureController::class, 'archive']);
        Route::post('factures/{id}/fichier', [Api\FactureController::class, 'upload']);
        Route::get('factures/{id}/fichier', [Api\FactureController::class, 'download']);
        Route::apiResource('factures', Api\FactureController::class)->parameters(['factures' => 'id']);
    });
    Route::get('documents', [Api\DocumentController::class, 'index']);
    Route::post('documents', [Api\DocumentController::class, 'store']);
    Route::get('documents/{id}/download', [Api\DocumentController::class, 'download']);

    // Dashboard & indicateurs
    Route::get('dashboard', [Api\DashboardController::class, 'index']);
    Route::get('dashboard/alerts', [Api\DashboardController::class, 'alerts']);
    Route::get('dashboard/missions-today', [Api\DashboardController::class, 'missionsToday']);
    Route::get('indicators/summary', [Api\IndicatorController::class, 'summary']);
    Route::get('indicators/{key}/history', [Api\IndicatorController::class, 'history']);

    // Carburant, maintenance, paramètres
    Route::get('fuel/analysis', [Api\FleetInsightsController::class, 'fuelAnalysis']);
    Route::get('fuel-prices', [Api\FleetInsightsController::class, 'fuelPrices']);
    Route::post('fuel-prices', [Api\FleetInsightsController::class, 'storeFuelPrice']);
    Route::get('maintenance/calendar', [Api\FleetInsightsController::class, 'maintenanceCalendar']);
    Route::get('settings', [Api\FleetInsightsController::class, 'settings']);
    Route::put('settings', [Api\FleetInsightsController::class, 'updateSettings']);

    // Rapports
    Route::get('reports/types', [Api\ReportController::class, 'types']);
    Route::get('reports', [Api\ReportController::class, 'index']);
    Route::post('reports/generate', [Api\ReportController::class, 'generate']);
    Route::post('reports/schedule', [Api\ReportController::class, 'schedule']);
    Route::get('report-schedules', [Api\ReportController::class, 'schedules']);
    Route::delete('report-schedules/{id}', [Api\ReportController::class, 'destroySchedule']);
    Route::get('reports/{id}/download', [Api\ReportController::class, 'download']);
    Route::post('reports/{id}/email', [Api\ReportController::class, 'email']);

    // Import / export en masse
    Route::get('import', [Api\ImportController::class, 'entities']);
    Route::get('import/workbook/template', [Api\ImportController::class, 'workbookTemplate']);
    Route::get('import/workbook/export', [Api\ImportController::class, 'workbookExport']);
    Route::post('import/workbook', [Api\ImportController::class, 'workbookImport']);
    Route::get('import/{entity}/template', [Api\ImportController::class, 'template']);
    Route::get('import/{entity}/export', [Api\ImportController::class, 'export']);
    Route::post('import/{entity}', [Api\ImportController::class, 'import']);

    // Administration
    Route::apiResource('users', Api\UserController::class)->parameters(['users' => 'id']);
    Route::get('audit-logs', [Api\AdminController::class, 'auditLogs']);
    Route::get('districts/{districtId}/devices', [Api\AdminController::class, 'devices']);
    Route::post('districts/{districtId}/sync-credentials/rotate', [Api\AdminController::class, 'rotateSyncCredentials']);
});
