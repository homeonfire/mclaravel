<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Активные рекламные кампании') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mb-6">
                <form method="GET" class="p-6 flex items-center gap-4">
                    <div>
                        <label for="store_selector" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Магазин</label>
                        <select name="store_id" id="store_selector" onchange="this.form.submit()" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}" @selected($store->id == $selectedStoreId)>
                                    {{ $store->store_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Название</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Тип</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Бюджет</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Дата создания</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                <a href="{{ route('advertising.show', $campaign->advertId) }}" class="hover:underline text-blue-400">
                                    {{ $campaign->name }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">{{ $campaign->type }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">{{ number_format($campaign->dailyBudget, 0, ',', ' ') }} ₽</td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">{{ \Carbon\Carbon::parse($campaign->createTime)->format('d.m.Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">Активных кампаний не найдено.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $campaigns->withQueryString()->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
