<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaign_products', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->integer('advertId');
            $table->integer('nmID');

            $table->primary(['store_id', 'advertId', 'nmID']);
            $table->foreign('advertId')->references('advertId')->on('ad_campaigns')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_products');
    }
};
