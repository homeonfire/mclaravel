<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Sku;

class LogisticsController extends Controller
{
    public function index(Request $request)
    {
        // 1. Получаем параметры фильтров и СОРТИРОВКИ
        $selectedStoreId = $request->input('store_id');
        $searchQuery = $request->input('search');
        $startDate = $request->input('start_date', now()->subDays(14)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        // По умолчанию сортируем по оборачиваемости (сначала проблемные)
        $sortColumn = $request->input('sort', 'turnover_days');
        $sortDirection = $request->input('direction', 'asc');

        $durationInDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

        // 2. Подзапрос для расчета среднего темпа продаж
        $salesPaceSubquery = DB::table('sales_raw')
            ->select('barcode', DB::raw("COUNT(*) / {$durationInDays} as avg_daily_sales"))
            ->where('saleID', 'like', 'S-%')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('barcode');

        // 3. Основной запрос для получения SKU
        $skusQuery = Sku::query()
            ->join('products', 'skus.product_nmID', '=', 'products.nmID')
            ->join('sku_stocks', 'skus.barcode', '=', 'sku_stocks.sku_barcode')
            ->leftJoinSub($salesPaceSubquery, 'sales_pace', 'skus.barcode', '=', 'sales_pace.barcode')
            ->select(
                'products.title', 'products.main_image_url', 'products.vendorCode',
                'skus.barcode', 'skus.tech_size',
                'sku_stocks.*',
                DB::raw('COALESCE(sales_pace.avg_daily_sales, 0) as avg_daily_sales')
            )
            ->when($selectedStoreId, function ($query, $storeId) {
                return $query->where('products.store_id', $storeId);
            })
            ->when($searchQuery, function ($query, $search) {
                return $query->where('products.vendorCode', 'like', "%{$search}%")
                    ->orWhere('skus.barcode', 'like', "%{$search}%");
            });

        // 4. Рассчитываем оборачиваемость (теперь это вычисляемое поле в запросе)
        $skusQuery->addSelect(DB::raw('
            CASE
                WHEN COALESCE(sales_pace.avg_daily_sales, 0) > 0 THEN FLOOR((sku_stocks.stock_wb + sku_stocks.stock_own) / sales_pace.avg_daily_sales)
                ELSE NULL
            END as turnover_days
        '));

        // 5. Применяем сортировку
        // Проверяем, что колонка разрешена для сортировки, для безопасности
        if (in_array($sortColumn, ['avg_daily_sales', 'stock_wb', 'turnover_days'])) {
            $skusQuery->orderBy($sortColumn, $sortDirection);
        }

        $skus = $skusQuery->paginate(50);

        // 6. Получаем данные для фильтров
        $stores = DB::table('stores')->get();

        return view('logistics.index', [
            'skus' => $skus,
            'stores' => $stores,
            'selectedStoreId' => $selectedStoreId,
            'searchQuery' => $searchQuery,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'sortColumn' => $sortColumn,
            'sortDirection' => $sortDirection,
        ]);
    }
}
