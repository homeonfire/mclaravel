<?php

namespace App\Console\Commands;

use App\Models\SkuStock;
use Dakword\WBSeller\API;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IngestStocks extends Command
{
    protected $signature = 'wb:ingest-stocks';
    protected $description = 'Загружает актуальные остатки товаров со складов WB';

    public function handle()
    {
        $this->info("Начало загрузки остатков...");
        $stores = DB::table('stores')->get();

        foreach ($stores as $store) {
            $this->line("================================================");
            $this->info("Обработка магазина: '{$store->store_name}'");
            $api = new API(['masterkey' => $store->api_key]);

            try {
                // Запрашиваем остатки по всем складам сразу
                $stocks = $api->Statistics()->stocks(0); // 0 означает все склады

                if (empty($stocks)) {
                    $this->warn("API не вернул данные по остаткам. Пропускаем.");
                    continue;
                }

                $this->info("Получено " . count($stocks) . " записей об остатках. Обновление БД...");
                $updatedCount = 0;

                foreach ($stocks as $stockItem) {
                    if (isset($stockItem->sku)) { // sku - это barcode
                        // Обновляем только поле stock_wb в нашей таблице
                        $result = SkuStock::where('sku_barcode', $stockItem->sku)
                            ->update(['stock_wb' => $stockItem->quantity]);
                        if($result) $updatedCount++;
                    }
                }
                $this->info("Обновлено $updatedCount записей.");

            } catch (Exception $e) {
                $this->error("Ошибка при запросе остатков для магазина '{$store->store_name}': " . $e->getMessage());
            }
        }

        $this->info("\nЗагрузка остатков успешно завершена!");
        return 0;
    }
}
