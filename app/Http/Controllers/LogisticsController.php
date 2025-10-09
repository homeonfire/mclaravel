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
        // 1. Получаем параметры фильтров из запроса
        $selectedStoreId = $request->input('store_id');
        $searchQuery = $request->input('search');

        $startDate = $request->input('start_date', now()->subDays(14)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        // +1, чтобы включить последнюю дату в расчет
        $durationInDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

        // 2. Подзапрос для расчета среднего темпа продаж
        $salesPaceSubquery = DB::table('orders_raw')
            // *** ИЗМЕНЕНИЕ ЗДЕСЬ: Меняем COUNT(id) на COUNT(*) ***
            ->select('barcode', DB::raw("COUNT(*) / {$durationInDays} as avg_daily_sales"))
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

        $skus = $skusQuery->paginate(50);

        // 4. Рассчитываем оборачиваемость для каждой полученной записи
        $skus->getCollection()->transform(function ($sku) {
            $totalStock = $sku->stock_wb + $sku->stock_own;
            $sku->turnover_days = ($sku->avg_daily_sales > 0) ? floor($totalStock / $sku->avg_daily_sales) : null;
            return $sku;
        });

        // 5. Получаем данные для фильтров
        $stores = DB::table('stores')->get();

        return view('logistics.index', [
            'skus' => $skus,
            'stores' => $stores,
            'selectedStoreId' => $selectedStoreId,
            'searchQuery' => $searchQuery,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}
