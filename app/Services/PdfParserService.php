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

    public function parse(string $filePath): array
    {
        $pdf = $this->parser->parseFile($filePath);
        $text = $pdf->getText();

        // --- Разделяем по врачам ---
        $blocks = preg_split(
            '/(?=\d{2}\.\d{2}\.\d{4}\s+[А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][а-яёa-z]+)/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        foreach ($blocks as $block) {
            // --- Ищем дату и врача ---
            if (
                !preg_match(
                    '/(\d{2}\.\d{2}\.\d{4})\s+([А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][а-яёa-z]+(?:\s+[А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][а-яёa-z]+){0,2})/u',
                    $block,
                    $m
                )
            ) {
                continue;
            }

            $date = date('Y-m-d', strtotime(str_replace('.', '-', $m[1])));
            $doctorName = $this->getShortName(trim($m[2]));
            $doctor = Doctor::firstOrCreate(['name' => $doctorName]);

            // --- Ищем приёмы ---
            preg_match_all(
                '/(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})\s*(.*?)\((.*?)\)\s*([А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][^+]+)\+?\s*([+]?\d[\d\s\-()]{7,})?\s*(.+?)(?=(?:\d{2}:\d{2}\s*-\s*\d{2}:\d{2}|Всего приемов|$))/su',
                $block,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $m) {
                $start = trim($m[1]);
                $end = trim($m[2]);
                $time = "{$start} - {$end}";

                $cabinet = trim($m[4] ?? '');
                $patientName = $this->getShortName(trim(preg_replace("/\s+/", ' ', $m[5])));

                if (!$patientName) {
                    $this->stats['skipped']++;
                    continue;
                }

                // --- Извлекаем телефоны ---
                preg_match_all('/(\+?\d[\d\s\-()]{7,})/u', $m[0], $phones);
                $phones = array_map(fn($p) => preg_replace('/\D+/', '', $p), $phones[1] ?? []);
                $phones = array_filter($phones);
                $primaryPhone = $phones[0] ?? null;

                $service = trim(preg_replace("/\s+/", ' ', $m[7]));

                // --- Пациент ---
                $patient = Patient::firstOrCreate(
                    ['full_name' => $patientName],
                    ['phone' => $primaryPhone ?? '']
                );

                if (!$patient->phone && $primaryPhone) {
                    $patient->update(['phone' => $primaryPhone]);
                }

                // --- Определяем статус ---
                $status = ($start === '00:00') ? 'cancelled' : 'scheduled';

                // --- Проверяем запись ---
                $appointment = Appointment::where([
                    ['doctor_id', $doctor->id],
                    ['patient_id', $patient->id],
                    ['date', $date],
                ])->first();

                if ($appointment) {
                    // Если уже есть, обновим время и статус
                    $appointment->update([
                        'time' => $time,
                        'service' => $service ?: 'Не указано',
                        'cabinet' => $cabinet ?: '',
                        'status' => $status,
                    ]);

                    $this->stats['updated']++;

                    Log::info("🔁 Обновлено: {$doctorName} — {$patientName} — {$date} {$time} ({$status})");
                } else {
                    // Если новой нет, создаём
                    Appointment::create([
                        'doctor_id' => $doctor->id,
                        'patient_id' => $patient->id,
                        'service' => $service ?: 'Не указано',
                        'cabinet' => $cabinet ?: '',
                        'date' => $date,
                        'time' => $time,
                        'status' => $status,
                    ]);

                    if ($status === 'cancelled') {
                        $this->stats['cancelled']++;
                        Log::info("❌ Отменён: {$doctorName} — {$patientName} — {$date} {$time}");
                    } else {
                        $this->stats['added']++;
                        Log::info("➕ Добавлено: {$doctorName} — {$patientName} — {$date} {$time}");
                    }
                }
            }
        }

        return $this->stats;
    }

    /**
     * Берёт только фамилию и имя (без отчества)
     */
    protected function getShortName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName));
        return implode(' ', array_slice($parts, 0, 2));
    }

    public function getStats(): array
    {
        return $this->stats;
    }
}
