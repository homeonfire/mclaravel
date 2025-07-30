<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders_raw', function (Blueprint $table) {
            $table->string('srid')->primary();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');

            // Явно указываем тип колонки и на что она ссылается
            $table->integer('nmId');
            $table->foreign('nmId')->references('nmID')->on('products')->onDelete('cascade');

            $table->string('gNumber', 50);
            $table->dateTime('date');
            $table->dateTime('lastChangeDate');
            $table->string('supplierArticle', 75)->nullable();
            $table->string('techSize', 30)->nullable();
            $table->string('barcode', 30)->nullable();
            $table->decimal('totalPrice', 10, 2);
            $table->integer('discountPercent');
            $table->string('warehouseName', 50)->nullable();
            $table->string('oblast', 200)->nullable();
            $table->integer('incomeID')->nullable();
            $table->bigInteger('odid')->nullable();
            $table->string('subject', 50)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('brand', 50)->nullable();
            $table->boolean('isCancel');
            $table->dateTime('cancel_dt')->nullable();
            $table->string('sticker')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders_raw');
    }
};
