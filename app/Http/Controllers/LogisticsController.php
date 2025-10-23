<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Sku;
use App\Models\SkuStock; // <-- ИСПРАВЛЕНИЕ ЗДЕСЬ: Правильный путь к модели

class LogisticsController extends Controller
{
    public function index(Request $request)
    {
        $selectedStoreId = $request->input('store_id');
        $searchQuery = $request->input('search');
        $startDate = $request->input('start_date', now()->subDays(14)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $sortColumn = $request->input('sort', 'title');
        $sortDirection = $request->input('direction', 'asc');
        $durationInDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

        $salesPaceSubquery = DB::table('sales_raw')
            ->select('barcode', DB::raw("COUNT(*) / {$durationInDays} as avg_daily_sales"))
            ->where('saleID', 'like', 'S%')
            ->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
            ->groupBy('barcode');

        $totalsSubquery = Sku::query()
            ->join('sku_stocks', 'skus.barcode', '=', 'sku_stocks.sku_barcode')
            ->leftJoinSub($salesPaceSubquery, 'sales_pace', 'skus.barcode', '=', 'sales_pace.barcode')
            ->select(
                'skus.product_nmID',
                DB::raw('SUM(COALESCE(sales_pace.avg_daily_sales, 0)) as total_avg_daily_sales'),
                DB::raw('SUM(sku_stocks.stock_wb) as total_stock_wb')
            )
            ->groupBy('skus.product_nmID');

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

        $skusGroupedByProduct = $skus->map(function ($sku) {
            $totalStock = $sku->stock_wb + $sku->stock_own;
            $sku->turnover_days = ($sku->avg_daily_sales > 0) ? floor($totalStock / $sku->avg_daily_sales) : null;
            return $sku;
        })->groupBy('product_nmID');

        // *** ИСПРАВЛЕНИЕ ЗДЕСЬ: Заполняем логику расчета итогов ***
        $productTotals = [];
        foreach ($skusGroupedByProduct as $nmID => $skusInGroup) {
            $totalSales = $skusInGroup->sum('avg_daily_sales');
            $totalStockWb = $skusInGroup->sum('stock_wb');
            $totalStockOwn = $skusInGroup->sum('stock_own');
            $totalInWayToClient = $skusInGroup->sum('in_way_to_client');
            $totalInWayFromClient = $skusInGroup->sum('in_way_from_client');
            $totalInTransitToWb = $skusInGroup->sum('in_transit_to_wb');

            $totalStock = $totalStockWb + $totalStockOwn;
            $totalTurnover = ($totalSales > 0) ? floor($totalStock / $totalSales) : null;

            $productTotals[$nmID] = [
                'total_avg_daily_sales' => $totalSales,
                'total_stock_wb' => $totalStockWb,
                'total_in_way_to_client' => $totalInWayToClient,
                'total_in_way_from_client' => $totalInWayFromClient,
                'total_stock_own' => $totalStockOwn,
                'total_in_transit_to_wb' => $totalInTransitToWb,
                'total_turnover_days' => $totalTurnover,
            ];
        }

        return view('logistics.index', [
            'products' => $products,
            'skusGroupedByProduct' => $skusGroupedByProduct,
            'productTotals' => $productTotals, // <-- И передаем итоги в представление
            'stores' => DB::table('stores')->get(),
            'selectedStoreId' => $selectedStoreId,
            'searchQuery' => $searchQuery,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'sortColumn' => $sortColumn,
            'sortDirection' => $sortDirection,
        ]);
    }

    public function updateStock(Request $request, SkuStock $skuStock)
    {
        // Валидируем только те поля, которые пришли в запросе
        $validated = $request->validate([
            'stock_own' => 'sometimes|required|integer|min:0',
            'in_transit_to_wb' => 'sometimes|required|integer|min:0',
            'in_transit_general' => 'sometimes|required|integer|min:0', // Новое поле
            'at_factory' => 'sometimes|required|integer|min:0',         // Новое поле
        ]);

        $skuStock->update($validated);

        // Возвращаем JSON-ответ для AJAX
        // (Мы будем использовать AJAX для обновления без перезагрузки страницы)
        return response()->json(['success' => true, 'message' => 'Остатки обновлены!']);
    }
}
