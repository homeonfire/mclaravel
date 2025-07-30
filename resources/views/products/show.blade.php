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
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Основная информация</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600 dark:text-gray-400">
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

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Дата</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Переходы</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">В корзину</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Заказы</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Выкупы</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @php
                            function render_diff_table($current, $previous) {
                                $diff = $current - ($previous ?? 0);
                                $diff_str = '';
                                if ($previous !== null) {
                                    if ($diff > 0) $diff_str = "<span class='text-green-500 text-xs ml-1'>(+{$diff})</span>";
                                    if ($diff < 0) $diff_str = "<span class='text-red-500 text-xs ml-1'>({$diff})</span>";
                                }
                                echo number_format($current) . $diff_str;
                            }
                        @endphp

                        @forelse ($stats as $stat)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    {{ \Carbon\Carbon::parse($stat->report_date)->format('d.m.Y') }}
                                </td>
                                <td class="px-6 py-4 text-sm dark:text-white">{!! render_diff_table($stat->openCardCount, $stat->previous->openCardCount ?? null) !!}</td>
                                <td class="px-6 py-4 text-sm dark:text-white">{!! render_diff_table($stat->addToCartCount, $stat->previous->addToCartCount ?? null) !!}</td>
                                <td class="px-6 py-4 text-sm dark:text-white">{!! render_diff_table($stat->ordersCount, $stat->previous->ordersCount ?? null) !!}</td>
                                <td class="px-6 py-4 text-sm dark:text-white">{!! render_diff_table($stat->buyoutsCount, $stat->previous->buyoutsCount ?? null) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-4 text-center">Нет данных для отображения.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Участие в рекламных кампаниях</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Название кампании</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Статус</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Дата создания</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Дата обновления</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($product->adCampaigns as $campaign)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $campaign->name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($campaign->status == 9)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Активна</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Неактивна</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                        {{ $campaign->createTime ? \Carbon\Carbon::parse($campaign->createTime)->format('d.m.Y H:i') : '–' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                        {{ $campaign->changeTime ? \Carbon\Carbon::parse($campaign->changeTime)->format('d.m.Y H:i') : '–' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                        Этот товар не участвует в рекламных кампаниях.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

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
