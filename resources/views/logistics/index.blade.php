<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Управление логистикой и остатками
        </h2>
    </x-slot>

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
                            <input type="text" name="search" id="search" value="{{ $searchQuery }}" placeholder="Артикул или баркод..." class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            @php
                                // Функция для генерации ссылок сортировки
                                if (!function_exists('renderSortLink')) {
                                    function renderSortLink($label, $column, $currentSort, $currentDir) {
                                        $newDir = ($currentSort == $column && $currentDir == 'desc') ? 'asc' : 'desc';
                                        $icon = '';
                                        if ($currentSort == $column) {
                                            $icon = $currentDir == 'desc' ? ' ▼' : ' ▲';
                                        }
                                        $url = route('logistics.index', array_merge(request()->query(), ['sort' => $column, 'direction' => $newDir]));
                                        return "<a href='{$url}'>{$label}{$icon}</a>";
                                    }
                                }
                            @endphp
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Товар / SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                {!! renderSortLink('Продаж/день', 'avg_daily_sales', $sortColumn, $sortDirection) !!}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                {!! renderSortLink('Остаток WB', 'stock_wb', $sortColumn, $sortDirection) !!}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">К клиенту</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">От клиента</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Свой склад</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">В пути на WB</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                {!! renderSortLink('Оборачиваемость', 'turnover_days', $sortColumn, $sortDirection) !!}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Комментарий</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($skus as $sku)
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-12 w-12">
                                            @if($sku->main_image_url)
                                                <img class="h-12 w-12 rounded-md object-cover" src="{{ $sku->main_image_url }}" alt="">
                                            @else
                                                <div class="h-12 w-12 rounded-md bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs text-gray-400">Нет фото</div>
                                            @endif
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ Str::limit($sku->title, 40) }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">Размер: <b>{{ $sku->tech_size }}</b> | {{ $sku->barcode }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ number_format($sku->avg_daily_sales, 2, ',', ' ') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ number_format($sku->stock_wb, 0, ',', ' ') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-500">{{ number_format($sku->in_way_to_client, 0, ',', ' ') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-500">{{ number_format($sku->in_way_from_client, 0, ',', ' ') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{-- Редактируемое поле для $sku->stock_own --}}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{-- Редактируемое поле для $sku->in_transit_to_wb --}}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold">
                                    @if(is_null($sku->turnover_days))
                                        <span class="text-gray-400">∞</span>
                                    @else
                                        @php
                                            $color = 'text-green-500';
                                            if ($sku->turnover_days < 30) $color = 'text-yellow-500';
                                            if ($sku->turnover_days < 7) $color = 'text-red-500';
                                        @endphp
                                        <span class="{{ $color }}">{{ $sku->turnover_days }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{-- Здесь будет иконка комментариев --}}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="p-6 text-center text-gray-500">Артикулы (SKU) не найдены. Убедитесь, что вы запустили команду `php artisan wb:sync-skus`.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $skus->withQueryString()->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
