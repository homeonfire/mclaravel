<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Поведенческий отчет') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-6">
                <form method="GET" class="p-6 flex flex-wrap items-center gap-4">
                    @include('reports.partials.filters')
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Товар</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Переходы</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">В корзину</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Заказы</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Выкупы</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @php
                            function render_diff($current, $previous) {
                                $diff = $current - ($previous ?? 0);
                                $diff_str = '';
                                if ($diff > 0) $diff_str = "<span class='text-green-500 text-xs ml-1'>(+{$diff})</span>";
                                if ($diff < 0) $diff_str = "<span class='text-red-500 text-xs ml-1'>({$diff})</span>";
                                echo number_format($current) . $diff_str;
                            }
                        @endphp

                        @forelse ($items as $item)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    <a href="{{ route('products.show', $item->product_nmID) }}" class="hover:underline text-blue-400">
                                        {{ $item->product_title }}
                                    </a>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->vendor_code }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm dark:text-white">{!! render_diff($item->openCardCount, $item->prev_openCardCount) !!}</td>
                                <td class="px-6 py-4 text-sm dark:text-white">{!! render_diff($item->addToCartCount, $item->prev_addToCartCount) !!}</td>
                                <td class="px-6 py-4 text-sm dark:text-white">{!! render_diff($item->ordersCount, $item->prev_ordersCount) !!}</td>
                                <td class="px-6 py-4 text-sm dark:text-white">{!! render_diff($item->buyoutsCount, $item->prev_buyoutsCount) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-4 text-center">Данных нет.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $items->withQueryString()->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
