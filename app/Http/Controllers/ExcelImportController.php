<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExcelImportLog;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage; // Добавляем Storage
use App\Jobs\ProcessInTransitExcel; // <-- Наша новая задача (Job)
use Illuminate\Support\Str;
use Throwable;
use App\Jobs\ProcessOwnStockExcel; // <-- Добавляем новую задачу

class ExcelImportController extends Controller
{
    public function showInTransitForm()
    {
        $logs = ExcelImportLog::where('import_type', 'in-transit-to-wb')
            ->orderBy('created_at', 'desc')
            ->limit(50) // Можно увеличить лимит
            ->get()
            ->groupBy('batch_id');

        return view('excel-import.in-transit-to-wb', compact('logs'));
    }

    public function processInTransitFile(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx|max:10240', // 10MB max
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors(['excel_file' => $e->validator->errors()->first()])->withInput();
        }

        $file = $request->file('excel_file');
        $originalFileName = $file->getClientOriginalName();
        $batchId = Str::uuid()->toString();

        try {
            // Сохраняем файл во временную папку (например, storage/app/temp_excel_imports)
            // Убедись, что папка существует или создай ее
            $path = $file->store('temp_excel_imports');

            // Ставим задачу в очередь
            ProcessInTransitExcel::dispatch($path, $originalFileName, $batchId);

            // Записываем стартовый лог
            ExcelImportLog::create([
                'import_type' => 'in-transit-to-wb',
                'batch_id' => $batchId,
                'status' => 'queued',
                'message' => "Файл '{$originalFileName}' добавлен в очередь на обработку.",
            ]);
            Log::info("[ExcelImport:in-transit-to-wb:Batch $batchId] File '{$originalFileName}' queued for processing.");

            return back()->with('success', "Файл '{$originalFileName}' успешно загружен и добавлен в очередь на обработку. Результаты появятся в логе ниже через некоторое время.");

        } catch (Throwable $e) {
            Log::error("Failed to store uploaded file or dispatch job: " . $e->getMessage());
            return back()->with('error', 'Не удалось сохранить файл или поставить задачу в очередь. Ошибка: ' . $e->getMessage());
        }
    }

    // НОВЫЙ МЕТОД: Обработать файл для "Нашего склада"
    public function processOwnStockFile(Request $request)
    {
        try {
            $request->validate([ 'excel_file' => 'required|file|mimes:xlsx|max:10240', ]);
        } catch (ValidationException $e) {
            return back()->withErrors(['excel_file' => $e->validator->errors()->first()])->withInput();
        }

        $file = $request->file('excel_file');
        $originalFileName = $file->getClientOriginalName();
        $batchId = Str::uuid()->toString();
        $importType = 'own-stock'; // <-- Новый тип

        try {
            $path = $file->store('temp_excel_imports');
            ProcessOwnStockExcel::dispatch($path, $originalFileName, $batchId); // <-- Новая задача

            ExcelImportLog::create([
                'import_type' => $importType,
                'batch_id' => $batchId,
                'status' => 'queued',
                'message' => "Файл '{$originalFileName}' (Наш склад) добавлен в очередь.",
            ]);
            Log::info("[ExcelImport:$importType:Batch $batchId] File '{$originalFileName}' queued.");

            return back()->with('success', "Файл '{$originalFileName}' успешно загружен и добавлен в очередь.");

        } catch (Throwable $e) {
            Log::error("Failed to store uploaded file or dispatch job ($importType): " . $e->getMessage());
            return back()->with('error', 'Не удалось сохранить файл или поставить задачу в очередь. Ошибка: ' . $e->getMessage());
        }
    }

    // НОВЫЙ МЕТОД: Показать форму для "Нашего склада"
    public function showOwnStockForm()
    {
        $logs = ExcelImportLog::where('import_type', 'own-stock') // <-- Фильтр по новому типу
        ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->groupBy('batch_id');

        return view('excel-import.own-stock', compact('logs')); // <-- Новый view
    }
}
