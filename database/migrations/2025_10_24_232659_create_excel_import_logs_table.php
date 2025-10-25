<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excel_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('import_type'); // Тип импорта (напр., 'in-transit-to-wb')
            $table->string('batch_id'); // Уникальный ID для каждой загрузки
            $table->string('status'); // 'error', 'skipped', 'warning'
            $table->integer('row_number')->nullable(); // Номер строки в Excel
            $table->string('barcode')->nullable(); // Баркод (если есть)
            $table->text('message'); // Сообщение об ошибке/пропуске
            $table->timestamps();

            $table->index(['import_type', 'batch_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excel_import_logs');
    }
};
