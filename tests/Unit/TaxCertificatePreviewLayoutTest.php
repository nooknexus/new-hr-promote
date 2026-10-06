<?php

use App\Livewire\TaxCertificate;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

test('tax certificate preview renders form fields without a background image', function () {
    $preview = Livewire::test(TaxCertificate::class)
        ->set('previewCertificate', [
            'taxYear' => 2567,
            'certificateNo' => '151302',
            'totalIncome' => 315500,
            'taxWithheld' => 0,
            'governmentPensionFund' => 18930,
            'providentFund' => 0,
            'formattedIssueDate' => '29 มกราคม 2568',
            'employeeInfo' => [
                'name' => 'ทดสอบ เอกสาร',
                'pid' => '1234567890123',
                'department' => 'หน่วยงานทดสอบ',
            ],
        ])
        ->html();

    $document = new DOMDocument;
    @$document->loadHTML($preview);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//article[contains(@class, "tax-certificate-document")]')->length)->toBe(1)
        ->and($xpath->query('//article[contains(@class, "tax-certificate-document")]//img')->length)->toBe(0)
        ->and($xpath->query('//article[contains(@class, "tax-certificate-document")]//table')->length)->toBe(1)
        ->and($xpath->query('//section[contains(@class, "tax-certificate-recipient")]//p[contains(@class, "tax-certificate-party-id")]/span')->item(0)->textContent)->toBe('1-2345-67890-12-3');
});
