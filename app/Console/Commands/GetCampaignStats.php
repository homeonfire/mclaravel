<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
use Dakword\WBSeller\Enum\AdvertType;
use Exception;
use DateTime;

class GetCampaignStats extends Command
{
    protected $signature = 'wb:get-campaign-stats {advertId : ID рекламной кампании для сбора статистики}';
    protected $description = 'Собирает всю статистику (общую и по ключам) для одной конкретной рекламной кампании за вчерашний день';

    public function handle()
    {
        $advertId = $this->argument('advertId');
        $this->info("Начало сбора статистики для РК #{$advertId}...");

        try {
            $store = DB::table('stores')->first();
            if (!$store) {
                $this->error("В таблице `stores` нет ни одного магазина.");
                return 1;
            }
            $this->line("Используем ключ для магазина: '{$store->store_name}'");

            $campaign = DB::table('ad_campaigns')
                ->where('store_id', $store->id)
                ->where('advertId', $advertId)
                ->first();

            if (!$campaign) {
                $this->warn("Кампания с ID {$advertId} не найдена в вашей локальной таблице `ad_campaigns`.");
                $this->line("Убедитесь, что команда `wb:update-campaigns` уже загрузила эту кампанию.");
                return 1;
            }
            $campaignType = $campaign->type;

            $api = new API(['masterkey' => $store->api_key]);
            $advApi = $api->Adv();

            $dateTo = new DateTime('yesterday');
            $dateFrom = new DateTime('yesterday');
            $reportDate = $dateTo->format('Y-m-d');

            // 1. ЗАПРОС И СОХРАНЕНИЕ ОБЩЕЙ СТАТИСТИКИ
            $this->comment("-> Запрос общей статистики...");
            $statsResponse = $advApi->statistic([['id' => (int)$advertId, 'dates' => [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]]]);

            if (isset($statsResponse[0]->days) && is_array($statsResponse[0]->days)) {
                $this->saveDailyStats($statsResponse[0]->days, $store->id, $advertId);
                $this->info("   - Общая статистика за вчера сохранена.");
            } else {
                $this->warn("   - Общая статистика не найдена.");
            }
            sleep(2); // Пауза

            // 2. ЗАПРОС И СОХРАНЕНИЕ СТАТИСТИКИ ПО КЛЮЧЕВЫМ СЛОВАМ
            $this->comment("-> Запрос статистики по ключевым словам...");
            $keywordsData = null;
            if ($campaignType == AdvertType::AUTO) {
                $keywordsResponse = $advApi->Auto()->advertStatisticByWords((int)$advertId);
                if (isset($keywordsResponse->words->keywords) && is_array($keywordsResponse->words->keywords)) {
                    $keywordsData = $keywordsResponse->words->keywords;
                }
            } elseif ($campaignType == AdvertType::ON_SEARCH_CATALOG || $campaignType == AdvertType::ON_SEARCH) {
                $keywordsResponse = $advApi->Auction()->advertStatisticByWords((int)$advertId);
                if (isset($keywordsResponse->words->keywords) && is_array($keywordsResponse->words->keywords)) {
                    $keywordsData = $keywordsResponse->words->keywords;
                }
            }

            if ($keywordsData) {
                $this->saveKeywordStats($keywordsData, $store->id, $advertId, $reportDate);
                $this->info("   - Статистика по " . count($keywordsData) . " ключевым словам сохранена.");
            } else {
                $this->line("   - Статистика по ключевым словам отсутствует.");
            }

            $this->info("\nСбор статистики для кампании #{$advertId} успешно завершен!");
            return 0;

        } catch (Exception $e) {
            $this->error("Произошла критическая ошибка: " . $e->getMessage());
            $this->error("Файл: " . $e->getFile() . " Строка: " . $e->getLine());
            return 1;
        }
    }

    private function saveDailyStats(array $dailyStats, int $storeId, int $advertId): void
    {
        foreach ($dailyStats as $dayStat) {
            DB::table('ad_campaign_daily_stats')->updateOrInsert(
                ['advertId' => $advertId, 'report_date' => (new DateTime($dayStat->date))->format('Y-m-d')],
                [
                    'store_id' => $storeId, 'views' => $dayStat->views ?? 0,
                    'clicks' => $dayStat->clicks ?? 0, 'ctr' => $dayStat->ctr ?? 0.00,
                    'cpc' => $dayStat->cpc ?? 0.00, 'sum' => $dayStat->sum ?? 0.00,
                    'atbs' => $dayStat->atbs ?? 0, 'orders' => $dayStat->orders ?? 0,
                    'cr' => $dayStat->cr ?? 0.00, 'shks' => $dayStat->shks ?? 0,
                    'sum_price' => $dayStat->sum_price ?? 0.00
                ]
            );
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
