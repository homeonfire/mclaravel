<?php

namespace App\Console\Commands;

use App\Models\SkuStock;
use Dakword\WBSeller\API;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use DateTime; // <-- Добавляем для использования DateTime

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
                // *** ИЗМЕНЕНИЕ ЗДЕСЬ: Передаем текущую дату, а не 0 ***
                $stocks = $api->Statistics()->stocks(new DateTime());

                if (empty($stocks)) {
                    $this->warn("API не вернул данные по остаткам. Пропускаем.");
                    continue;
                }

                $this->info("Получено " . count($stocks) . " записей об остатках. Обновление БД...");
                $updatedCount = 0;

                foreach ($stocks as $stockItem) {
                    // В этом ответе API штрихкод приходит в поле 'barcode'
                    if (isset($stockItem->barcode)) {
                        // Обновляем только поле stock_wb в нашей таблице
                        $result = SkuStock::where('sku_barcode', $stockItem->barcode)
                            ->update(['stock_wb' => $stockItem->quantity]);
                        if($result) $updatedCount++;
                    }
                }
                $this->info("Обновлено $updatedCount записей.");

            } catch (\Throwable $e) {
                $this->error("Ошибка при запросе остатков для магазина '{$store->store_name}': " . $e->getMessage());
            }
        }

        $this->info("\nЗагрузка остатков успешно завершена!");
        return 0;
    }
}
