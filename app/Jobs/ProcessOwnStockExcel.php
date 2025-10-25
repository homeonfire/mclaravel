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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessOwnStockExcel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $filePath;
    protected string $originalFileName;
    protected string $importType;
    protected string $batchId;

    public function __construct(string $filePath, string $originalFileName, string $batchId)
    {
        $this->filePath = $filePath;
        $this->originalFileName = $originalFileName;
        $this->importType = 'own-stock';
        $this->batchId = $batchId;
    }

    public function handle(): void
    {
        $logEntry = function($status, $rowNum, $barcode, $message) {
            try {
                ExcelImportLog::create([
                    'import_type' => $this->importType,
                    'batch_id' => $this->batchId,
                    'status' => $status,
                    'row_number' => $rowNum,
                    'barcode' => $barcode,
                    'message' => $message,
                ]);
                $logMessage = "[ExcelImport:{$this->importType}:Batch {$this->batchId}]";
                if ($rowNum) $logMessage .= " Row $rowNum";
                if ($barcode) $logMessage .= " ($barcode)";
                $logMessage .= ": $message";
                match ($status) { 'error' => Log::error($logMessage), 'warning' => Log::warning($logMessage), default => Log::info($logMessage), };
            } catch (Throwable $e) { Log::emergency("Failed to write import log: " . $e->getMessage()); }
        };

        $logEntry('info', null, null, "Начало фоновой обработки файла (Наш склад): {$this->originalFileName}");

        try {
            $sheetIndex = 0; // Второй лист
            $sheetName = 'Наш склад';
            $fullPath = Storage::path($this->filePath);

            if (!Storage::exists($this->filePath)) { throw new \Exception("Временный файл не найден: {$this->filePath}"); }

            $logEntry('info', null, null, "Чтение файла...");
            $excelData = Excel::toCollection(null, $fullPath);
            if (!isset($excelData[$sheetIndex])) { throw new \Exception("Лист #{$sheetIndex} ('{$sheetName}') не найден в файле."); }
            $rows = $excelData[$sheetIndex];

            if ($rows->isEmpty()) { throw new \Exception("Лист #{$sheetIndex} ('{$sheetName}') пуст."); }

            $dataRows = $rows->slice(1);
            $totalRowsToProcess = $dataRows->count();
            if ($totalRowsToProcess === 0) {
                $logEntry('warning', null, null, "Нет данных для обработки (после пропуска заголовка).");
                goto cleanup;
            }

            $logEntry('info', null, null, "Найдено строк для обработки: {$totalRowsToProcess}. Начинаем построчное обновление...");

            $successCount = 0; $errorCount = 0; $skippedNoBarcodeCount = 0; $skippedNotFoundCount = 0;

            foreach ($dataRows as $index => $row) {
                $rowIndex = $index + 2;
                $barcode = trim($row[11] ?? null); // Колонка L
                $quantityStr = trim($row[8] ?? null); // Колонка I

                if (empty($barcode)) { $skippedNoBarcodeCount++; continue; }

                $quantity = filter_var($quantityStr, FILTER_VALIDATE_INT);
                if ($quantity === false || $quantity < 0) {
                    $errorCount++;
                    $logEntry('error', $rowIndex, $barcode, "Некорректное количество '{$quantityStr}'");
                    continue;
                }

                try {
                    $skuExists = DB::table('skus')->where('barcode', $barcode)->exists();
                    if ($skuExists) {
                        $skuStock = SkuStock::updateOrCreate(
                            ['sku_barcode' => $barcode],
                            ['stock_own' => $quantity] // Обновляем Свой склад
                        );
                        $logEntry('success', $rowIndex, $barcode, "Обновлено: Свой склад = {$quantity}");
                        $successCount++;
                    } else {
                        $skippedNotFoundCount++;
                        $logEntry('warning', $rowIndex, $barcode, "SKU не найден в справочнике ('skus').");
                    }
                } catch (Throwable $dbError) {
                    $errorCount++;
                    $logEntry('error', $rowIndex, $barcode, "Ошибка БД: " . $dbError->getMessage());
                }

                // Лог прогресса каждые 100 строк
                if (($index + 1) % 100 == 0) {
                    $processedRows = $index + 1;
                    $logEntry('info', null, null, 'Обработано ' . $processedRows . ' из ' . $totalRowsToProcess . ' строк...');
                }
            } // end foreach

            $message = "Обработка файла '{$this->originalFileName}' (Наш склад) завершена. Успешно: {$successCount}. ";
            if ($errorCount > 0) $message .= "Ошибок: {$errorCount}. ";
            if ($skippedNotFoundCount > 0) $message .= "Пропущено (SKU не найден): {$skippedNotFoundCount}. ";
            if ($skippedNoBarcodeCount > 0) $message .= "Пропущено строк без ШК: {$skippedNoBarcodeCount}. ";
            $logEntry('info', null, null, $message);

        } catch (Throwable $e) {
            $logEntry('error', null, null, 'Критическая ошибка при обработке файла: ' . $e->getMessage());
            Log::emergency("[CRITICAL ExcelImport:{$this->importType}:Batch {$this->batchId}] {$e->getMessage()}\n" . $e->getTraceAsString());
            // $this->fail($e); // Можно пометить задачу как проваленную
        }

        cleanup:
        if (Storage::exists($this->filePath)) {
            Storage::delete($this->filePath);
            $logEntry('info', null, null, "Временный файл удален: {$this->filePath}");
        }
    }
}
