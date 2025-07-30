<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- ВАЖНО: Убедитесь, что эта строка есть

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            // Создаем колонку БЕЗ уникального индекса
            $table->string('api_key', 1000);
            $table->string('store_name', 100);
        });

        // Добавляем уникальный индекс через сырой SQL-запрос,
        // чтобы обойти проблему с длиной ключа
        DB::statement('ALTER TABLE stores ADD UNIQUE stores_api_key_unique(api_key(191))');
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
