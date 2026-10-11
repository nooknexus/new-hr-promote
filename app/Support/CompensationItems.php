<?php

namespace App\Support;

/**
 * Monthly compensation items an office keys for a person, which personnel
 * types (person.type_id) each item applies to, and how it feeds the provincial
 * health office's withholding-tax certificate. ลูกจ้างประจำ (type 2) is paid
 * through the Comptroller General's certificate and is never keyed here.
 */
final class CompensationItems
{
    public const CIVIL_SERVANT = 1;
    public const GOVERNMENT_EMPLOYEE = 3;
    public const MINISTRY_EMPLOYEE = 4;

    /** Personnel types whose monthly compensation may be keyed. */
    public const TYPES = [self::CIVIL_SERVANT, self::GOVERNMENT_EMPLOYEE, self::MINISTRY_EMPLOYEE];

    public const PROVIDENT_FUND_NAME = 'กองทุนสำรองเลี้ยงชีพพนักงานกระทรวงสาธารณสุข';

    /** @var array<string, array{label: string, kind: 'income'|'deduction', types: list<int>}> */
    public const ITEMS = [
        'salary' => ['label' => 'เงินเดือน/ค่าจ้าง', 'kind' => 'income', 'types' => [self::GOVERNMENT_EMPLOYEE, self::MINISTRY_EMPLOYEE]],
        'pts' => ['label' => 'พ.ต.ส.', 'kind' => 'income', 'types' => [self::CIVIL_SERVANT]],
        'non_practice' => ['label' => 'เงินเพิ่มไม่ทำเวชฯ', 'kind' => 'income', 'types' => [self::CIVIL_SERVANT]],
        'kts' => ['label' => 'ค.ต.ส.', 'kind' => 'income', 'types' => [self::GOVERNMENT_EMPLOYEE]],
        'back_pay' => ['label' => 'ตกเบิก', 'kind' => 'income', 'types' => self::TYPES],
        'other_amount' => ['label' => 'อื่นๆ', 'kind' => 'income', 'types' => self::TYPES],
        'withholding_tax' => ['label' => 'ภาษีหัก ณ ที่จ่าย', 'kind' => 'deduction', 'types' => self::TYPES],
        'social_security' => ['label' => 'ประกันสังคม', 'kind' => 'deduction', 'types' => [self::GOVERNMENT_EMPLOYEE, self::MINISTRY_EMPLOYEE]],
        'provident_fund' => ['label' => self::PROVIDENT_FUND_NAME, 'kind' => 'deduction', 'types' => [self::MINISTRY_EMPLOYEE]],
    ];

    /** @return list<string> */
    public static function fields(): array
    {
        return array_keys(self::ITEMS);
    }

    /** @return list<string> */
    public static function incomeFields(): array
    {
        return array_keys(array_filter(self::ITEMS, fn (array $item): bool => $item['kind'] === 'income'));
    }

    public static function allowsType(?int $typeId): bool
    {
        return in_array($typeId, self::TYPES, true);
    }

    public static function allows(string $field, ?int $typeId): bool
    {
        return in_array($typeId, self::ITEMS[$field]['types'] ?? [], true);
    }

    /**
     * Problems with one month's figures for a person of the given type.
     *
     * @param  array<string, mixed>  $values  item => amount, plus other_name
     * @return list<string>
     */
    public static function problems(array $values, ?int $typeId): array
    {
        if (! self::allowsType($typeId)) {
            return ['ประเภทการจ้างของบุคลากรนี้ไม่ต้องบันทึกค่าตอบแทนรายเดือน'];
        }

        $problems = [];
        foreach (self::ITEMS as $field => $item) {
            if ((float) ($values[$field] ?? 0) != 0.0 && ! self::allows($field, $typeId)) {
                $type = PersonnelTypeScope::TYPES[$typeId] ?? (string) $typeId;
                $problems[] = "{$item['label']} ใช้กับ{$type}ไม่ได้";
            }
        }
        if ((float) ($values['other_amount'] ?? 0) != 0.0 && trim((string) ($values['other_name'] ?? '')) === '') {
            $problems[] = 'กรุณาระบุชื่อรายการอื่นๆ';
        }

        return $problems;
    }
}
