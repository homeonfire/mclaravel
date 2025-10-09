<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';
    protected $primaryKey = 'nmID';
    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'main_image_url',
        'title',
        'brand',
        'nmID',
        'vendorCode',
        'cost_price', // Наше новое поле
        'subjectName',
        'imtID',
        'subjectID',
        'nmUUID'
    ];

    /**
     * Определяет связь "продукт принадлежит магазину".
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function trackedByUsers()
    {
        return $this->belongsToMany(User::class, 'product_user', 'product_nmID', 'user_id');
    }

    public function adCampaigns()
    {
        return $this->belongsToMany(
            AdCampaign::class,
            'ad_campaign_products', // Название сводной таблицы
            'nmID',                 // Внешний ключ для Product в сводной таблице
            'advertId'              // Внешний ключ для AdCampaign в сводной таблице
        );
    }
}
