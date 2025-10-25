<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkuWarehouseStock extends Model
{
    use HasFactory;

    protected $table = 'sku_warehouse_stocks';

    protected $fillable = [
        'sku_barcode',
        'warehouse_id',
        'warehouse_name',
        'quantity',
        'in_way_to_client',
        'in_way_from_client',
    ];
}
