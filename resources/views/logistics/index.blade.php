<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Управление логистикой и остатками
        </h2>
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

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            {{-- Панель фильтров --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-6">
                <form method="GET" action="{{ route('logistics.index') }}" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                        <div>
                            <label for="start_date" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Продажи с</label>
                            <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="end_date" class="block font-medium text-sm text-gray-700 dark:text-gray-300">по</label>
                            <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="store_id" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Магазин</label>
                            <select name="store_id" id="store_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Все магазины</option>
                                @foreach ($stores as $store)
                                    <option value="{{ $store->id }}" @selected($selectedStoreId == $store->id)>{{ $store->store_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="search" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Поиск</label>
                            <input type="text" name="search" id="search" value="{{ $searchQuery }}" placeholder="Артикул или название..." class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none">Применить</button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Основная таблица --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            @php
                                if (!function_exists('renderSortLink')) {
                                    function renderSortLink($label, $column, $currentSort, $currentDir) {
                                        $newDir = ($currentSort == $column && $currentDir == 'desc') ? 'asc' : 'desc';
                                        $icon = '';
                                        if ($currentSort == $column) { $icon = $currentDir == 'desc' ? ' ▼' : ' ▲'; }
                                        $url = route('logistics.index', array_merge(request()->query(), ['sort' => $column, 'direction' => $newDir]));
                                        return "<a href='{$url}'>{$label}{$icon}</a>";
                                    }
                                }
                            @endphp
                            <th class="w-12"></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{!! renderSortLink('Товар / SKU', 'title', $sortColumn, $sortDirection) !!}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{!! renderSortLink('Продаж/день', 'total_avg_daily_sales', $sortColumn, $sortDirection) !!}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{!! renderSortLink('Остаток WB', 'total_stock_wb', $sortColumn, $sortDirection) !!}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">К клиенту</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">От клиента</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Свой склад</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">В пути на WB</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">В пути (склад)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">На фабрике</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Оборачиваемость</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Комментарий</th>
                        </tr>
                        </thead>
                        @forelse ($products as $product)
                            @php $totals = $productTotals[$product->nmID] ?? null; @endphp
                            <tbody x-data="{ expanded: false }" class="border-t border-gray-200 dark:border-gray-700">
                            {{-- ОСНОВНАЯ СТРОКА ТОВАРА --}}
                            <tr @click="expanded = !expanded" class="cursor-pointer bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 font-bold">
                                <td class="px-4 py-2"><button class="text-gray-500 dark:text-gray-400"><svg class="h-5 w-5 transform transition-transform" :class="{'rotate-90': expanded}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></button></td>
                                <td class="px-6 py-2">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-12 w-12"><img class="h-12 w-12 rounded-md object-cover" src="{{ $product->main_image_url }}" alt=""></div>
                                        <div class="ml-4">
                                            <div class="text-sm text-gray-900 dark:text-white">{{ $product->title }}</div>
                                            <div class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $product->vendorCode }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ number_format($totals['total_avg_daily_sales'] ?? 0, 2, ',', ' ') }}</td>
                                <td class="px-6 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ number_format($totals['total_stock_wb'] ?? 0, 0, ',', ' ') }}</td>
                                <td class="px-6 py-2 whitespace-nowrap text-sm text-blue-500">{{ number_format($totals['total_in_way_to_client'] ?? 0, 0, ',', ' ') }}</td>
                                <td class="px-6 py-2 whitespace-nowrap text-sm text-yellow-500">{{ number_format($totals['total_in_way_from_client'] ?? 0, 0, ',', ' ') }}</td>
                                <td class="px-6 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ number_format($totals['total_stock_own'] ?? 0, 0, ',', ' ') }}</td>
                                <td class="px-6 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ number_format($totals['total_in_transit_to_wb'] ?? 0, 0, ',', ' ') }}</td>
                                <td class="px-6 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ number_format($totals['total_in_transit_general'] ?? 0, 0, ',', ' ') }}</td>
                                <td class="px-6 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ number_format($totals['total_at_factory'] ?? 0, 0, ',', ' ') }}</td>
                                <td class="px-6 py-2 whitespace-nowrap text-sm">
                                    @if(is_null($totals['total_turnover_days'] ?? null)) <span class="text-gray-400">∞</span>
                                    @else
                                        @php $color = $totals['total_turnover_days'] < 7 ? 'text-red-500' : ($totals['total_turnover_days'] < 30 ? 'text-yellow-500' : 'text-green-500'); @endphp
                                        <span class="{{ $color }}">{{ $totals['total_turnover_days'] }}</span>
                                    @endif
                                </td>
                                <td></td>
                            </tr>

                            {{-- СКРЫТЫЕ СТРОКИ С SKU --}}
                            @foreach ($skusGroupedByProduct[$product->nmID] ?? [] as $sku)
                                <tr x-show="expanded" x-transition class="bg-white dark:bg-gray-800 font-normal">
                                    <td></td>
                                    <td class="px-6 py-4"><div class="pl-16 text-sm text-gray-500 dark:text-gray-400">Размер: <b>{{ $sku->tech_size }}</b> | {{ $sku->barcode }}</div></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ number_format($sku->avg_daily_sales, 2, ',', ' ') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ number_format($sku->stock_wb, 0, ',', ' ') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-500">{{ number_format($sku->in_way_to_client, 0, ',', ' ') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-500">{{ number_format($sku->in_way_from_client, 0, ',', ' ') }}</td>

                                    {{-- РЕДАКТИРУЕМЫЕ ЯЧЕЙКИ --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-data="editableCell({{ $sku->stock_own }}, '{{ route('logistics.updateStock', $sku->id) }}', 'stock_own', {{ $sku->id }})">
                                        <span x-show="!editing" @click="startEditing" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded" x-text="value"></span>
                                        <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter="saveValue" @keydown.escape="cancelEditing" @click.outside="saveValue" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1">
                                        <p x-show="errorMessage" x-text="errorMessage" class="text-xs text-red-500"></p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-data="editableCell({{ $sku->in_transit_to_wb }}, '{{ route('logistics.updateStock', $sku->id) }}', 'in_transit_to_wb', {{ $sku->id }})">
                                        <span x-show="!editing" @click="startEditing" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded" x-text="value"></span>
                                        <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter="saveValue" @keydown.escape="cancelEditing" @click.outside="saveValue" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1">
                                        <p x-show="errorMessage" x-text="errorMessage" class="text-xs text-red-500"></p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-data="editableCell({{ $sku->in_transit_general }}, '{{ route('logistics.updateStock', $sku->id) }}', 'in_transit_general', {{ $sku->id }})">
                                        <span x-show="!editing" @click="startEditing" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded" x-text="value"></span>
                                        <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter="saveValue" @keydown.escape="cancelEditing" @click.outside="saveValue" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1">
                                        <p x-show="errorMessage" x-text="errorMessage" class="text-xs text-red-500"></p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-data="editableCell({{ $sku->at_factory }}, '{{ route('logistics.updateStock', $sku->id) }}', 'at_factory', {{ $sku->id }})">
                                        <span x-show="!editing" @click="startEditing" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded" x-text="value"></span>
                                        <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter="saveValue" @keydown.escape="cancelEditing" @click.outside="saveValue" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1">
                                        <p x-show="errorMessage" x-text="errorMessage" class="text-xs text-red-500"></p>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold">
                                        @if(is_null($sku->turnover_days)) <span class="text-gray-400">∞</span>
                                        @else
                                            @php $color = $sku->turnover_days < 7 ? 'text-red-500' : ($sku->turnover_days < 30 ? 'text-yellow-500' : 'text-green-500'); @endphp
                                            <span class="{{ $color }}">{{ $sku->turnover_days }}</span>
                                        @endif
                                    </td>
                                    <td></td> {{-- Пустая ячейка для Комментария --}}
                                </tr>
                            @endforeach
                            </tbody>
                        @empty
                            <tbody class="bg-white dark:bg-gray-800">
                            <tr><td colspan="12" class="p-6 text-center text-gray-500">Товары по вашим фильтрам не найдены.</td></tr> {{-- Обновляем colspan --}}
                            </tbody>
                        @endforelse
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $products->withQueryString()->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
