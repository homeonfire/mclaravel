<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB; // <-- THIS IS THE FIX

class PlanningController extends Controller
{
    public function index(Request $request)
    {
        // 1. Get all filter parameters from the URL
        $selectedMonth = $request->input('month', now()->format('Y-m'));
        $selectedStoreId = $request->input('store_id');
        $searchQuery = $request->input('search');

        $startDate = Carbon::parse($selectedMonth)->startOfMonth();

        // 2. Get the list of all stores for the dropdown
        $stores = DB::table('stores')->orderBy('store_name')->get();

        // 3. Get products, applying filters
        $products = Product::query()
            ->when($selectedStoreId, function ($query, $storeId) {
                // If a store is selected, filter by store_id
                return $query->where('store_id', $storeId);
            })
            ->when($searchQuery, function ($query, $search) {
                // If there's a search query, look for the vendor code
                return $query->where('vendorCode', 'like', "%{$search}%");
            })
            ->orderBy('title')
            ->get();

        // 4. Get existing plans for the filtered products
        $productNmIDs = $products->pluck('nmID');
        $plans = ProductPlan::where('period_start_date', $startDate->toDateString())
            ->whereIn('product_nmID', $productNmIDs)
            ->get()
            ->keyBy('product_nmID');

        return view('planning.index', [
            'products' => $products,
            'plans' => $plans,
            'selectedMonth' => $selectedMonth,
            'stores' => $stores,
            'selectedStoreId' => $selectedStoreId,
            'searchQuery' => $searchQuery,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
            'plans' => 'required|array',
            'plans.*.*' => 'nullable|integer|min:0',
        ]);

        $startDate = Carbon::parse($validated['month'])->startOfMonth();

        foreach ($validated['plans'] as $nmId => $metrics) {
            if (count(array_filter($metrics)) > 0) {
                ProductPlan::updateOrCreate(
                    [
                        'product_nmID' => $nmId,
                        'period_start_date' => $startDate->toDateString(),
                        'store_id' => Product::where('nmID', $nmId)->value('store_id'),
                    ],
                    [
                        'plan_openCardCount' => $metrics['openCardCount'] ?? null,
                        'plan_addToCartCount' => $metrics['addToCartCount'] ?? null,
                        'plan_ordersCount' => $metrics['ordersCount'] ?? null,
                        'plan_buyoutsCount' => $metrics['buyoutsCount'] ?? null,
                        'plan_ordersSumRub' => $metrics['ordersSumRub'] ?? null,
                        'plan_buyoutsSumRub' => $metrics['buyoutsSumRub'] ?? null,
                        'plan_cancelCount' => $metrics['cancelCount'] ?? null,
                        'plan_cancelSumRub' => $metrics['cancelSumRub'] ?? null,
                        'plan_avgPriceRub' => $metrics['avgPriceRub'] ?? null,
                    ]
                );
            }
        }
        return back()->with('status', 'Планы успешно сохранены!');
    }
}
