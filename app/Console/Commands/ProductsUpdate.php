<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;

class ProductsUpdate extends Command
{
    protected $signature = 'wb:update-products';
    protected $description = 'Загружает и обновляет список товаров (включая главное изображение) для всех магазинов';

    public function handle()
    {
        $this->info("Начало обновления списка товаров...");

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

                $api = new API(['masterkey' => $store->api_key]);
                $contentApi = $api->Content();

                $totalProcessed = 0;
                $page = 1;
                $limit = 100;
                $cursorUpdatedAt = '';
                $cursorNmID = 0;

                do {
                    $this->line("Запрашиваем страницу #{$page}...");
                    $result = $contentApi->getCardsList('', $limit, $cursorUpdatedAt, $cursorNmID);

                    if (isset($result->error) && $result->error) {
                        $this->error("Ошибка API: " . $result->errorText);
                        continue 2;
                    }

                    $cards = $result->cards ?? [];
                    $countOnPage = count($cards);

                    if ($countOnPage === 0) {
                        $this->line("Больше нет товаров.");
                        break;
                    }

                    $this->comment("Получено {$countOnPage} товаров. Сохранение в БД...");

                    $productsData = [];
                    foreach ($cards as $card) {
                        // --- ИСПРАВЛЕННАЯ ЛОГИКА ЗДЕСЬ ---
                        // Берем полную ссылку напрямую из ответа API.
                        $mainImageUrl = null;
                        if (!empty($card->photos) && is_array($card->photos) && isset($card->photos[0]->big)) {
                            $mainImageUrl = $card->photos[0]->big;
                        }

                        $productsData[] = [
                            'store_id' => $store->id,
                            'nmID' => $card->nmID,
                            'imtID' => $card->imtID,
                            'nmUUID' => $card->nmUUID,
                            'subjectID' => $card->subjectID,
                            'subjectName' => $card->subjectName,
                            'vendorCode' => $card->vendorCode,
                            'brand' => $card->brand,
                            'title' => $card->title,
                            'main_image_url' => $mainImageUrl, // <-- Добавляем правильную ссылку
                        ];
                    }

                    DB::table('products')->upsert(
                        $productsData,
                        ['store_id', 'nmID'],
                        ['imtID', 'nmUUID', 'subjectID', 'subjectName', 'vendorCode', 'brand', 'title', 'main_image_url']
                    );

                    $totalProcessed += $countOnPage;
                    $cursorUpdatedAt = $result->cursor->updatedAt;
                    $cursorNmID = $result->cursor->nmID;
                    $page++;
                    sleep(1);

                } while ($countOnPage === $limit);

                $this->info("Синхронизация для '{$store->store_name}' завершена. Всего обработано: {$totalProcessed} товаров.");
            }

            $this->info("Все магазины успешно обработаны!");
            return 0;

        } catch (\Exception $e) {
            $this->error("Произошла критическая ошибка: " . $e->getMessage());
            return 1;
        }
    }
}
