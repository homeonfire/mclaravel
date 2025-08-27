<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\BehavioralStat;
use Illuminate\Support\Carbon;

class ProductController extends Controller
{
    /**
     * НОВЫЙ МЕТОД: Отображает список всех товаров с поиском и пагинацией.
     */
    public function index(Request $request)
    {
        // 1. Получаем все параметры для фильтрации, включая новый флаг
        $searchQuery = $request->input('search');
        $storeId = $request->input('store_id');
        $withActiveCampaign = $request->boolean('with_active_campaign'); // Удобный метод для получения true/false

        // 2. Получаем список всех магазинов для выпадающего списка
        $stores = DB::table('stores')->orderBy('store_name')->get();

        // 3. Создаем подзапрос для получения статистики за последнюю доступную дату
        $latestStatsSubquery = DB::table('behavioral_stats as bs1')
            ->select('bs1.nmID', 'bs1.store_id', 'bs1.openCardCount')
            ->join(DB::raw('(SELECT nmID, store_id, MAX(report_date) as max_date FROM behavioral_stats GROUP BY nmID, store_id) as bs2'), function($join) {
                $join->on('bs1.nmID', '=', 'bs2.nmID')
                    ->on('bs1.store_id', '=', 'bs2.store_id')
                    ->on('bs1.report_date', '=', 'bs2.max_date');
            });

        // 4. Строим основной запрос
        $productsQuery = Product::with('store')
            ->leftJoinSub($latestStatsSubquery, 'latest_stats', function ($join) {
                $join->on('products.nmID', '=', 'latest_stats.nmID')
                    ->on('products.store_id', '=', 'latest_stats.store_id');
            })
            ->select('products.*', DB::raw('COALESCE(latest_stats.openCardCount, 0) as latest_day_views'))
            ->when($searchQuery, function ($query, $search) {
                return $query->where('products.vendorCode', 'like', "%{$search}%");
            })
            ->when($storeId, function ($query, $storeId) {
                return $query->where('products.store_id', $storeId);
            })
            // *** НОВЫЙ ФИЛЬТР ЗДЕСЬ ***
            ->when($withActiveCampaign, function ($query) {
                // Используем WHERE EXISTS для эффективного отбора товаров,
                // которые существуют в таблице ad_campaign_products и связаны
                // с кампанией в статусе 9 (активна).
                return $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('ad_campaign_products as acp')
                        ->join('ad_campaigns as ac', function($join) {
                            $join->on('acp.advertId', '=', 'ac.advertId')
                                ->on('acp.store_id', '=', 'ac.store_id');
                        })
                        ->whereColumn('acp.nmID', 'products.nmID')
                        ->whereColumn('acp.store_id', 'products.store_id')
                        ->where('ac.status', 9); // 9 = AdvertStatus::PLAY (активна)
                });
            });

        // 5. Сортируем и разбиваем на страницы
        $products = $productsQuery->orderBy('latest_day_views', 'desc')
            ->orderBy('products.updated_at', 'desc')
            ->paginate(30);

        return view('products.index', [
            'products' => $products,
            'stores' => $stores,
            'selectedStoreId' => $storeId,
            'withActiveCampaign' => $withActiveCampaign, // Передаем состояние фильтра в представление
        ]);
    }

    /**
     * Отображает детальную страницу продукта со всей статистикой.
     */
    public function show(Request $request, Product $product)
    {
        $product->load('adCampaigns');
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
}
