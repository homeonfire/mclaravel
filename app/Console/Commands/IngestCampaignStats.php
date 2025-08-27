<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
use Dakword\WBSeller\Enum\AdvertStatus;
use Exception;
use DateTime;

class IngestCampaignStats extends Command
{
    protected $signature = 'wb:ingest-campaign-stats {--date= : Дата для сбора статистики в формате Y-m-d. По умолчанию - вчера.}';
    protected $description = '2. Собирает ежедневную статистику для АКТИВНЫХ РК, обрабатывая их пачками';

    public function handle()
    {
        $dateString = $this->option('date') ?? (new DateTime('yesterday'))->format('Y-m-d');
        $this->info("Начало сбора статистики за {$dateString}...");

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

                // Получаем активные кампании из НАШЕЙ БАЗЫ ДАННЫХ
                $activeCampaigns = DB::table('ad_campaigns')
                    ->where('store_id', $store->id)
                    ->where('status', AdvertStatus::PLAY)
                    ->get();

                if ($activeCampaigns->isEmpty()) {
                    $this->warn("Активных кампаний для магазина '{$store->store_name}' не найдено в локальной БД.");
                    continue;
                }
                $this->info("Найдено активных кампаний: " . $activeCampaigns->count());

                $campaignChunks = $activeCampaigns->chunk(30);
                $this->info("Всего будет обработано пачек: " . $campaignChunks->count());

                $progressBar = $this->output->createProgressBar($activeCampaigns->count());
                $progressBar->start();

                foreach ($campaignChunks as $chunk) {
                    $this->line("\nОбработка пачки из " . $chunk->count() . " кампаний...");

                    try {
                        // Массовый запрос общей статистики
                        $this->comment("   - Запрос общей статистики для пачки...");
                        $payload = $chunk->map(fn($c) => ['id' => $c->advertId, 'dates' => [$dateString, $dateString]])->all();
                        $statsResponse = $advApi->statistic($payload);
                        if (!empty($statsResponse)) {
                            $this->saveDailyStats($statsResponse, $store->id);
                        }

                        // Последовательные запросы по ключевым словам
                        foreach($chunk as $campaign) {
                            $this->comment("   - Запрос статистики по ключам для РК #{$campaign->advertId}...");
                            $keywordsResponse = $advApi->Auction()->advertStatisticByWords((int)$campaign->advertId);
                            if (isset($keywordsResponse->words->keywords) && is_array($keywordsResponse->words->keywords)) {
                                $this->saveKeywordStats($keywordsResponse->words->keywords, $store->id, $campaign->advertId, $dateString);
                            }
                            $progressBar->advance();
                            sleep(2);
                        }

                    } catch (Exception $e) {
                        $this->error("\nОшибка при обработке пачки: " . $e->getMessage());
                        $progressBar->advance($chunk->count());
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
                $dataToInsert[] = ['store_id' => $storeId, 'advertId' => $advertId, 'report_date' => $reportDate, 'keyword' => $keywordStat->keyword, 'views' => $keywordStat->count];
            }
            if (!empty($dataToInsert)) {
                DB::table('ad_campaign_keyword_stats')->insert($dataToInsert);
            }
        });
    }
}
