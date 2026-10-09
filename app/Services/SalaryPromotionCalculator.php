<?php

namespace App\Services;

use App\Support\PersonnelTypeScope;

/**
 * Salary promotion formula. The increase is base × percent / 100 rounded up
 * to the next 10 baht. Once the salary reaches its level ceiling it stops
 * rising and the unrounded amount is paid as เงินค่าตอบแทนพิเศษ (extra);
 * a round that crosses the ceiling raises the salary to the ceiling and pays
 * the remainder as extra.
 */
class SalaryPromotionCalculator
{
    public function ceilingFor(?int $typeId, ?string $level): ?int
    {
        $level = self::normaliseLevel($level);
        if ($typeId === null || $level === '') {
            return null;
        }

        $ceiling = config("salary_ceilings.{$typeId}", [])[$level] ?? null;

        return $ceiling === null ? null : (int) $ceiling;
    }

    /** Why no ceiling applies, or null when one does. */
    public function describe(?int $typeId, ?string $level): ?string
    {
        $normalised = self::normaliseLevel($level);
        if ($typeId === null) {
            return 'ไม่พบประเภทการจ้างของบุคลากร จึงคำนวณแบบปัดขึ้นหลักสิบโดยไม่ตรวจเพดาน';
        }
        if ($normalised === '') {
            return 'ไม่พบระดับของบุคลากร จึงคำนวณแบบปัดขึ้นหลักสิบโดยไม่ตรวจเพดาน';
        }
        if (config("salary_ceilings.{$typeId}", []) === []) {
            $type = PersonnelTypeScope::TYPES[$typeId] ?? (string) $typeId;

            return "ยังไม่มีตารางเพดานเงินเดือนของ{$type} จึงคำนวณแบบปัดขึ้นหลักสิบโดยไม่ตรวจเพดาน";
        }

        return $this->ceilingFor($typeId, $level) === null
            ? "ไม่พบเพดานของระดับ{$normalised} จึงคำนวณแบบปัดขึ้นหลักสิบโดยไม่ตรวจเพดาน"
            : null;
    }

    /** @return array{sum: string, extra: string, new_salary: string} */
    public function calculate(string $before, string $base, string $percent, ?int $ceiling): array
    {
        $beforeCents = self::cents((float) $before);
        $rawCents = self::cents((float) $base * (float) $percent / 100);
        $roundedCents = intdiv($rawCents + 999, 1000) * 1000;
        $ceilingCents = $ceiling === null ? null : $ceiling * 100;

        if ($ceilingCents === null || $beforeCents + $roundedCents <= $ceilingCents) {
            [$sum, $extra] = [$roundedCents, 0];
        } elseif ($beforeCents >= $ceilingCents) {
            [$sum, $extra] = [0, $rawCents];
        } else {
            $sum = $ceilingCents - $beforeCents;
            $extra = max(0, $rawCents - $sum);
        }

        return [
            'sum' => self::money($sum),
            'extra' => self::money($extra),
            'new_salary' => self::money($beforeCents + $sum),
        ];
    }

    public static function normaliseLevel(?string $level): string
    {
        $level = preg_replace('/\s+/u', '', trim((string) $level)) ?? '';

        return str_starts_with($level, 'ระดับ') ? mb_substr($level, 5) : $level;
    }

    private static function cents(float $value): int
    {
        return (int) round(round($value, 2) * 100);
    }

    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
