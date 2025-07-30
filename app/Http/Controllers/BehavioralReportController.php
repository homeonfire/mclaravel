<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BehavioralReportController extends Controller
{
    public function index(Request $request)
    {
        $stores = Store::orderBy('store_name')->get();
        $selectedStoreId = $request->input('store_id', $stores->first()->id ?? null);
        $reportDate = $request->input('date', now()->toDateString());
        $previousDate = now()->parse($reportDate)->subDay()->toDateString();

        $query = DB::table('behavioral_stats as c')
            ->join('products as p', 'c.nmID', '=', 'p.nmID')
            ->leftJoin('behavioral_stats as prev', function ($join) use ($previousDate) {
                $join->on('c.nmID', '=', 'prev.nmID')
                    ->on('c.store_id', '=', 'prev.store_id')
                    ->where('prev.report_date', '=', $previousDate);
            })
            ->where('c.report_date', $reportDate)
            ->select(
                'p.title as product_title', 'p.vendorCode as vendor_code', 'c.*',
                'p.nmID as product_nmID',
                'prev.openCardCount as prev_openCardCount',
                'prev.addToCartCount as prev_addToCartCount',
                'prev.ordersCount as prev_ordersCount',
                'prev.buyoutsCount as prev_buyoutsCount'
            )
            ->orderByDesc('c.ordersCount');

        if ($selectedStoreId) {
            $query->where('c.store_id', $selectedStoreId);
        } else {
            $query->where('c.store_id', -1);
        }

        $items = $query->paginate(100);

        return view('reports.behavioral', [
            'items' => $items,
            'stores' => $stores,
            'selectedStoreId' => $selectedStoreId,
            'reportDate' => $reportDate,
        ]);
    }
}
