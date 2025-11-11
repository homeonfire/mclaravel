<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryStatus extends Model
{
    use HasFactory;

    protected $table = 'factory_statuses';

    // $guarded = [] отключает массовую защиту, будь осторожен
    // Либо используй $fillable для всех полей из миграции
    protected $guarded = [];

    // Указываем, что эти поля должны быть датами
    protected $casts = [
        'production_start_date' => 'date',
    ];
}
