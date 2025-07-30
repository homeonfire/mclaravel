<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdCampaignController extends Controller
{
    public function index(Request $request)
    {
        $stores = Store::orderBy('store_name')->get();
        $selectedStoreId = $request->input('store_id', $stores->first()->id ?? null);

        $query = AdCampaign::query()
            ->where('status', 9); // <-- Фильтр по активным кампаниям

        if ($selectedStoreId) {
            $query->where('store_id', $selectedStoreId);
        } else {
            $query->where('store_id', -1); // Не показывать ничего, если магазин не выбран
        }

        $campaigns = $query->orderBy('createTime', 'desc')->paginate(30);

        return view('advertising.index', [
            'campaigns' => $campaigns,
            'stores' => $stores,
            'selectedStoreId' => $selectedStoreId,
        ]);
    }

    public function show(Request $request, AdCampaign $campaign)
    {
        // Подгружаем продукты и дневную статистику (без изменений)
        $campaign->load(['products', 'dailyStats']);

        // --- НОВАЯ ЛОГИКА ДЛЯ КЛЮЧЕВЫХ СЛОВ ---
        // 1. Находим 5 самых популярных ключевых слов по сумме показов за все время
        $topKeywordStrings = DB::table('ad_campaign_keyword_stats')
            ->where('advertId', $campaign->advertId)
            ->groupBy('keyword')
            ->orderByRaw('SUM(views) DESC')
            ->limit(5)
            ->pluck('keyword'); // Получаем массив из названий ключей

        // 2. Получаем всю дневную статистику для этих 5 ключей
        $topKeywordsStats = [];
        if ($topKeywordStrings->isNotEmpty()) {
            $topKeywordsStats = DB::table('ad_campaign_keyword_stats')
                ->where('advertId', $campaign->advertId)
                ->whereIn('keyword', $topKeywordStrings)
                ->orderBy('report_date', 'desc') // Сортируем по дате, чтобы свежие были вверху
                ->get();
        }
        // ------------------------------------------

        // Готовим данные для графика (без изменений)
        $dailyStatsForChart = $campaign->dailyStats()->latest('report_date')->limit(30)->get()->reverse();
        $chartData = [
            'labels' => $dailyStatsForChart->pluck('report_date')->map(fn($date) => \Carbon\Carbon::parse($date)->format('d.m')),
            'datasets' => [
                ['label' => 'Показы', 'data' => $dailyStatsForChart->pluck('views')->values(), 'borderColor' => '#3b82f6'],
                ['label' => 'Клики', 'data' => $dailyStatsForChart->pluck('clicks')->values(), 'borderColor' => '#22c55e'],
                ['label' => 'Расходы (руб)', 'data' => $dailyStatsForChart->pluck('sum')->values(), 'borderColor' => '#ef4444'],
            ]
        ];

        return view('advertising.show', [
            'campaign' => $campaign,
            'chartData' => json_encode($chartData),
            'topKeywordsStats' => $topKeywordsStats // <-- Передаем новую переменную
        ]);
    }
}
