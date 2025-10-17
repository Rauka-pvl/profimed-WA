<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Appointment;
use Smalot\PdfParser\Parser;
use Carbon\Carbon;

class PdfParserService
{
    protected Parser $parser;

    protected array $stats = [
        'added' => 0,
        'updated' => 0,
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

        // --- Разбиваем на блоки по врачам ---
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
                    '/(\d{2}\.\d{2}\.\d{4})\s+([А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][а-яёa-zәіңғүұқөһ]+(?:\s+[А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][а-яёa-zәіңғүұқөһ]+)?)(?=\s+(?!Время))/u',
                    $block,
                    $m
                )
            ) {
                continue;
            }

            $date = date('Y-m-d', strtotime(str_replace('.', '-', $m[1])));
            $doctorName = trim($m[2]);
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
                $patientName = trim(preg_replace("/\s+/", ' ', $m[5]));

                // 🔹 Извлекаем все номера телефонов (могут быть через запятую, /, пробел)
                preg_match_all('/(\+?\d[\d\s\-()]{7,})/u', $m[0], $phones);
                $phones = array_map(fn($p) => preg_replace('/\D+/', '', $p), $phones[1] ?? []);
                $phones = array_filter($phones); // убираем пустые
                $primaryPhone = $phones[0] ?? null;
                $allPhones = implode(', ', array_unique($phones));

                $service = trim(preg_replace("/\s+/", ' ', $m[7]));

                if (!$patientName) {
                    $this->stats['skipped']++;
                    continue;
                }

                // 🔹 Пациент
                $patient = Patient::firstOrCreate(
                    ['full_name' => $patientName],
                    ['phone' => $primaryPhone ?? '']
                );

                // 🔹 Обновим телефон, если он появился
                if (!$patient->phone && $primaryPhone) {
                    $patient->update(['phone' => $primaryPhone]);
                }

                // 🔹 Проверим, не "отменённый" ли приём
                $isCancelled = ($start === '00:00' || $end === '00:00');

                // 🔹 Ищем существующий приём по пациенту, врачу и дате
                $appointment = Appointment::where([
                    ['doctor_id', $doctor->id],
                    ['patient_id', $patient->id],
                    ['date', $date],
                ])->first();

                if ($appointment) {
                    // 🔸 Если новое время 00:00 → отменяем
                    if ($isCancelled) {
                        $appointment->update([
                            'status' => 'cancelled',
                            'time' => '00:00',
                            'cabinet' => $cabinet ?: '',
                            'service' => $service ?: 'Не указано',
                        ]);
                        $this->stats['updated']++;
                        continue;
                    }

                    // 🔸 Если уже существующая запись имела другое время — обновляем как запланированную
                    if ($appointment->time !== $time) {
                        $appointment->update([
                            'time' => $time,
                            'status' => 'scheduled',
                            'cabinet' => $cabinet ?: '',
                            'service' => $service ?: 'Не указано',
                        ]);
                        $this->stats['updated']++;
                        continue;
                    }

                    // 🔸 Если совпадает полностью — просто обновляем данные
                    $appointment->update([
                        'service' => $service ?: 'Не указано',
                        'cabinet' => $cabinet ?: '',
                    ]);
                    $this->stats['updated']++;
                } else {
                    // 🔹 Новый приём
                    Appointment::create([
                        'doctor_id' => $doctor->id,
                        'patient_id' => $patient->id,
                        'service' => $service ?: 'Не указано',
                        'cabinet' => $cabinet ?: '',
                        'date' => $date,
                        'time' => $time,
                        'status' => $isCancelled ? 'cancelled' : 'scheduled',
                    ]);
                    $this->stats['added']++;
                }
            }
        }

        return $this->stats;
    }

    public function getStats(): array
    {
        return $this->stats;
    }
}
