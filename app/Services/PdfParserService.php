<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Appointment;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log;

class PdfParserService
{
    protected Parser $parser;

    protected array $stats = [
        'added' => 0,
        'updated' => 0,
        'cancelled' => 0,
        'skipped' => 0,
    ];

    public function __construct()
    {
        $this->parser = new Parser();
    }

    public function parse(string $filePath)
    {
        $pdf = $this->parser->parseFile($filePath);
        $text = preg_replace('/\s+/', ' ', $pdf->getText()); // нормализуем пробелы

        // Разделяем по врачам (ищем дату + имя врача)
        $blocks = preg_split(
            '/(?=\d{2}\.\d{2}\.\d{4}\s+[А-ЯЁA-ZӘІҢҒҮҰҚӨҺ][а-яёa-zәіңғүұқөһ]+\s+[А-ЯЁA-ZӘІҢҒҮҰҚӨҺ][а-яёa-zәіңғүұқөһ]+)/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        foreach ($blocks as $block) {
            if (!preg_match('/(\d{2}\.\d{2}\.\d{4})\s+([А-ЯЁA-ZӘІҢҒҮҰҚӨҺ][а-яёa-zәіңғүұқөһ]+\s+[А-ЯЁA-ZӘІҢҒҮҰҚӨҺ][а-яёa-zәіңғүұқөһ]+)/u', $block, $m)) {
                continue;
            }

            $date = date('Y-m-d', strtotime(str_replace('.', '-', $m[1])));
            $doctorName = $this->cleanName($m[2]);
            $doctor = Doctor::firstOrCreate(['name' => $doctorName]);

            // Парсим приёмы
            preg_match_all(
                '/(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2}).*?\(([^)]+)\)\s*([А-ЯЁA-ZӘІҢҒҮҰҚӨҺ][^+]+)\+?([\d\s\-\(\)+]*)\s*(.+?)(?=(?:\d{2}:\d{2}\s*-\s*\d{2}:\d{2}|Всего приемов|$))/su',
                $block,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $m) {
                $start = trim($m[1]);
                $end = trim($m[2]);
                $time = "{$start} - {$end}";
                $cabinet = trim($m[3]);
                $patientName = $this->cleanName($m[4]);

                // --- Извлекаем все телефоны ---
                $rawBlock = preg_replace('/(\d)(\d{2}:\d{2})/', '$1 $2', $m[0]); // вставляем пробел перед временем
                preg_match_all('/(\+?\d[\d\s\-()]{7,})/u', $rawBlock, $phones);

                $phones = collect($phones[1] ?? [])
                    ->map(function ($p) {
                        $p = preg_replace('/[^\d+]/', '', $p); // чистим всё, кроме цифр и +
                        if (str_starts_with($p, '8')) {
                            $p = '+7' . substr($p, 1);
                        } elseif (!str_starts_with($p, '+')) {
                            $p = '+' . $p;
                        }
                        return strlen($p) >= 10 ? $p : null;
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                $allPhones = count($phones) ? implode(', ', $phones) : null;
                $primaryPhone = $phones[0] ?? null;

                $service = trim($m[6]);

                if (!$patientName) {
                    $this->stats['skipped']++;
                    continue;
                }

                $patient = Patient::firstOrCreate(
                    ['full_name' => $patientName],
                    ['phone' => $allPhones ?? null]
                );

                if (!$patient->phone && !empty($allPhones)) {
                    $patient->update(['phone' => $allPhones]);
                }

                $isCancelled = ($start === '00:00' || $end === '00:00');
                $status = $isCancelled ? 'cancelled' : 'scheduled';

                $appointment = Appointment::where([
                    ['doctor_id', $doctor->id],
                    ['patient_id', $patient->id],
                    ['date', $date],
                ])->first();

                if ($appointment) {
                    $appointment->update([
                        'time' => $time,
                        'service' => $service ?: 'Не указано',
                        'cabinet' => $cabinet ?: '',
                        'status' => $status,
                        'phones' => $allPhones,
                    ]);
                    $this->stats['updated']++;
                } else {
                    Appointment::create([
                        'doctor_id' => $doctor->id,
                        'patient_id' => $patient->id,
                        'date' => $date,
                        'time' => $time,
                        'service' => $service ?: 'Не указано',
                        'cabinet' => $cabinet ?: '',
                        'status' => $status,
                        'phones' => $allPhones,
                    ]);
                    $isCancelled ? $this->stats['cancelled']++ : $this->stats['added']++;
                }
            }
        }

        return $this->stats;
    }

    protected function cleanName(string $text): string
    {
        $text = preg_replace('/\b(Время|врач|каб\.?|кб\.?)\b/iu', '', $text);
        $text = trim(preg_replace('/[^А-Яа-яЁёӘІҢҒҮҰҚӨҺ\s-]/u', '', $text));
        $parts = preg_split('/\s+/', $text);
        return implode(' ', array_slice($parts, 0, 2));
    }
}
