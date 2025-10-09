<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Планирование показателей
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto sm:px-6 lg:px-8">

            {{-- Форма фильтрации --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 mb-6">
                <form id="filters-form" method="GET" action="{{ route('planning.index') }}">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

                        {{-- Выбор месяца --}}
                        <div>
                            <label for="month" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Месяц</label>
                            <input type="month" name="month" id="month" value="{{ $selectedMonth }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        {{-- Выбор магазина --}}
                        <div>
                            <label for="store_id" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Магазин</label>
                            <select name="store_id" id="store_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Все магазины</option>
                                @foreach ($stores as $store)
                                    <option value="{{ $store->id }}" @selected($selectedStoreId == $store->id)>{{ $store->store_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Поиск по артикулу --}}
                        <div>
                            <label for="search" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Артикул продавца</label>
                            <input type="text" name="search" id="search" value="{{ $searchQuery }}" placeholder="Введите vendorCode..." class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        {{-- Кнопка "Применить" --}}
                        <div>
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none">
                                Применить фильтры
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Форма для сохранения планов --}}
            <form method="POST" action="{{ route('planning.store') }}">
                @csrf
                <input type="hidden" name="month" value="{{ $selectedMonth }}">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150">
                            Сохранить планы
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-700 px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/2">Товар</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">План заказов, шт</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">План выкупов, шт</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($products as $product)
                                @php $plan = $plans[$product->nmID] ?? null; @endphp
                                <tr>
                                    <td class="sticky left-0 bg-white dark:bg-gray-800 px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-12 w-12">
                                                @if($product->main_image_url)
                                                    <img class="h-12 w-12 rounded-md object-cover" src="{{ $product->main_image_url }}" alt="">
                                                @else
                                                    <div class="h-12 w-12 rounded-md bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs text-gray-400">Нет фото</div>
                                                @endif
                                            </div>
                                            <div class="ml-4">
                                                <a href="{{ route('products.show', $product->nmID) }}" class="font-medium text-gray-900 dark:text-white hover:underline">
                                                    {{ Str::limit($product->title, 50) }}
                                                </a>
                                                <div class="text-xs text-gray-500">{{ $product->vendorCode }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <input type="number" name="plans[{{ $product->nmID }}][ordersCount]" value="{{ $plan->plan_ordersCount ?? '' }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                    </td>
                                    <td class="px-6 py-4">
                                        <input type="number" name="plans[{{ $product->nmID }}][buyoutsCount]" value="{{ $plan->plan_buyoutsCount ?? '' }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="p-6 text-center text-gray-500">Товары по вашему запросу не найдены.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
