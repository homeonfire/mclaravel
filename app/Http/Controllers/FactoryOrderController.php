<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExcelImportLog;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessFactoryOrderExcel; // Для сложного файла
use App\Jobs\ProcessSimpleOrderFile;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Support\Facades\DB; // <-- Убедись, что DB импортирован
use App\Models\SimpleOrder;

class FactoryOrderController extends Controller
{
    /**
     * Показать форму загрузки (СЛОЖНЫЙ ФАЙЛ)
     */
    public function showForm()
    {
        $logs = ExcelImportLog::where('import_type', 'factory-order')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->groupBy('batch_id');

        return view('excel-import.factory-order', compact('logs'));
    }

    /**
     * Принять СЛОЖНЫЙ файл и поставить задачу в очередь.
     */
    public function processFile(Request $request)
    {
        try {
            $request->validate(['excel_file' => 'required|file|mimes:xlsx|max:10240',]);
        } catch (ValidationException $e) {
            return back()->withErrors(['excel_file' => $e->validator->errors()->first()])->withInput();
        }

        $file = $request->file('excel_file');
        $originalFileName = $file->getClientOriginalName();
        $batchId = Str::uuid()->toString();
        $importType = 'factory-order';

        try {
            $path = $file->store('temp_excel_imports');
            ProcessFactoryOrderExcel::dispatch($path, $originalFileName, $batchId);

            ExcelImportLog::create([
                'import_type' => $importType,
                'batch_id' => $batchId,
                'status' => 'queued',
                'message' => "Файл '{$originalFileName}' добавлен в очередь на обработку.",
            ]);
            Log::info("[ExcelImport:$importType:Batch $batchId] File '{$originalFileName}' queued for processing.");

            return back()->with('success', "Файл '{$originalFileName}' успешно загружен и добавлен в очередь.");

        } catch (Throwable $e) {
            Log::error("Failed to store uploaded file or dispatch job ($importType): " . $e->getMessage());
            return back()->with('error', 'Не удалось сохранить файл или поставить задачу в очередь. Ошибка: ' . $e->getMessage());
        }
    }

    // --- НОВЫЙ МЕТОД: Показать форму для Простого Заказа ---
    public function showSimpleForm()
    {
        $logs = ExcelImportLog::where('import_type', 'simple-order') // <-- Новый тип
        ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->groupBy('batch_id');

        return view('excel-import.simple-order', compact('logs')); // <-- Новый view
    }

    // --- НОВЫЙ МЕТОД: Обработать Простой Заказ ---
    public function processSimpleFile(Request $request)
    {
        try {
            $request->validate([ 'excel_file' => 'required|file|mimes:xlsx|max:10240', ]);
        } catch (ValidationException $e) {
            return back()->withErrors(['excel_file' => $e->validator->errors()->first()])->withInput();
        }

        $file = $request->file('excel_file');
        $originalFileName = $file->getClientOriginalName();
        $batchId = Str::uuid()->toString();
        $importType = 'simple-order'; // <-- Новый тип

        try {
            $path = $file->store('temp_excel_imports');
            ProcessSimpleOrderFile::dispatch($path, $originalFileName, $batchId); // <-- Новая задача

            ExcelImportLog::create([
                'import_type' => $importType,
                'batch_id' => $batchId,
                'status' => 'queued',
                'message' => "Файл '{$originalFileName}' (Простой заказ) добавлен в очередь.",
            ]);
            Log::info("[ExcelImport:$importType:Batch $batchId] File '{$originalFileName}' queued.");

            return back()->with('success', "Файл '{$originalFileName}' успешно загружен и добавлен в очередь.");

        } catch (Throwable $e) {
            Log::error("Failed to store uploaded file or dispatch job ($importType): " . $e->getMessage());
            return back()->with('error', 'Не удалось сохранить файл или поставить задачу в очередь. Ошибка: ' . $e->getMessage());
        }
    }

    // *** НОВЫЙ МЕТОД ДЛЯ ОТОБРАЖЕНИЯ СТРАНИЦЫ ***
    public function showSimpleOrdersList(Request $request)
    {
        $searchQuery = $request->input('search');
        $storeId = $request->input('store_id');

        // 1. Основной запрос к simple_orders
        $ordersQuery = SimpleOrder::query()
            // 2. Присоединяем SKU, чтобы получить product_nmID
            ->join('skus', 'simple_orders.sku_barcode', '=', 'skus.barcode')
            // 3. Присоединяем Products, чтобы получить инфо о товаре
            ->join('products', 'skus.product_nmID', '=', 'products.nmID')
            ->select(
                'products.main_image_url',
                'products.title',
                'products.vendorCode',
                'skus.tech_size',
                'simple_orders.sku_barcode',
                'simple_orders.order_quantity'
            )
            // 4. Фильтры
            ->when($storeId, function ($query, $storeId) {
                return $query->where('products.store_id', $storeId);
            })
            ->when($searchQuery, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('products.vendorCode', 'like', "%{$search}%")
                        ->orWhere('products.title', 'like', "%{$search}%")
                        ->orWhere('simple_orders.sku_barcode', 'like', "%{$search}%");
                });
            });

        // 5. Сортировка и Пагинация
        $orders = $ordersQuery->orderBy('simple_orders.order_quantity', 'desc')
            ->paginate(50);

        // 6. Данные для фильтров
        $stores = DB::table('stores')->get();

        return view('excel-import.simple-orders-index', [
            'orders' => $orders,
            'stores' => $stores,
            'selectedStoreId' => $storeId,
            'searchQuery' => $searchQuery,
        ]);
    }
}
