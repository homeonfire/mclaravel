<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\BehavioralStat;
use Illuminate\Support\Carbon;
use App\Models\ProductPlan;
use App\Models\Sku;

class ProductController extends Controller
{
    /**
     * НОВЫЙ МЕТОД: Отображает список всех товаров с поиском и пагинацией.
     */
    public function index(Request $request)
    {
        // 1. Получаем все параметры фильтров
        $searchQuery = $request->input('search');
        $storeId = $request->input('store_id');
        $withActiveCampaign = $request->boolean('with_active_campaign');
        $showActiveOnly = $request->boolean('show_active_only'); // <-- ВОЗВРАЩАЕМ ЭТУ СТРОКУ

        // 2. Получаем список магазинов
        $stores = DB::table('stores')->orderBy('store_name')->get();

        // 3. Подзапрос для суммирования остатков WB
        $wbStockSubquery = DB::table('skus')
            ->join('sku_warehouse_stocks', 'skus.barcode', '=', 'sku_warehouse_stocks.sku_barcode')
            ->select('skus.product_nmID', DB::raw('SUM(sku_warehouse_stocks.quantity) as total_stock_wb'))
            ->groupBy('skus.product_nmID');

        // 4. Основной запрос
        $productsQuery = Product::with('store')
            ->leftJoinSub($wbStockSubquery, 'wb_stock', function ($join) {
                $join->on('products.nmID', '=', 'wb_stock.product_nmID');
            })
            ->select('products.*', DB::raw('COALESCE(wb_stock.total_stock_wb, 0) as total_stock_wb'))

            // Фильтры
            ->when($searchQuery, function ($query, $search) {
                return $query->where('products.vendorCode', 'like', "%{$search}%");
            })
            ->when($storeId, function ($query, $storeId) {
                return $query->where('products.store_id', $storeId);
            })
            ->when($withActiveCampaign, function ($query) {
                return $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('ad_campaign_products as acp')
                        ->join('ad_campaigns as ac', function($join) {
                            $join->on('acp.advertId', '=', 'ac.advertId')
                                ->on('acp.store_id', '=', 'ac.store_id');
                        })
                        ->whereColumn('acp.nmID', 'products.nmID')
                        ->whereColumn('acp.store_id', 'products.store_id')
                        ->where('ac.status', 9);
                });
            })

            // *** ВОЗВРАЩАЕМ ФИЛЬТР "ПОКАЗАТЬ АКТУАЛЬНЫЕ" ***
            ->when($showActiveOnly, function ($query) {
                $currentMonth = Carbon::now()->month;
                return $query->whereExists(function ($subQuery) use ($currentMonth) {
                    $subQuery->select(DB::raw(1))
                        ->from('product_seasonality')
                        ->whereColumn('product_seasonality.product_nmID', 'products.nmID')
                        ->where(function ($periodQuery) use ($currentMonth) {
                            $periodQuery->whereRaw('start_month <= end_month AND ? BETWEEN start_month AND end_month', [$currentMonth])
                                ->orWhereRaw('start_month > end_month AND (? >= start_month OR ? <= end_month)', [$currentMonth, $currentMonth]);
                        });
                });
            })

            // Фильтр по ненулевым остаткам (как договорились)
            ->where(DB::raw('COALESCE(wb_stock.total_stock_wb, 0)'), '>', 0);


        // 5. Сортировка и пагинация
        $products = $productsQuery->orderBy('total_stock_wb', 'desc')
            ->orderBy('products.updated_at', 'desc')
            ->paginate(30);

        return view('products.index', [
            'products' => $products,
            'stores' => $stores,
            'selectedStoreId' => $storeId,
            'withActiveCampaign' => $withActiveCampaign,
            'showActiveOnly' => $showActiveOnly, // <-- ВОЗВРАЩАЕМ ПЕРЕДАЧУ В VIEW
        ]);
    }

    /**
     * Отображает детальную страницу продукта со всей статистикой.
     */
    public function show(Request $request, Product $product)
    {
        $product->load(['store', 'adCampaigns', 'seasonalityPeriods']);;
        $isTracked = auth()->user()->trackedProducts()->where('nmID', $product->nmID)->exists();

        // --- 1. ДАННЫЕ ДЛЯ СВОДКИ ЗА ВЧЕРАШНИЙ ДЕНЬ ---
        $yesterdayStats = BehavioralStat::where('nmID', $product->nmID)
            ->where('store_id', $product->store_id)
            ->whereDate('report_date', now()->subDay())
            ->first();

        $dayBeforeYesterdayStats = BehavioralStat::where('nmID', $product->nmID)
            ->where('store_id', $product->store_id)
            ->whereDate('report_date', now()->subDays(2))
            ->first();

        // --- 2. СТАТИСТИКА ЗА 7 ДНЕЙ (ДЛЯ ПЕРВОЙ ТАБЛИЦЫ И ГРАФИКА) ---
        $stats = BehavioralStat::where('nmID', $product->nmID)
            ->where('store_id', $product->store_id)
            ->whereMonth('report_date', now()->month)
            ->whereYear('report_date', now()->year)
            ->orderBy('report_date', 'asc')
            ->get();

        $chartData = [
            'labels' => $stats->pluck('report_date')->values()->map(function ($date) {
                return Carbon::parse($date)->format('d.m');
            }),
            'datasets' => [
                // --- ОСНОВНЫЕ МЕТРИКИ (КОЛИЧЕСТВО) ---
                [ 'label' => 'Переходы в карточку', 'yAxisID' => 'y', 'data' => $stats->pluck('openCardCount'), 'borderColor' => '#3b82f6', 'tension' => 0.1 ],
                [ 'label' => 'Добавления в корзину', 'yAxisID' => 'y', 'data' => $stats->pluck('addToCartCount'), 'borderColor' => '#a855f7', 'tension' => 0.1 ],

                // --- ШКАЛА ДЛЯ МАЛЕНЬКИХ МЕТРИК ---
                [ 'label' => 'Заказы, шт', 'yAxisID' => 'y2', 'data' => $stats->pluck('ordersCount'), 'borderColor' => '#22c55e', 'tension' => 0.1 ],
                [ 'label' => 'Выкупы, шт', 'yAxisID' => 'y2', 'data' => $stats->pluck('buyoutsCount'), 'borderColor' => '#14b8a6', 'tension' => 0.1, 'hidden' => true ], // Скрыт по умолчанию
                [ 'label' => 'Отмены, шт', 'yAxisID' => 'y2', 'data' => $stats->pluck('cancelCount'), 'borderColor' => '#ef4444', 'tension' => 0.1, 'hidden' => true ], // Скрыт по умолчанию

                // --- ФИНАНСОВЫЕ МЕТРИКИ (СУММЫ В РУБЛЯХ) ---
                // Используем вторую ось Y для сумм, чтобы масштабы не конфликтовали
                [ 'label' => 'Сумма заказов, ₽', 'yAxisID' => 'y1', 'data' => $stats->pluck('ordersSumRub'), 'borderColor' => '#f59e0b', 'tension' => 0.1, 'hidden' => true ],
                [ 'label' => 'Сумма выкупов, ₽', 'yAxisID' => 'y1', 'data' => $stats->pluck('buyoutsSumRub'), 'borderColor' => '#f97316', 'tension' => 0.1, 'hidden' => true ],
            ]
        ];

        $statsWithComparison = $stats->map(function ($item, $key) use ($stats) {
            $item->previous = $stats->get($key + 1);
            return $item;
        });

        // --- *** КЛЮЧЕВОЕ ИЗМЕНЕНИЕ: ГОТОВИМ ДАТЫ С ДНЯМИ НЕДЕЛИ *** ---
        $dayMap = ['ВС', 'ПН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'СБ'];

        // --- 3. ДАННЫЕ ДЛЯ СВОДНОЙ ТАБЛИЦЫ ЗА ТЕКУЩИЙ МЕСЯЦ ---
        $monthlyStats = BehavioralStat::where('nmID', $product->nmID)
            ->where('store_id', $product->store_id)
            ->whereMonth('report_date', now()->month)
            ->whereYear('report_date', now()->year)
            ->orderBy('report_date', 'asc')
            ->get();

        $previousMonthStats = BehavioralStat::where('nmID', $product->nmID)
            ->where('store_id', $product->store_id)
            ->whereBetween('report_date', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->get();

        $datesForPivot = $monthlyStats->pluck('report_date')->unique()->map(function ($dateString) use ($dayMap) {
            $carbonDate = Carbon::parse($dateString);
            return ['full_date' => $carbonDate->format('d.m'), 'day_of_week' => $dayMap[$carbonDate->dayOfWeek]];
        });

        $pivotedData = $this->pivotData($monthlyStats);

        // --- 4. ДАННЫЕ ДЛЯ ТАБЛИЦЫ С ВЫБОРОМ ПЕРИОДА ---
        if ($request->has('start_date') && $request->has('end_date') && $request->start_date && $request->end_date) {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
        } else {
            $startDate = now()->subMonthNoOverflow()->startOfMonth();
            $endDate = now()->subMonthNoOverflow()->endOfMonth();
        }

        $durationInDays = $endDate->diffInDays($startDate);
        $previousPeriodStartDate = $startDate->copy()->subDays($durationInDays + 1);
        $previousPeriodEndDate = $startDate->copy()->subDay();

        $customPeriodStats = BehavioralStat::where('nmID', $product->nmID)
            ->where('store_id', $product->store_id)
            ->whereBetween('report_date', [$startDate, $endDate])
            ->orderBy('report_date', 'asc')->get();

        $previousCustomPeriodStats = BehavioralStat::where('nmID', $product->nmID)
            ->where('store_id', $product->store_id)
            ->whereBetween('report_date', [$previousPeriodStartDate, $previousPeriodEndDate])
            ->get();

        // *** НОВЫЙ БЛОК 1: Расчет чистой прибыли для обоих периодов ***
        $costPrice = $product->cost_price;
        $customPeriodStats->each(function ($stat) use ($costPrice) {
            $stat->netProfit = $stat->buyoutsSumRub - ($stat->buyoutsCount * $costPrice);
        });
        $previousCustomPeriodStats->each(function ($stat) use ($costPrice) {
            $stat->netProfit = $stat->buyoutsSumRub - ($stat->buyoutsCount * $costPrice);
        });
        // *** КОНЕЦ НОВОГО БЛОКА 1 ***

        $datesForCustomPivot = $customPeriodStats->pluck('report_date')->unique()->map(function ($dateString) use ($dayMap) {
            $carbonDate = Carbon::parse($dateString);
            return ['full_date' => $carbonDate->format('d.m'), 'day_of_week' => $dayMap[$carbonDate->dayOfWeek]];
        });

        $pivotedCustomData = $this->pivotData($customPeriodStats);

        // --- 5. ОБЩИЙ СПИСОК МЕТРИК ДЛЯ ОБЕИХ СВОДНЫХ ТАБЛИЦ ---
        $metricsForPivot = [
            'openCardCount' => 'Переходы', 'addToCartCount' => 'В корзину',
            'ordersCount' => 'Заказы, шт', 'buyoutsCount' => 'Выкупы, шт', 'cancelCount' => 'Отмены, шт',
            'ordersSumRub' => 'Сумма заказов, ₽', 'buyoutsSumRub' => 'Сумма выкупов, ₽', 'cancelSumRub' => 'Сумма отмен, ₽',
            'avgPriceRub' => 'Ср. цена, ₽', 'conversion_to_cart' => 'Конверсия в корзину, %',
            'conversion_cart_to_order' => 'Конверсия в заказ, %', 'conversion_click_to_order' => 'Конверсия из клика в заказ, %',
            'netProfit' => 'Чистая прибыль, ₽',
        ];

        // A. Определяем период для рекламной таблицы: из запроса или по умолчанию (текущий месяц)
        if ($request->has('ad_start_date') && $request->has('ad_end_date') && $request->ad_start_date && $request->ad_end_date) {
            $adStartDate = Carbon::parse($request->input('ad_start_date'));
            $adEndDate = Carbon::parse($request->input('ad_end_date'));
        } else {
            $adStartDate = now()->startOfMonth();
            $adEndDate = now()->endOfMonth();
        }

        // B. Получаем АГРЕГИРОВАННУЮ статистику по рекламе для этого товара за выбранный период
        $aggregatedAdStats = DB::table('ad_campaign_daily_stats as ds')
            ->join('ad_campaign_products as ap', function($join) {
                $join->on('ds.advertId', '=', 'ap.advertId')
                    ->on('ds.store_id', '=', 'ap.store_id');
            })
            ->where('ap.nmID', $product->nmID)
            ->where('ap.store_id', $product->store_id)
            ->whereBetween('ds.report_date', [$adStartDate, $adEndDate])
            ->select(
                'ds.report_date',
                DB::raw('SUM(ds.views) as views'),
                DB::raw('SUM(ds.clicks) as clicks'),
                DB::raw('SUM(ds.sum) as sum'),
                DB::raw('SUM(ds.atbs) as atbs'),
                DB::raw('SUM(ds.orders) as orders'),
                DB::raw('SUM(ds.shks) as shks'),
                DB::raw('SUM(ds.sum_price) as sum_price')
            )
            ->groupBy('ds.report_date')
            ->orderBy('ds.report_date', 'asc')
            ->get();

        // C. "Переворачиваем" агрегированные данные для удобного отображения
        $pivotedAdData = [];
        foreach ($aggregatedAdStats as $stat) {
            $dateKey = Carbon::parse($stat->report_date)->format('d.m');
            $stat->ctr = ($stat->views > 0) ? ($stat->clicks / $stat->views) * 100 : 0;
            $stat->cpc = ($stat->clicks > 0) ? ($stat->sum / $stat->clicks) : 0;

            $statArray = (array)$stat;
            foreach ($statArray as $column => $value) {
                $pivotedAdData[$column][$dateKey] = $value;
            }
        }

        $datesForAdPivot = $aggregatedAdStats->pluck('report_date')->unique()->map(function ($dateString) use ($dayMap) {
            $carbonDate = Carbon::parse($dateString);
            return ['full_date' => $carbonDate->format('d.m'), 'day_of_week' => $dayMap[$carbonDate->dayOfWeek]];
        });

        $adMetricsForPivot = [
            'views' => 'Показы', 'clicks' => 'Клики', 'ctr' => 'CTR, %',
            'cpc' => 'CPC, ₽', 'sum' => 'Расходы, ₽', 'atbs' => 'В корзину',
            'orders' => 'Заказы, шт', 'sum_price' => 'Сумма заказов, ₽'
        ];

        // --- *** НОВЫЙ БЛОК: Логика для План/Факт за месяц *** ---

        // 1. Определяем месяц для отображения: из запроса или текущий по умолчанию
        $selectedPlanMonth = $request->input('plan_month', now()->format('Y-m'));
        $startOfMonth = Carbon::parse($selectedPlanMonth)->startOfMonth();
        $endOfMonth = Carbon::parse($selectedPlanMonth)->endOfMonth();

        // 2. Получаем ПЛАН на этот месяц из таблицы product_plans
        $planData = ProductPlan::where('product_nmID', $product->nmID)
            ->where('store_id', $product->store_id)
            ->where('period_start_date', $startOfMonth->toDateString())
            ->first();

        // 3. Получаем ФАКТ за этот месяц из таблицы behavioral_stats
        // Убираем некорректный оператор '=='
        $factDataMonthly = BehavioralStat::where('nmID', $product->nmID)
            ->where('store_id', $product->store_id)
            ->whereBetween('report_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('SUM(ordersCount) as total_orders, SUM(buyoutsCount) as total_buyouts, SUM(ordersSumRub) as total_orders_sum, SUM(openCardCount) as total_clicks, SUM(addToCartCount) as total_add_to_cart')
            ->first();

        // Рассчитываем фактическую конверсию
        $fact_cr_to_cart = 0;
        if ($factDataMonthly && $factDataMonthly->total_clicks > 0) {
            $fact_cr_to_cart = ($factDataMonthly->total_add_to_cart / $factDataMonthly->total_clicks) * 100;
        }

        // --- 7. ДАННЫЕ ДЛЯ БЛОКА ЛОГИСТИКИ ПО SKU ---
// --- 7. *** ИСПРАВЛЕНИЕ ЗДЕСЬ: ДАННЫЕ ДЛЯ БЛОКА ЛОГИСТИКИ ПО SKU *** ---
        // Используем даты из фильтра "Отчет за произвольный период" ($startDate, $endDate)
        $durationInDaysSku = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

        // Подзапрос 1: Темп продаж по баркодам
        $salesPaceSubquerySku = DB::table('sales_raw')
            ->select('barcode', DB::raw("COUNT(*) / {$durationInDaysSku} as avg_daily_sales"))
            ->where('saleID', 'like', 'S%')
            ->where('nmId', $product->nmID) // Фильтруем сразу по текущему товару
            ->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
            ->groupBy('barcode');

        // Подзапрос 2: Суммарные остатки WB по баркодам
        $warehouseTotalsSubquerySku = DB::table('sku_warehouse_stocks')
            ->select(
                'sku_barcode',
                DB::raw('SUM(quantity) as total_stock_wb'),
                DB::raw('SUM(in_way_to_client) as total_in_way_to_client'),
                DB::raw('SUM(in_way_from_client) as total_in_way_from_client')
            )
            ->whereIn('sku_barcode', function($query) use ($product) {
                // Выбираем баркоды только этого товара
                $query->select('barcode')->from('skus')->where('product_nmID', $product->nmID);
            })
            ->groupBy('sku_barcode');

        // Основной запрос: Собираем все данные по SKU для этого товара
        $skusForProduct = Sku::query()
            ->join('sku_stocks', 'skus.barcode', '=', 'sku_stocks.sku_barcode') // Ручные остатки
            ->leftJoinSub($salesPaceSubquerySku, 'sales_pace', 'skus.barcode', '=', 'sales_pace.barcode') // Продажи
            ->leftJoinSub($warehouseTotalsSubquerySku, 'warehouse_totals', 'skus.barcode', '=', 'warehouse_totals.sku_barcode') // Остатки WB
            ->where('skus.product_nmID', $product->nmID) // Только этот товар
            ->select(
                'skus.barcode', 'skus.tech_size',
                'sku_stocks.id', // ID из sku_stocks для редактирования
                'sku_stocks.stock_own',
                'sku_stocks.in_transit_to_wb',
                'sku_stocks.in_transit_general',
                'sku_stocks.at_factory',
                DB::raw('COALESCE(sales_pace.avg_daily_sales, 0) as avg_daily_sales'),
                DB::raw('COALESCE(warehouse_totals.total_stock_wb, 0) as stock_wb'),
                DB::raw('COALESCE(warehouse_totals.total_in_way_to_client, 0) as in_way_to_client'),
                DB::raw('COALESCE(warehouse_totals.total_in_way_from_client, 0) as in_way_from_client')
            )
            ->orderBy('skus.tech_size', 'asc')
            ->get();

        // Рассчитываем оборачиваемость для каждого SKU
        $skusForProduct->transform(function ($sku) {
            $totalStock = $sku->stock_wb + $sku->stock_own;
            $sku->turnover_days = ($sku->avg_daily_sales > 0) ? floor($totalStock / $sku->avg_daily_sales) : null;
            return $sku;
        });
        // --- КОНЕЦ БЛОКА ЛОГИСТИКИ ПО SKU ---
// --- КОНЕЦ БЛОКА ЛОГИСТИКИ ПО SKU ---

        return view('products.show', [
            'product' => $product,
            'yesterdayStats' => $yesterdayStats,
            'dayBeforeYesterdayStats' => $dayBeforeYesterdayStats,
            'chartData' => json_encode($chartData),
            'stats' => $statsWithComparison,
            'isTracked' => $isTracked,
            'monthlyStats' => $monthlyStats,
            'previousMonthStats' => $previousMonthStats,
            'datesForPivot' => $datesForPivot,
            'pivotedData' => $pivotedData,
            'metricsForPivot' => $metricsForPivot,
            'customPeriodStats' => $customPeriodStats,
            'previousCustomPeriodStats' => $previousCustomPeriodStats,
            'datesForCustomPivot' => $datesForCustomPivot,
            'pivotedCustomData' => $pivotedCustomData,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'aggregatedAdStats' => $aggregatedAdStats,
            'pivotedAdData' => $pivotedAdData,
            'datesForAdPivot' => $datesForAdPivot,
            'adMetricsForPivot' => $adMetricsForPivot,
            'adStartDate' => $adStartDate->toDateString(),
            'adEndDate' => $adEndDate->toDateString(),
            'selectedPlanMonth' => $selectedPlanMonth,
            'planData' => $planData,
            'factDataMonthly' => $factDataMonthly,
            'fact_cr_to_cart' => $fact_cr_to_cart,
            'skusForProduct' => $skusForProduct, // <-- Передаем новые данные в view
        ]);
    }

    public function toggleTracking(Request $request, Product $product)
    {
        $user = $request->user();

        // Метод toggle "переключает" связь: если она есть - удаляет, если нет - добавляет.
        // Это невероятно удобно и избавляет от лишних проверок.
        $user->trackedProducts()->toggle($product->nmID);

        return back()->with('status', 'Статус отслеживания изменен!');
    }

    /**
     * Вспомогательная функция для "переворачивания" данных и расчета конверсий.
     */
    /**
     * Вспомогательная функция для "переворачивания" данных и расчета конверсий.
     */
    private function pivotData($statsCollection)
    {
        $pivotedData = [];
        foreach ($statsCollection as $stat) {
            $dateKey = Carbon::parse($stat->report_date)->format('d.m');

            // *** КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ ЗДЕСЬ ***
            // Используем ->getAttributes() для получения чистого массива данных из модели
            $statArray = $stat->getAttributes();

            foreach ($statArray as $column => $value) {
                $pivotedData[$column][$dateKey] = $value;
            }
            // Расчет конверсий остается без изменений
            $pivotedData['conversion_to_cart'][$dateKey] = ($stat->openCardCount > 0) ? ($stat->addToCartCount / $stat->openCardCount) * 100 : 0;
            $pivotedData['conversion_cart_to_order'][$dateKey] = ($stat->addToCartCount > 0) ? ($stat->ordersCount / $stat->addToCartCount) * 100 : 0;
            $pivotedData['conversion_click_to_order'][$dateKey] = ($stat->openCardCount > 0) ? ($stat->ordersCount / $stat->openCardCount) * 100 : 0;
        }
        return $pivotedData;
    }

    public function updateCostPrice(Request $request, Product $product)
    {
        $validated = $request->validate([
            'cost_price' => 'required|numeric|min:0'
        ]);

        $product->update([
            'cost_price' => $validated['cost_price']
        ]);

        return back()->with('status', 'Себестоимость успешно обновлена!');
    }

    public function addSeasonality(Request $request, Product $product)
    {
        $validated = $request->validate([
            'start_month' => 'required|integer|between:1,12',
            'end_month' => 'required|integer|between:1,12',
        ]);

        // Простая проверка, чтобы конец не был раньше начала (можно усложнить для переходов через год)
        if ($validated['start_month'] > $validated['end_month']) {
            return back()->withErrors(['seasonality' => 'Месяц окончания не может быть раньше месяца начала.']);
        }

        $product->seasonalityPeriods()->create($validated);

        return back()->with('status', 'Период актуальности добавлен.');
    }

    public function deleteSeasonality(ProductSeasonality $period)
    {
        // Дополнительная проверка, что период принадлежит текущему товару (для безопасности)
        // if ($period->product_nmID !== $product->nmID) { abort(403); }

        $period->delete();
        return back()->with('status', 'Период актуальности удален.');
    }
}
