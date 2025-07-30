<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Кампания: {{ $campaign->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Статистика за 30 дней</h3>
                <canvas id="campaignChart"></canvas>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Статистика по дням</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        {{-- ... здесь будет таблица ... --}}
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Дневная статистика по топ-5 ключевым словам</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase">Дата</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase">Ключевое слово</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-300 uppercase">Показы</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                        @forelse($topKeywordsStats as $stat)
                            <tr>
                                <td class="px-4 py-3 text-sm dark:text-white">{{ \Carbon\Carbon::parse($stat->report_date)->format('d.m.Y') }}</td>
                                <td class="px-4 py-3 text-sm dark:text-white">{{ $stat->keyword }}</td>
                                <td class="px-4 py-3 text-sm font-bold dark:text-white">{{ number_format($stat->views) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-4 text-center text-gray-500">Нет статистики по ключевым словам.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Товары в кампании</h3>
                <ul class="divide-y divide-gray-700">
                    @forelse($campaign->products as $product)
                        <li class="py-2 flex items-center">
                            <img src="{{ $product->main_image_url }}" class="h-10 w-10 rounded-md object-cover mr-4">
                            <a href="{{ route('products.show', $product->nmID) }}" class="hover:underline dark:text-white">{{ $product->title }}</a>
                        </li>
                    @empty
                        <li>Нет привязанных товаров.</li>
                    @endforelse
                </ul>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('campaignChart');
            if (!ctx) return;
            const chartData = {!! $chartData !!};

            new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: { /* ... опции для графика ... */ }
            });
        });
    </script>
</x-app-layout>
