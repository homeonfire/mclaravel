<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
use Dakword\WBSeller\Enum\AdvertStatus;
use Exception;

class FindCampaignById extends Command
{
    protected $signature = 'wb:find-campaign {advertId : ID рекламной кампании для поиска}';
    protected $description = 'Находит и выводит подробную информацию о РК, перебирая все кампании';

    public function handle()
    {
        $advertIdToFind = $this->argument('advertId');
        $this->info("Начинаем поиск рекламной кампании с ID: {$advertIdToFind}...");

        try {
            $store = DB::table('stores')->first();
            if (!$store) {
                $this->error('В таблице `stores` не найдено ни одного магазина.');
                return 1;
            }
            $this->line("Используем ключ для магазина: '{$store->store_name}'");

            $api = new API(['masterkey' => $store->api_key]);
            $advApi = $api->Adv();

            $statusesToFetch = [
                AdvertStatus::PLAY, AdvertStatus::PAUSE,
                AdvertStatus::READY, AdvertStatus::DONE
            ];

            $foundCampaign = null;

            $this->comment("Перебираем все кампании для поиска нужной...");
            $progressBar = $this->output->createProgressBar(count($statusesToFetch) * 2);
            $progressBar->start();

            foreach ($statusesToFetch as $status) {
                foreach ([8, 9] as $type) { // 8 - Авто, 9 - Поиск+Каталог
                    try {
                        $campaigns = $advApi->advertsInfo($status, $type);
                        if (!empty($campaigns)) {
                            foreach ((array)$campaigns as $campaign) {
                                if (is_object($campaign) && isset($campaign->advertId) && $campaign->advertId == $advertIdToFind) {
                                    $foundCampaign = $campaign;
                                    // Если нашли, выходим из всех циклов
                                    break 2;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $this->warn(" Предупреждение API (статус {$status}, тип {$type}): " . $e->getMessage());
                    }
                    $progressBar->advance();
                    sleep(1);
                }
            }
            $progressBar->finish();
            $this->newLine(2);

            if ($foundCampaign) {
                $this->info("Кампания с ID {$advertIdToFind} найдена!");
                echo json_encode($foundCampaign, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $this->newLine();
            } else {
                $this->warn("Кампания с ID {$advertIdToFind} не найдена среди активных, приостановленных или завершенных.");
            }

            return 0;

        } catch (Exception $e) {
            $this->error("Произошла критическая ошибка: " . $e->getMessage());
            return 1;
        }
    }
}
