<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
use Dakword\WBSeller\Enum\AdvertStatus;
use Dakword\WBSeller\Enum\AdvertType;
use Exception;
use DateTime;

class SyncCampaigns extends Command
{
    protected $signature = 'wb:sync-campaigns';
    protected $description = '1. Синхронизирует список РК и их связи с товарами';

    public function handle()
    {
        $this->info("Начало синхронизации рекламных кампаний...");

        try {
            $stores = DB::table('stores')->get();
            if ($stores->isEmpty()) {
                $this->warn("В таблице `stores` нет ни одного магазина.");
                return 1;
            }

            foreach ($stores as $store) {
                $this->line("================================================");
                $this->info("Обработка магазина: '{$store->store_name}' (ID: {$store->id})");

                $api = new API(['masterkey' => $store->api_key]);
                $advApi = $api->Adv();

                // Получаем все кампании в основных статусах
                $statusesToFetch = [AdvertStatus::PLAY, AdvertStatus::PAUSE, AdvertStatus::READY, AdvertStatus::DONE];
                $allCampaigns = $this->fetchAllCampaigns($advApi, $statusesToFetch);

                if (empty($allCampaigns)) {
                    $this->warn("Не найдено кампаний для магазина '{$store->store_name}'.");
                    continue;
                }

                $this->info("Всего получено " . count($allCampaigns) . " кампаний. Сохранение в БД...");

                // Сохраняем все в одной транзакции
                DB::transaction(function () use ($allCampaigns, $store) {
                    foreach ($allCampaigns as $campaign) {
                        if (!is_object($campaign) || !isset($campaign->advertId)) {
                            $this->warn("Пропущена невалидная запись: " . json_encode($campaign));
                            continue;
                        }

                        // 1. Сохраняем/обновляем основную информацию
                        DB::table('ad_campaigns')->upsert(
                            [['store_id' => $store->id, 'advertId' => $campaign->advertId, 'name' => $campaign->name, 'type' => $campaign->type, 'status' => $campaign->status, 'dailyBudget' => $campaign->dailyBudget, 'createTime' => $this->formatDate($campaign->createTime), 'changeTime' => $this->formatDate($campaign->changeTime), 'startTime' => $this->formatDate($campaign->startTime), 'endTime' => $this->formatDate($campaign->endTime), 'searchPluseState' => $campaign->searchPluseState ?? null, 'raw_data' => json_encode($campaign)]],
                            ['store_id', 'advertId'],
                            ['name', 'type', 'status', 'dailyBudget', 'changeTime', 'startTime', 'endTime', 'searchPluseState', 'raw_data']
                        );

                        // 2. Синхронизируем связанные товары
                        $nmIds = $this->extractNmIds($campaign);
                        DB::table('ad_campaign_products')->where('store_id', $store->id)->where('advertId', $campaign->advertId)->delete();
                        if (!empty($nmIds)) {
                            $productLinks = [];
                            foreach ($nmIds as $nmId) {
                                $productExists = DB::table('products')->where('store_id', $store->id)->where('nmID', $nmId)->exists();
                                if ($productExists) {
                                    $productLinks[] = ['store_id' => $store->id, 'advertId' => $campaign->advertId, 'nmID' => $nmId];
                                } else {
                                    $this->warn("Предупреждение: Товар nmID {$nmId} для РК #{$campaign->advertId} не найден в `products`. Связь не будет создана.");
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
            $this->info("\nВсе магазины успешно обработаны!");
            return 0;
        } catch (Exception $e) {
            $this->error("Произошла критическая ошибка: " . $e->getMessage());
            return 1;
        }
    }

    private function fetchAllCampaigns(API\Endpoint\Adv $advApi, array $statuses): array
    {
        $allCampaigns = [];
        foreach ($statuses as $status) {
            foreach ([AdvertType::AUTO, AdvertType::ON_SEARCH_CATALOG] as $type) {
                try {
                    $campaigns = $advApi->advertsInfo($status, $type);
                    if (!empty($campaigns)) {
                        $allCampaigns = array_merge($allCampaigns, array_filter((array)$campaigns, 'is_object'));
                    }
                } catch (Exception $e) {
                    $this->error("Ошибка API при запросе (статус {$status}, тип {$type}): " . $e->getMessage());
                }
                sleep(2);
            }
        }
        return $allCampaigns;
    }

    private function formatDate($dateString): ?string
    {
        if (empty($dateString)) return null;
        try { return (new DateTime($dateString))->format('Y-m-d H:i:s'); }
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
