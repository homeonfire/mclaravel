<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
use Dakword\WBSeller\Enum\AdvertStatus;
use Dakword\WBSeller\Enum\AdvertType;
use Exception;
use DateTime;

class IngestCampaignStats extends Command
{
    protected $signature = 'wb:ingest-campaign-stats';
    protected $description = 'Собирает статистику для всех АКТИВНЫХ РК, обрабатывая их пачками';

    public function handle()
    {
        $this->info("Начало сбора статистики по активным рекламным кампаниям...");

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

                $this->comment("Получение списка активных кампаний...");
                $activeCampaigns = $this->getActiveCampaigns($advApi);

                if (empty($activeCampaigns)) {
                    $this->warn("Активных кампаний для магазина '{$store->store_name}' не найдено.");
                    continue;
                }
                $this->info("Найдено активных кампаний: " . count($activeCampaigns));

                // Разбиваем все кампании на пачки по 30 штук
                $campaignChunks = array_chunk($activeCampaigns, 30);
                $this->info("Всего будет обработано пачек: " . count($campaignChunks));

                $progressBar = $this->output->createProgressBar(count($activeCampaigns));
                $progressBar->start();

                foreach ($campaignChunks as $chunk) {
                    $this->line("\nОбработка пачки из " . count($chunk) . " кампаний...");

                    try {
                        // --- 1. МАССОВЫЙ ЗАПРОС ОБЩЕЙ СТАТИСТИКИ ---
                        $this->comment("   - Запрос общей статистики для пачки...");
                        $dateTo = new DateTime('yesterday');
                        $dateFrom = new DateTime('yesterday');
                        $payload = [];
                        foreach($chunk as $campaign) {
                            $payload[] = ['id' => (int)$campaign->advertId, 'dates' => [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]];
                        }
                        $statsResponse = $advApi->statistic($payload);

                        // Сохраняем общую статистику
                        if (!empty($statsResponse)) {
                            $this->saveDailyStats($statsResponse, $store->id);
                            $this->info("     - Общая статистика сохранена.");
                        }

                        // --- 2. ПОСЛЕДОВАТЕЛЬНЫЕ ЗАПРОСЫ ПО КЛЮЧЕВЫМ СЛОВАМ (API не поддерживает пачки) ---
                        foreach($chunk as $campaign) {
                            $this->comment("   - Запрос статистики по ключам для РК #{$campaign->advertId}...");
                            $keywordsResponse = $advApi->Auction()->advertStatisticByWords((int)$campaign->advertId);
                            if (isset($keywordsResponse->words->keywords) && is_array($keywordsResponse->words->keywords)) {
                                $this->saveKeywordStats($keywordsResponse->words->keywords, $store->id, $campaign->advertId, $dateTo->format('Y-m-d'));
                            }
                            $progressBar->advance();
                            sleep(2); // Небольшая пауза между запросами по ключам
                        }

                    } catch (Exception $e) {
                        $this->error("   - Ошибка при обработке пачки: " . $e->getMessage());
                        $progressBar->advance(count($chunk)); // Пропускаем всю пачку в прогресс-баре
                    }

                    $this->comment("   - Пауза 60 секунд перед следующей пачкой...");
                    sleep(60);
                }
                $progressBar->finish();
                $this->info("\nОбработка магазина '{$store->store_name}' завершена.");
            }
            $this->info("\nВсе магазины успешно обработаны!");
            return 0;

        } catch (Exception $e) {
            $this->error("Произошла критическая ошибка: " . $e->getMessage());
            return 1;
        }
    }

    private function getActiveCampaigns(API\Endpoint\Adv $advApi): array
    {
        $activeCampaigns = [];
        foreach ([AdvertType::AUTO, AdvertType::ON_SEARCH_CATALOG] as $type) {
            $campaigns = $advApi->advertsInfo(AdvertStatus::PLAY, $type);
            if (!empty($campaigns)) {
                $activeCampaigns = array_merge($activeCampaigns, array_filter((array)$campaigns, 'is_object'));
            }
            sleep(2);
        }
        return $activeCampaigns;
    }

    private function saveDailyStats(array $statsResponse, int $storeId): void
    {
        foreach ($statsResponse as $campaignStat) {
            if (isset($campaignStat->days) && is_array($campaignStat->days)) {
                foreach ($campaignStat->days as $dayStat) {
                    DB::table('ad_campaign_daily_stats')->updateOrInsert(
                        ['advertId' => $campaignStat->advertId, 'report_date' => (new DateTime($dayStat->date))->format('Y-m-d')],
                        ['store_id' => $storeId, 'views' => $dayStat->views ?? 0, 'clicks' => $dayStat->clicks ?? 0, 'ctr' => $dayStat->ctr ?? 0.00, 'cpc' => $dayStat->cpc ?? 0.00, 'sum' => $dayStat->sum ?? 0.00, 'atbs' => $dayStat->atbs ?? 0, 'orders' => $dayStat->orders ?? 0, 'cr' => $dayStat->cr ?? 0.00, 'shks' => $dayStat->shks ?? 0, 'sum_price' => $dayStat->sum_price ?? 0.00]
                    );
                }
            }
        }
    }

    private function saveKeywordStats(array $keywords, int $storeId, int $advertId, string $reportDate): void
    {
        DB::transaction(function() use ($keywords, $storeId, $advertId, $reportDate) {
            DB::table('ad_campaign_keyword_stats')->where('advertId', $advertId)->where('report_date', $reportDate)->delete();
            $dataToInsert = [];
            foreach ($keywords as $keywordStat) {
                $dataToInsert[] = [
                    'store_id' => $storeId, 'advertId' => $advertId, 'report_date' => $reportDate,
                    'keyword' => $keywordStat->keyword, 'views' => $keywordStat->count,
                ];
            }
            if (!empty($dataToInsert)) {
                DB::table('ad_campaign_keyword_stats')->insert($dataToInsert);
            }
        });
    }
}
