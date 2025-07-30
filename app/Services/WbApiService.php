<?php

namespace App\Services;

use Dakword\WBSeller\API;
use Dakword\WbSeller\;
use Illuminate\Support\Facades\Log; // Добавляем для логирования ошибок

class WbApiService
{
    protected Api $api;

    /**
     * Теперь конструктор принимает API-ключ.
     */
    public function __construct(string $apiKey)
    {
        if (empty($apiKey)) {
            throw new \Exception("Передан пустой API ключ.");
        }

        $client = new Client(['token' => $apiKey]);
        $this->api = new Api($client);
    }

    /**
     * НОВЫЙ МЕТОД: Получает поведенческую статистику (воронку продаж) за конкретную дату.
     *
     * @param string $date 'Y-m-d'
     * @return array
     */
    public function getBehavioralStats(string $date): array
    {
        try {
            // Библиотека ожидает дату в формате 'Y-m-d'
            // Метод getSupplierAnalyticsDetailByPeriod обычно возвращает данные по дням
            return $this->api->getSupplierAnalyticsDetailByPeriod($date, $date);
        } catch (\Exception $e) {
            // Записываем ошибку в лог, чтобы можно было ее отследить
            Log::error('Ошибка API Wildberries: ' . $e->getMessage());
            return []; // Возвращаем пустой массив в случае ошибки
        }
    }
}
