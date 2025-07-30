<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // --- ИСПРАВЛЕНИЕ ЗДЕСЬ ---
            // Явно создаем колонку типа integer, чтобы она соответствовала nmID в таблице products
            $table->integer('product_nmID');
            $table->foreign('product_nmID')->references('nmID')->on('products')->onDelete('cascade');
            // --------------------------

            $table->timestamps();

            // Уникальный ключ, чтобы пользователь не мог отслеживать один и тот же товар дважды
            $table->unique(['user_id', 'product_nmID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_user');
    }
};
