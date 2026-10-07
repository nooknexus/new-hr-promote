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
