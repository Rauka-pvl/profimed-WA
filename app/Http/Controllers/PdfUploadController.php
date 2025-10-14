<?php

namespace App\Http\Controllers;

use App\Services\PdfParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PdfUploadController extends Controller
{
    protected $pdfParser;

    public function __construct(PdfParserService $pdfParser)
    {
        $this->pdfParser = $pdfParser;
    }

    public function showUploadForm()
    {
        return view('appointments.upload');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:102400', // max 100MB
        ]);

        try {
            // Сохраняем PDF
            $file = $request->file('pdf');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('pdfs', $filename);

            // Получаем полный путь к файлу
            $fullPath = Storage::path($path);

            // Парсим PDF
            $stats = $this->pdfParser->parse($fullPath);

            return redirect()->route('appointment.upload')
                ->with('success', 'PDF успешно загружен и обработан!')
                ->with('stats', $stats);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Ошибка при обработке PDF: ' . $e->getMessage());
        }
    }
}
