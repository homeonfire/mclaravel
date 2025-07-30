<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaign_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->integer('advertId');
            $table->date('report_date');

            $table->integer('views')->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('ctr', 5, 2)->default(0); // 5 цифр всего, 2 после запятой
            $table->decimal('cpc', 10, 2)->default(0);
            $table->decimal('sum', 10, 2)->default(0);
            $table->integer('atbs')->default(0);
            $table->integer('orders')->default(0);
            $table->decimal('cr', 5, 2)->default(0);
            $table->integer('shks')->default(0);
            $table->decimal('sum_price', 10, 2)->default(0);

            // Внешний ключ к таблице ad_campaigns
            $table->foreign('advertId')->references('advertId')->on('ad_campaigns')->onDelete('cascade');

            // Уникальный индекс, чтобы не было дублей за один день
            $table->unique(['advertId', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_daily_stats');
    }
};
