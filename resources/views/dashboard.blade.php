<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Дашборд') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @forelse ($topProductsByStore as $storeName => $products)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <h3 class="text-lg font-medium mb-4">Топ 5: {{ $storeName }}</h3>

                            @if($products->count() > 0)
                                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($products as $product)
                                        <li class="py-4 flex items-center">
                                            @if($product->main_image_url)
                                                <img class="h-12 w-12 rounded-md object-cover" src="{{ $product->main_image_url }}" alt="">
                                            @else
                                                <div class="h-12 w-12 bg-gray-700 rounded-md flex items-center justify-center text-xs text-gray-400">Нет фото</div>
                                            @endif

                                            <div class="ml-4 flex-grow">
                                                <a href="{{ route('products.show', $product->nmID) }}" class="text-sm font-semibold text-gray-900 dark:text-white hover:underline">
                                                    {{ Str::limit($product->title, 35) }}
                                                </a>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Арт: {{ $product->vendorCode }}</p>
                                            </div>

                                            <div class="text-right ml-4">
                                                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $product->orders_count }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">заказов</p>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-gray-500">Нет данных для отображения.</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-1 md:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-500 text-center">
                            Нет магазинов или данных для отображения.
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">Отслеживаемые товары</h3>
                    @if($trackedProducts->count() > 0)
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($trackedProducts as $product)
                                <li class="py-4 flex items-center">
                                    @if($product->main_image_url)
                                        <img class="h-12 w-12 rounded-md object-cover" src="{{ $product->main_image_url }}" alt="">
                                    @else
                                        <div class="h-12 w-12 bg-gray-700 rounded-md flex items-center justify-center text-xs text-gray-400">Нет фото</div>
                                    @endif
                                    <div class="ml-4 flex-grow">
                                        <a href="{{ route('products.show', $product->nmID) }}" class="text-sm font-semibold text-gray-900 dark:text-white hover:underline">
                                            {{ $product->title }}
                                        </a>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Арт: {{ $product->vendorCode }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-gray-500">Вы пока не отслеживаете ни одного товара.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
