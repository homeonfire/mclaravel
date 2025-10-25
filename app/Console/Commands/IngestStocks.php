<?php

namespace App\Console\Commands;

use App\Models\SkuWarehouseStock;
use Dakword\WBSeller\API;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException; // <-- Добавляем для отлова ошибки БД
use Illuminate\Support\Facades\DB;
use DateTime;
// Убираем Sku и Collection, они больше не нужны для этой команды
// use App\Models\Sku;
// use Illuminate\Support\Collection;

class IngestStocks extends Command
{
    protected $signature = 'wb:ingest-stocks';
    protected $description = 'Загружает остатки товаров по каждому складу WB, пропуская неизвестные баркоды';

    public function handle()
    {
        $this->info("Начало загрузки остатков по складам...");
        $stores = DB::table('stores')->get();

        foreach ($stores as $store) {
            $this->line("================================================");
            $this->info("Обработка магазина: '{$store->store_name}'");
            $api = new API(['masterkey' => $store->api_key]);

            try {
                $stocksApiResponse = $api->Statistics()->stocks(new DateTime());

                if (empty($stocksApiResponse)) {
                    $this->warn("API не вернул данные по остаткам. Пропускаем.");
                    continue;
                }

                $this->info("Получено " . count($stocksApiResponse) . " записей об остатках по складам. Построчная обработка...");

                $processedCount = 0; // Счетчик успешно обработанных
                $skippedCount = 0;   // Счетчик пропущенных из-за ошибки FK
                $invalidDataCount = 0; // Счетчик записей с неполными данными

                // Инициализируем прогресс-бар
                $progressBar = $this->output->createProgressBar(count($stocksApiResponse));
                $progressBar->start();

                foreach ($stocksApiResponse as $stockItem) {
                    // Проверяем наличие barcode и warehouseName
                    if (isset($stockItem->barcode) && isset($stockItem->warehouseName)) {
                        try {
                            // *** ИЗМЕНЕНИЕ: Используем updateOrCreate для каждой записи ***
                            SkuWarehouseStock::updateOrCreate(
                                [
                                    'sku_barcode' => $stockItem->barcode,
                                    'warehouse_name' => $stockItem->warehouseName // Используем имя склада как часть уникального ключа
                                ],
                                [
                                    'warehouse_id' => null, // ID склада у нас нет
                                    'quantity' => $stockItem->quantity ?? 0,
                                    'in_way_to_client' => $stockItem->inWayToClient ?? 0,
                                    'in_way_from_client' => $stockItem->inWayFromClient ?? 0,
                                    // updated_at обновится автоматически Eloquent'ом
                                ]
                            );
                            $processedCount++;

                        } catch (QueryException $e) {
                            // **ЛОВИМ ОШИБКУ ВНЕШНЕГО КЛЮЧА (код 1452)**
                            if ($e->errorInfo[1] == 1452) {
                                $skippedCount++;
                                // Выводим предупреждение только один раз для каждого пропущенного баркода, чтобы не засорять лог
                                static $warnedBarcodes = [];
                                if (!isset($warnedBarcodes[$stockItem->barcode])) {
                                    $this->line(''); // Перенос строки для читаемости
                                    $this->warn("  -> Баркод {$stockItem->barcode} не найден в таблице 'skus'. Записи по нему пропускаются.");
                                    $warnedBarcodes[$stockItem->barcode] = true;
                                }
                            } else {
                                // Если это другая ошибка БД, выводим ее
                                $this->line(''); // Перенос строки
                                $this->error("  -> Ошибка БД при обработке баркода {$stockItem->barcode}: " . $e->getMessage());
                            }
                        } catch (\Throwable $e) {
                            // Ловим другие возможные ошибки при обработке записи
                            $this->line(''); // Перенос строки
                            $this->error("  -> Непредвиденная ошибка при обработке баркода {$stockItem->barcode}: " . $e->getMessage());
                        }
                    } else {
                        $invalidDataCount++;
                    }
                    $progressBar->advance(); // Продвигаем прогресс-бар
                } // Конец foreach

                $progressBar->finish(); // Завершаем прогресс-бар

                $this->info("\nУспешно обработано: {$processedCount} записей.");
                if ($skippedCount > 0) {
                    $this->warn("Пропущено из-за отсутствия баркода в 'skus': {$skippedCount} записей.");
                    $this->warn("Рекомендуется запустить 'php artisan wb:sync-skus', чтобы обновить справочник SKU.");
                }
                if ($invalidDataCount > 0) {
                    $this->warn("Пропущено из-за неполных данных от API (отсутствует barcode или warehouseName): {$invalidDataCount} записей.");
                }


            } catch (\Throwable $e) { // Ловим общие ошибки API
                $this->error("\nОшибка при запросе остатков для магазина '{$store->store_name}': " . $e->getMessage());
                $this->error("Файл: " . $e->getFile() . " Строка: " . $e->getLine());
            }
        } // Конец foreach ($stores)

        $this->info("\nЗагрузка остатков по складам успешно завершена!");
        return 0;
    }
}
