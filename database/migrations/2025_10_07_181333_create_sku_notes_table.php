<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sku_notes', function (Blueprint $table) {
            $table->id();
            $table->string('sku_barcode');
            $table->text('note');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->foreign('sku_barcode')->references('barcode')->on('skus')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('sku_notes');
    }
};
