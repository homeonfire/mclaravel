<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaign_keyword_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->integer('advertId');
            $table->date('report_date');
            $table->string('keyword');

            $table->integer('views')->default(0)->comment('Показы');
            $table->integer('clicks')->default(0)->comment('Клики');
            $table->decimal('ctr', 5, 2)->default(0);
            $table->decimal('cpc', 10, 2)->default(0);
            $table->decimal('sum', 10, 2)->default(0)->comment('Расходы, руб.');

            // Внешний ключ, ссылающийся на составной ключ в ad_campaigns
            $table->foreign(['store_id', 'advertId'])
                ->references(['store_id', 'advertId'])
                ->on('ad_campaigns')
                ->onDelete('cascade');

            // Уникальный индекс, чтобы избежать дубликатов
            $table->unique(['store_id', 'advertId', 'report_date', 'keyword'], 'store_advert_date_keyword_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_keyword_stats');
    }
};
