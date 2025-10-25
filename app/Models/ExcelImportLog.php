<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ExcelImportLog extends Model
{
    protected $fillable = [
        'import_type', 'batch_id', 'status',
        'row_number', 'barcode', 'message',
    ];
}
