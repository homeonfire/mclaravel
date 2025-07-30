<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
use Dakword\WBSeller\Enum\AdvertStatus;
use Exception;

class AdCampaignsUpdate extends Command
{
    protected $signature = 'wb:update-campaigns';
    protected $description = 'Загружает и обновляет РК и связанные с ними товары для всех магазинов';

    public function handle()
    {
        $this->info("Начало обновления рекламных кампаний...");

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

                $api = new API(['masterkey' => $store->api_key]);
                $advApi = $api->Adv();

                $statusesToFetch = [AdvertStatus::PLAY, AdvertStatus::PAUSE, AdvertStatus::READY, AdvertStatus::DONE];
                $allCampaigns = [];

                $this->comment("Запрос кампаний по разным статусам...");
                foreach ($statusesToFetch as $status) {
                    foreach ([8, 9] as $type) { // 8 - Авто, 9 - Поиск+Каталог
                        $this->line("-> Запрос статуса: {$status}, тип: {$type}");
                        try {
                            $campaigns = $advApi->advertsInfo($status, $type);
                            if (!empty($campaigns)) {
                                $allCampaigns = array_merge($allCampaigns, (array)$campaigns);
                            }
                        } catch (Exception $e) {
                            $this->error("Ошибка API при запросе статуса {$status}, тип {$type}: " . $e->getMessage());
                        }
                        sleep(1);
                    }
                }

                if (empty($allCampaigns)) {
                    $this->warn("Активных или недавних кампаний не найдено для магазина '{$store->store_name}'.");
                    continue;
                }

                $this->info("Всего получено " . count($allCampaigns) . " потенциальных записей. Сохранение в БД...");

                DB::transaction(function () use ($allCampaigns, $store) {
                    foreach ($allCampaigns as $campaign) {
                        // *** КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ ***
                        // Проверяем, что это валидный объект кампании, а не строка с ошибкой
                        if (!is_object($campaign) || !isset($campaign->advertId)) {
                            $this->warn("Пропущена невалидная запись в ответе API: " . json_encode($campaign));
                            continue; // Пропускаем эту итерацию и переходим к следующей
                        }

                        // 1. Сохраняем или обновляем основную информацию о кампании
                        DB::table('ad_campaigns')->upsert(
                            [[
                                'store_id' => $store->id, 'advertId' => $campaign->advertId,
                                'name' => $campaign->name, 'type' => $campaign->type, 'status' => $campaign->status,
                                'dailyBudget' => $campaign->dailyBudget, 'createTime' => $this->formatDate($campaign->createTime),
                                'changeTime' => $this->formatDate($campaign->changeTime), 'startTime' => $this->formatDate($campaign->startTime),
                                'endTime' => $this->formatDate($campaign->endTime), 'searchPluseState' => $campaign->searchPluseState ?? null,
                                'raw_data' => json_encode($campaign),
                            ]],
                            ['store_id', 'advertId'],
                            ['name', 'type', 'status', 'dailyBudget', 'changeTime', 'startTime', 'endTime', 'searchPluseState', 'raw_data']
                        );

                        // 2. Синхронизируем связанные товары (nmID)
                        $nmIds = $this->extractNmIds($campaign);

                        DB::table('ad_campaign_products')->where('store_id', $store->id)->where('advertId', $campaign->advertId)->delete();

                        if (!empty($nmIds)) {
                            $productLinks = [];
                            foreach ($nmIds as $nmId) {
                                $productExists = DB::table('products')->where('store_id', $store->id)->where('nmID', $nmId)->exists();
                                if ($productExists) {
                                    $productLinks[] = ['store_id' => $store->id, 'advertId' => $campaign->advertId, 'nmID' => $nmId];
                                } else {
                                    $this->warn("Предупреждение: Товар с nmID {$nmId} не найден в `products`. Связь не будет создана.");
                                }
                            }
                            if(!empty($productLinks)) {
                                DB::table('ad_campaign_products')->insert($productLinks);
                            }
                        }
                    }
                });

                $this->info("Синхронизация для магазина '{$store->store_name}' завершена.");
            }

            $this->info("Все магазины успешно обработаны!");
            return 0;

        } catch (Exception $e) {
            $this->error("Произошла критическая ошибка: " . $e->getMessage());
            $this->error("Файл: " . $e->getFile() . " Строка: " . $e->getLine());
            return 1;
        }
    }

    private function formatDate($dateString): ?string
    {
        if (empty($dateString)) return null;
        try { return (new \DateTime($dateString))->format('Y-m-d H:i:s'); }
        catch (Exception $e) { return null; }
    }

    private function extractNmIds(object $campaign): array
    {
        if (!empty($campaign->unitedParams[0]->nms)) {
            return $campaign->unitedParams[0]->nms;
        }
        return [];
    }
}
