<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Sku;
use App\Models\SkuStock;
use Dakword\WBSeller\API;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncSkus extends Command
{
    protected $signature = 'wb:sync-skus';
    protected $description = 'Синхронизирует справочник SKU (размеров и баркодов) из карточек товаров';

    public function handle()
    {
        $this->info("Начало синхронизации SKU...");
        $stores = DB::table('stores')->get();

        $totalNewSkus = 0;
        $totalUpdatedSkus = 0;

        foreach ($stores as $store) {
            $this->line("\n================================================");
            $this->info("Обработка магазина: '{$store->store_name}'");
            $api = new API(['masterkey' => $store->api_key]);

            $products = Product::where('store_id', $store->id)->orderBy('nmID', 'desc')->get();

            if ($products->isEmpty()) {
                $this->warn("Товары для магазина не найдены. Пропускаем.");
                continue;
            }

            $progressBar = $this->output->createProgressBar($products->count());
            $this->info("Найдено товаров для обработки: " . $products->count());
            $progressBar->start();

            foreach ($products as $product) {
                try {
                    $response = $api->Content()->getCardByNmID($product->nmID);

                    if (empty($response) || !isset($response->cards) || empty($response->cards)) {
                        usleep(700000);
                        $progressBar->advance();
                        continue;
                    }

                    $card = $response->cards[0];

                    if (isset($card->sizes) && is_array($card->sizes)) {
                        foreach ($card->sizes as $size) {
                            if (isset($size->skus) && is_array($size->skus) && !empty($size->skus)) {
                                foreach ($size->skus as $barcode) {
                                    $sku = Sku::updateOrCreate(
                                        ['barcode' => $barcode],
                                        [
                                            'product_nmID' => $product->nmID,
                                            'tech_size' => $size->techSize,
                                            'wb_size' => $size->wbSize ?? null,
                                        ]
                                    );

                                    if ($sku->wasRecentlyCreated) {
                                        $totalNewSkus++;
                                    } else {
                                        $totalUpdatedSkus++;
                                    }
                                    SkuStock::firstOrCreate(['sku_barcode' => $sku->barcode]);
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $this->line("\n<error>Ошибка при обработке товара {$product->nmID}: {$e->getMessage()}</error>");
                }

                $progressBar->advance();

                // *** ИЗМЕНЕНИЕ ЗДЕСЬ ***
                usleep(700000); // Пауза в 0.7 секунды
            }

            $progressBar->finish();
            $this->info("\nМагазин '{$store->store_name}' обработан.");
        }

        $this->info("\n================================================");
        $this->info("Синхронизация SKU успешно завершена!");
        $this->info("Всего создано новых SKU: $totalNewSkus");
        $this->info("Всего обновлено существующих SKU: $totalUpdatedSkus");
        return 0;
    }
}
