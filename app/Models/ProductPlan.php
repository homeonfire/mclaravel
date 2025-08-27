<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ProductPlan extends Model
{
    use HasFactory;
    protected $fillable = [
        'store_id', 'product_nmID', 'period_start_date',
        'plan_openCardCount', 'plan_addToCartCount',
        'plan_ordersCount', 'plan_buyoutsCount',
        'plan_ordersSumRub', 'plan_buyoutsSumRub',
        'plan_cancelCount', 'plan_cancelSumRub',
        'plan_avgPriceRub',
    ];
}
