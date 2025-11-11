<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Загрузка Заказа (Простой формат)
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">

                {{-- Форма загрузки файла --}}
                <form action="{{ route('import.simple-order.process') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Загрузка файла "Простой заказ" (.xlsx)
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Система обработает **первый лист** файла. Баркод из колонки **B**, Количество из колонки **D**.
                        </p>

                        {{-- Сообщения сессии --}}
                        @if (session('success'))
                            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-200 rounded">
                                {{ session('success') }}
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-200 rounded">
                                {{ session('error') }} <a href="#logs" class="underline">См. лог ниже.</a>
                            </div>
                        @endif
                        @error('excel_file')
                        <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-200 rounded">
                            {{ $message }}
                        </div>
                        @enderror

                        {{-- Поле выбора файла --}}
                        <div class="mb-4">
                            <label for="excel_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Выберите файл (.xlsx)</label>
                            <input type="file" name="excel_file" id="excel_file" required accept=".xlsx"
                                   class="block w-full text-sm text-gray-500 dark:text-gray-400
                                          file:mr-4 file:py-2 file:px-4
                                          file:rounded-md file:border-0
                                          file:text-sm file:font-semibold
                                          file:bg-blue-50 dark:file:bg-blue-900 file:text-blue-700 dark:file:text-blue-300
                                          hover:file:bg-blue-100 dark:hover:file:bg-blue-800 cursor-pointer">
                        </div>

                        {{-- Кнопка Загрузить --}}
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Загрузить и поставить в очередь
                            </button>
                        </div>
                    </div>
                </form>

            </div>

            {{-- Блок с логами --}}
            <div id="logs" class="mt-8">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        История загрузок (Простой заказ)
                    </h3>
                    <a href="{{ route('import.simple-order.show') }}" class="text-sm text-blue-500 hover:underline">Обновить лог</a>
                </div>
                @if($logs->isEmpty())
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-500 dark:text-gray-400">
                        Логи еще отсутствуют.
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($logs as $batchId => $batchLogs)
                            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        Загрузка от: {{ $batchLogs->first()->created_at->format('d.m.Y H:i:s') }}
                                        <span class="text-xs text-gray-400 ml-2">(ID: {{ Str::limit($batchId, 8) }})</span>
                                        {{-- Статус --}}
                                        @php
                                            $hasErrors = $batchLogs->contains('status', 'error');
                                            $isFinished = $batchLogs->contains(fn($log)=> str_contains($log->message, 'завершена'));
                                            $isQueued = $batchLogs->contains('status', 'queued');
                                            $isProcessing = !$isFinished && !$isQueued;
                                        @endphp
                                        @if($hasErrors) <span class="ml-2 px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-800 rounded-full">Есть ошибки</span>
                                        @elseif($isQueued && !$isProcessing && !$isFinished) <span class="ml-2 px-2 py-0.5 text-xs font-semibold bg-gray-100 text-gray-800 rounded-full">В очереди</span>
                                        @elseif($isProcessing) <span class="ml-2 px-2 py-0.5 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">Обработка...</span>
                                        @elseif($isFinished && !$hasErrors) <span class="ml-2 px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Завершено</span>
                                        @elseif($isFinished && $hasErrors) <span class="ml-2 px-2 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-800 rounded-full">Завершено с ошибками</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-800/50 max-h-60 overflow-y-auto text-xs font-mono">
                                    @foreach($batchLogs->sortBy('created_at') as $log)
                                        <p class="mb-1 @if($log->status === 'error') text-red-600 dark:text-red-400 @elseif($log->status === 'warning') text-yellow-600 dark:text-yellow-400 @elseif($log->status === 'info') text-blue-600 dark:text-blue-400 @elseif($log->status === 'queued') text-gray-500 dark:text-gray-400 @else text-green-600 dark:text-green-400 @endif">
                                            <span class="text-gray-400">{{ $log->created_at->format('H:i:s') }}</span>
                                            @if($log->row_number) [Стр. {{ $log->row_number }}] @endif
                                            @if($log->barcode) [{{ $log->barcode }}] @endif
                                            - {{ $log->message }}
                                        </p>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
