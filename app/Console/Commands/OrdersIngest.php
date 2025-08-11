<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
use Exception;
use DateTime;

class OrdersIngest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wb:ingest-orders {--start-date= : Дата начала в формате Y-m-d. По умолчанию - 7 дней назад.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Загружает данные о заказах пачками по 100, пропуская те, для которых нет товара в БД';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDateString = $this->option('start-date') ?? (new DateTime('-7 days'))->format('Y-m-d');

        $this->info("Начало загрузки заказов с даты: {$startDateString}...");

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

        } catch (Exception $e) {
            $this->error("Произошла критическая ошибка: " . $e->getMessage());
            $this->error("Файл: " . $e->getFile() . " Строка: " . $e->getLine());
            return 1;
        }
    }

    /**
     * Обрабатывает один магазин, загружая для него все заказы.
     *
     * @param object $store
     * @param string $startDateString
     */
    private function processStore(object $store, string $startDateString): void
    {
        $api = new API(['masterkey' => $store->api_key]);
        $statisticsApi = $api->Statistics();

        $knownNmIDs = DB::table('products')->where('store_id', $store->id)->pluck('nmID')->all();

        $currentDate = new DateTime($startDateString);
        $endDate = new DateTime();

        while ($currentDate < $endDate) {
            $dateToProcess = $currentDate->format('Y-m-d');
            $this->comment(" -> Обрабатываем дату: " . $dateToProcess . "...");

            $fullReport = $statisticsApi->ordersOnDate($currentDate);

            if (!is_array($fullReport) || empty($fullReport)) {
                $this->line("    - Нет данных за эту дату. Пропускаем.");
            } else {
                $this->line("    - Получено " . count($fullReport) . " записей. Начинаем проверку и пакетную загрузку...");

                $ordersChunk = []; // Массив для накопления пачки
                $chunkSize = 100; // Размер пачки
                $totalInserted = 0;

                foreach ($fullReport as $item) {
                    // Проверяем, есть ли nmID заказа в нашем списке известных товаров
                    if (!in_array($item->nmId, $knownNmIDs)) {
                        $this->warn("    - Товар с nmID: {$item->nmId} не найден в `products`. Заказ пропущен.");
                        continue;
                    }

                    // Если товар найден, добавляем его в пачку
                    $ordersChunk[] = [
                        'store_id' => $store->id, 'gNumber' => $item->gNumber, 'date' => $item->date,
                        'lastChangeDate' => $item->lastChangeDate, 'supplierArticle' => $item->supplierArticle,
                        'techSize' => $item->techSize, 'barcode' => $item->barcode, 'totalPrice' => $item->totalPrice,
                        'discountPercent' => $item->discountPercent, 'warehouseName' => $item->warehouseName,
                        'oblast' => $item->oblast ?? null, 'incomeID' => $item->incomeID ?? null,
                        'odid' => $item->odid ?? null, 'nmId' => $item->nmId, 'subject' => $item->subject,
                        'category' => $item->category, 'brand' => $item->brand, 'isCancel' => (int)$item->isCancel,
                        'cancel_dt' => $item->cancel_dt ?? null, 'sticker' => $item->sticker ?? null, 'srid' => $item->srid
                    ];

                    // *** КЛЮЧЕВОЕ ИЗМЕНЕНИЕ ЗДЕСЬ ***
                    // Если пачка накопилась, вставляем ее в БД и очищаем
                    if (count($ordersChunk) >= $chunkSize) {
                        DB::table('orders_raw')->upsert($ordersChunk, ['srid'], ['lastChangeDate', 'isCancel', 'cancel_dt']);
                        $totalInserted += count($ordersChunk);
                        $ordersChunk = []; // Очищаем пачку
                    }
                }

                // Вставляем оставшуюся часть пачки, если она не пустая
                if (!empty($ordersChunk)) {
                    DB::table('orders_raw')->upsert($ordersChunk, ['srid'], ['lastChangeDate', 'isCancel', 'cancel_dt']);
                    $totalInserted += count($ordersChunk);
                }

                if ($totalInserted > 0) {
                    $this->info("    - Данные по " . $totalInserted . " заказам за " . $dateToProcess . " успешно загружены.");
                } else {
                    $this->line("    - Нет заказов для известных товаров за эту дату.");
                }
            }

            $currentDate->modify('+1 day');
            if ($currentDate < $endDate) {
                $this->comment("    - Пауза 40 секунд...");
                sleep(40);
            }
        }
    }
}
