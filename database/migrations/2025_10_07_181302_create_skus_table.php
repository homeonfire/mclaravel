<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('skus', function (Blueprint $table) {
            $table->id();
            $table->string('barcode')->unique();
            $table->integer('product_nmID'); // <-- ФИНАЛЬНОЕ ИСПРАВЛЕНИЕ ЗДЕСЬ (INT)
            $table->string('tech_size');
            $table->string('wb_size')->nullable();
            $table->timestamps();
            $table->foreign('product_nmID')->references('nmID')->on('products')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('skus');
    }
};
