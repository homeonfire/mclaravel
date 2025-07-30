<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * НОВЫЙ МЕТОД: Отображает список всех товаров с поиском и пагинацией.
     */
    public function index(Request $request)
    {
        // Получаем поисковый запрос из URL
        $searchQuery = $request->input('search');

        $products = Product::query()
            ->when($searchQuery, function ($query, $search) {
                // Если есть поисковый запрос, ищем по vendorCode
                return $query->where('vendorCode', 'like', "%{$search}%");
            })
            ->orderBy('updated_at', 'desc') // Сортируем по дате обновления
            ->paginate(30); // Разбиваем на страницы по 30 товаров

        return view('products.index', [
            'products' => $products,
        ]);
    }
    public function show(Product $product)
    {
        $product->load('adCampaigns');
        // ИСПРАВЛЕНИЕ ЗДЕСЬ: Вычисляем, отслеживает ли текущий пользователь этот товар
        $isTracked = auth()->user()->trackedProducts()->where('nmID', $product->nmID)->exists();

        // --- Получаем данные для графика и таблицы ---
        $stats = DB::table('behavioral_stats')
            ->where('nmID', $product->nmID)
            ->where('store_id', $product->store_id)
            ->orderBy('report_date', 'desc')
            ->limit(7)
            ->get();

        // Готовим данные для Chart.js
        $chartData = [
            'labels' => $stats->pluck('report_date')->reverse()->values()->map(function ($date) {
                return \Carbon\Carbon::parse($date)->format('d.m');
            }),
            'datasets' => [
                [
                    'label' => 'Переходы в карточку',
                    'data' => $stats->pluck('openCardCount')->reverse()->values(),
                    'borderColor' => '#3b82f6',
                    'tension' => 0.1
                ],
                [
                    'label' => 'Добавления в корзину',
                    'data' => $stats->pluck('addToCartCount')->reverse()->values(),
                    'borderColor' => '#a855f7',
                    'tension' => 0.1
                ],
                [
                    'label' => 'Заказы',
                    'data' => $stats->pluck('ordersCount')->reverse()->values(),
                    'borderColor' => '#22c55e',
                    'tension' => 0.1
                ]
            ]
        ];

        // Готовим данные для таблицы
        $statsWithComparison = $stats->map(function ($item, $key) use ($stats) {
            $item->previous = $stats->get($key + 1);
            return $item;
        });

        return view('products.show', [
            'product' => $product,
            'chartData' => json_encode($chartData),
            'stats' => $statsWithComparison,
            'isTracked' => $isTracked // <-- И ПЕРЕДАЕМ ЭТОТ ФЛАГ В ПРЕДСТАВЛЕНИЕ
        ]);
    }

    public function toggleTracking(Request $request, Product $product)
    {
        $user = $request->user();

        // Метод toggle "переключает" связь: если она есть - удаляет, если нет - добавляет.
        // Это невероятно удобно и избавляет от лишних проверок.
        $user->trackedProducts()->toggle($product->nmID);

        return back()->with('status', 'Статус отслеживания изменен!');
    }
}
