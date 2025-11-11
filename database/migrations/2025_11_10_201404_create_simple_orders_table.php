<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simple_orders', function (Blueprint $table) {
            $table->id();
            $table->string('sku_barcode')->unique(); // Баркод (Колонка B)
            $table->integer('order_quantity')->default(0); // Количество (Колонка D)
            $table->timestamps();

            $table->foreign('sku_barcode')->references('barcode')->on('skus')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simple_orders');
    }
};
