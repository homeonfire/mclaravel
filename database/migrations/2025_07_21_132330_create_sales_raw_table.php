<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_raw', function (Blueprint $table) {
            $table->string('saleID')->primary();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');

            // Явно указываем тип и ссылку для nmId
            $table->integer('nmId');
            $table->foreign('nmId')->references('nmID')->on('products')->onDelete('cascade');

            // Явно указываем тип и ссылку для srid
            $table->string('srid');
            $table->foreign('srid')->references('srid')->on('orders_raw')->onDelete('cascade');

            $table->string('gNumber', 50);
            $table->dateTime('date');
            $table->dateTime('lastChangeDate');
            $table->string('supplierArticle', 75)->nullable();
            $table->string('techSize', 30)->nullable();
            $table->string('barcode', 30)->nullable();
            $table->decimal('totalPrice', 10, 2);
            $table->integer('discountPercent');
            $table->boolean('isSupply');
            $table->boolean('isRealization');
            $table->decimal('promoCodeDiscount', 10, 2)->nullable();
            $table->string('warehouseName', 50)->nullable();
            $table->string('countryName', 200)->nullable();
            $table->string('oblastOkrugName', 200)->nullable();
            $table->string('regionName', 200)->nullable();
            $table->integer('incomeID')->nullable();
            $table->bigInteger('odid')->nullable();
            $table->decimal('spp', 10, 2);
            $table->decimal('forPay', 10, 2);
            $table->decimal('finishedPrice', 10, 2);
            $table->decimal('priceWithDisc', 10, 2);
            $table->string('subject', 50)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('brand', 50)->nullable();
            $table->integer('IsStorno')->nullable();
            $table->string('sticker')->nullable();
            $table->string('order_status', 10);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_raw');
    }
};
