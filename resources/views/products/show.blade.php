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

    {{-- Alpine.js компонент для редактирования ячейки --}}
    <script>
        function editableCell(initialValue, updateUrl, fieldName, skuStockId) {
            return {
                editing: false,
                value: initialValue,
                originalValue: initialValue,
                updateUrl: updateUrl,
                fieldName: fieldName,
                skuStockId: skuStockId,
                errorMessage: '',
                startEditing() {
                    this.originalValue = this.value; // Сохраняем оригинал перед редактированием
                    this.editing = true;
                    this.$nextTick(() => this.$refs.input.focus()); // Фокус на поле ввода
                },
                cancelEditing() {
                    this.value = this.originalValue; // Возвращаем старое значение
                    this.editing = false;
                    this.errorMessage = '';
                },
                saveValue() {
                    this.errorMessage = '';
                    const newValue = parseInt(this.value);
                    if (isNaN(newValue) || newValue < 0) {
                        this.errorMessage = 'Нужно число >= 0';
                        return;
                    }
                    this.value = newValue;

                    fetch(this.updateUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ [this.fieldName]: this.value })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.editing = false;
                                this.originalValue = this.value;
                                // Можно добавить уведомление
                            } else {
                                this.errorMessage = data.message || 'Ошибка сохранения';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            this.errorMessage = 'Ошибка сети';
                        });
                }
            }
        }
    </script>
    <meta name="csrf-token" content="{{ csrf_token() }}">

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
        $months = [1=>'Янв', 2=>'Фев', 3=>'Мар', 4=>'Апр', 5=>'Май', 6=>'Июн', 7=>'Июл', 8=>'Авг', 9=>'Сен', 10=>'Окт', 11=>'Ноя', 12=>'Дек'];
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
                    {{-- *** НОВЫЙ БЛОК ДЛЯ СЕБЕСТОИМОСТИ *** --}}
                    <div>
                        <dt class="font-medium text-gray-900 dark:text-gray-100">Себестоимость</dt>

                        <div id="cost-price-display">
                            <span class="font-bold text-lg text-gray-900 dark:text-white">{{ number_format($product->cost_price, 2, ',', ' ') }} ₽</span>
                            <a href="#" id="edit-cost-price-btn" class="ml-2 text-blue-500 hover:underline text-xs">Изменить</a>
                        </div>

                        <form id="cost-price-form" action="{{ route('products.updateCostPrice', $product) }}" method="POST" class="hidden">
                            @csrf
                            @method('PATCH')
                            <div class="flex items-center">
                                <input type="number" step="0.01" name="cost_price" value="{{ $product->cost_price }}" class="block w-32 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm">
                                <button type="submit" class="ml-2 inline-flex items-center px-3 py-1 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Сохранить</button>
                            </div>
                        </form>
                    </div>
                    {{-- *** КОНЕЦ НОВОГО БЛОКА *** --}}
                </div>
            </div>

            {{-- *** НОВЫЙ БЛОК ДЛЯ СЕЗОННОСТИ *** --}}
            <div class="md:col-span-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <dt class="font-medium text-gray-900 dark:text-gray-100 mb-2">Месяцы актуальности</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">
                    {{-- Список существующих периодов --}}
                    @if($product->seasonalityPeriods->isNotEmpty())
                        <ul class="space-y-1 mb-3">
                            @php
                                $months = [1=>'Янв', 2=>'Фев', 3=>'Мар', 4=>'Апр', 5=>'Май', 6=>'Июн', 7=>'Июл', 8=>'Авг', 9=>'Сен', 10=>'Окт', 11=>'Ноя', 12=>'Дек'];
                            @endphp
                            @foreach($product->seasonalityPeriods as $period)
                                <li class="flex items-center justify-between bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                                    <span>{{ $months[$period->start_month] }} - {{ $months[$period->end_month] }}</span>
                                    <form action="{{ route('products.deleteSeasonality', $period) }}" method="POST" onsubmit="return confirm('Удалить этот период?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Удалить</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mb-3 italic">Периоды не заданы.</p>
                    @endif

                    {{-- Форма добавления нового периода --}}
                    <form action="{{ route('products.addSeasonality', $product) }}" method="POST" class="flex items-end space-x-2">
                        @csrf
                        <div>
                            <label for="start_month" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Начало</label>
                            <select name="start_month" id="start_month" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" style="padding-right: 1.5rem;">
                                @foreach($months as $num => $name)
                                    <option value="{{ $num }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="end_month" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Конец</label>
                            <select name="end_month" id="end_month" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" style="padding-right: 1.5rem;">
                                @foreach($months as $num => $name)
                                    <option value="{{ $num }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">Добавить</button>
                    </form>
                    @error('seasonality')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </dd>
            </div>
            {{-- *** КОНЕЦ НОВОГО БЛОКА *** --}}

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

            {{-- *** ОБНОВЛЕННЫЙ БЛОК: ПЛАН/ФАКТ ЗА МЕСЯЦ С KPI *** --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">

                {{-- Форма для выбора месяца --}}
                <form method="GET" action="{{ route('products.show', $product->nmID) }}#plan-fact-block" class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                    {{-- Скрытые поля для сохранения состояния других фильтров --}}
                    <input type="hidden" name="start_date" value="{{ $startDate }}">
                    <input type="hidden" name="end_date" value="{{ $endDate }}">
                    <input type="hidden" name="ad_start_date" value="{{ $adStartDate }}">
                    <input type="hidden" name="ad_end_date" value="{{ $adEndDate }}">
                    <div class="flex items-end space-x-4">
                        <div>
                            <label for="plan_month" class="block text-sm font-medium text-gray-700 dark:text-gray-300">План/Факт за месяц</label>
                            <input type="month" name="plan_month" id="plan_month" value="{{ $selectedPlanMonth }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150">
                            Показать
                        </button>
                    </div>
                </form>

                <div id="plan-fact-block">
                    {{-- Функция для рендера одного KPI (уже должна быть определена выше в файле) --}}
                    @php
                        if (!function_exists('render_kpi_progress')) {
                            function render_kpi_progress($title, $fact, $plan) {
                                $percent = ($plan > 0) ? ($fact / $plan) * 100 : 0;
                                $percent_display = number_format($percent, 0);
                                $bgColor = 'bg-blue-600';
                                if ($percent >= 100) $bgColor = 'bg-green-500';
                                if ($percent < 75) $bgColor = 'bg-yellow-500';
                                if ($percent < 50) $bgColor = 'bg-red-500';

                                echo "<div class='mb-4'>";
                                echo "<div class='flex justify-between items-center text-sm mb-1'><span class='text-gray-600 dark:text-gray-400'>{$title}</span><span class='font-semibold text-gray-900 dark:text-white'>" . number_format($fact, 0, ',', ' ') . " / " . number_format($plan, 0, ',', ' ') . "</span></div>";
                                echo "<div class='w-full bg-gray-200 dark:bg-gray-600 rounded-full h-4 relative'><div class='{$bgColor} h-4 rounded-full flex items-center justify-center text-white text-xs font-bold' style='width: " . min($percent, 100) . "%'><span>{$percent_display}%</span></div></div>";
                                echo "</div>";
                            }
                        }
                    @endphp

                    @if($planData)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {{-- Выводим KPI, используя данные из контроллера --}}
                            {!! render_kpi_progress('Заказы, шт', $factDataMonthly->total_orders ?? 0, $planData->plan_ordersCount ?? 0) !!}
                            {!! render_kpi_progress('Выкупы, шт', $factDataMonthly->total_buyouts ?? 0, $planData->plan_buyoutsCount ?? 0) !!}
                            {{-- {!! render_kpi_progress('Сумма заказов, ₽', $factDataMonthly->total_orders_sum ?? 0, $planData->plan_ordersSumRub ?? 0) !!} --}}
                        </div>
                    @else
                        <div class="text-center text-gray-500 py-8">
                            <p>План для этого товара на выбранный месяц не задан.</p>
                            <a href="{{ route('planning.index', ['month' => $selectedPlanMonth, 'search' => $product->vendorCode]) }}" class="mt-2 inline-block text-blue-500 hover:underline">Перейти к планированию</a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Блок со сводной таблицей с ВЫБОРОМ ПЕРИОДА --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('products.show', $product->nmID) }}" class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                    <input type="hidden" name="plan_month" value="{{ $selectedPlanMonth }}">
                    <input type="hidden" name="ad_start_date" value="{{ $adStartDate }}">
                    <input type="hidden" name="ad_end_date" value="{{ $adEndDate }}">
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

                                {{-- *** КЛЮЧЕВОЕ ИЗМЕНЕНИЕ ЗДЕСЬ *** --}}
                                @if ($key == 'netProfit' && $product->cost_price <= 0)
                                    {{-- Если это строка прибыли и себестоимость не задана, выводим сообщение --}}
                                    <td colspan="{{ count($datesForCustomPivot) + 1 }}" class="px-6 py-4 text-center text-sm text-yellow-600 dark:text-yellow-40ag-400">
                                        Не указана себестоимость. <a href="#cost-price-display" id="edit-cost-price-link" class="underline">Изменить</a>
                                    </td>
                                @else
                                    {{-- Иначе выводим ячейки как обычно --}}
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
                                @endif
                                {{-- *** КОНЕЦ ИЗМЕНЕНИЯ *** --}}
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
                    <input type="hidden" name="plan_month" value="{{ $selectedPlanMonth }}">
                    <input type="hidden" name="start_date" value="{{ $startDate }}">
                    <input type="hidden" name="end_date" value="{{ $endDate }}">
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

            {{-- *** НОВЫЙ БЛОК: ЛОГИСТИКА ПО SKU *** --}}

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 p-6 border-b border-gray-200 dark:border-gray-700">
                            Остатки и логистика по размерам (SKU)
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Размер / Баркод</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Продаж/день</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Остаток WB</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">К клиенту</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">От клиента</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Свой склад</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">В пути на WB</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">В пути (склад)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">На фабрике</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Оборачиваемость</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($skusForProduct as $sku)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">Размер: <b>{{ $sku->tech_size }}</b></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $sku->barcode }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ number_format($sku->avg_daily_sales, 2, ',', ' ') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ number_format($sku->stock_wb, 0, ',', ' ') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-500">{{ number_format($sku->in_way_to_client, 0, ',', ' ') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-500">{{ number_format($sku->in_way_from_client, 0, ',', ' ') }}</td>

                                        {{-- РЕДАКТИРУЕМЫЕ ЯЧЕЙКИ --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
                                            x-data="editableCell({{ $sku->stock_own }}, '{{ route('logistics.updateStock', $sku->id) }}', 'stock_own', {{ $sku->id }})">
                                            <span x-show="!editing" @click="startEditing" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded" x-text="value"></span>
                                            <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter="saveValue" @keydown.escape="cancelEditing" @click.outside="saveValue" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1">
                                            <p x-show="errorMessage" x-text="errorMessage" class="text-xs text-red-500"></p>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
                                            x-data="editableCell({{ $sku->in_transit_to_wb }}, '{{ route('logistics.updateStock', $sku->id) }}', 'in_transit_to_wb', {{ $sku->id }})">
                                            <span x-show="!editing" @click="startEditing" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded" x-text="value"></span>
                                            <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter="saveValue" @keydown.escape="cancelEditing" @click.outside="saveValue" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1">
                                            <p x-show="errorMessage" x-text="errorMessage" class="text-xs text-red-500"></p>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
                                            x-data="editableCell({{ $sku->in_transit_general }}, '{{ route('logistics.updateStock', $sku->id) }}', 'in_transit_general', {{ $sku->id }})">
                                            <span x-show="!editing" @click="startEditing" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded" x-text="value"></span>
                                            <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter="saveValue" @keydown.escape="cancelEditing" @click.outside="saveValue" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1">
                                            <p x-show="errorMessage" x-text="errorMessage" class="text-xs text-red-500"></p>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
                                            x-data="editableCell({{ $sku->at_factory }}, '{{ route('logistics.updateStock', $sku->id) }}', 'at_factory', {{ $sku->id }})">
                                            <span x-show="!editing" @click="startEditing" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded" x-text="value"></span>
                                            <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter="saveValue" @keydown.escape="cancelEditing" @click.outside="saveValue" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1">
                                            <p x-show="errorMessage" x-text="errorMessage" class="text-xs text-red-500"></p>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold">
                                            @if(is_null($sku->turnover_days))
                                                <span class="text-gray-400">∞</span>
                                            @else
                                                @php $color = $sku->turnover_days < 7 ? 'text-red-500' : ($sku->turnover_days < 30 ? 'text-yellow-500' : 'text-green-500'); @endphp
                                                <span class="{{ $color }}">{{ $sku->turnover_days }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                            Размеры (SKU) для этого товара еще не синхронизированы. Запустите команду `php artisan wb:sync-skus`.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
            {{-- *** КОНЕЦ НОВОГО БЛОКА *** --}}

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
                // *** НОВЫЙ СКРИПТ ДЛЯ РЕДАКТИРОВАНИЯ СЕБЕСТОИМОСТИ ***
                const displayBlock = document.getElementById('cost-price-display');
                const formBlock = document.getElementById('cost-price-form');
                const editBtn = document.getElementById('edit-cost-price-btn');

                if (editBtn) {
                    editBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        displayBlock.classList.add('hidden');
                        formBlock.classList.remove('hidden');
                    });
                }

                // *** ДОПОЛНЕНИЕ К СКРИПТУ: Ссылка для быстрого редактирования себестоимости ***
                const editCostPriceLink = document.getElementById('edit-cost-price-link');
                if(editCostPriceLink) {
                    editCostPriceLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('edit-cost-price-btn').click();
                        document.getElementById('cost-price-form').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                }
            });
        </script>

</x-app-layout>
