<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SkuStock extends Model {
    protected $fillable = ['sku_barcode', 'stock_wb', 'stock_own', 'in_transit_to_wb', 'in_transit_general', 'at_factory'];
}
