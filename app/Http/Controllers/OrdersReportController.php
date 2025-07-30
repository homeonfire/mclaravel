<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdersReportController extends Controller
{
    public function index(Request $request)
    {
        $stores = Store::orderBy('store_name')->get();
        $selectedStoreId = $request->input('store_id', $stores->first()->id ?? null);
        $reportDate = $request->input('date', now()->toDateString());

        $query = DB::table('orders_raw as o')
            ->join('products as p', 'o.nmId', '=', 'p.nmID')
            ->leftJoin('sales_raw as s', 'o.srid', '=', 's.srid')
            ->whereDate('o.date', $reportDate)
            ->select(
                'p.nmID as product_nmID',
                'p.title as product_title',
                'p.vendorCode as vendor_code',
                DB::raw("COUNT(DISTINCT o.srid) as orders_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.order_status = 'sale' THEN s.srid END) as payments_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN s.order_status = 'refund' THEN s.srid END) as refunds_count")
            )
            ->groupBy('p.nmID', 'p.title', 'p.vendorCode')
            ->orderByDesc('orders_count');

        if ($selectedStoreId) {
            $query->where('o.store_id', $selectedStoreId);
        } else {
            $query->where('o.store_id', -1); // Гарантируем пустой результат, если нет магазинов
        }

        $items = $query->paginate(100);

        return view('reports.orders', [
            'items' => $items,
            'stores' => $stores,
            'selectedStoreId' => $selectedStoreId,
            'reportDate' => $reportDate,
        ]);
    }
}
