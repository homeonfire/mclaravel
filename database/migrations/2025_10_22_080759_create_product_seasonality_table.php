<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_seasonality', function (Blueprint $table) {
            $table->id();
            $table->integer('product_nmID'); // Связь с таблицей products по nmID
            $table->integer('start_month'); // Номер месяца начала (1-12)
            $table->integer('end_month'); // Номер месяца окончания (1-12)
            $table->timestamps();

            $table->foreign('product_nmID')->references('nmID')->on('products')->onDelete('cascade');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_seasonality');
    }
};
