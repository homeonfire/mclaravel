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

    <div class="py-12">
        {{-- ИЗМЕНЕНИЕ №1: Убираем класс max-w-7xl, чтобы контейнер стал на всю ширину --}}
        <div class="mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Основная информация</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm text-gray-600 dark:text-gray-400">
                    <div>
                        <dt class="font-medium text-gray-900 dark:text-gray-100">Магазин</dt>
                        <dd>{{ $product->store->store_name ?? 'Не указан' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-900 dark:text-gray-100">Бренд</dt>
                        <dd>{{ $product->brand }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-900 dark:text-gray-100">Артикул WB (nmID)</dt>
                        <dd>{{ $product->nmID }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-900 dark:text-gray-100">Артикул продавца</dt>
                        <dd>{{ $product->vendorCode }}</dd>
                    </div>
                </div>
            </div>


            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Динамика показателей за 7 дней</h3>
                <canvas id="behavioralChart"></canvas>
            </div>

            {{-- Блок с детальной таблицей статистики --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Дата</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Переходы</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">В корзину</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Заказы, шт</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Сумма заказов, ₽</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Выкупы, шт</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Сумма выкупов, ₽</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Отмены, шт</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Сумма отмен, ₽</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Остаток MP</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Остаток WB</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @php
                            function render_diff_table($current, $previous) {
                                $diff = $current - ($previous ?? 0);
                                $diff_str = '';
                                if ($previous !== null && $diff != 0) {
                                    $diff_formatted = number_format($diff, 0, ',', ' ');
                                    if ($diff > 0) $diff_str = "<span class='text-green-500 text-xs ml-1'>(+{$diff_formatted})</span>";
                                    if ($diff < 0) $diff_str = "<span class='text-red-500 text-xs ml-1'>({$diff_formatted})</span>";
                                }
                                echo is_numeric($current) ? number_format($current, 0, ',', ' ') : $current;
                                echo $diff_str;
                            }
                        @endphp

                        @forelse ($stats as $stat)
                            <tr>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    {{ \Carbon\Carbon::parse($stat->report_date)->format('d.m.Y') }}
                                </td>
                                <td class="px-4 py-4 text-sm dark:text-white">{!! render_diff_table($stat->openCardCount, $stat->previous->openCardCount ?? null) !!}</td>
                                <td class="px-4 py-4 text-sm dark:text-white">{!! render_diff_table($stat->addToCartCount, $stat->previous->addToCartCount ?? null) !!}</td>
                                <td class="px-4 py-4 text-sm dark:text-white">{!! render_diff_table($stat->ordersCount, $stat->previous->ordersCount ?? null) !!}</td>
                                <td class="px-4 py-4 text-sm dark:text-white">{!! render_diff_table($stat->ordersSumRub, $stat->previous->ordersSumRub ?? null) !!}</td>
                                <td class="px-4 py-4 text-sm dark:text-white">{!! render_diff_table($stat->buyoutsCount, $stat->previous->buyoutsCount ?? null) !!}</td>
                                <td class="px-4 py-4 text-sm dark:text-white">{!! render_diff_table($stat->buyoutsSumRub, $stat->previous->buyoutsSumRub ?? null) !!}</td>
                                <td class="px-4 py-4 text-sm dark:text-white">{!! render_diff_table($stat->cancelCount, $stat->previous->cancelCount ?? null) !!}</td>
                                <td class="px-4 py-4 text-sm dark:text-white">{!! render_diff_table($stat->cancelSumRub, $stat->previous->cancelSumRub ?? null) !!}</td>
                                <td class="px-4 py-4 text-sm dark:text-white">{!! render_diff_table($stat->stocksMp, $stat->previous->stocksMp ?? null) !!}</td>
                                <td class="px-4 py-4 text-sm dark:text-white">{!! render_diff_table($stat->stocksWb, $stat->previous->stocksWb ?? null) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="p-4 text-center text-gray-500">Нет данных для отображения.</td></tr>
                        @endforelse
                        </tbody>

                        {{-- *** КЛЮЧЕВОЕ ИЗМЕНЕНИЕ ЗДЕСЬ *** --}}
                        {{-- Добавляем секцию tfoot для итогов, только если есть данные --}}
                        @if($stats->isNotEmpty())
                            <tfoot class="bg-gray-100 dark:bg-gray-700 font-bold">
                            <tr>
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">Итого</td>
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">{{ number_format($stats->sum('openCardCount'), 0, ',', ' ') }}</td>
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">{{ number_format($stats->sum('addToCartCount'), 0, ',', ' ') }}</td>
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">{{ number_format($stats->sum('ordersCount'), 0, ',', ' ') }}</td>
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">{{ number_format($stats->sum('ordersSumRub'), 0, ',', ' ') }}</td>
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">{{ number_format($stats->sum('buyoutsCount'), 0, ',', ' ') }}</td>
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">{{ number_format($stats->sum('buyoutsSumRub'), 0, ',', ' ') }}</td>
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">{{ number_format($stats->sum('cancelCount'), 0, ',', ' ') }}</td>
                                <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">{{ number_format($stats->sum('cancelSumRub'), 0, ',', ' ') }}</td>
                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400 text-center" colspan="2">

                                </td>
                            </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            {{-- *** ОБНОВЛЕННЫЙ БЛОК: Сводная таблица с выделенными и закрепленными столбцами *** --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Воронка (Текущий месяц)</h3>
                <h5 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Итого за месяц показывает разницу с прошлым месяцем</h5>
                <div class="overflow-x-auto">
                    @php
                        // Функция для рендера ячейки с разницей по дням
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

                        // Функция для рендера ИТОГОВОЙ ячейки с разницей по месяцам
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

                        // Рассчитываем итоговые значения
                        $totalOpenCard = $monthlyStats->sum('openCardCount');
                        $totalAddToCart = $monthlyStats->sum('addToCartCount');
                        $totalOrders = $monthlyStats->sum('ordersCount');
                        $prevTotalOpenCard = $previousMonthStats->sum('openCardCount');
                        $prevTotalAddToCart = $previousMonthStats->sum('addToCartCount');
                        $prevTotalOrders = $previousMonthStats->sum('ordersCount');
                        $currentTotals = [
                            'conversion_to_cart' => ($totalOpenCard > 0) ? ($totalAddToCart / $totalOpenCard) * 100 : 0,
                            'conversion_cart_to_order' => ($totalAddToCart > 0) ? ($totalOrders / $totalAddToCart) * 100 : 0,
                            'conversion_click_to_order' => ($totalOpenCard > 0) ? ($totalOrders / $totalOpenCard) * 100 : 0,
                        ];
                        $previousTotals = [
                            'conversion_to_cart' => ($prevTotalOpenCard > 0) ? ($prevTotalAddToCart / $prevTotalOpenCard) * 100 : 0,
                            'conversion_cart_to_order' => ($prevTotalAddToCart > 0) ? ($prevTotalOrders / $prevTotalAddToCart) * 100 : 0,
                            'conversion_click_to_order' => ($prevTotalOpenCard > 0) ? ($prevTotalOrders / $prevTotalOpenCard) * 100 : 0,
                        ];
                    @endphp

                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            {{-- *** ИЗМЕНЕНИЕ: Добавляем классы для темно-серого фона --}}
                            <th class="sticky left-0 z-30 bg-gray-200 dark:bg-gray-900 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-48">Метрика</th>
                            <th class="sticky left-[192px] z-30 bg-gray-200 dark:bg-gray-900 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-40">Итого за месяц</th>

                            @foreach ($datesForPivot as $date)
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $date }}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($metricsForPivot as $key => $title)
                            <tr>
                                {{-- *** ИЗМЕНЕНИЕ: Добавляем классы для темно-серого фона --}}
                                <td class="sticky left-0 z-20 bg-gray-100 dark:bg-gray-800 px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white w-48">{{ $title }}</td>
                                <td class="sticky left-[192px] z-20 bg-gray-100 dark:bg-gray-800 px-4 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white w-40">
                                    @php
                                        $isPercentage = str_contains($key, 'conversion');
                                        if($isPercentage){
                                            render_total_cell($currentTotals[$key] ?? 0, $previousTotals[$key] ?? 0, true);
                                        } else if ($key == 'avgPriceRub') {
                                            render_total_cell($monthlyStats->avg($key) ?? 0, $previousMonthStats->avg($key) ?? 0);
                                        } else {
                                            render_total_cell($monthlyStats->sum($key), $previousMonthStats->sum($key));
                                        }
                                    @endphp
                                </td>

                                @foreach ($datesForPivot as $date)
                                    <td class="px-4 py-4 text-center text-sm dark:text-white">
                                        @php
                                            $currentValue = $pivotedData[$key][$date] ?? 0;
                                            $previousDateKey = \Carbon\Carbon::createFromFormat('d.m', $date)->subDay()->format('d.m');
                                            $previousValue = $pivotedData[$key][$previousDateKey] ?? null;
                                            render_pivoted_cell($currentValue, $previousValue, $isPercentage);
                                        @endphp
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($datesForPivot) + 2 }}" class="p-4 text-center text-gray-500">Нет данных для отображения.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- *** НОВЫЙ БЛОК: Сводная таблица с выбором периода *** --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">

                {{-- Форма для выбора периода --}}
                <form method="GET" action="{{ route('products.show', $product->nmID) }}" class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
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

                <div class="overflow-x-auto">
                    @php
                        // Рассчитываем итоговые значения для кастомного периода
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

                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="sticky left-0 z-30 bg-gray-200 dark:bg-gray-900 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-48">Метрика</th>
                            <th class="sticky left-[192px] z-30 bg-gray-200 dark:bg-gray-900 px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase w-40">Итого за период</th>

                            @foreach ($datesForCustomPivot as $date)
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $date }}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($metricsForPivot as $key => $title)
                            <tr>
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

                                @foreach ($datesForCustomPivot as $date)
                                    <td class="px-4 py-4 text-center text-sm dark:text-white">
                                        @php
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


        </div>
    </div>

    {{-- Скрипт для инициализации графика --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('behavioralChart');
            if (!ctx) return; // Добавим проверку на всякий случай
            const chartData = {!! $chartData !!};

            new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true, ticks: { color: '#9ca3af' }, grid: { color: '#374151' } },
                        x: { ticks: { color: '#9ca3af' }, grid: { color: '#374151' } }
                    },
                    plugins: { legend: { labels: { color: '#d1d5db' } } }
                }
            });
        });
    </script>
</x-app-layout>
