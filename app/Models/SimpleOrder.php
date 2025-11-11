<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpleOrder extends Model
{
    use HasFactory;

    protected $table = 'simple_orders';

    protected $fillable = [
        'sku_barcode',
        'order_quantity',
    ];
}
