<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Appointment;
use Smalot\PdfParser\Parser;

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
                    '/(\d{2}\.\d{2}\.\d{4})\s+([А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][а-яёa-zәіңғүұқөһ]+(?:\s+[А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][а-яёa-zәіңғүұқөһ]+){0,2})/u',
                    $block,
                    $m
                )
            ) {
                continue;
            }

            $date = date('Y-m-d', strtotime(str_replace('.', '-', $m[1])));

            // 🔹 Берём только фамилию и имя врача
            $doctorNameParts = explode(' ', trim($m[2]));
            $doctorName = implode(' ', array_slice($doctorNameParts, 0, 2));

            $doctor = Doctor::firstOrCreate(['name' => $doctorName]);

            // --- Ищем приёмы ---
            preg_match_all(
                '/(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})\s*(.*?)\((.*?)\)\s*([А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][^+]+)\+?\s*([+]?\d[\d\s\-()]{7,})?\s*(.+?)(?=(?:\d{2}:\д{2}\s*-\s*\д{2}:\д{2}|Всего приемов|$))/su',
                $block,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $m) {
                $start = trim($m[1]);
                $end = trim($m[2]);
                $time = "{$start} - {$end}";

                $cabinet = trim($m[4] ?? '');

                // 🔹 Берём только ФИО пациента — фамилия + имя
                $patientFull = trim(preg_replace("/\s+/", ' ', $m[5]));
                $patientParts = explode(' ', $patientFull);
                $patientName = implode(' ', array_slice($patientParts, 0, 2));

                // --- Извлекаем телефоны ---
                preg_match_all('/(\+?\d[\d\s\-()]{7,})/u', $m[0], $phones);
                $phones = array_map(fn($p) => preg_replace('/\D+/', '', $p), $phones[1] ?? []);
                $phones = array_filter($phones);
                $primaryPhone = $phones[0] ?? null;

                $service = trim(preg_replace("/\s+/", ' ', $m[7]));

                if (!$patientName) {
                    $this->stats['skipped']++;
                    continue;
                }

                // --- Пациент ---
                $patient = Patient::firstOrCreate(
                    ['full_name' => $patientName],
                    ['phone' => $primaryPhone ?? '']
                );

                if (!$patient->phone && $primaryPhone) {
                    $patient->update(['phone' => $primaryPhone]);
                }

                // --- Проверим статус ---
                $isCancelled = ($start === '00:00');

                // --- Проверим запись ---
                $appointment = Appointment::where([
                    ['doctor_id', $doctor->id],
                    ['patient_id', $patient->id],
                    ['date', $date],
                    ['time', $time],
                ])->first();

                if ($appointment) {
                    $appointment->update([
                        'service' => $service ?: 'Не указано',
                        'cabinet' => $cabinet ?: '',
                        'status' => $isCancelled ? 'cancelled' : 'scheduled',
                    ]);
                    $this->stats['updated']++;
                } else {
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
