<?php

use App\Services\LeaveEntitlementService;
use Carbon\Carbon;

dataset('annual leave policies', [
    'civil servant over ten years' => [
        'ข้าราชการ', '2015-09-24', 10.0, 20.0, 30.0, true,
    ],
    'government employee with one completed year' => [
        'พนักงานราชการ', '2024-01-01', 10.0, 5.0, 15.0, true,
    ],
    'ministry employee under six months' => [
        'พนักงานกระทรวงสาธารณสุข', '2026-06-01', 0.0, 0.0, 0.0, false,
    ],
]);

test('it calculates annual leave policy from personnel type and employment start date', function (
    string $typeName,
    string $employmentStartedAt,
    float $currentYearDays,
    float $carryoverCap,
    float $maximumDays,
    bool $completedSixMonths,
) {
    $person = (object) [
        'type_name' => $typeName,
        'employment_started_at' => $employmentStartedAt,
    ];

    $entitlement = (new LeaveEntitlementService)->forPersonnel(
        $person,
        2569,
        2.0,
        Carbon::parse('2026-09-25'),
    );

    expect($entitlement)
        ->toMatchArray([
            'available' => true,
            'current_year_days' => $currentYearDays,
            'carryover_cap_days' => $carryoverCap,
            'maximum_days' => $maximumDays,
            'completed_six_months' => $completedSixMonths,
            'used_days' => 2.0,
        ]);
})->with('annual leave policies');

test('a civil servant with more than ten years of service receives thirty days of annual-leave entitlement', function () {
    $person = (object) [
        'type_name' => 'ข้าราชการ',
        'employment_started_at' => '2010-01-01',
    ];

    $entitlement = (new LeaveEntitlementService)->forPersonnel(
        $person,
        2569,
        2.0,
        Carbon::parse('2026-09-25'),
        20.0,
    );

    expect($entitlement)->toMatchArray([
        'carryover_actual_days' => 20.0,
        'total_entitled_days' => 30.0,
        'remaining_days' => 28.0,
    ]);
});

test('a civil servant without a start date is treated as under ten years of service', function () {
    $entitlement = (new LeaveEntitlementService)->forPersonnel(
        (object) ['type_name' => 'ข้าราชการ', 'employment_started_at' => null],
        2570,
        3,
        Carbon::create(2026, 12, 1),
        carryoverDays: 15,
    );

    expect($entitlement)->toMatchArray([
        'available' => true,
        'personnel_type' => 'ข้าราชการ',
        'start_date_assumed' => true,
        'employment_started_at' => null,
        'completed_six_months' => true,
        'current_year_days' => 10.0,
        'carryover_cap_days' => 10.0,
        'maximum_days' => 20.0,
        'carryover_actual_days' => 10.0,
        'total_entitled_days' => 20.0,
        'remaining_days' => 17.0,
    ]);
});

test('other personnel types without a start date still ask for the start date', function (string $type) {
    $entitlement = (new LeaveEntitlementService)->forPersonnel(
        (object) ['type_name' => $type, 'employment_started_at' => null],
        2570,
    );

    expect($entitlement['available'])->toBeFalse()
        ->and($entitlement['message'])->toContain('ไม่พบวันเริ่มงาน');
})->with(['พนักงานราชการ', 'พนักงานกระทรวง', 'ลูกจ้างประจำ']);
