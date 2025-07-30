<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavioral_stats', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->date('report_date');

            // Явно указываем тип и ссылку
            $table->integer('nmID');
            $table->foreign('nmID')->references('nmID')->on('products')->onDelete('cascade');

            $table->integer('openCardCount')->default(0);
            $table->integer('addToCartCount')->default(0);
            $table->integer('ordersCount')->default(0);
            $table->integer('ordersSumRub')->default(0);
            $table->integer('buyoutsCount')->default(0);
            $table->integer('buyoutsSumRub')->default(0);
            $table->integer('cancelCount')->default(0);
            $table->integer('cancelSumRub')->default(0);
            $table->integer('avgPriceRub')->default(0);
            $table->integer('stocksMp')->default(0);
            $table->integer('stocksWb')->default(0);

            $table->primary(['store_id', 'report_date', 'nmID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavioral_stats');
    }
};
