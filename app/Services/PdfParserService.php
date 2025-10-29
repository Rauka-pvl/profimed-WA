<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Appointment;
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

    // Черный список врачей (регистронезависимо)
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
        $text = preg_replace('/\s+/', ' ', $pdf->getText());

        // Разделяем по врачам
        $blocks = preg_split(
            '/(?=\d{2}\.\d{2}\.\d{4}\s+[А-ЯЁA-ZӘІҢҒҮҰҚӨҺ][а-яёa-zәіңғүұқөһ]+\s+[А-ЯЁA-ZӘІҢҒҮҰҚӨҺ])/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        foreach ($blocks as $block) {
            // Извлекаем дату и имя врача
            if (!preg_match('/(\d{2}\.\d{2}\.\d{4})\s+([А-ЯЁA-ZӘІҢҒҮҰҚӨҺ][^\n\r]+?)(?=Время\s+Кабинет|$)/u', $block, $m)) {
                continue;
            }

            $date = date('Y-m-d', strtotime(str_replace('.', '-', $m[1])));
            $doctorName = $this->cleanDoctorName($m[2]);

            // Проверка на черный список
            if ($this->isBlacklisted($doctorName)) {
                Log::info("Врач пропущен (черный список): {$doctorName}");
                continue;
            }

            $doctor = Doctor::firstOrCreate(['name' => $doctorName]);
            Log::info("Обработка врача: {$doctorName}");

            // Улучшенная регулярка для парсинга приёмов
            // Захватываем 2-3 слова для ФИО пациента
            preg_match_all(
                '/(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})\s+[^\(]*?\(\s*([^)]+)\s*\)\s+([А-ЯЁA-ZӘІҢҒҮҰҚӨҺ][а-яёa-zәіңғүұқөһ]+(?:\s+[А-ЯЁA-ZӘІҢҒҮҰҚӨҺ][а-яёa-zәіңғүұқөһ]+){1,2})\s*(.*?)(?=\d{2}:\d{2}\s*-\s*\d{2}:\d{2}|Всего приемов|Время\s+Кабинет|$)/su',
                $block,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $m) {
                $this->processAppointment($m, $doctor, $date);
            }
        }

        return $this->stats;
    }

    protected function processAppointment(array $match, Doctor $doctor, string $date): void
    {
        $start = trim($match[1]);
        $end = trim($match[2]);
        $time = "{$start} - {$end}";
        $cabinet = trim($match[3]);
        $patientName = $this->cleanPatientName($match[4]);

        // Извлечение остальной части (телефоны + услуга)
        $remainder = $match[5] ?? '';

        // Извлекаем телефоны ДО того, как появится следующее время
        $phones = $this->extractPhones($remainder);
        $primaryPhone = $phones[0] ?? null; // Только первый валидный номер
        $allPhones = $primaryPhone; // Сохраняем только один номер

        // Извлекаем услугу (всё после телефонов до конца или до времени)
        $service = $this->extractService($remainder);

        if (!$patientName) {
            $this->stats['skipped']++;
            Log::warning("Пациент пропущен: пустое имя");
            return;
        }

        // Проверка на выездные услуги
        if (Str::contains(mb_strtolower($service), ['на дому', 'выезд'])) {
            $this->stats['skipped']++;
            Log::info("Приём пропущен (выезд): {$patientName}");
            return;
        }

        // Определяем статус
        $isCancelled = ($start === '00:00' || $end === '00:00');
        $status = $isCancelled ? 'cancelled' : 'scheduled';

        // Создаем или обновляем пациента
        $patient = Patient::firstOrCreate(
            ['full_name' => $patientName],
            ['phone' => $allPhones]
        );

        if (!$patient->phone && !empty($allPhones)) {
            $patient->update(['phone' => $allPhones]);
        }

        // Создаем или обновляем приём
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
            Log::info("Приём обновлён: {$patientName} у {$doctor->name}");
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
            Log::info("Приём добавлен: {$patientName} у {$doctor->name}");
        }
    }

    protected function extractPhones(string $text): array
    {
        // Убираем всё после времени следующего приёма
        $text = preg_replace('/\d{2}:\d{2}\s*-\s*\d{2}:\d{2}.*$/su', '', $text);

        // Ищем все телефоны
        preg_match_all('/\+?[78]\s*\d{3}\s*\d{3}\s*\d{2}\s*\d{2}/u', $text, $phoneMatches);

        $phones = collect($phoneMatches[0] ?? [])
            ->map(function ($p) {
                $p = preg_replace('/[^\d+]/', '', $p);
                if (str_starts_with($p, '8')) {
                    $p = '+7' . substr($p, 1);
                } elseif (preg_match('/^7\d{10}$/', $p)) {
                    $p = '+' . $p;
                }
                return (strlen($p) >= 11 && strlen($p) <= 13) ? $p : null;
            })
            ->filter()
            ->values()
            ->toArray();

        // Проверяем первый номер
        if (!empty($phones)) {
            $firstPhone = $phones[0];

            // Проверяем все варианты проблемных номеров
            if ($this->isInvalidPhone($firstPhone)) {
                // Если первый номер невалидный, берём второй (если есть)
                if (isset($phones[1])) {
                    return [$phones[1]];
                } else {
                    // Если второго нет - не записываем телефон вообще
                    return [];
                }
            }

            // Возвращаем только первый валидный номер
            return [$firstPhone];
        }

        return [];
    }

    protected function isInvalidPhone(string $phone): bool
    {
        // Все варианты невалидных номеров
        $invalidPrefixes = [
            '+77182',
            '+7182',
            '+7 7182',
            '+7 182',
        ];

        foreach ($invalidPrefixes as $prefix) {
            $normalizedPrefix = str_replace(' ', '', $prefix);
            $normalizedPhone = str_replace(' ', '', $phone);

            if (str_starts_with($normalizedPhone, $normalizedPrefix)) {
                return true;
            }
        }

        return false;
    }

    protected function extractService(string $text): string
    {
        // Удаляем телефоны
        $text = preg_replace('/\+?[78]\s*\d{3}\s*\d{3}\s*\d{2}\s*\d{2}/u', '', $text);

        // Удаляем лишний текст (примечания, и т.д.)
        $text = preg_replace('/Примечание:.*$/su', '', $text);
        $text = preg_replace('/Всего приемов.*$/su', '', $text);

        // Очищаем и возвращаем
        return trim($text) ?: 'Не указано';
    }

    protected function cleanDoctorName(string $text): string
    {
        // Удаляем информацию о кабинетах в скобках
        $text = preg_replace('/\([^)]*\)/u', '', $text);

        // Удаляем служебные слова
        $text = preg_replace('/\b(Время|врач|каб\.?|кб\.?|закрыт|Прикрепленный контингент)\b/iu', '', $text);

        // Очищаем от спецсимволов, но сохраняем казахские буквы
        $text = trim(preg_replace('/[^А-Яа-яЁёӘәІіҢңҒғҮүҰұҚқӨөҺһA-Za-z\s-]/u', '', $text));

        // Берём только первые два слова (Фамилия Имя)
        $parts = preg_split('/\s+/u', $text);
        $result = implode(' ', array_slice($parts, 0, 2));

        return trim($result);
    }

    protected function cleanPatientName(string $text): string
    {
        // Удаляем служебные слова
        $text = preg_replace('/\b(Прикрепленный контингент|Частное Лицо)\b/iu', '', $text);

        // Очищаем от лишних символов, сохраняем казахские буквы
        $text = trim(preg_replace('/[^А-Яа-яЁёӘәІіҢңҒғҮүҰұҚқӨөҺһA-Za-z\s-]/u', '', $text));

        // Берём до 3 слов (Фамилия Имя Отчество), если есть
        $parts = preg_split('/\s+/u', $text);
        $result = implode(' ', array_slice($parts, 0, 3));

        return trim($result);
    }

    protected function isBlacklisted(string $doctorName): bool
    {
        $lowerName = mb_strtolower($doctorName);

        foreach ($this->doctorBlacklist as $blacklisted) {
            if (str_contains($lowerName, $blacklisted)) {
                return true;
            }
        }

        return false;
    }
}
