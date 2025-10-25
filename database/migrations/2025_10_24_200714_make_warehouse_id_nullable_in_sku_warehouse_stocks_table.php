<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sku_warehouse_stocks', function (Blueprint $table) {
            $table->bigInteger('warehouse_id')->nullable()->change(); // Изменяем колонку
        });
    }
    public function down(): void { // Логика отката (можно упростить)
        Schema::table('sku_warehouse_stocks', function (Blueprint $table) {
            $table->bigInteger('warehouse_id')->nullable(false)->change();
        });
    }
};
