<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
use DateTime;
// use Exception; // Больше не нужен, так как мы ловим Throwable

class IngestBehavioralStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wb:ingest-behavioral {--start-date= : Дата начала в формате Y-m-d. По умолчанию - 7 дней назад.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Загружает поведенческую статистику, пропуская записи по неизвестным товарам';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDateString = $this->option('start-date') ?? (new DateTime('-7 days'))->format('Y-m-d');
        $this->info("Начало загрузки поведенческой статистики с даты: {$startDateString}...");

        try {
            $stores = DB::table('stores')->get();
            if ($stores->isEmpty()) {
                $this->warn("В таблице `stores` нет ни одного магазина.");
                return 1;
            }
            $this->info("Найдено магазинов для обработки: " . $stores->count());

            foreach ($stores as $store) {
                $this->line("================================================");
                $this->info("Обработка магазина: '{$store->store_name}' (ID: {$store->id})");
                $this->processStore($store, $startDateString);
            }
            $this->info("\nВсе магазины успешно обработаны!");
            return 0;
        } catch (\Throwable $e) { // <-- ИЗМЕНЕНИЕ 1
            $this->error("Произошла критическая ошибка: " . $e->getMessage());
            $this->error("Файл: " . $e->getFile() . " Строка: " . $e->getLine());
            return 1;
        }
    }

    /**
     * Processes a single store, loading all behavioral statistics.
     *
     * @param object $store
     * @param string $startDateString
     */
    private function processStore(object $store, string $startDateString): void
    {
        $api = new API(['masterkey' => $store->api_key]);
        $analyticsApi = $api->Analytics();
        $knownNmIDs = DB::table('products')->where('store_id', $store->id)->pluck('nmID')->all();
        $currentDate = new DateTime($startDateString);
        $endDate = new DateTime();

        while ($currentDate < $endDate) {
            $dateToProcess = $currentDate->format('Y-m-d');
            $this->comment(" -> Обрабатываем дату: " . $dateToProcess . "...");

            try {
                $dateFrom = (clone $currentDate)->setTime(0, 0, 0);
                $dateTo = (clone $currentDate)->setTime(23, 59, 59);

                $result = $analyticsApi->nmReportDetail($dateFrom, $dateTo, []);

                if (!isset($result->data->cards) || empty($result->data->cards)) {
                    $this->line("    - Нет данных за эту дату. Пропускаем.");
                } else {
                    $this->line("    - Получено " . count($result->data->cards) . " записей. Начинаем проверку и пакетную загрузку...");

                    $statsData = [];
                    $skippedCount = 0;
                    foreach ($result->data->cards as $cardData) {
                        if (!in_array($cardData->nmID, $knownNmIDs)) {
                            $this->warn("    - Товар с nmID: {$cardData->nmID} не найден в `products`. Статистика пропущена.");
                            $skippedCount++;
                            continue;
                        }

                        $periodStats = $cardData->statistics->selectedPeriod;
                        $stocks = $cardData->stocks;

                        $statsData[] = [
                            'store_id' => $store->id,
                            'report_date' => $dateToProcess,
                            'nmID' => $cardData->nmID,
                            'openCardCount' => $periodStats->openCardCount ?? 0,
                            'addToCartCount' => $periodStats->addToCartCount ?? 0,
                            'ordersCount' => $periodStats->ordersCount ?? 0,
                            'ordersSumRub' => $periodStats->ordersSumRub ?? 0,
                            'buyoutsCount' => $periodStats->buyoutsCount ?? 0,
                            'buyoutsSumRub' => $periodStats->buyoutsSumRub ?? 0,
                            'cancelCount' => $periodStats->cancelCount ?? 0,
                            'cancelSumRub' => $periodStats->cancelSumRub ?? 0,
                            'avgPriceRub' => $periodStats->avgPriceRub ?? 0,
                            'stocksMp' => $stocks->stocksMp ?? 0,
                            'stocksWb' => $stocks->stocksWb ?? 0,
                        ];
                    }

                    if (!empty($statsData)) {
                        $chunks = array_chunk($statsData, 100);
                        foreach ($chunks as $chunk) {
                            DB::table('behavioral_stats')->upsert(
                                $chunk,
                                ['store_id', 'report_date', 'nmID'],
                                ['openCardCount', 'addToCartCount', 'ordersCount', 'ordersSumRub', 'buyoutsCount', 'buyoutsSumRub', 'cancelCount', 'cancelSumRub', 'avgPriceRub', 'stocksMp', 'stocksWb']
                            );
                        }
                        $this->info("    - Данные по " . count($statsData) . " товарам за " . $dateToProcess . " успешно загружены.");
                    }
                    if ($skippedCount > 0) {
                        $this->warn("    - Всего пропущено " . $skippedCount . " записей из-за отсутствия родительского товара.");
                    }
                }
            } catch (\Throwable $e) { // <-- ИЗМЕНЕНИЕ 2
                $this->error("    - КРИТИЧЕСКАЯ ОШИБКА при обработке даты {$dateToProcess}: " . $e->getMessage());
                $this->warn("    - Пропускаем эту дату и продолжаем...");
            }

            $currentDate->modify('+1 day');
            if ($currentDate < $endDate) {
                $this->comment("    - Пауза 60 секунд...");
                sleep(60);
            }
        }
    }
}
