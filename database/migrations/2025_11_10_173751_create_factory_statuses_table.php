<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Эта таблица будет хранить ТЕКУЩИЙ производственный статус для каждого SKU
        Schema::create('factory_statuses', function (Blueprint $table) {
            $table->id();

            // Связь с нашей системой
            $table->string('sku_barcode')->unique(); // Колонка C (ШК)

            // Данные, которые мы берем из "строки-заголовка" артикула
            $table->string('factory_article')->nullable(); // Колонка A (款号)
            $table->string('wb_article')->nullable(); // Колонка B (Артикул WB)
            $table->date('production_start_date')->nullable(); // Колонка E (Дата произ-ва)
            $table->string('production_status')->nullable(); // Колонка F (进度)
            $table->integer('status_at_factory')->default(0);    // Колонка J (在车间)
            $table->integer('status_in_washing')->default(0);    // Колонка K (在水洗)
            $table->integer('status_in_transit_to_sklad')->default(0); // Колонка L (在途 на наш склад)
            $table->integer('status_post_processing')->default(0); // Колонка M (在后部)
            $table->integer('shipped_quantity')->default(0);     // Колонка N (出货数)

            // Данные, которые мы берем из строки самого SKU
            $table->string('size_name')->nullable(); // Колонка D (码比)
            $table->integer('order_quantity')->default(0); // Колонка G (下单数)

            $table->timestamps(); // Будет показывать, когда мы последний раз обновляли запись

            $table->foreign('sku_barcode')->references('barcode')->on('skus')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factory_statuses');
    }
};
