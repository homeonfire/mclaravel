<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sku_stocks', function (Blueprint $table) {
            $table->integer('in_way_to_client')->default(0)->after('stock_wb');
            $table->integer('in_way_from_client')->default(0)->after('in_way_to_client');
        });
    }

    public function down(): void
    {
        Schema::table('sku_stocks', function (Blueprint $table) {
            $table->dropColumn(['in_way_to_client', 'in_way_from_client']);
        });
    }
};
