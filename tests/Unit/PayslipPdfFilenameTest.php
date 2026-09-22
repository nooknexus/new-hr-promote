<?php

use App\Services\PayslipPdfService;
use Tests\TestCase;

uses(TestCase::class);

test('payslip download filename does not expose the employee identifier', function () {
    $filename = PayslipPdfService::generatePayslipFilename('202509', '1234567890123');

    expect($filename)->toBe('payslip_2568_09.pdf')
        ->not->toContain('1234567890123');
});
