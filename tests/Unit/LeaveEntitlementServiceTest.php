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

test('personnel types without a leave policy still ask for the start date', function () {
    $entitlement = (new LeaveEntitlementService)->forPersonnel(
        (object) ['type_name' => 'ลูกจ้างชั่วคราว', 'employment_started_at' => null],
        2570,
    );

    expect($entitlement['available'])->toBeFalse()
        ->and($entitlement['message'])->toContain('ไม่พบวันเริ่มงาน');
});

test('each personnel type without a start date gets its past-first-year entitlement', function (string $type, float $carryoverCap, float $maximum) {
    $entitlement = (new LeaveEntitlementService)->forPersonnel(
        (object) ['type_name' => $type, 'employment_started_at' => null],
        2570,
        0,
        Carbon::create(2026, 12, 1),
        carryoverDays: 30,
    );

    expect($entitlement)->toMatchArray([
        'available' => true,
        'personnel_type' => $type,
        'current_year_days' => 10.0,
        'carryover_cap_days' => $carryoverCap,
        'maximum_days' => $maximum,
        'total_entitled_days' => $maximum,
    ]);
})->with([
    'civil servant' => ['ข้าราชการ', 10.0, 20.0],
    'permanent employee' => ['ลูกจ้างประจำ', 10.0, 20.0],
    'government employee' => ['พนักงานราชการ', 5.0, 15.0],
    'ministry employee' => ['พนักงานกระทรวง', 5.0, 15.0],
]);

test('permanent employees follow the civil-servant rule including the ten-year carryover', function (string $startedAt, float $carryoverCap) {
    $entitlement = (new LeaveEntitlementService)->forPersonnel(
        (object) ['type_name' => 'ลูกจ้างประจำ', 'employment_started_at' => $startedAt],
        2570,
        0,
        Carbon::create(2026, 12, 1),
    );

    expect($entitlement)->toMatchArray([
        'available' => true,
        'personnel_type' => 'ลูกจ้างประจำ',
        'current_year_days' => 10.0,
        'carryover_cap_days' => $carryoverCap,
        'maximum_days' => 10.0 + $carryoverCap,
    ]);
})->with([
    'under ten years' => ['2020-01-01', 10.0],
    'more than ten years' => ['2010-01-01', 20.0],
]);

test('a Ministry of Public Health employee without a start date gets 10 days plus up to 5 carried over', function () {
    $entitlement = (new LeaveEntitlementService)->forPersonnel(
        (object) ['type_name' => 'พนักงานกระทรวง', 'employment_started_at' => null],
        2570,
        4,
        Carbon::create(2026, 12, 1),
        carryoverDays: 9,
    );

    expect($entitlement)->toMatchArray([
        'available' => true,
        'personnel_type' => 'พนักงานกระทรวง',
        'start_date_assumed' => true,
        'current_year_days' => 10.0,
        'carryover_cap_days' => 5.0,
        'maximum_days' => 15.0,
        'carryover_actual_days' => 5.0,
        'total_entitled_days' => 15.0,
        'remaining_days' => 11.0,
    ]);
});

test('a Ministry of Public Health employee follows the six-month first-year rule', function (string $startedAt, float $currentYearDays, float $carryoverCap) {
    $entitlement = (new LeaveEntitlementService)->forPersonnel(
        (object) ['type_name' => 'พนักงานกระทรวง', 'employment_started_at' => $startedAt],
        2570,
        0,
        Carbon::create(2026, 12, 1),
        carryoverDays: 8,
    );

    expect($entitlement)->toMatchArray([
        'current_year_days' => $currentYearDays,
        'carryover_cap_days' => $carryoverCap,
        'maximum_days' => $currentYearDays + $carryoverCap,
    ]);
})->with([
    'under six months' => ['2026-08-01', 0.0, 0.0],
    'six months, under one year' => ['2026-05-01', 10.0, 0.0],
    'past the first year' => ['2024-01-01', 10.0, 5.0],
]);
