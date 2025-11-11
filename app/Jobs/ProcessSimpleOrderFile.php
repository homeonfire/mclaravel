<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\SimpleOrder;
use App\Models\ExcelImportLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessSimpleOrderFile implements ShouldQueue
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
        $this->importType = 'simple-order';
        $this->batchId = $batchId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // *** ИСПРАВЛЕНИЕ: Определение $logEntry ПЕРЕМЕЩЕНО ВВЕРХ ***
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

                match ($status) {
                    'error' => Log::error($logMessage),
                    'warning' => Log::warning($logMessage),
                    default => Log::info($logMessage),
                };
            } catch (Throwable $e) {
                Log::emergency("Failed to write import log: " . $e->getMessage());
            }
        };

        // Теперь этот вызов корректен
        $logEntry('info', null, null, "Начало фоновой обработки файла (Простой заказ): {$this->originalFileName}");

        try {
            $sheetIndex = 0; // Первый лист
            $fullPath = Storage::path($this->filePath);

            if (!Storage::exists($this->filePath)) {
                throw new \Exception("Временный файл не найден: {$this->filePath}");
            }

            $logEntry('info', null, null, "Чтение файла...");
            $excelData = Excel::toCollection(null, $fullPath);
            if (!isset($excelData[$sheetIndex])) {
                throw new \Exception("Лист #{$sheetIndex} не найден.");
            }
            $rows = $excelData[$sheetIndex];

            $dataRows = $rows->slice(1); // Пропускаем заголовок (Строка 1)
            $totalRowsToProcess = $dataRows->count();
            if ($totalRowsToProcess === 0) {
                $logEntry('warning', null, null, "Нет данных для обработки (после пропуска заголовка).");
                goto cleanup;
            }

            $logEntry('info', null, null, "Найдено строк для обработки: {$totalRowsToProcess}. Начинаем обновление...");

            $successCount = 0;
            $errorCount = 0;
            $skippedNoBarcodeCount = 0;
            $skippedNotFoundCount = 0;

            foreach ($dataRows as $index => $row) {
                $rowIndex = $index + 2;
                $barcode = trim($row[1] ?? null);     // Колонка B (Штрихкод)
                $quantityStr = trim($row[3] ?? null); // Колонка D (Количество)

                if (empty($barcode)) {
                    $skippedNoBarcodeCount++;
                    continue; // Не логируем пустые
                }

                $quantity = filter_var($quantityStr, FILTER_VALIDATE_INT);
                if ($quantity === false || $quantity < 0) {
                    $errorCount++;
                    $logEntry('error', $rowIndex, $barcode, "Некорректное количество '{$quantityStr}'");
                    continue;
                }

                try {
                    if (!DB::table('skus')->where('barcode', $barcode)->exists()) {
                        $logEntry('warning', $rowIndex, $barcode, "SKU не найден в справочнике 'skus'. Строка пропущена.");
                        $skippedNotFoundCount++;
                        continue;
                    }

                    SimpleOrder::updateOrCreate(
                        ['sku_barcode' => $barcode], // Поле для поиска
                        ['order_quantity' => $quantity] // Поле для обновления/вставки
                    );
                    $successCount++;

                } catch (Throwable $dbError) {
                    $logEntry('error', $rowIndex, $barcode, "Ошибка БД: " . $dbError->getMessage());
                    $errorCount++;
                }

                if (($index + 1) % 100 == 0) {
                    $processedRows = $index + 1;
                    $logEntry('info', null, null, 'Обработано ' . $processedRows . ' из ' . $totalRowsToProcess . ' строк...');
                }

            } // end foreach

            $message = "Обработка файла '{$this->originalFileName}' (Простой заказ) завершена. Успешно: {$successCount}. ";
            if ($errorCount > 0) $message .= "Ошибок: {$errorCount}. ";
            if ($skippedNotFoundCount > 0) $message .= "Пропущено (SKU не найден): {$skippedNotFoundCount}. ";
            if ($skippedNoBarcodeCount > 0) $message .= "Пропущено строк без ШК: {$skippedNoBarcodeCount}. ";
            $logEntry('info', null, null, $message);

        } catch (Throwable $e) {
            $logEntry('error', null, null, 'Критическая ошибка при обработке файла: ' . $e->getMessage());
            Log::emergency("[CRITICAL ExcelImport:{$this->importType}:Batch {$this->batchId}] {$e->getMessage()}\n" . $e->getTraceAsString());
            $this.fail($e);
        }

        cleanup:
        if (Storage::exists($this->filePath)) {
            Storage::delete($this->filePath);
            $logEntry('info', null, null, "Временный файл удален: {$this->filePath}");
        }
    }
}
