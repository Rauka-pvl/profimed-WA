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

        // --- Разбиваем по врачам (убираем слово "Время") ---
        $blocks = preg_split(
            '/(?=\d{2}\.\d{2}\.\d{4}\s+[А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][а-яёa-z]+\s+[А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][а-яёa-z]+)/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        foreach ($blocks as $block) {
            // --- Ищем дату и врача (только фамилия + имя) ---
            if (
                !preg_match(
                    '/(\d{2}\.\d{2}\.\d{4})\s+([А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][а-яёa-z]+)\s+([А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][а-яёa-z]+)/u',
                    $block,
                    $m
                )
            ) {
                continue;
            }

            $date = date('Y-m-d', strtotime(str_replace('.', '-', $m[1])));
            $doctorName = trim("{$m[2]} {$m[3]}");
            $doctor = Doctor::firstOrCreate(['name' => $doctorName]);

            // --- Ищем приёмы ---
            preg_match_all(
                '/(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2}).*?\((.*?)\)\s*([А-ЯЁA-ZЁӘІҢҒҮҰҚӨҺ][^+]+)\+?\s*([+]?\d[\d\s\-()]{7,})?\s*(.+?)(?=(?:\d{2}:\d{2}|Всего|$))/su',
                $block,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $m) {
                $start = trim($m[1]);
                $end = trim($m[2]);
                $time = "{$start} - {$end}";

                // Отметка отменённого приёма
                $isCancelled = ($start === '00:00' || $end === '00:00');

                $cabinet = trim($m[3] ?? '');
                $patientName = trim(preg_replace("/\s+/", ' ', $m[4]));

                // Берём только фамилию и имя пациента
                if (preg_match('/^([А-ЯЁӘІҢҒҮҰҚӨҺ][а-яёәіңғүұқөһ]+)\s+([А-ЯЁӘІҢҒҮҰҚӨҺ][а-яёәіңғүұқөһ]+)/u', $patientName, $pm)) {
                    $patientName = "{$pm[1]} {$pm[2]}";
                }

                preg_match_all('/(\+?\d[\d\s\-()]{7,})/u', $m[0], $phones);
                $phones = array_map(fn($p) => preg_replace('/\D+/', '', $p), $phones[1] ?? []);
                $phones = array_filter($phones);
                $primaryPhone = $phones[0] ?? null;

                $service = trim(preg_replace("/\s+/", ' ', $m[6] ?? ''));

                if (!$patientName) {
                    $this->stats['skipped']++;
                    continue;
                }

                // Пациент
                $patient = Patient::firstOrCreate(
                    ['full_name' => $patientName],
                    ['phone' => $primaryPhone ?? '']
                );

                if (!$patient->phone && $primaryPhone) {
                    $patient->update(['phone' => $primaryPhone]);
                }

                // Проверим существующую запись
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
