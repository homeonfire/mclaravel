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

            $table->integer('views')->default(0);
            // Сюда можно будет добавить clicks, sum и другие поля, если они понадобятся

            // Внешний ключ к таблице ad_campaigns
            $table->foreign('advertId')->references('advertId')->on('ad_campaigns')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_keyword_stats');
    }
};
