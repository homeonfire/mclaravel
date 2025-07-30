<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->integer('advertId')->primary();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->integer('type')->nullable();
            $table->integer('status')->nullable();
            $table->integer('dailyBudget')->nullable();
            $table->dateTime('createTime')->nullable();
            $table->dateTime('changeTime')->nullable();
            $table->dateTime('startTime')->nullable();
            $table->dateTime('endTime')->nullable();
            $table->boolean('searchPluseState')->nullable();
            $table->json('raw_data')->nullable();

            $table->unique(['store_id', 'advertId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
