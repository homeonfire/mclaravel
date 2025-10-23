<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Список всех товаров') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            {{-- Форма поиска и фильтрации --}}
            {{-- Search and Filter Form --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-6">
                <form method="GET" action="{{ route('products.index') }}" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">

                        {{-- Filter by Store --}}
                        <div>
                            <label for="store_id" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Магазин</label>
                            <select name="store_id" id="store_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Все магазины</option>
                                @foreach ($stores as $store)
                                    <option value="{{ $store->id }}" @selected($selectedStoreId == $store->id)>
                                        {{ $store->store_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Search by Vendor Code --}}
                        <div>
                            <label for="search" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Поиск по артикулу продавца</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Введите vendorCode..." class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        {{-- Filter by Active Campaign --}}
                        <div class="flex items-center h-10"> {{-- This container helps with vertical alignment --}}
                            <input type="checkbox" name="with_active_campaign" id="with_active_campaign" value="1" @checked($withActiveCampaign) class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                            <label for="with_active_campaign" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">Только с активной РК</label>
                        </div>

                        {{-- *** НОВЫЙ ФИЛЬТР "ПОКАЗАТЬ АКТУАЛЬНЫЕ" *** --}}
                        <div class="flex items-center justify-start pt-6"> {{-- Смещаем чекбокс немного вниз для выравнивания --}}
                            <label for="show_active_only" class="inline-flex items-center">
                                <input type="checkbox" name="show_active_only" id="show_active_only" value="1" @checked($showActiveOnly) class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800">
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Показать актуальные</span>
                            </label>
                        </div>

                        {{-- Submit Button --}}
                        <div>
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none">
                                Применить
                            </button>
                        </div>

                    </div>
                </form>
            </div>

            {{-- Таблица с товарами --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Фото</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Название</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Показы (актуальные)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Артикул WB</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Артикул продавца</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Магазин</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($products as $product)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 @if($product->latest_day_views > 0) bg-blue-50 dark:bg-blue-900/20 @endif">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex-shrink-0 h-16 w-16">
                                        @if($product->main_image_url)
                                            <img class="h-16 w-16 rounded-md object-cover" src="{{ $product->main_image_url }}" alt="">
                                        @else
                                            <div class="h-16 w-16 rounded-md bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs text-gray-400">Нет фото</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <a href="{{ route('products.show', $product->nmID) }}" class="hover:underline">
                                            {{ Str::limit($product->title, 50) }}
                                        </a>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $product->brand }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white">
                                    {{ number_format($product->latest_day_views, 0, ',', ' ') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $product->nmID }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $product->vendorCode }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $product->store->store_name ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    Товары не найдены.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $products->withQueryString()->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
