<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BehavioralStat extends Model
{
    use HasFactory;

    /**
     * Имя таблицы, связанной с моделью.
     *
     * @var string
     */
    protected $table = 'behavioral_stats';

    /**
     * Атрибуты, которые можно массово присваивать.
     *
     * @var array
     */
    protected $fillable = [
        'store_id',
        'report_date',
        'nmID',
        'openCardCount',
        'addToCartCount',
        'ordersCount',
        'ordersSumRub',
        'buyoutsCount',
        'buyoutsSumRub',
        'cancelCount',
        'cancelSumRub',
        'avgPriceRub',
        'stocksMp',
        'stocksWb',
    ];

    /**
     * Отключаем временные метки created_at и updated_at.
     *
     * @var bool
     */
    public $timestamps = false;
}
