<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdCampaign extends Model
{
    use HasFactory;
    protected $table = 'ad_campaigns';
    protected $primaryKey = 'advertId';
    public $incrementing = false;

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'ad_campaign_products', // Название сводной таблицы
            'advertId',             // Внешний ключ для AdCampaign в сводной таблице
            'nmID'                  // Внешний ключ для Product в сводной таблице
        );
    }

    // НОВАЯ СВЯЗЬ: Дневная статистика
    public function dailyStats()
    {
        return $this->hasMany(AdCampaignDailyStat::class, 'advertId', 'advertId');
    }

    // НОВАЯ СВЯЗЬ: Статистика по ключам
    public function keywordStats()
    {
        return $this->hasMany(AdCampaignKeywordStat::class, 'advertId', 'advertId');
    }
}
