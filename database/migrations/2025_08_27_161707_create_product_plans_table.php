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
            $table->unsignedBigInteger('product_nmID'); // nmID товара

            $table->string('period_type')->default('month'); // Тип периода: 'month' или 'week'
            $table->date('period_start_date'); // Дата начала периода (напр., '2025-09-01')

            // Колонки для плановых показателей
            $table->integer('plan_openCardCount')->nullable();
            $table->integer('plan_addToCartCount')->nullable();
            $table->integer('plan_ordersCount')->nullable();
            $table->integer('plan_buyoutsCount')->nullable();
            $table->integer('plan_ordersSumRub')->nullable();
            $table->integer('plan_buyoutsSumRub')->nullable();

            $table->timestamps();

            // Уникальный ключ, чтобы не было двух планов для одного товара на один и тот же период
            $table->unique(['store_id', 'product_nmID', 'period_start_date']);

            // Внешние ключи для целостности данных
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            // Примечание: Внешний ключ к products не добавляем, так как там нет составного ключа
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_plans');
    }
};
