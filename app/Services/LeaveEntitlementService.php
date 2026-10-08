<?php

namespace App\Services;

use Carbon\Carbon;
use Throwable;

class LeaveEntitlementService
{
    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function forPersonnel(
        ?object $person,
        int $fiscalYear,
        float $usedDays = 0,
        ?Carbon $asOf = null,
        float $carryoverDays = 0,
    ): array
    {
        if ($person === null) {
            return $this->unavailable('ไม่พบวันเริ่มงาน กรุณาให้ผู้ดูแลระบบบันทึกข้อมูลบุคลากร');
        }

        $typeName = trim((string) ($person->type_name ?? ''));
        $referenceDate = $this->referenceDateForFiscalYear($fiscalYear, $asOf);

        if (empty($person->employment_started_at)) {
            // Without a recorded start date, assume service past the first year:
            // civil servants and permanent employees under ten years (10 + up to
            // 10 carried over = 20); government and Ministry of Public Health
            // employees (10 + up to 5 carried over = 15).
            $startedAt = null;
            $policy = $this->policyWithoutStartDate($typeName);

            if ($policy === null) {
                return $this->unavailable('ไม่พบวันเริ่มงาน กรุณาให้ผู้ดูแลระบบบันทึกข้อมูลบุคลากร');
            }
        } else {
            if ($typeName === '') {
                return $this->unavailable('ไม่พบประเภทเจ้าหน้าที่ กรุณาให้ผู้ดูแลระบบบันทึกข้อมูลบุคลากร');
            }

            try {
                $startedAt = Carbon::parse($person->employment_started_at)->startOfDay();
            } catch (Throwable) {
                return $this->unavailable('วันเริ่มงานไม่ถูกต้อง กรุณาให้ผู้ดูแลระบบตรวจสอบข้อมูลบุคลากร');
            }

            $policy = $this->policyFor($typeName, $startedAt, $referenceDate);
        }
        if ($policy === null) {
            return $this->unavailable('ยังไม่มีเงื่อนไขสิทธิวันลาพักผ่อนสำหรับประเภท “'.$typeName.'”');
        }

        $usedDays = max(0, $usedDays);
        $carryoverActualDays = min($policy['carryover_cap_days'], max(0, $carryoverDays));
        $totalEntitledDays = $policy['current_year_days'] + $carryoverActualDays;

        return [
            'available' => true,
            'personnel_type' => $policy['personnel_type'],
            'employment_started_at' => $startedAt?->toDateString(),
            'start_date_assumed' => $startedAt === null,
            'reference_date' => $referenceDate->toDateString(),
            'completed_six_months' => $policy['completed_six_months'],
            'current_year_days' => $policy['current_year_days'],
            'carryover_cap_days' => $policy['carryover_cap_days'],
            'maximum_days' => $policy['current_year_days'] + $policy['carryover_cap_days'],
            'used_days' => $usedDays,
            'remaining_current_year_days' => max(0, $policy['current_year_days'] - $usedDays),
            'carryover_actual_days' => $carryoverActualDays,
            'total_entitled_days' => $totalEntitledDays,
            'remaining_days' => max(0, $totalEntitledDays - $usedDays),
            'message' => null,
        ];
    }

    /** @return array<string, bool|float|int|string|null> */
    private function unavailable(string $message): array
    {
        return [
            'available' => false,
            'personnel_type' => null,
            'employment_started_at' => null,
            'start_date_assumed' => false,
            'reference_date' => null,
            'completed_six_months' => false,
            'current_year_days' => 0.0,
            'carryover_cap_days' => 0.0,
            'maximum_days' => 0.0,
            'used_days' => 0.0,
            'remaining_current_year_days' => 0.0,
            'carryover_actual_days' => null,
            'total_entitled_days' => 0.0,
            'remaining_days' => 0.0,
            'message' => $message,
        ];
    }

    /** @return array{personnel_type: string, completed_six_months: bool, current_year_days: float, carryover_cap_days: float}|null */
    private function policyWithoutStartDate(string $typeName): ?array
    {
        $carryoverCap = match (true) {
            str_contains($typeName, 'ข้าราชการ') => ['ข้าราชการ', 10.0],
            str_contains($typeName, 'ลูกจ้างประจำ') => ['ลูกจ้างประจำ', 10.0],
            str_contains($typeName, 'พนักงานราชการ') => ['พนักงานราชการ', 5.0],
            str_contains($typeName, 'พนักงานกระทรวง') => ['พนักงานกระทรวง', 5.0],
            default => null,
        };

        return $carryoverCap === null ? null : [
            'personnel_type' => $carryoverCap[0],
            'completed_six_months' => true,
            'current_year_days' => 10.0,
            'carryover_cap_days' => $carryoverCap[1],
        ];
    }

    /** @return array{personnel_type: string, completed_six_months: bool, current_year_days: float, carryover_cap_days: float}|null */
    private function policyFor(string $typeName, Carbon $startedAt, Carbon $referenceDate): ?array
    {
        $completedSixMonths = $startedAt->copy()->addMonthsNoOverflow(6)->lte($referenceDate);
        $completedOneYear = $startedAt->copy()->addYear()->lte($referenceDate);
        $moreThanTenYears = $startedAt->copy()->addYears(10)->lt($referenceDate);

        // Permanent employees (ลูกจ้างประจำ) follow the civil-servant rule.
        foreach (['ข้าราชการ', 'ลูกจ้างประจำ'] as $civilServiceType) {
            if (str_contains($typeName, $civilServiceType)) {
                return [
                    'personnel_type' => $civilServiceType,
                    'completed_six_months' => $completedSixMonths,
                    'current_year_days' => 10.0,
                    'carryover_cap_days' => $moreThanTenYears ? 20.0 : 10.0,
                ];
            }
        }

        if (str_contains($typeName, 'พนักงานราชการ')) {
            return [
                'personnel_type' => 'พนักงานราชการ',
                'completed_six_months' => $completedSixMonths,
                'current_year_days' => $completedSixMonths ? 10.0 : 0.0,
                'carryover_cap_days' => $completedOneYear ? 5.0 : 0.0,
            ];
        }

        if (str_contains($typeName, 'พนักงานกระทรวง')) {
            return [
                'personnel_type' => 'พนักงานกระทรวง',
                'completed_six_months' => $completedSixMonths,
                'current_year_days' => $completedSixMonths ? 10.0 : 0.0,
                'carryover_cap_days' => $completedOneYear ? 5.0 : 0.0,
            ];
        }

        return null;
    }

    private function referenceDateForFiscalYear(int $fiscalYear, ?Carbon $asOf): Carbon
    {
        $fiscalYearEnd = $fiscalYear - 543;
        $fiscalStart = Carbon::create($fiscalYearEnd - 1, 10, 1)->startOfDay();
        $fiscalEnd = Carbon::create($fiscalYearEnd, 9, 30)->endOfDay();
        $referenceDate = ($asOf ?? Carbon::today())->copy()->startOfDay();

        if ($referenceDate->lt($fiscalStart)) {
            return $fiscalStart;
        }

        if ($referenceDate->gt($fiscalEnd)) {
            return $fiscalEnd;
        }

        return $referenceDate;
    }
}
