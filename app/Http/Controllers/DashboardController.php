<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; // <-- Добавляем импорт Auth

class DashboardController extends Controller
{
    public function index()
    {
        // --- Логика для топ-5 товаров по магазинам (остается без изменений) ---
        $stores = Store::all();
        $topProductsByStore = [];
        $lastOrderDate = DB::table('orders_raw')->max('date');

        if ($lastOrderDate) {
            $endDate = \Carbon\Carbon::parse($lastOrderDate)->endOfDay();
            $startDate = $endDate->copy()->subDays(6)->startOfDay();

            foreach ($stores as $store) {
                $topProducts = DB::table('orders_raw as o')
                    ->join('products as p', 'o.nmId', '=', 'p.nmID')
                    ->where('o.store_id', $store->id)
                    ->whereBetween('o.date', [$startDate, $endDate])
                    ->select('p.nmID', 'p.title', 'p.vendorCode', 'p.main_image_url', DB::raw('COUNT(o.srid) as orders_count'))
                    ->groupBy('p.nmID', 'p.title', 'p.vendorCode', 'p.main_image_url')
                    ->orderByDesc('orders_count')
                    ->limit(5)
                    ->get();
                $topProductsByStore[$store->store_name] = $topProducts;
            }
        }

        // --- НОВАЯ ЛОГИКА: Получаем отслеживаемые товары ---
        $trackedProducts = Auth::user()
            ->trackedProducts() // Используем связь, которую мы создали
            ->orderBy('title')
            ->get();

        return view('dashboard', [
            'topProductsByStore' => $topProductsByStore,
            'trackedProducts' => $trackedProducts, // <-- Передаем новый список в представление
        ]);
    }
}
