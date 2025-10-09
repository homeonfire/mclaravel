<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_nmID');

            $table->string('period_type')->default('month');
            $table->date('period_start_date');

            // Сразу добавляем все колонки для планов
            $table->integer('plan_openCardCount')->nullable();
            $table->integer('plan_addToCartCount')->nullable();
            $table->integer('plan_ordersCount')->nullable();
            $table->integer('plan_buyoutsCount')->nullable();
            $table->integer('plan_ordersSumRub')->nullable();
            $table->integer('plan_buyoutsSumRub')->nullable();
            $table->integer('plan_cancelCount')->nullable();
            $table->integer('plan_cancelSumRub')->nullable();
            $table->integer('plan_avgPriceRub')->nullable();

            $table->timestamps();

            $table->unique(['store_id', 'product_nmID', 'period_start_date']);
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_plans');
    }
};
