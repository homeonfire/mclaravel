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
                // Ensure initialValue is a number for calculations/display
                value: Number(initialValue) || 0,
                originalValue: Number(initialValue) || 0,
                updateUrl: updateUrl,
                fieldName: fieldName,
                skuStockId: skuStockId,
                errorMessage: '',
                startEditing() {
                    this.originalValue = this.value; // Save original before editing
                    this.editing = true;
                    this.$nextTick(() => {
                        this.$refs.input.focus();
                        this.$refs.input.select(); // Select text on focus
                    });
                },
                cancelEditing() {
                    this.value = this.originalValue; // Restore old value
                    this.editing = false;
                    this.errorMessage = '';
                },
                saveValue() {
                    this.errorMessage = '';
                    // Allow 0, handle potential non-numeric input gracefully
                    const newValue = parseInt(this.value);
                    if (isNaN(newValue) || newValue < 0) {
                        // If input is invalid, revert to original value or handle as needed
                        // this.value = this.originalValue; // Option 1: Revert immediately
                        this.errorMessage = 'Нужно целое число ≥ 0'; // Option 2: Show error
                        this.$refs.input.focus(); // Keep focus on input
                        return; // Stop saving
                    }
                    this.value = newValue; // Update display value to the validated number

                    fetch(this.updateUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ [this.fieldName]: this.value })
                    })
                        .then(response => {
                            if (!response.ok) {
                                // Handle HTTP errors (like 422 validation error, 500 server error)
                                return response.json().then(errData => {
                                    throw new Error(errData.message || `HTTP error! status: ${response.status}`);
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                this.editing = false;
                                this.originalValue = this.value; // Update original upon successful save
                                // Optional: Add success notification (e.g., using a toast library)
                                // console.log('Saved:', this.fieldName, this.value);
                            } else {
                                // Handle application-level errors returned in JSON
                                this.errorMessage = data.message || 'Ошибка сохранения';
                                this.value = this.originalValue; // Revert to original if save failed
                            }
                        })
                        .catch(error => {
                            console.error('Save Error:', error);
                            this.errorMessage = error.message || 'Ошибка сети или сервера';
                            this.value = this.originalValue; // Revert to original on network/server error
                        });
                }
            }
        }
    </script>
    {{-- CSRF токен для AJAX запросов --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- ОСНОВНОЙ КОНТЕЙНЕР x-data ДЛЯ МОДАЛЬНОГО ОКНА --}}
    <div x-data="{ isModalOpen: false, modalSkuBarcode: '', modalSkuSize: '', modalWarehouseDetails: [] }" @keydown.escape.window="isModalOpen = false">

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
                                        return "<a href='{$url}' class='hover:text-gray-900 dark:hover:text-white'>{$label}{$icon}</a>";
                                    }
                                }
                            @endphp
                            <th class="w-12 px-4"></th> {{-- Width for button --}}
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
                                <td class="px-4 py-2"><button class="text-gray-500 dark:text-gray-400 focus:outline-none"><svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-90': expanded}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></button></td>
                                <td class="px-6 py-2">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-12 w-12">
                                            @if($product->main_image_url)
                                                <img class="h-12 w-12 rounded-md object-cover" src="{{ $product->main_image_url }}" alt="">
                                            @else
                                                <div class="h-12 w-12 rounded-md bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs text-gray-400">Нет фото</div>
                                            @endif
                                        </div>
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
                                <td></td> {{-- Пустая ячейка для Комментария --}}
                            </tr>

                            {{-- СКРЫТЫЕ СТРОКИ С SKU --}}
                            @foreach ($skusGroupedByProduct[$product->nmID] ?? [] as $sku)
                                 {{-- Use teleport to avoid nested table issues with transitions --}}
                                    <tr x-show="expanded" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform -translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform -translate-y-2" class="bg-white dark:bg-gray-800 font-normal border-l-4 border-transparent hover:border-blue-300 dark:hover:border-blue-700">
                                        <td class="px-4"></td> {{-- Keep first cell aligned --}}
                                        <td class="px-6 py-4">
                                            <div class="pl-16 text-sm text-gray-500 dark:text-gray-400">Размер: <b class="text-gray-700 dark:text-gray-300">{{ $sku->tech_size }}</b> | {{ $sku->barcode }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ number_format($sku->avg_daily_sales, 2, ',', ' ') }}</td>
                                        {{-- Суммарный остаток WB с кнопкой детализации --}}
                                        {{-- Суммарный остаток WB с кнопкой ОТКРЫТИЯ МОДАЛКИ --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span>{{ number_format($sku->stock_wb, 0, ',', ' ') }}</span>
                                            @if($sku->warehouse_details->count() > 0)
                                                <button @click.stop=" isModalOpen = true;
                                                                            modalSkuBarcode = '{{ $sku->barcode }}';
                                                                            modalSkuSize = '{{ $sku->tech_size }}';
                                                                            modalWarehouseDetails = {{ json_encode($sku->warehouse_details->toArray()) }}; "
                                                        class="ml-1 text-blue-500 hover:text-blue-700 focus:outline-none p-1 inline-block align-middle"
                                                        title="Показать остатки по складам WB">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                    </svg>
                                                </button>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-500">{{ number_format($sku->in_way_to_client, 0, ',', ' ') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-500">{{ number_format($sku->in_way_from_client, 0, ',', ' ') }}</td>

                                        {{-- РЕДАКТИРУЕМЫЕ ЯЧЕЙКИ --}}
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-data="editableCell({{ $sku->stock_own }}, '{{ route('logistics.updateStock', $sku->id) }}', 'stock_own', {{ $sku->id }})">
                                            <div class="relative min-w-[60px]" @click.away="if(editing) saveValue()"> {{-- Save on click outside --}}
                                                <span x-show="!editing" @click="startEditing()" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded min-h-[20px] block" x-text="value"></span>
                                                <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter.prevent="saveValue()" @keydown.escape.prevent="cancelEditing()" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                <p x-show="errorMessage" x-text="errorMessage" class="absolute text-xs text-red-500 -bottom-4 left-0"></p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-data="editableCell({{ $sku->in_transit_to_wb }}, '{{ route('logistics.updateStock', $sku->id) }}', 'in_transit_to_wb', {{ $sku->id }})">
                                            <div class="relative min-w-[60px]" @click.away="if(editing) saveValue()">
                                                <span x-show="!editing" @click="startEditing()" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded min-h-[20px] block" x-text="value"></span>
                                                <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter.prevent="saveValue()" @keydown.escape.prevent="cancelEditing()" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                <p x-show="errorMessage" x-text="errorMessage" class="absolute text-xs text-red-500 -bottom-4 left-0"></p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-data="editableCell({{ $sku->in_transit_general }}, '{{ route('logistics.updateStock', $sku->id) }}', 'in_transit_general', {{ $sku->id }})">
                                            <div class="relative min-w-[60px]" @click.away="if(editing) saveValue()">
                                                <span x-show="!editing" @click="startEditing()" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded min-h-[20px] block" x-text="value"></span>
                                                <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter.prevent="saveValue()" @keydown.escape.prevent="cancelEditing()" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                <p x-show="errorMessage" x-text="errorMessage" class="absolute text-xs text-red-500 -bottom-4 left-0"></p>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-data="editableCell({{ $sku->at_factory }}, '{{ route('logistics.updateStock', $sku->id) }}', 'at_factory', {{ $sku->id }})">
                                            <div class="relative min-w-[60px]" @click.away="if(editing) saveValue()">
                                                <span x-show="!editing" @click="startEditing()" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 p-1 rounded min-h-[20px] block" x-text="value"></span>
                                                <input type="number" x-show="editing" x-ref="input" x-model="value" @keydown.enter.prevent="saveValue()" @keydown.escape.prevent="cancelEditing()" class="w-20 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm text-sm p-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                <p x-show="errorMessage" x-text="errorMessage" class="absolute text-xs text-red-500 -bottom-4 left-0"></p>
                                            </div>
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
                            <tr><td colspan="12" class="p-6 text-center text-gray-500">Товары по вашим фильтрам не найдены.</td></tr>
                            </tbody>
                        @endforelse
                    </table>
                </div>
            </div>

            {{-- Пагинация --}}
            <div class="mt-6">
                {{ $products->withQueryString()->links() }}
            </div>

        </div>
        {{-- *** МОДАЛЬНОЕ ОКНО ДЛЯ ОСТАТКОВ ПО СКЛАДАМ *** --}}
        <div x-show="isModalOpen"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="isModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity" aria-hidden="true"><div class="absolute inset-0 bg-gray-500 opacity-75 dark:bg-gray-900 dark:opacity-75"></div></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="isModalOpen"
                     x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     @click.outside="isModalOpen = false"
                     class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full"
                     role="dialog" aria-modal="true" aria-labelledby="modal-headline">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-headline">Остатки по складам WB</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Размер: <b x-text="modalSkuSize"></b> | Баркод: <b x-text="modalSkuBarcode"></b></p>
                                <div class="mt-2 overflow-x-auto max-h-60"> {{-- Добавил max-h-60 для скролла, если складов много --}}
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0"> {{-- Сделал заголовок липким --}}
                                        <tr>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Склад</th>
                                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Остаток, шт</th>
                                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">К клиенту</th>
                                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">От клиента</th>
                                        </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <template x-for="detail in modalWarehouseDetails" :key="detail.warehouse_name">
                                            <tr>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300" x-text="detail.warehouse_name"></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white font-semibold" x-text="detail.quantity"></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-right text-blue-500" x-text="detail.in_way_to_client"></td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-right text-yellow-500" x-text="detail.in_way_from_client"></td>
                                            </tr>
                                        </template>
                                        {{-- Строка "Итого" --}}
                                        <tr class="bg-gray-50 dark:bg-gray-700/50 font-bold border-t-2 border-gray-300 dark:border-gray-600">
                                            <td class="px-4 py-2 text-left text-xs text-gray-600 dark:text-gray-400 uppercase">Итого</td>
                                            <td class="px-4 py-2 text-right text-sm text-gray-900 dark:text-white" x-text="modalWarehouseDetails.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0)"></td>
                                            <td class="px-4 py-2 text-right text-sm text-blue-500" x-text="modalWarehouseDetails.reduce((sum, item) => sum + (Number(item.in_way_to_client) || 0), 0)"></td>
                                            <td class="px-4 py-2 text-right text-sm text-yellow-500" x-text="modalWarehouseDetails.reduce((sum, item) => sum + (Number(item.in_way_from_client) || 0), 0)"></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <template x-if="!modalWarehouseDetails || modalWarehouseDetails.length === 0">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4 italic">Нет данных по остаткам на складах WB для этого SKU.</p>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="isModalOpen = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Закрыть
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
        {{-- *** КОНЕЦ МОДАЛЬНОГО ОКНА *** --}}
    </div>
</x-app-layout>
