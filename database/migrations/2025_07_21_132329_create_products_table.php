<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            // Указываем, что nmID - это главный ключ типа integer
            $table->integer('nmID')->primary();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->integer('imtID')->nullable();
            $table->string('nmUUID')->nullable();
            $table->integer('subjectID')->nullable();
            $table->string('subjectName')->nullable();
            $table->string('vendorCode')->nullable();
            $table->string('brand')->nullable();
            $table->string('title')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
