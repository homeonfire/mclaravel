<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
use Exception;
use DateTime;

class SalesIngest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wb:ingest-sales {--start-date= : Дата начала в формате Y-m-d. По умолчанию - 7 дней назад.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Загружает данные о продажах, пропуская операции без соответствующего заказа в БД';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDateString = $this->option('start-date') ?? (new DateTime('-7 days'))->format('Y-m-d');

        $this->info("Начало загрузки продаж/возвратов с даты: {$startDateString}...");

        try {
            $stores = DB::table('stores')->get();
            if ($stores->isEmpty()) {
                $this->warn("В таблице `stores` нет ни одного магазина.");
                return 1;
            }

            $this->info("Найдено магазинов для обработки: " . count($stores));

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
     * Обрабатывает один магазин, загружая для него все продажи и возвраты.
     *
     * @param object $store
     * @param string $startDateString
     */
    private function processStore(object $store, string $startDateString): void
    {
        $api = new API(['masterkey' => $store->api_key]);
        $statisticsApi = $api->Statistics();

        $currentDate = new DateTime($startDateString);
        $endDate = new DateTime();

        while ($currentDate < $endDate) {
            $dateToProcess = $currentDate->format('Y-m-d');
            $this->comment(" -> Обрабатываем дату: " . $dateToProcess . "...");

            $fullReport = $statisticsApi->salesOnDate($currentDate);

            if (!is_array($fullReport) || empty($fullReport)) {
                $this->line("    - Нет данных за эту дату. Пропускаем.");
            } else {
                $this->line("    - Получено " . count($fullReport) . " записей. Начинаем проверку и пакетную загрузку...");

                $salesChunk = [];
                $chunkSize = 100;
                $totalInserted = 0;
                $skippedCount = 0;

                foreach ($fullReport as $item) {
                    // *** КЛЮЧЕВОЕ ИЗМЕНЕНИЕ ЗДЕСЬ ***
                    // Проверяем, существует ли связанный заказ.
                    $orderExists = DB::table('orders_raw')
                        ->where('srid', $item->srid)
                        ->where('store_id', $store->id)
                        ->exists();

                    // Если заказа нет - выводим предупреждение и пропускаем эту запись
                    if (!$orderExists) {
                        $this->warn("    - Не найден заказ для srid: {$item->srid}. Операция продажи/возврата пропущена.");
                        $skippedCount++;
                        continue;
                    }

                    // Если заказ найден, добавляем данные в пачку для вставки
                    $salesChunk[] = [
                        'store_id' => $store->id,
                        'gNumber' => $item->gNumber, 'date' => $item->date, 'lastChangeDate' => $item->lastChangeDate,
                        'supplierArticle' => $item->supplierArticle, 'techSize' => $item->techSize, 'barcode' => $item->barcode,
                        'totalPrice' => $item->totalPrice, 'discountPercent' => $item->discountPercent, 'isSupply' => (int)$item->isSupply,
                        'isRealization' => (int)$item->isRealization, 'promoCodeDiscount' => $item->promoCodeDiscount ?? null,
                        'warehouseName' => $item->warehouseName, 'countryName' => $item->countryName, 'oblastOkrugName' => $item->oblastOkrugName,
                        'regionName' => $item->regionName, 'incomeID' => $item->incomeID ?? null,
                        'saleID' => $item->saleID, 'odid' => $item->odid ?? null,
                        'spp' => $item->spp, 'forPay' => $item->forPay,
                        'finishedPrice' => $item->finishedPrice, 'priceWithDisc' => $item->priceWithDisc, 'nmId' => $item->nmId,
                        'subject' => $item->subject, 'category' => $item->category, 'brand' => $item->brand,
                        'IsStorno' => $item->IsStorno ?? null, 'sticker' => $item->sticker ?? null, 'srid' => $item->srid,
                        'order_status' => ($item->saleID[0] === 'S') ? 'sale' : 'refund'
                    ];

                    if (count($salesChunk) >= $chunkSize) {
                        DB::table('sales_raw')->upsert($salesChunk, ['saleID'], array_keys($salesChunk[0]));
                        $totalInserted += count($salesChunk);
                        $salesChunk = [];
                    }
                }

                if (!empty($salesChunk)) {
                    DB::table('sales_raw')->upsert($salesChunk, ['saleID'], array_keys($salesChunk[0]));
                    $totalInserted += count($salesChunk);
                }

                if ($totalInserted > 0) {
                    $this->info("    - Данные по " . $totalInserted . " операциям за " . $dateToProcess . " успешно загружены.");
                }
                if ($skippedCount > 0) {
                    $this->warn("    - Пропущено " . $skippedCount . " операций из-за отсутствия родительского заказа.");
                }
            }

            $currentDate->modify('+1 day');
            if ($currentDate < $endDate) {
                $this->comment("    - Пауза 60 секунд...");
                sleep(61);
            }
        }
    }
}
