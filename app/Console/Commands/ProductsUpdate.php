<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
// Убираем Exception, так как ловим Throwable
use Throwable; // <-- Добавляем Throwable

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
                $storeErrorOccurred = false; // Флаг ошибки для текущего магазина

                do {
                    $this->line("Запрашиваем страницу #{$page}...");

                    // *** НАЧАЛО ИЗМЕНЕНИЙ: Оборачиваем вызов API в try...catch ***
                    try {
                        $result = $contentApi->getCardsList('', $limit, $cursorUpdatedAt, $cursorNmID);

                        // Проверяем на стандартную ошибку API (если вернулся объект, но с полем error)
                        if (isset($result->error) && $result->error) {
                            $this->error("Ошибка API WB: " . ($result->errorText ?? 'Неизвестная ошибка WB'));
                            $storeErrorOccurred = true; // Ставим флаг ошибки
                            break; // Прерываем do...while для ЭТОГО магазина
                        }

                        // Проверяем, что ответ действительно содержит ожидаемые данные
                        if (!isset($result->cards) || !isset($result->cursor)) {
                            $this->error("Ошибка: API вернул некорректный формат ответа (отсутствуют cards или cursor).");
                            // Выведем, что пришло, для отладки
                            $this->line("Ответ API: " . json_encode($result));
                            $storeErrorOccurred = true;
                            break; // Прерываем do...while
                        }

                    } catch (Throwable $e) {
                        // Ловим КРИТИЧЕСКИЕ ошибки (включая TypeError 'string returned')
                        $this->error("Критическая ошибка при запросе к API: " . $e->getMessage());
                        $this->error("Магазин '{$store->store_name}' будет пропущен.");
                        $storeErrorOccurred = true; // Ставим флаг ошибки
                        break; // Прерываем do...while для ЭТОГО магазина
                    }
                    // *** КОНЕЦ ИЗМЕНЕНИЙ ***


                    $cards = $result->cards; // Теперь мы уверены, что $result->cards существует
                    $countOnPage = count($cards);

                    if ($countOnPage === 0) {
                        $this->line("Больше нет товаров.");
                        break;
                    }

                    $this->comment("Получено {$countOnPage} товаров. Сохранение в БД...");

                    $productsData = [];
                    foreach ($cards as $card) {
                        $mainImageUrl = null;
                        if (!empty($card->photos) && is_array($card->photos) && isset($card->photos[0]->big)) {
                            $mainImageUrl = $card->photos[0]->big;
                        }

                        $productsData[] = [
                            'store_id' => $store->id,
                            'nmID' => $card->nmID,
                            'imtID' => $card->imtID ?? null, // Добавляем ?? null на всякий случай
                            'nmUUID' => $card->nmUUID ?? null,
                            'subjectID' => $card->subjectID ?? null,
                            'subjectName' => $card->subjectName ?? null,
                            'vendorCode' => $card->vendorCode,
                            'brand' => $card->brand ?? null,
                            'title' => $card->title ?? null,
                            'main_image_url' => $mainImageUrl,
                        ];
                    }

                    // Обернем и запись в БД в try-catch на случай проблем с базой
                    try {
                        DB::table('products')->upsert(
                            $productsData,
                            ['store_id', 'nmID'],
                            ['imtID', 'nmUUID', 'subjectID', 'subjectName', 'vendorCode', 'brand', 'title', 'main_image_url', 'updated_at' => now()] // Добавим updated_at
                        );
                    } catch (Throwable $dbError) {
                        $this->error("Ошибка при записи в БД: " . $dbError->getMessage());
                        $storeErrorOccurred = true;
                        break; // Прерываем do...while
                    }

                    $totalProcessed += $countOnPage;
                    $cursorUpdatedAt = $result->cursor->updatedAt;
                    $cursorNmID = $result->cursor->nmID;
                    $page++;
                    usleep(700000); // Пауза 0.7 сек для соблюдения лимитов

                } while ($countOnPage === $limit && !$storeErrorOccurred); // Добавляем проверку флага ошибки

                if (!$storeErrorOccurred) {
                    $this->info("Синхронизация для '{$store->store_name}' завершена. Всего обработано: {$totalProcessed} товаров.");
                } else {
                    $this->warn("Обработка магазина '{$store->store_name}' прервана из-за ошибки.");
                }
            } // Конец foreach ($stores)

            $this->info("\nВсе магазины успешно обработаны (или пропущены из-за ошибок)!");
            return 0;

            // Ловим только самые общие ошибки на верхнем уровне
        } catch (Throwable $e) {
            $this->error("Произошла НЕПРЕДВИДЕННАЯ критическая ошибка: " . $e->getMessage());
            $this->error("Файл: " . $e->getFile() . " Строка: " . $e->getLine());
            return 1;
        }
    }
}
