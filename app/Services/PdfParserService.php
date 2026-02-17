<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\PatientDeviceToken;
use Illuminate\Support\Str;
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

    protected array $doctorBlacklist = [
        'куличкина людмила',
        'отделение проф.осмотра',
        'отделение профосмотра',
        'физио кабинет',
        'косарев андрей',
        'процедурный кабинет',
        'прививочный кабинет',
        'реализация товаров',
        'регистрация лабораторных',
        'регистратор узи',
    ];

    public function __construct()
    {
        $this->parser = new Parser();
    }

    public function parse(string $filePath)
    {
        $pdf = $this->parser->parseFile($filePath);
        $rawText = $pdf->getText();

        // Нормализуем переносы строк
        $text = preg_replace('/\r\n|\r/', "\n", $rawText);
        $lines = explode("\n", $text);

        $date = $this->extractDate($lines);
        $doctorName = $this->extractDoctorName($lines);
        $defaultService = $this->extractDefaultService($text);

        if (!$date || !$doctorName) {
            Log::warning('PDF: не удалось извлечь дату или врача', compact('date', 'doctorName'));
            return $this->stats;
        }

        $dateStr = $date->format('Y-m-d');
        $doctorName = $this->cleanDoctorName($doctorName);

        if ($this->isBlacklisted($doctorName)) {
            Log::info("Врач пропущен (черный список): {$doctorName}");
            return $this->stats;
        }

        $doctor = Doctor::firstOrCreate(['name' => $doctorName]);
        Log::info("Обработка врача: {$doctorName}, дата: {$dateStr}");

        $rows = $this->extractAppointmentRows($lines);

        foreach ($rows as $row) {
            $this->processAppointmentRow($row, $doctor, $dateStr, $defaultService);
        }

        return $this->stats;
    }

    /**
     * Извлечь дату из PDF (Период: DD.MM.YYYY или строка DD.MM.YYYY)
     */
    protected function extractDate(array $lines): ?\DateTime
    {
        foreach ($lines as $line) {
            if (preg_match('/Период:\s*(\d{2})\.(\d{2})\.(\d{4})/u', $line, $m)) {
                $d = \DateTime::createFromFormat('Y-m-d', "{$m[3]}-{$m[2]}-{$m[1]}");
                return $d ?: null;
            }
            if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\s+/u', trim($line), $m)) {
                $d = \DateTime::createFromFormat('Y-m-d', "{$m[3]}-{$m[2]}-{$m[1]}");
                return $d ?: null;
            }
        }
        return null;
    }

    /**
     * Извлечь имя врача (Специалисты: ФИО или из строки DD.MM.YYYY Фамилия Имя)
     */
    protected function extractDoctorName(array $lines): ?string
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/Специалисты:\s*(.+)/u', $line, $m)) {
                return trim($m[1]);
            }
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}\s+(.+)/u', $line, $m)) {
                return trim($m[1]);
            }
        }
        return null;
    }

    /**
     * Извлечь услугу по умолчанию (строка после "Услуги" до "Всего приемов")
     */
    protected function extractDefaultService(string $text): string
    {
        if (preg_match('/Услуги\s*\n\s*([^\n]+?)(?=\s*Всего приемов|$)/su', $text, $m)) {
            $service = trim(preg_replace('/\s+/', ' ', $m[1]));
            return $service ?: 'Не указано';
        }
        return 'Не указано';
    }

    /**
     * Разбить текст на блоки по строкам с временем приёма и извлечь данные по каждому приёму
     */
    protected function extractAppointmentRows(array $lines): array
    {
        $rows = [];
        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            $line = trim($lines[$i]);

            // Ищем строку с временем вида "07:45 - 08:00"
            if (preg_match('/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})\s*$/u', $line, $timeMatch)) {
                $time = $timeMatch[1] . ' - ' . $timeMatch[2];
                $blockLines = [];

                $i++;
                while ($i < $n) {
                    $next = trim($lines[$i]);
                    // Следующий приём или конец блока
                    if (preg_match('/^\d{2}:\d{2}\s*-\s*\d{2}:\d{2}\s*$/u', $next)) {
                        break;
                    }
                    if (Str::startsWith(mb_strtolower($next), 'всего приемов')) {
                        break;
                    }
                    if ($next !== '') {
                        $blockLines[] = $next;
                    }
                    $i++;
                }

                $row = $this->parseBlockIntoRow($time, $blockLines);
                if ($row) {
                    $rows[] = $row;
                }
                continue;
            }

            $i++;
        }

        return $rows;
    }

    /**
     * Из блока строк после времени извлечь кабинет, ФИО, телефон, услугу
     */
    protected function parseBlockIntoRow(string $time, array $blockLines): ?array
    {
        $cabinetParts = [];
        $patientParts = [];
        $phone = null;
        $service = null;

        foreach ($blockLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Номер телефона
            if (preg_match('/^\+?[78][\s\d\-]{10,}$/u', preg_replace('/\s+/', '', $line))) {
                $phone = preg_replace('/[^\d+]/', '', $line);
                if (str_starts_with($phone, '8')) {
                    $phone = '+7' . substr($phone, 1);
                } elseif (preg_match('/^7\d{10}$/', $phone)) {
                    $phone = '+' . $phone;
                }
                continue;
            }

            // Уже нашли телефон — следующая короткая строка может быть услуга
            if ($phone !== null && $service === null && mb_strlen($line) < 100 && !preg_match('/^\d{2}:\d{2}/', $line)) {
                $service = $line;
                continue;
            }

            // Служебные слова — не ФИО
            if (preg_match('/кб\.|врач|диагностики|кабинет|цоколь|№\d+|функциональной|\(|\)/iu', $line)) {
                if (empty($patientParts)) {
                    $cabinetParts[] = $line;
                }
                continue;
            }

            // Похоже на ФИО: только буквы (кириллица/латиница) и пробелы, без цифр
            if (preg_match('/^[А-Яа-яЁёӘәІіҢңҒғҮүҰұҚқӨөҺһA-Za-z\s\-]+$/u', $line) && !preg_match('/\d/', $line)) {
                $patientParts[] = $line;
                continue;
            }

            // Всё остальное до первого ФИО — кабинет
            if (empty($patientParts) && $phone === null) {
                $cabinetParts[] = $line;
            }
        }

        $patientName = $this->cleanPatientName(implode(' ', $patientParts));
        if ($patientName === '') {
            return null;
        }

        return [
            'time' => $time,
            'cabinet' => trim(implode(' ', $cabinetParts)) ?: '',
            'patient_name' => $patientName,
            'phone' => $phone,
            'service' => $service,
        ];
    }

    protected function processAppointmentRow(array $row, Doctor $doctor, string $dateStr, string $defaultService): void
    {
        $time = $row['time'];
        $cabinet = $row['cabinet'];
        $patientName = $row['patient_name'];
        $phone = $row['phone'];
        $service = $row['service'] ?? $defaultService;

        $isCancelled = (Str::contains($time, '00:00'));
        $status = $isCancelled ? 'cancelled' : 'scheduled';

        if (Str::contains(mb_strtolower($service), ['на дому', 'выезд'])) {
            $this->stats['skipped']++;
            Log::info("Приём пропущен (выезд): {$patientName}");
            return;
        }

        $patient = Patient::firstOrCreate(
            ['full_name' => $patientName],
            ['phone' => $phone ?: null]
        );

        if (!$patient->phone && $phone) {
            $patient->update(['phone' => $phone]);
        }

        $appointment = Appointment::where([
            ['doctor_id', $doctor->id],
            ['patient_id', $patient->id],
            ['date', $dateStr],
        ])->first();

        $payload = [
            'time' => $time,
            'service' => $service ?: 'Не указано',
            'cabinet' => $cabinet ?: '',
            'status' => $status,
        ];

        if ($appointment) {
            $appointment->update($payload);
            $this->stats['updated']++;
            Log::info("Приём обновлён: {$patientName} у {$doctor->name}");

            $this->sendNotificationToPatient($patient, $patientName, $doctor->name, $dateStr, $time, 'PROFIMED - Обновление приёма!', 'Уважаемый(ая) %s, у вас обновлён приём: %s %s %s');
        } else {
            Appointment::create(array_merge($payload, [
                'doctor_id' => $doctor->id,
                'patient_id' => $patient->id,
                'date' => $dateStr,
            ]));
            $isCancelled ? $this->stats['cancelled']++ : $this->stats['added']++;
            Log::info("Приём добавлен: {$patientName} у {$doctor->name}");

            $this->sendNotificationToPatient($patient, $patientName, $doctor->name, $dateStr, $time, 'PROFIMED - Новый приём!', 'Уважаемый(ая) %s, у вас новый приём: %s %s %s');
        }
    }

    protected function sendNotificationToPatient(
        Patient $patient,
        string $patientName,
        string $doctorName,
        string $dateStr,
        string $time,
        string $title,
        string $bodyFormat
    ): void {
        $deviceTokens = PatientDeviceToken::where('patient_id', $patient->id)->pluck('device_token');
        $body = sprintf($bodyFormat, $patientName, $doctorName, $dateStr, $time);

        foreach ($deviceTokens as $deviceToken) {
            try {
                app(FirebaseService::class)->sendNotification($deviceToken, $title, $body);
            } catch (\Throwable $e) {
                Log::warning('Ошибка отправки FCM', ['patient_id' => $patient->id, 'error' => $e->getMessage()]);
            }
        }
    }

    protected function cleanDoctorName(string $text): string
    {
        $text = preg_replace('/\([^)]*\)/u', '', $text);
        $text = preg_replace('/\b(Время|врач|каб\.?|кб\.?|закрыт|Прикрепленный контингент)\b/iu', '', $text);
        $text = trim(preg_replace('/[^А-Яа-яЁёӘәІіҢңҒғҮүҰұҚқӨөҺһA-Za-z\s-]/u', '', $text));
        $parts = preg_split('/\s+/u', $text);
        return trim(implode(' ', array_slice($parts, 0, 2)));
    }

    protected function cleanPatientName(string $text): string
    {
        $text = preg_replace('/\b(Прикрепленный контингент|Частное Лицо)\b/iu', '', $text);
        $text = trim(preg_replace('/[^А-Яа-яЁёӘәІіҢңҒғҮүҰұҚқӨөҺһA-Za-z\s-]/u', '', $text));
        $parts = preg_split('/\s+/u', $text);
        return trim(implode(' ', array_slice($parts, 0, 3)));
    }

    protected function isBlacklisted(string $doctorName): bool
    {
        $lower = mb_strtolower($doctorName);
        foreach ($this->doctorBlacklist as $blacklisted) {
            if (Str::contains($lower, $blacklisted)) {
                return true;
            }
        }
        return false;
    }
}
