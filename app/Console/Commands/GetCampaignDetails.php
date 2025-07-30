<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
use Dakword\WBSeller\Enum\AdvertType;
use Exception;
use DateTime;

class GetCampaignDetails extends Command
{
    protected $signature = 'wb:get-campaign-details {advertId : ID рекламной кампании для получения деталей}';
    protected $description = 'Получает полную информацию и всю статистику эффективности для конкретной РК';

    public function handle()
    {
        $advertId = $this->argument('advertId');
        $this->info("Запрашиваем полную информацию для рекламной кампании с ID: {$advertId}...");

        try {
            $store = DB::table('stores')->first();
            if (!$store) {
                $this->error('В таблице `stores` не найдено ни одного магазина.');
                return 1;
            }
            $this->line("Используем ключ для магазина: '{$store->store_name}'");

            $wbSellerAPI = new API(['masterkey' => $store->api_key]);
            $advApi = $wbSellerAPI->Adv();

            $campaignDetails = DB::table('ad_campaigns')
                ->where('store_id', $store->id)
                ->where('advertId', $advertId)
                ->first();

            if (!$campaignDetails) {
                $this->warn("Кампания с ID {$advertId} не найдена в вашей локальной таблице `ad_campaigns`.");
                return 1;
            }
            $campaignType = $campaignDetails->type;
            $this->info("Тип кампании: " . $this->getCampaignTypeName($campaignType) . " (из локальной БД)");

            $fullStatistics = [];
            $dateTo = new DateTime();
            $dateFrom = (new DateTime())->modify('-7 days');

            // --- КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ ЗДЕСЬ ---
            // 1. Общая статистика (отправляем массив объектов)
            $this->comment("Получение общей статистики...");
            try {
                // API ожидает массив объектов, где каждый объект - это id и dates
                $fullStatistics['overall_stats'] = $advApi->statistic([[
                    'id' => (int)$advertId,
                    'dates' => [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]
                ]]);
            } catch (Exception $e) {
                $fullStatistics['overall_stats_error'] = $e->getMessage();
            }
            sleep(1);

            // 2. Статистика по ключевым словам
            $this->comment("Получение статистики по ключевым словам...");
            try {
                $fullStatistics['keyword_stats'] = $advApi->advertStatisticByKeywords((int)$advertId, $dateFrom, $dateTo);
            } catch (Exception $e) {
                $fullStatistics['keyword_stats_error'] = $e->getMessage();
            }
            sleep(1);

            // 3. Специфичная статистика
            if ($campaignType == AdvertType::AUTO) {
                $this->comment("Получение статистики по кластерам для Авто-кампании...");
                try {
                    $fullStatistics['auto_clusters_stats'] = $advApi->Auto()->advertStatisticByWords((int)$advertId);
                } catch (Exception $e) {
                    $fullStatistics['auto_clusters_stats_error'] = $e->getMessage();
                }
            } elseif ($campaignType == AdvertType::ON_SEARCH_CATALOG || $campaignType == AdvertType::ON_SEARCH) {
                $this->comment("Получение статистики по фразам для Поисковой кампании...");
                try {
                    $fullStatistics['search_words_stats'] = $advApi->Auction()->advertStatisticByWords((int)$advertId);
                } catch (Exception $e) {
                    $fullStatistics['search_words_stats_error'] = $e->getMessage();
                }
            }

            $finalReport = [
                'campaign_details' => json_decode($campaignDetails->raw_data),
                'performance_statistics' => $fullStatistics
            ];

            $this->info("Все данные успешно получены:");
            echo json_encode($finalReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $this->newLine();

            return 0;

        } catch (Exception $e) {
            $this->error("Произошла критическая ошибка: " . $e->getMessage());
            $this->error("Файл: " . $e->getFile() . " Строка: " . $e->getLine());
            return 1;
        }
    }

    private function getCampaignTypeName(int $typeId): string
    {
        $types = [
            AdvertType::ON_CATALOG => "В каталоге", AdvertType::ON_CARD => "В карточке товара",
            AdvertType::ON_SEARCH => "В поиске", AdvertType::ON_HOME_RECOM => "В рекомендациях",
            AdvertType::AUTO => "Автоматическая", AdvertType::ON_SEARCH_CATALOG => "Поиск + Каталог",
        ];
        return $types[$typeId] ?? 'Неизвестный тип';
    }
}
