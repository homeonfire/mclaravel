<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports_daily', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->date('report_date');

            // Явно указываем тип и ссылку
            $table->integer('nmID');
            $table->foreign('nmID')->references('nmID')->on('products')->onDelete('cascade');

            $table->integer('ordersCount')->default(0);
            $table->decimal('ordersSumRub', 10, 2)->default(0);
            $table->integer('buyoutsCount')->default(0);
            $table->decimal('buyoutsSumRub', 10, 2)->default(0);

            $table->primary(['store_id', 'report_date', 'nmID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports_daily');
    }
};
