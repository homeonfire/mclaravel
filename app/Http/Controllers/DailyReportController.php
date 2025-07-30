<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyReportController extends Controller
{
    public function index(Request $request)
    {
        $stores = Store::orderBy('store_name')->get();

        $selectedStoreId = $request->input('store_id', $stores->first()->id ?? null);
        $reportDate = $request->input('date', now()->toDateString());

        // 1. Начинаем строить запрос
        $query = DB::table('sales_raw as s')
            ->join('products as p', function($join) {
                $join->on('s.nmId', '=', 'p.nmID');
            })
            ->select(
                'p.nmID as product_nmID', 'p.title as product_title', 'p.brand as product_brand', 'p.vendorCode as vendor_code',
                DB::raw("SUM(CASE WHEN s.order_status = 'sale' THEN 1 ELSE 0 END) AS orders_count"),
                DB::raw("SUM(CASE WHEN s.order_status = 'sale' THEN s.forPay ELSE 0 END) AS buyouts_sum_rub"),
                DB::raw("SUM(CASE WHEN s.order_status = 'refund' THEN 1 ELSE 0 END) AS refunds_count"),
                DB::raw("SUM(CASE WHEN s.order_status = 'refund' THEN s.forPay ELSE 0 END) AS refunds_sum_rub")
            )
            ->groupBy('p.nmID', 'p.title', 'p.brand', 'p.vendorCode')
            ->orderByDesc('buyouts_sum_rub');

        // 2. Добавляем фильтры, только если они есть
        if ($selectedStoreId) {
            $startDate = $reportDate . " 00:00:00";
            $endDate = $reportDate . " 23:59:59";

            $query->where('s.store_id', $selectedStoreId)
                ->whereBetween('s.date', [$startDate, $endDate]);
        } else {
            // Если магазин не выбран, гарантируем пустой результат
            $query->where('s.store_id', -1);
        }

        // 3. Выполняем пагинацию в самом конце
        $items = $query->paginate(100);

        return view('reports.daily', [
            'items' => $items,
            'stores' => $stores,
            'selectedStoreId' => $selectedStoreId,
            'reportDate' => $reportDate,
        ]);
    }
}
