<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\SkuStock;
use App\Models\ExcelImportLog;
use Illuminate\Support\Facades\DB; // Добавляем DB фасад
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessInTransitExcel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $filePath;
    protected string $originalFileName;
    protected string $importType;
    protected string $batchId;
    // Можно добавить таймаут для задачи, если обработка очень долгая
    // public $timeout = 1200; // 20 минут

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, string $originalFileName, string $batchId)
    {
        $this->filePath = $filePath;
        $this->originalFileName = $originalFileName;
        $this->importType = 'in-transit-to-wb';
        $this->batchId = $batchId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Улучшенная функция логирования
        $logEntry = function($status, $rowNum, $barcode, $message) {
            try {
                ExcelImportLog::create([
                    'import_type' => $this->importType,
                    'batch_id' => $this->batchId,
                    'status' => $status, // 'info', 'success', 'warning', 'error'
                    'row_number' => $rowNum,
                    'barcode' => $barcode,
                    'message' => $message,
                ]);

                // Пишем в стандартный лог Laravel
                $logMessage = "[ExcelImport:{$this->importType}:Batch {$this->batchId}]";
                if ($rowNum) $logMessage .= " Row $rowNum";
                if ($barcode) $logMessage .= " ($barcode)";
                $logMessage .= ": $message";

                match ($status) {
                    'error' => Log::error($logMessage),
                    'warning' => Log::warning($logMessage),
                    default => Log::info($logMessage), // 'info', 'success', 'skipped'
                };

            } catch (Throwable $e) {
                Log::emergency("Failed to write import log: " . $e->getMessage());
            }
        };

        $logEntry('info', null, null, "Начало фоновой обработки файла: {$this->originalFileName}");

        try {
            $sheetIndex = 0;
            $sheetName = 'дорога';
            $fullPath = Storage::path($this->filePath);

            if (!Storage::exists($this->filePath)) {
                throw new \Exception("Временный файл не найден: {$this->filePath}");
            }

            $logEntry('info', null, null, "Чтение файла...");
            $rows = Excel::toCollection(null, $fullPath)[$sheetIndex] ?? null;

            if ($rows === null || $rows->isEmpty()) {
                throw new \Exception("Лист #{$sheetIndex} ('{$sheetName}') не найден или пуст.");
            }

            $dataRows = $rows->slice(1); // Пропускаем заголовок
            $totalRowsToProcess = $dataRows->count();
            if ($totalRowsToProcess === 0) {
                $logEntry('warning', null, null, "Нет данных для обработки (после пропуска заголовка).");
                goto cleanup;
            }

            $logEntry('info', null, null, "Найдено строк для обработки: {$totalRowsToProcess}. Начинаем построчное обновление...");

            $successCount = 0;
            $errorCount = 0;
            $skippedNoBarcodeCount = 0;
            $skippedNotFoundCount = 0;

            foreach ($dataRows as $index => $row) {
                $rowIndex = $index + 2; // Номер строки в Excel
                $barcode = trim($row[4] ?? null);
                $quantityStr = trim($row[5] ?? null);

                // 1. Пропускаем строки без баркода
                if (empty($barcode)) {
                    $skippedNoBarcodeCount++;
                    continue; // Не логируем каждый пропуск пустого ШК
                }

                // 2. Валидация количества
                $quantity = filter_var($quantityStr, FILTER_VALIDATE_INT);
                if ($quantity === false || $quantity < 0) {
                    $errorCount++;
                    $logEntry('error', $rowIndex, $barcode, "Некорректное количество '{$quantityStr}'");
                    continue;
                }

                // 3. Обновление SkuStock
                try {
                    // Используем updateOrCreate - он обновит или создаст, если SkuStock вдруг нет, но SKU есть
                    // Сначала проверяем наличие SKU в родительской таблице
                    $skuExists = DB::table('skus')->where('barcode', $barcode)->exists();

                    if ($skuExists) {
                        // Используем updateOrCreate для SkuStock - он найдет по ШК или создаст новую запись
                        $skuStock = SkuStock::updateOrCreate(
                            ['sku_barcode' => $barcode], // Поле для поиска
                            ['in_transit_to_wb' => $quantity] // Поле для обновления/вставки
                        );

                        // Проверяем, была ли запись обновлена или создана
                        // $skuStock->wasRecentlyCreated - если была создана
                        // $skuStock->wasChanged() - если была обновлена (или можно проверять $skuStock->getChanges())

                        $logEntry('success', $rowIndex, $barcode, "Обновлено: В пути на WB = {$quantity}");
                        $successCount++;

                    } else {
                        // SKU не найден в справочнике skus
                        $skippedNotFoundCount++;
                        $logEntry('warning', $rowIndex, $barcode, "SKU не найден в справочнике 'skus', остатки не обновлены.");
                    }

                } catch (Throwable $dbError) {
                    $errorCount++;
                    $logEntry('error', $rowIndex, $barcode, "Ошибка БД: " . $dbError->getMessage());
                }

                // Логируем прогресс каждые N строк (опционально)
                if (($index + 1) % 100 == 0) {
                    // Исправлено с конкатенацией:
                    $processedRows = $index + 1;
                    $logEntry('info', null, null, 'Обработано ' . $processedRows . ' из ' . $totalRowsToProcess . ' строк...');
                }

            } // end foreach

            $message = "Обработка файла '{$this->originalFileName}' завершена. Успешно обновлено/создано: {$successCount}. ";
            if ($errorCount > 0) $message .= "Ошибок: {$errorCount}. ";
            if ($skippedNotFoundCount > 0) $message .= "Пропущено (SKU не найден): {$skippedNotFoundCount}. ";
            if ($skippedNoBarcodeCount > 0) $message .= "Пропущено строк без ШК: {$skippedNoBarcodeCount}. ";
            $logEntry('info', null, null, $message);

        } catch (Throwable $e) {
            $logEntry('error', null, null, 'Критическая ошибка при обработке файла: ' . $e->getMessage());
            Log::emergency("[CRITICAL ExcelImport:{$this->importType}:Batch {$this->batchId}] {$e->getMessage()}\n" . $e->getTraceAsString());
            // Здесь можно пометить задачу как проваленную
            // $this->fail($e);
        }

        cleanup:
        if (Storage::exists($this->filePath)) {
            Storage::delete($this->filePath);
            $logEntry('info', null, null, "Временный файл удален: {$this->filePath}");
        }
    }
}
