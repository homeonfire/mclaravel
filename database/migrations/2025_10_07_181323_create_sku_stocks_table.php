<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sku_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('sku_barcode');
            $table->integer('stock_wb')->default(0);
            $table->integer('stock_own')->default(0);
            $table->integer('in_transit_to_wb')->default(0);
            $table->integer('in_transit_general')->default(0);
            $table->integer('at_factory')->default(0);
            $table->timestamps();
            $table->foreign('sku_barcode')->references('barcode')->on('skus')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('sku_stocks');
    }
};
