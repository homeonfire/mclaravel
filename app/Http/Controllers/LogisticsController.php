<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Sku;
use App\Models\SkuStock; // <-- ИСПРАВЛЕНИЕ ЗДЕСЬ: Правильный путь к модели
use App\Models\SkuWarehouseStock; // <-- Добавляем новую модель

class LogisticsController extends Controller
{
    public function index(Request $request)
    {
        $selectedStoreId = $request->input('store_id');
        $searchQuery = $request->input('search');
        $startDate = $request->input('start_date', now()->subDays(14)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        // *** ИЗМЕНЕНИЕ 1: Сортировка по умолчанию ***
        $sortColumn = $request->input('sort', 'total_stock_wb'); // Было 'title'
        $sortDirection = $request->input('direction', 'desc'); // Было 'asc'

        $durationInDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

        // 1. Темп продаж по SKU (без изменений)
        $salesPaceSubquery = DB::table('sales_raw')
            ->select('barcode', DB::raw("COUNT(*) / {$durationInDays} as avg_daily_sales"))
            ->where('saleID', 'like', 'S%')
            ->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
            ->groupBy('barcode');

        // 2. СУММАРНЫЕ остатки для ТОВАРА (для сортировки и итогов)
        //    (Добавляем все поля, необходимые для итоговой строки)
        $totalsSubquery = Sku::query()
            ->join('sku_stocks', 'skus.barcode', '=', 'sku_stocks.sku_barcode')
            ->leftJoin('sku_warehouse_stocks', 'skus.barcode', '=', 'sku_warehouse_stocks.sku_barcode')
            ->leftJoinSub($salesPaceSubquery, 'sales_pace', 'skus.barcode', '=', 'sales_pace.barcode')
            ->select(
                'skus.product_nmID',
                DB::raw('SUM(COALESCE(sales_pace.avg_daily_sales, 0)) as total_avg_daily_sales'),
                DB::raw('SUM(COALESCE(sku_warehouse_stocks.quantity, 0)) as total_stock_wb'),
                DB::raw('SUM(COALESCE(sku_warehouse_stocks.in_way_to_client, 0)) as total_in_way_to_client'),
                DB::raw('SUM(COALESCE(sku_warehouse_stocks.in_way_from_client, 0)) as total_in_way_from_client'),
                DB::raw('SUM(sku_stocks.stock_own) as total_stock_own'),
                DB::raw('SUM(sku_stocks.in_transit_to_wb) as total_in_transit_to_wb'),
                DB::raw('SUM(sku_stocks.in_transit_general) as total_in_transit_general'),
                DB::raw('SUM(sku_stocks.at_factory) as total_at_factory')
            )
            ->groupBy('skus.product_nmID');

        // 3. Основной запрос к ТОВАРАМ
        $productsQuery = Product::query()
            ->leftJoinSub($totalsSubquery, 'totals', 'products.nmID', '=', 'totals.product_nmID')
            ->select('products.*',
                DB::raw('COALESCE(totals.total_avg_daily_sales, 0) as total_avg_daily_sales'),
                DB::raw('COALESCE(totals.total_stock_wb, 0) as total_stock_wb'),
                DB::raw('COALESCE(totals.total_in_way_to_client, 0) as total_in_way_to_client'),
                DB::raw('COALESCE(totals.total_in_way_from_client, 0) as total_in_way_from_client'),
                DB::raw('COALESCE(totals.total_stock_own, 0) as total_stock_own'),
                DB::raw('COALESCE(totals.total_in_transit_to_wb, 0) as total_in_transit_to_wb'),
                DB::raw('COALESCE(totals.total_in_transit_general, 0) as total_in_transit_general'),
                DB::raw('COALESCE(totals.total_at_factory, 0) as total_at_factory')
            )
            ->when($selectedStoreId, function ($query, $storeId) {
                return $query->where('products.store_id', $storeId);
            })
            ->when($searchQuery, function ($query, $search) {
                return $query->where('products.title', 'like', "%{$search}%")
                    ->orWhere('products.vendorCode', 'like', "%{$search}%");
            });

        // *** ИЗМЕНЕНИЕ 2: Показываем только товары с остатком WB > 0 ***
        // Мы фильтруем по `totals.total_stock_wb`, который присоединен через leftJoinSub
        $productsQuery->where(DB::raw('COALESCE(totals.total_stock_wb, 0)'), '>', 0);

        // 4. Применяем сортировку
        if (in_array($sortColumn, ['total_avg_daily_sales', 'total_stock_wb', 'title'])) {
            $productsQuery->orderBy($sortColumn, $sortDirection);
        } else {
            $productsQuery->orderBy('total_stock_wb', 'desc'); // Запасной вариант сортировки
        }

        $products = $productsQuery->paginate(15);
        $productNmIDsOnPage = $products->pluck('nmID');

        // 5. Получаем ДАННЫЕ по SKU для товаров на текущей странице
        $skus = Sku::query()
            ->join('sku_stocks', 'skus.barcode', '=', 'sku_stocks.sku_barcode')
            ->leftJoin('sku_warehouse_stocks', 'skus.barcode', '=', 'sku_warehouse_stocks.sku_barcode')
            ->leftJoinSub($salesPaceSubquery, 'sales_pace', 'skus.barcode', '=', 'sales_pace.barcode')
            ->whereIn('skus.product_nmID', $productNmIDsOnPage)
            ->select(
                'skus.product_nmID', 'skus.barcode', 'skus.tech_size',
                'sku_stocks.id', 'sku_stocks.stock_own', 'sku_stocks.in_transit_to_wb',
                'sku_stocks.in_transit_general', 'sku_stocks.at_factory',
                DB::raw('SUM(COALESCE(sku_warehouse_stocks.quantity, 0)) as stock_wb'),
                DB::raw('SUM(COALESCE(sku_warehouse_stocks.in_way_to_client, 0)) as in_way_to_client'),
                DB::raw('SUM(COALESCE(sku_warehouse_stocks.in_way_from_client, 0)) as in_way_from_client'),
                DB::raw('COALESCE(sales_pace.avg_daily_sales, 0) as avg_daily_sales')
            )
            ->groupBy('skus.barcode', 'skus.product_nmID', 'skus.tech_size', 'sku_stocks.id', 'sku_stocks.stock_own', 'sku_stocks.in_transit_to_wb', 'sku_stocks.in_transit_general', 'sku_stocks.at_factory', 'sales_pace.avg_daily_sales')
            ->orderBy('skus.tech_size')
            ->get();


        // 6. Получаем ДЕТАЛЬНЫЕ остатки по складам WB
        $warehouseStocksDetailed = SkuWarehouseStock::whereIn('sku_barcode', $skus->pluck('barcode'))
            ->get()
            ->groupBy('sku_barcode');

        // 7. Рассчитываем оборачиваемость для SKU, добавляем детализацию и группируем по товару
        $skusGroupedByProduct = $skus->map(function ($sku) use ($warehouseStocksDetailed) {
            $sku->warehouse_details = $warehouseStocksDetailed->get($sku->barcode, collect());
            $totalStockSku = $sku->stock_wb + $sku->stock_own;
            $sku->turnover_days = ($sku->avg_daily_sales > 0) ? floor($totalStockSku / $sku->avg_daily_sales) : null;
            return $sku;
        })->groupBy('product_nmID');

        // 8. Расчет итогов для родительской строки
        //    (Теперь мы можем просто использовать данные из $product, т.к. $productsQuery их уже содержит)
        $productTotals = [];
        foreach ($products as $product) {
            $nmID = $product->nmID;

            $totalStock = $product->total_stock_wb + $product->total_stock_own;
            $totalTurnover = ($product->total_avg_daily_sales > 0) ? floor($totalStock / $product->total_avg_daily_sales) : null;

            $productTotals[$nmID] = [
                'total_avg_daily_sales'    => $product->total_avg_daily_sales,
                'total_stock_wb'           => $product->total_stock_wb,
                'total_in_way_to_client'   => $product->total_in_way_to_client,
                'total_in_way_from_client' => $product->total_in_way_from_client,
                'total_stock_own'          => $product->total_stock_own,
                'total_in_transit_to_wb'   => $product->total_in_transit_to_wb,
                'total_in_transit_general' => $product->total_in_transit_general,
                'total_at_factory'         => $product->total_at_factory,
                'total_turnover_days'      => $totalTurnover,
            ];
        }

        // 9. Передача данных в представление
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
