<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Dakword\WBSeller\API;
use Dakword\WBSeller\Enum\AdvertStatus;
use Dakword\WBSeller\Enum\AdvertType;

class GetActiveCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wb:get-active-campaigns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Получает список активных РК для первого магазина и выводит результат в формате JSON';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // 1. Получаем первый магазин из базы данных
            $store = DB::table('stores')->first();

            if (!$store) {
                $this->error(json_encode(['error' => 'В таблице `stores` не найдено ни одного магазина.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return 1;
            }

            // 2. Инициализируем API с ключом из базы
            $wbSellerAPI = new API(['masterkey' => $store->api_key]);
            $advApi = $wbSellerAPI->Adv();

            // 3. Определяем статус "активна" (Идут показы)
            $activeStatus = AdvertStatus::PLAY;

            // 4. Получаем все возможные типы рекламных кампаний
            $campaignTypes = AdvertType::all();

            $allActiveCampaigns = [];

            // 5. Проходим в цикле по каждому типу и запрашиваем активные кампании
            foreach ($campaignTypes as $typeId) {
                $campaigns = $advApi->advertsInfo($activeStatus, $typeId);
                if (!empty($campaigns)) {
                    // Преобразуем объект в массив для единообразия
                    $allActiveCampaigns = array_merge($allActiveCampaigns, (array)$campaigns);
                }
                sleep(1); // Небольшая пауза между запросами
            }

            // 6. Выводим итоговый результат в формате JSON
            echo json_encode($allActiveCampaigns, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            return 0; // Успешное завершение

        } catch (\Exception $e) {
            $this->error(json_encode([
                'error' => 'Произошла критическая ошибка',
                'message' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 1;
        }
    }
}
