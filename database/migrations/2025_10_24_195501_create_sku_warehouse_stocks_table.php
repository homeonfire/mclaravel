<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('sku_barcode');
            $table->bigInteger('warehouse_id')->nullable(); // Делаем ID склада необязательным
            $table->string('warehouse_name');   // Название склада WB
            $table->integer('quantity')->default(0); // Остаток на этом складе
            $table->integer('in_way_to_client')->default(0);
            $table->integer('in_way_from_client')->default(0);
            $table->timestamps(); // Для отслеживания свежести данных

            // Уникальный ключ по паре баркод + склад
            $table->unique(['sku_barcode', 'warehouse_id']);

            $table->foreign('sku_barcode')->references('barcode')->on('skus')->onDelete('cascade');
            // Не добавляем foreign key на склады WB, т.к. их справочника у нас нет
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_warehouse_stocks');
    }
};
