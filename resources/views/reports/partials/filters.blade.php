<div>
    <label for="store_selector" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Магазин</label>
    <select name="store_id" id="store_selector" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
        @foreach ($stores as $store)
            <option value="{{ $store->id }}" @selected($store->id == $selectedStoreId)>
                {{ $store->store_name }}
            </option>
        @endforeach
    </select>
</div>
<div>
    <label for="date_selector" class="block font-medium text-sm text-gray-700 dark:text-gray-300">Дата</label>
    <input type="date" name="date" value="{{ $reportDate }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm">
</div>
<div>
    <label class="block text-sm">&nbsp;</label>
    <button type="submit" class="mt-1 inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest">Показать</button>
</div>
