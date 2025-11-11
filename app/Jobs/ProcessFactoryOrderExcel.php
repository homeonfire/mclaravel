<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\FactoryStatus;
use App\Models\ExcelImportLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon; // Убедись, что Carbon подключен

class ProcessFactoryOrderExcel implements ShouldQueue
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
        $this->importType = 'factory-order';
        $this->batchId = $batchId;
    }

    private function logEntry($status, $rowNum, $barcode, $message) {
        try {
            ExcelImportLog::create([
                'import_type' => $this->importType, 'batch_id' => $this->batchId, 'status' => $status,
                'row_number' => $rowNum, 'barcode' => $barcode, 'message' => $message,
            ]);
            $logMessage = "[ExcelImport:{$this->importType}:Batch {$this->batchId}] Row $rowNum ($barcode): $message";
            match ($status) { 'error' => Log::error($logMessage), 'warning' => Log::warning($logMessage), default => Log::info($logMessage), };
        } catch (Throwable $e) { Log::emergency("Failed to write import log: " . $e->getMessage()); }
    }

    public function handle(): void
    {
        $this->logEntry('info', null, null, "Начало фоновой обработки файла: {$this->originalFileName}");

        try {
            $sheetIndex = 0;
            $fullPath = Storage::path($this->filePath);
            if (!Storage::exists($this->filePath)) { throw new \Exception("Временный файл не найден: {$this->filePath}"); }

            $this->logEntry('info', null, null, "Чтение файла...");
            $excelData = Excel::toCollection(null, $fullPath);
            if (!isset($excelData[$sheetIndex])) { throw new \Exception("Лист #{$sheetIndex} не найден."); }
            $rows = $excelData[$sheetIndex];

            $dataRows = $rows->slice(1);
            if ($dataRows->isEmpty()) {
                $this->logEntry('warning', null, null, "Нет данных для обработки (после пропуска заголовка).");
                goto cleanup;
            }

            $this->logEntry('info', null, null, "Найдено строк для обработки: " . $dataRows->count() . ". Начинаем парсинг...");

            $successCount = 0; $errorCount = 0; $skippedCount = 0;

            // Временный массив для хранения данных из "строки-заголовка" артикула
            $currentArticleData = null;

            foreach ($dataRows as $index => $row) {
                $rowIndex = $index + 2;

                $factoryArticle = trim($row[0] ?? null); // Колонка A (款号)
                $barcode = trim($row[2] ?? null);        // Колонка C (ШК)

                // *** НОВАЯ ЛОГИКА ***

                // ШАГ 1: Если Колонка A (Артикул) НЕ ПУСТАЯ, это 100% начало новой группы.
                // Мы обновляем $currentArticleData, даже если в этой строке есть баркод.
                if (!empty($factoryArticle)) {
                    $this->logEntry('info', $rowIndex, $barcode, "Найдена новая группа артикула: {$factoryArticle}");

                    // Конвертируем дату Excel (может быть числом)
                    $excelDate = $row[4] ?? null; // Колонка E
                    $prodStartDate = null;
                    if (!empty($excelDate)) {
                        try {
                            if (is_numeric($excelDate)) {
                                $prodStartDate = Date::excelToDateTimeObject($excelDate)->format('Y-m-d');
                            } else {
                                $prodStartDate = Carbon::parse($excelDate)->format('Y-m-d');
                            }
                        } catch (Throwable $e) {
                            $this->logEntry('warning', $rowIndex, $factoryArticle, "Не удалось распознать дату '{$excelDate}'.");
                        }
                    }

                    // Сохраняем данные заголовка
                    $currentArticleData = [
                        'factory_article'     => $factoryArticle,
                        'wb_article'          => trim($row[1] ?? null), // B
                        'production_start_date' => $prodStartDate,         // E
                        'production_status'   => trim($row[5] ?? null), // F
                        'status_at_factory'     => (int)($row[9] ?? 0),  // J
                        'status_in_washing'     => (int)($row[10] ?? 0), // K
                        'status_in_transit_to_sklad' => (int)($row[11] ?? 0), // L
                        'status_post_processing'=> (int)($row[12] ?? 0), // M
                        'shipped_quantity'      => (int)($row[13] ?? 0), // N
                    ];
                }

                // ШАГ 2: Если Колонка C (Баркод) НЕ ПУСТАЯ, это строка SKU.
                // Мы должны ее обработать.
                if (!empty($barcode)) {
                    // Проверяем, есть ли у нас "родительские" данные (на случай, если файл начался с SKU)
                    if ($currentArticleData === null) {
                        $this->logEntry('warning', $rowIndex, $barcode, "Найден SKU, но для него не найдена строка-заголовок артикула (в Колонке A). Строка пропущена.");
                        $skippedCount++;
                        continue; // Пропускаем этот SKU
                    }

                    // Проверяем, существует ли SKU в нашей базе
                    if (!DB::table('skus')->where('barcode', $barcode)->exists()) {
                        $this->logEntry('warning', $rowIndex, $barcode, "SKU не найден в справочнике 'skus'. Строка пропущена.");
                        $skippedCount++;
                        continue; // Пропускаем этот SKU
                    }

                    // Все проверки пройдены, готовим данные SKU
                    $skuData = [
                        'size_name'       => trim($row[3] ?? null), // Колонка D (码比)
                        'order_quantity'  => (int)($row[6] ?? 0),  // Колонка G (下单数)
                    ];

                    // Объединяем "родительские" данные и данные SKU
                    $finalData = array_merge($currentArticleData, $skuData);

                    // Обновляем или создаем запись в БД
                    try {
                        FactoryStatus::updateOrCreate(
                            ['sku_barcode' => $barcode], // Уникальный ключ
                            $finalData // Данные для обновления или вставки
                        );
                        $successCount++;
                    } catch (Throwable $dbError) {
                        $this->logEntry('error', $rowIndex, $barcode, "Ошибка БД: " . $dbError->getMessage());
                        $errorCount++;
                    }

                    // ШАГ 3: Если и Колонка A, и Колонка C пустые - это мусорная строка.
                } elseif (empty($factoryArticle) && empty($barcode)) {
                    // $this->logEntry('info', $rowIndex, null, "Пропущена пустая строка.");
                    $skippedCount++;
                }

            } // end foreach

            $message = "Обработка файла '{$this->originalFileName}' (Заказ с завода) завершена. Успешно: {$successCount}. ";
            if ($errorCount > 0) $message .= "Ошибок: {$errorCount}. ";
            if ($skippedCount > 0) $message .= "Пропущено (пустых/ненайденных): {$skippedCount}. ";
            $this->logEntry('info', null, null, $message);

        } catch (Throwable $e) { /* ... обработка критической ошибки ... */ }

        cleanup:
        if (Storage::exists($this->filePath)) {
            Storage::delete($this->filePath);
            $this->logEntry('info', null, null, "Временный файл удален: {$this->filePath}");
        }
    }
}
