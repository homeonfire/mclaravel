<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlanningController; // Добавьте это вверху файла
use App\Http\Controllers\ProductController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\ExcelImportController; // Добавь вверху

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware(['auth', 'verified'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/reports/daily', [App\Http\Controllers\DailyReportController::class, 'index'])->name('reports.daily');
    Route::get('/reports/orders', [App\Http\Controllers\OrdersReportController::class, 'index'])->name('reports.orders');
    Route::get('/reports/behavioral', [App\Http\Controllers\BehavioralReportController::class, 'index'])->name('reports.behavioral');
    Route::get('/products/{product}', [App\Http\Controllers\ProductController::class, 'show'])->name('products.show');
    Route::get('/products', [App\Http\Controllers\ProductController::class, 'index'])->name('products.index');
    Route::post('/products/{product}/toggle-tracking', [App\Http\Controllers\ProductController::class, 'toggleTracking'])->name('products.toggleTracking');
    Route::get('/advertising', [App\Http\Controllers\AdCampaignController::class, 'index'])->name('advertising.index');
    Route::get('/advertising/{campaign}', [App\Http\Controllers\AdCampaignController::class, 'show'])->name('advertising.show');
    Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
    Route::post('/planning', [PlanningController::class, 'store'])->name('planning.store');
    Route::patch('/products/{product}/cost-price', [ProductController::class, 'updateCostPrice'])->name('products.updateCostPrice');

    Route::get('/logistics', [LogisticsController::class, 'index'])->name('logistics.index');

    Route::post('/products/{product}/seasonality', [ProductController::class, 'addSeasonality'])->name('products.addSeasonality');
    Route::delete('/products/seasonality/{period}', [ProductController::class, 'deleteSeasonality'])->name('products.deleteSeasonality');

    Route::patch('/logistics/sku-stock/{skuStock}', [LogisticsController::class, 'updateStock'])->name('logistics.updateStock');
    // Маршрут для отображения страницы загрузки
    Route::get('/import/in-transit-to-wb', [ExcelImportController::class, 'showInTransitForm'])->name('import.in-transit-to-wb.show');
// Маршрут для обработки загрузки файла и запуска SSE
    Route::post('/import/in-transit-to-wb', [ExcelImportController::class, 'processInTransitFile'])->name('import.in-transit-to-wb.process');

    // Маршруты для "Нашего склада"
    Route::get('/import/own-stock', [ExcelImportController::class, 'showOwnStockForm'])->name('import.own-stock.show');
    Route::post('/import/own-stock', [ExcelImportController::class, 'processOwnStockFile'])->name('import.own-stock.process');
});

require __DIR__.'/auth.php';
