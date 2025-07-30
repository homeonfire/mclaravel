<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Список всех товаров') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-6">
                <form method="GET" class="p-6">
                    <label for="search" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Поиск по артикулу продавца</label>
                    <div class="flex items-center mt-1">
                        <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Введите vendorCode..." class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <button type="submit" class="ml-4 inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none">Найти</button>
                    </div>
                </form>
            </div>

            @if($products->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach ($products as $product)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg flex flex-col transform hover:scale-105 transition-transform duration-300">
                            <a href="{{ route('products.show', $product->nmID) }}">
                                <div class="aspect-w-1 aspect-h-1 w-full bg-gray-700">
                                    @if($product->main_image_url)
                                        <img src="{{ $product->main_image_url }}" alt="{{ $product->title }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-sm text-gray-400">Нет фото</div>
                                    @endif
                                </div>
                            </a>

                            <div class="p-4 flex flex-col flex-grow">
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">{{ $product->brand }}</p>
                                <h3 class="font-semibold text-gray-900 dark:text-white flex-grow">
                                    <a href="{{ route('products.show', $product->nmID) }}" class="hover:underline">
                                        {{ Str::limit($product->title, 45) }}
                                    </a>
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Арт: {{ $product->vendorCode }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                    Товары не найдены.
                </div>
            @endif


            <div class="mt-6">
                {{ $products->withQueryString()->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
