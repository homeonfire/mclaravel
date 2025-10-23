<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSeasonality extends Model
{
    use HasFactory;

    protected $table = 'product_seasonality'; // Указываем имя таблицы

    protected $fillable = [
        'product_nmID',
        'start_month',
        'end_month',
    ];
}
