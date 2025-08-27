<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $product->title }}
            </h2>
            <form method="POST" action="{{ route('products.toggleTracking', $product->nmID) }}">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150">
                    {{ $isTracked ? 'Перестать отслеживать' : 'Отслеживать товар' }}
                </button>
            </form>
        </div>
    </x-slot>

    {{-- *** ЕДИНЫЙ БЛОК PHP ДЛЯ ВСЕХ ФУНКЦИЙ И РАСЧЕТОВ *** --}}
    @php
        // Объявляем вспомогательные функции ОДИН РАЗ, чтобы избежать ошибок
        if (!function_exists('render_summary_card')) {
            function render_summary_card($title, $currentValue, $previousValue) {
                $current = $currentValue ?? 0;
                $previous = $previousValue ?? 0;
                $diff = $current - $previous;
                $diff_str = '';
                if ($diff > 0) {
                    $diff_str = "<span class='text-green-500 text-xs ml-1'>(+ " . number_format($diff, 0, ',', ' ') . ")</span>";
                } elseif ($diff < 0) {
                    $diff_str = "<span class='text-red-500 text-xs ml-1'>(" . number_format($diff, 0, ',', ' ') . ")</span>";
                } else {
                    $diff_str = "<span class='text-gray-500 text-xs ml-1'>(0)</span>";
                }
                return "<div class='bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg text-center'><dt class='text-sm font-medium text-gray-500 dark:text-gray-400 truncate'>{$title}</dt><dd class='mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white'>" . number_format($current, 0, ',', ' ') . $diff_str . "</dd></div>";
            }
        }
        if (!function_exists('render_pivoted_cell')) {
            function render_pivoted_cell($currentValue, $previousValue, $isPercentage = false) {
                $diff = $currentValue - $previousValue;
                $diff_str = '';
                if ($previousValue !== null && $diff != 0) {
                    $diff_formatted = $isPercentage ? number_format($diff, 2, ',', ' ') : number_format($diff, 0, ',', ' ');
                    if ($diff > 0) $diff_str = "<span class='block text-green-500 text-xs'>(+{$diff_formatted})</span>";
                    if ($diff < 0) $diff_str = "<span class='block text-red-500 text-xs'>({$diff_formatted})</span>";
                }
                $current_formatted = $isPercentage ? number_format($currentValue, 2, ',', ' ') : number_format($currentValue, 0, ',', ' ');
                echo $current_formatted;
                echo $diff_str;
            }
        }
        if (!function_exists('render_total_cell')) {
            function render_total_cell($currentMonthSum, $previousMonthSum, $isPercentage = false) {
                $diff = $currentMonthSum - $previousMonthSum;
                $diff_str = '';
                if ($previousMonthSum != 0 && $diff != 0) {
                    $diff_formatted = $isPercentage ? number_format($diff, 2, ',', ' ') : number_format($diff, 0, ',', ' ');
                    if ($diff > 0) $diff_str = "<span class='block text-green-500 text-xs'>(+{$diff_formatted})</span>";
                    if ($diff < 0) $diff_str = "<span class='block text-red-500 text-xs'>({$diff_formatted})</span>";
                }
                $current_formatted = $isPercentage ? number_format($currentMonthSum, 2, ',', ' ') . '%' : number_format($currentMonthSum, 0, ',', ' ');
                echo $current_formatted;
                echo $diff_str;
            }
        }

        // Расчеты для месячной таблицы
        $totalOpenCard_month = $monthlyStats->sum('openCardCount');
        $totalAddToCart_month = $monthlyStats->sum('addToCartCount');
        $totalOrders_month = $monthlyStats->sum('ordersCount');
        $prevTotalOpenCard_month = $previousMonthStats->sum('openCardCount');
        $prevTotalAddToCart_month = $previousMonthStats->sum('addToCartCount');
        $prevTotalOrders_month = $previousMonthStats->sum('ordersCount');
        $currentTotals_month = [
            'conversion_to_cart' => ($totalOpenCard_month > 0) ? ($totalAddToCart_month / $totalOpenCard_month) * 100 : 0,
            'conversion_cart_to_order' => ($totalAddToCart_month > 0) ? ($totalOrders_month / $totalAddToCart_month) * 100 : 0,
            'conversion_click_to_order' => ($totalOpenCard_month > 0) ? ($totalOrders_month / $totalOpenCard_month) * 100 : 0,
        ];
        $previousTotals_month = [
            'conversion_to_cart' => ($prevTotalOpenCard_month > 0) ? ($prevTotalAddToCart_month / $prevTotalOpenCard_month) * 100 : 0,
            'conversion_cart_to_order' => ($prevTotalAddToCart_month > 0) ? ($prevTotalOrders_month / $prevTotalAddToCart_month) * 100 : 0,
            'conversion_click_to_order' => ($prevTotalOpenCard_month > 0) ? ($prevTotalOrders_month / $prevTotalOpenCard_month) * 100 : 0,
        ];

        // Расчеты для кастомной таблицы
        $totalOpenCard_custom = $customPeriodStats->sum('openCardCount');
        $totalAddToCart_custom = $customPeriodStats->sum('addToCartCount');
        $totalOrders_custom = $customPeriodStats->sum('ordersCount');
        $prevTotalOpenCard_custom = $previousCustomPeriodStats->sum('openCardCount');
        $prevTotalAddToCart_custom = $previousCustomPeriodStats->sum('addToCartCount');
        $prevTotalOrders_custom = $previousCustomPeriodStats->sum('ordersCount');
        $currentTotals_custom = [
            'conversion_to_cart' => ($totalOpenCard_custom > 0) ? ($totalAddToCart_custom / $totalOpenCard_custom) * 100 : 0,
            'conversion_cart_to_order' => ($totalAddToCart_custom > 0) ? ($totalOrders_custom / $totalAddToCart_custom) * 100 : 0,
            'conversion_click_to_order' => ($totalOpenCard_custom > 0) ? ($totalOrders_custom / $totalOpenCard_custom) * 100 : 0,
        ];
        $previousTotals_custom = [
            'conversion_to_cart' => ($prevTotalOpenCard_custom > 0) ? ($prevTotalAddToCart_custom / $prevTotalOpenCard_custom) * 100 : 0,
            'conversion_cart_to_order' => ($prevTotalAddToCart_custom > 0) ? ($prevTotalOrders_custom / $prevTotalAddToCart_custom) * 100 : 0,
            'conversion_click_to_order' => ($prevTotalOpenCard_custom > 0) ? ($prevTotalOrders_custom / $prevTotalOpenCard_custom) * 100 : 0,
        ];
    @endphp

    <div class="py-12">
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Блок с основной информацией --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Основная информация</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm text-gray-600 dark:text-gray-400">
                    <div><dt class="font-medium text-gray-900 dark:text-gray-100">Магазин</dt><dd>{{ $product->store->store_name ?? 'Не указан' }}</dd></div>
                    <div><dt class="font-medium text-gray-900 dark:text-gray-100">Бренд</dt><dd>{{ $product->brand }}</dd></div>
                    <div><dt class="font-medium text-gray-900 dark:text-gray-100">Артикул WB (nmID)</dt><dd>{{ $product->nmID }}</dd></div>
                    <div><dt class="font-medium text-gray-900 dark:text-gray-100">Артикул продавца</dt><dd>{{ $product->vendorCode }}</dd></div>
                </div>
            </div>

            {{-- Блок со сводкой за вчерашний день --}}
            @if($yesterdayStats)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Ключевые показатели за вчера ({{ \Carbon\Carbon::parse($yesterdayStats->report_date)->format('d.m.Y') }})</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
                        {!! render_summary_card('Переходы', $yesterdayStats->openCardCount, $dayBeforeYesterdayStats->openCardCount ?? 0) !!}
                        {!! render_summary_card('В корзину', $yesterdayStats->addToCartCount, $dayBeforeYesterdayStats->addToCartCount ?? 0) !!}
                        {!! render_summary_card('Заказы, шт', $yesterdayStats->ordersCount, $dayBeforeYesterdayStats->ordersCount ?? 0) !!}
                        {!! render_summary_card('Сумма заказов, ₽', $yesterdayStats->ordersSumRub, $dayBeforeYesterdayStats->ordersSumRub ?? 0) !!}
                        {!! render_summary_card('Выкупы, шт', $yesterdayStats->buyoutsCount, $dayBeforeYesterdayStats->buyoutsCount ?? 0) !!}
                        {!! render_summary_card('Сумма выкупов, ₽', $yesterdayStats->buyoutsSumRub, $dayBeforeYesterdayStats->buyoutsSumRub ?? 0) !!}
                        {!! render_summary_card('Отмены, шт', $yesterdayStats->cancelCount, $dayBeforeYesterdayStats->cancelCount ?? 0) !!}
                        {!! render_summary_card('Сумма отмен, ₽', $yesterdayStats->cancelSumRub, $dayBeforeYesterdayStats->cancelSumRub ?? 0) !!}
                    </div>
                </div>
            @endif

            {{-- Блок с графиком --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Динамика показателей за 7 дней</h3>
                <div class="relative h-96">
                    <canvas id="behavioralChart"></canvas>
                </div>
            </div>



            {{-- Блок со сводной таблицей с ВЫБОРОМ ПЕРИОДА --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('products.show', $product->nmID) }}" class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Отчет за произвольный период</h3>
                    <div class="flex items-end space-x-4">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Дата начала</label>
                            <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Дата окончания</label>
                            <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150">
                            Показать
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">"Итого" сравнивается с предыдущим периодом такой же длительности.</p>
                </form>

                <div id="metric-toggles" class="mb-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Настроить отображение метрик:</h4>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        @foreach ($metricsForPivot as $key => $title)
                            <label for="toggle_{{ $key }}" class="inline-flex items-center">
                                <input type="checkbox" id="toggle_{{ $key }}" value="{{ $key }}" class="metric-toggle-checkbox rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800">
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ $title }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="sticky left-0 z-30 bg-gray-200 dark:bg-gray-900 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-48">Метрика</th>
                            <th class="sticky left-[192px] z-30 bg-gray-200 dark:bg-gray-900 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-40">Итого за период</th>
                            @foreach ($datesForCustomPivot as $dateInfo)
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="min-width: 100px;">
                                    <span>{{ $dateInfo['full_date'] }}</span>
                                    <span class="block font-normal">{{ $dateInfo['day_of_week'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody id="custom-period-tbody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($metricsForPivot as $key => $title)
                            <tr data-metric-key="{{ $key }}">
                                <td class="sticky left-0 z-20 bg-gray-100 dark:bg-gray-800 px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white w-48">{{ $title }}</td>
                                <td class="sticky left-[192px] z-20 bg-gray-100 dark:bg-gray-800 px-4 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white w-40">
                                    @php
                                        $isPercentage = str_contains($key, 'conversion');
                                        if($isPercentage){
                                            render_total_cell($currentTotals_custom[$key] ?? 0, $previousTotals_custom[$key] ?? 0, true);
                                        } else if ($key == 'avgPriceRub') {
                                            render_total_cell($customPeriodStats->avg($key) ?? 0, $previousCustomPeriodStats->avg($key) ?? 0);
                                        } else {
                                            render_total_cell($customPeriodStats->sum($key), $previousCustomPeriodStats->sum($key));
                                        }
                                    @endphp
                                </td>
                                @foreach ($datesForCustomPivot as $dateInfo)
                                    <td class="px-4 py-4 text-center text-sm dark:text-white" style="min-width: 120px;">
                                        @php
                                            $date = $dateInfo['full_date'];
                                            $isPercentage = str_contains($key, 'conversion');
                                            $currentValue = $pivotedCustomData[$key][$date] ?? 0;
                                            $previousDateKey = \Carbon\Carbon::createFromFormat('d.m', $date)->subDay()->format('d.m');
                                            $previousValue = $pivotedCustomData[$key][$previousDateKey] ?? null;
                                            render_pivoted_cell($currentValue, $previousValue, $isPercentage);
                                        @endphp
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($datesForCustomPivot) + 2 }}" class="p-4 text-center text-gray-500">Нет данных за выбранный период.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- *** НОВЫЙ БЛОК: Агрегированная статистика по рекламе с выбором периода *** --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">

                {{-- Форма для выбора периода --}}
                <form method="GET" action="{{ route('products.show', $product->nmID) }}#ad-stats-table" class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Сводная статистика по рекламе</h3>
                    <div class="flex items-end space-x-4">
                        <div>
                            <label for="ad_start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Дата начала</label>
                            <input type="date" name="ad_start_date" id="ad_start_date" value="{{ $adStartDate }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="ad_end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Дата окончания</label>
                            <input type="date" name="ad_end_date" id="ad_end_date" value="{{ $adEndDate }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150">
                            Показать
                        </button>
                    </div>
                </form>

                <div id="ad-stats-table" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="sticky left-0 z-30 bg-gray-200 dark:bg-gray-900 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-48">Метрика</th>
                            <th class="sticky left-[192px] z-30 bg-gray-200 dark:bg-gray-900 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-40">Итого за период</th>
                            @foreach ($datesForAdPivot as $dateInfo)
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="min-width: 100px;">
                                    <span>{{ $dateInfo['full_date'] }}</span>
                                    <span class="block font-normal">{{ $dateInfo['day_of_week'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($adMetricsForPivot as $key => $title)
                            <tr>
                                <td class="sticky left-0 z-20 bg-gray-100 dark:bg-gray-800 px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white w-48">{{ $title }}</td>
                                <td class="sticky left-[192px] z-20 bg-gray-100 dark:bg-gray-800 px-4 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white w-40">
                                    @if($key == 'ctr')
                                        {{ $aggregatedAdStats->sum('views') > 0 ? number_format($aggregatedAdStats->sum('clicks') / $aggregatedAdStats->sum('views') * 100, 2, ',', ' ') : '0,00' }}%
                                    @elseif($key == 'cpc')
                                        {{ $aggregatedAdStats->sum('clicks') > 0 ? number_format($aggregatedAdStats->sum('sum') / $aggregatedAdStats->sum('clicks'), 2, ',', ' ') : '0,00' }}
                                    @else
                                        {{ number_format($aggregatedAdStats->sum($key), 0, ',', ' ') }}
                                    @endif
                                </td>
                                @foreach ($datesForAdPivot as $dateInfo)
                                    <td class="px-4 py-4 text-center text-sm dark:text-white" style="min-width: 100px;">
                                        @php
                                            $date = $dateInfo['full_date'];
                                            $isPercentage = ($key == 'ctr');
                                            $isMoney = in_array($key, ['cpc', 'sum']);

                                            // *** ИЗМЕНЕНИЕ ЗДЕСЬ ***
                                            $currentValue = $pivotedAdData[$key][$date] ?? 0;
                                            $previousDateKey = \Carbon\Carbon::createFromFormat('d.m', $date)->subDay()->format('d.m');
                                            $previousValue = $pivotedAdData[$key][$previousDateKey] ?? null;

                                            // Используем нашу универсальную функцию для рендера ячейки
                                            render_pivoted_cell($currentValue, $previousValue, $isPercentage || $isMoney);
                                        @endphp
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($datesForAdPivot) + 2 }}" class="p-4 text-center text-gray-500">Нет данных по рекламной статистике за выбранный период.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>


        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const chartCtx = document.getElementById('behavioralChart');
                if (chartCtx) {
                    const chartData = {!! $chartData !!};
                    new Chart(chartCtx, {
                        type: 'line',
                        data: chartData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, ticks: { color: '#9ca3af' }, grid: { color: '#374151' } },
                                x: { ticks: { color: '#9ca3af' }, grid: { color: '#374151' } }
                            },
                            plugins: { legend: { labels: { color: '#d1d5db' } } }
                        }
                    });
                }

                const cookieName = 'visible_metrics_{{ $product->nmID }}';
                function setCookie(name, value, days) {
                    let expires = "";
                    if (days) {
                        const date = new Date();
                        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                        expires = "; expires=" + date.toUTCString();
                    }
                    document.cookie = name + "=" + (JSON.stringify(value) || "") + expires + "; path=/; SameSite=Lax";
                }
                function getCookie(name) {
                    const nameEQ = name + "=";
                    const ca = document.cookie.split(';');
                    for (let i = 0; i < ca.length; i++) {
                        let c = ca[i];
                        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                        if (c.indexOf(nameEQ) === 0) {
                            try { return JSON.parse(c.substring(nameEQ.length, c.length)); }
                            catch (e) { return null; }
                        }
                    }
                    return null;
                }

                const checkboxes = document.querySelectorAll('.metric-toggle-checkbox');
                const tableBody = document.getElementById('custom-period-tbody');
                if(tableBody){
                    const tableRows = tableBody.querySelectorAll('tr[data-metric-key]');
                    let visibleMetrics = getCookie(cookieName);
                    if (!visibleMetrics) {
                        visibleMetrics = ['openCardCount', 'addToCartCount', 'ordersCount', 'buyoutsCount', 'conversion_to_cart', 'conversion_cart_to_order'];
                        setCookie(cookieName, visibleMetrics, 365);
                    }
                    function updateTableVisibility() {
                        tableRows.forEach(row => {
                            row.style.display = visibleMetrics.includes(row.dataset.metricKey) ? '' : 'none';
                        });
                    }
                    function updateCheckboxStates() {
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = visibleMetrics.includes(checkbox.value);
                        });
                    }
                    updateCheckboxStates();
                    updateTableVisibility();
                    checkboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', () => {
                            if (checkbox.checked) {
                                if (!visibleMetrics.includes(checkbox.value)) visibleMetrics.push(checkbox.value);
                            } else {
                                visibleMetrics = visibleMetrics.filter(metric => metric !== checkbox.value);
                            }
                            setCookie(cookieName, visibleMetrics, 365);
                            updateTableVisibility();
                        });
                    });
                }
            });
        </script>

</x-app-layout>
