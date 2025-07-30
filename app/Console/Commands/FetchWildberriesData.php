<?php

namespace App\Console\Commands;

use App\Models\BehavioralStat;
use App\Models\Store;
use App\Services\WbApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FetchWildberriesData extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     */
    protected $signature = 'wb:fetch-behavioral';

    /**
     * Описание консольной команды.
     */
    protected $description = 'Получает поведенческую статистику (воронку) по дням и сохраняет в БД';

    /**
     * Выполнить команду.
     */
    public function handle()
    {
        // --- Укажите стартовую дату ---
        $startDate = Carbon::parse('2025-07-15'); // Пример
        // --------------------------------

        $endDate = Carbon::today();
        $this->info("Начинаем сбор поведенческой статистики с {$startDate->format('d.m.Y')} по {$endDate->format('d.m.Y')}.");

        $stores = Store::whereNotNull('api_key')->where('api_key', '!=', '')->get();
        if ($stores->isEmpty()) {
            $this->error("Не найдено ни одного магазина с API-ключом в базе данных.");
            return 1;
        }

        $this->info("Найдено магазинов для обработки: " . $stores->count());

        foreach ($stores as $store) {
            $this->line("\n--- Обработка магазина: <fg=yellow>{$store->store_name}</fg=yellow> ---");

            try {
                $wbApiService = new WbApiService($store->api_key);
            } catch (\Exception $e) {
                $this->error("Не удалось инициализировать сервис для магазина '{$store->store_name}': " . $e->getMessage());
                continue;
            }

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateString = $date->toDateString();
                $this->info("Запрашиваем данные за {$dateString}...");

                $stats = $wbApiService->getBehavioralStats($dateString);

                if (empty($stats)) {
                    $this->warn("Данные за {$dateString} отсутствуют или произошла ошибка API.");
                } else {
                    foreach ($stats as $stat) {
                        // ИСПОЛЬЗУЕМ ПОЛНЫЙ СПИСОК ПОЛЕЙ
                        BehavioralStat::updateOrCreate(
                            [
                                'store_id' => $store->id,
                                'report_date' => $dateString,
                                'nmID' => $stat['nmId'], // 'nmId' из API
                            ],
                            [
                                'openCardCount' => $stat['openCardCount'] ?? 0,
                                'addToCartCount' => $stat['addToCartCount'] ?? 0,
                                'ordersCount' => $stat['ordersCount'] ?? 0,
                                'ordersSumRub' => $stat['ordersSumRub'] ?? 0,
                                'buyoutsCount' => $stat['buyoutsCount'] ?? 0,
                                'buyoutsSumRub' => $stat['buyoutsSumRub'] ?? 0,
                                'cancelCount' => $stat['cancelCount'] ?? 0,
                                'cancelSumRub' => $stat['cancelSumRub'] ?? 0,
                                'avgPriceRub' => $stat['avgPriceRub'] ?? 0,
                                'stocksMp' => $stat['stocksMp'] ?? 0,
                                'stocksWb' => $stat['stocksWb'] ?? 0,
                            ]
                        );
                    }
                    $this->info("Данные за {$dateString} успешно сохранены.");
                }

                if (!$date->isSameDay($endDate)) {
                    $this->info("Пауза 30 секунд...");
                    sleep(30);
                }
            }
        }

        $this->info("\nГотово! Сбор данных завершен.");
        return 0;
    }
}
