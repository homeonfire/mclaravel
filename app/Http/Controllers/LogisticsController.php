<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Sku;

class LogisticsController extends Controller
{
    public function index(Request $request)
    {
        // 1. Получаем параметры фильтров и сортировки
        $selectedStoreId = $request->input('store_id');
        $searchQuery = $request->input('search');
        $startDate = $request->input('start_date', now()->subDays(14)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $sortColumn = $request->input('sort', 'title');
        $sortDirection = $request->input('direction', 'asc');
        $durationInDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

        // 2. Подзапрос для расчета темпа продаж по каждому SKU
        $salesPaceSubquery = DB::table('sales_raw')
            ->select('barcode', DB::raw("COUNT(*) / {$durationInDays} as avg_daily_sales"))
            ->where('saleID', 'like', 'S%')
            ->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
            ->groupBy('barcode');

        // 3. Подзапрос для расчета СУММАРНЫХ показателей для каждого ТОВАРА (для сортировки)
        $totalsSubquery = Sku::query()
            ->join('sku_stocks', 'skus.barcode', '=', 'sku_stocks.sku_barcode')
            ->leftJoinSub($salesPaceSubquery, 'sales_pace', 'skus.barcode', '=', 'sales_pace.barcode')
            ->select(
                'skus.product_nmID',
                DB::raw('SUM(COALESCE(sales_pace.avg_daily_sales, 0)) as total_avg_daily_sales'),
                DB::raw('SUM(sku_stocks.stock_wb) as total_stock_wb')
            )
            ->groupBy('skus.product_nmID');

        // 4. Основной запрос к ТОВАРАМ с пагинацией, фильтрами и сортировкой
        $productsQuery = Product::query()
            ->leftJoinSub($totalsSubquery, 'totals', 'products.nmID', '=', 'totals.product_nmID')
            ->select('products.*', 'totals.total_avg_daily_sales', 'totals.total_stock_wb')
            ->when($selectedStoreId, function ($query, $storeId) {
                return $query->where('store_id', $storeId);
            })
            ->when($searchQuery, function ($query, $search) {
                return $query->where('title', 'like', "%{$search}%")
                    ->orWhere('vendorCode', 'like', "%{$search}%");
            });

        if (in_array($sortColumn, ['total_avg_daily_sales', 'total_stock_wb', 'title'])) {
            $productsQuery->orderBy($sortColumn, $sortDirection);
        }

        $products = $productsQuery->paginate(15);
        $productNmIDsOnPage = $products->pluck('nmID');

        // 5. Получаем ВСЕ SKU для товаров на текущей странице (для раскрывающихся строк)
        $skus = Sku::query()
            ->join('products', 'skus.product_nmID', '=', 'products.nmID')
            ->join('sku_stocks', 'skus.barcode', '=', 'sku_stocks.sku_barcode')
            ->leftJoinSub($salesPaceSubquery, 'sales_pace', 'skus.barcode', '=', 'sales_pace.barcode')
            ->whereIn('skus.product_nmID', $productNmIDsOnPage)
            ->select(
                'products.title', 'products.main_image_url', 'products.vendorCode', 'skus.product_nmID',
                'skus.barcode', 'skus.tech_size', 'sku_stocks.*',
                DB::raw('COALESCE(sales_pace.avg_daily_sales, 0) as avg_daily_sales')
            )
            ->get();

        // 6. Рассчитываем оборачиваемость и группируем SKU
        $skusGroupedByProduct = $skus->map(function ($sku) {
            $totalStock = $sku->stock_wb + $sku->stock_own;
            $sku->turnover_days = ($sku->avg_daily_sales > 0) ? floor($totalStock / $sku->avg_daily_sales) : null;
            return $sku;
        })->groupBy('product_nmID');

        // 7. Расчет итоговых сумм для отображения в родительских строках
        $productTotals = [];
        foreach ($skusGroupedByProduct as $nmID => $skusInGroup) { /* ... */ }

        // 8. Передача всех данных в представление
        return view('logistics.index', [
            'products' => $products,
            'skusGroupedByProduct' => $skusGroupedByProduct,
            'productTotals' => $productTotals,
            'stores' => DB::table('stores')->get(),
            'selectedStoreId' => $selectedStoreId,
            'searchQuery' => $searchQuery,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'sortColumn' => $sortColumn,
            'sortDirection' => $sortDirection,
        ]);
    }
}
