<?php

use App\Services\SalaryPromotionCalculator;
use Tests\TestCase;

uses(TestCase::class);

test('the increase is rounded up to the next ten baht below the ceiling', function (string $base, string $percent, string $sum) {
    expect(app(SalaryPromotionCalculator::class)->calculate('27520', $base, $percent, 58390))
        ->toBe(['sum' => $sum, 'extra' => '0.00', 'new_salary' => number_format(27520 + (float) $sum, 2, '.', '')]);
})->with([
    '981.13 → 990' => ['23930', '4.1', '990.00'],
    '1,021.01 → 1,030' => ['102101', '1', '1030.00'],
    '1,026 → 1,030' => ['102600', '1', '1030.00'],
    'a round 980 stays 980' => ['24500', '4', '980.00'],
]);

test('a salary already at its ceiling pays the unrounded increase as extra', function () {
    expect(app(SalaryPromotionCalculator::class)->calculate('58390', '36470', '3.7', 58390))
        ->toBe(['sum' => '0.00', 'extra' => '1349.39', 'new_salary' => '58390.00']);
});

test('a round that reaches the ceiling stops there and pays the rest as extra', function () {
    $calculator = app(SalaryPromotionCalculator::class);

    expect($calculator->calculate('58000', '23930', '4.1', 58390))
        ->toBe(['sum' => '390.00', 'extra' => '591.13', 'new_salary' => '58390.00'])
        ->and($calculator->calculate('54560', '31610', '4', 54820))
        ->toBe(['sum' => '260.00', 'extra' => '1004.40', 'new_salary' => '54820.00']);
});

test('without a ceiling the increase is only rounded', function () {
    expect(app(SalaryPromotionCalculator::class)->calculate('58000', '23930', '4.1', null))
        ->toBe(['sum' => '990.00', 'extra' => '0.00', 'new_salary' => '58990.00']);
});

test('ceilings are looked up by personnel type and normalised level', function () {
    $calculator = app(SalaryPromotionCalculator::class);

    expect($calculator->ceilingFor(1, 'ชำนาญการ'))->toBe(58390)
        ->and($calculator->ceilingFor(1, 'ระดับชำนาญการ'))->toBe(58390)
        ->and($calculator->ceilingFor(1, ' ชำนาญการ พิเศษ '))->toBe(69040)
        ->and($calculator->ceilingFor(1, 'ปฏิบัติงาน'))->toBe(38750)
        ->and($calculator->ceilingFor(3, 'ชำนาญการ'))->toBeNull()
        ->and($calculator->ceilingFor(1, null))->toBeNull()
        ->and($calculator->ceilingFor(null, 'ชำนาญการ'))->toBeNull()
        ->and($calculator->describe(1, 'ชำนาญการ'))->toBeNull()
        ->and($calculator->describe(1, ''))->toContain('ไม่พบระดับ')
        ->and($calculator->describe(1, 'ไม่มีจริง'))->toContain('ไม่พบเพดานของระดับไม่มีจริง')
        ->and($calculator->describe(2, 'ชำนาญงาน'))->toContain('ลูกจ้างประจำ');
});
