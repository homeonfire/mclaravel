<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Sku extends Model {
    protected $fillable = ['barcode', 'product_nmID', 'tech_size', 'wb_size'];
}
