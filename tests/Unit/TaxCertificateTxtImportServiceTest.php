<?php

use App\Services\TaxCertificateTxtImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

/** @param array<int, string> $overrides */
function taxCertificateTxtRecord(array $overrides = []): string
{
    $fields = [
        'TC-0001',
        'กรมทดสอบ',
        '0105550000000',
        '99 ถนนทดสอบ',
        '0000000000000',
        '0105550000000',
        'ผู้ทดสอบ ระบบ',
        '1 ถนนผู้รับเงิน',
        '0001',
        '2',
        '2567',
        '000003155000',
        '000000001234',
        '000003156234',
        '000000001234',
        'สามหมื่นหนึ่งพันห้าร้อยห้าสิบบาทถ้วน',
        '1',
        '000000189300',
        '3',
        '29012568',
    ];

    return implode('$', array_replace($fields, $overrides));
}

function taxCertificateTxtFile(string $contents, string $extension = 'txt'): string
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'tax-certificate-import-');

    if ($temporaryPath === false) {
        throw new RuntimeException('Unable to create a temporary tax certificate fixture.');
    }

    $path = $temporaryPath.'.'.$extension;

    if (! rename($temporaryPath, $path)) {
        @unlink($temporaryPath);

        throw new RuntimeException('Unable to name a temporary tax certificate fixture.');
    }

    file_put_contents($path, $contents);

    return $path;
}

function removeTaxCertificateTxtFile(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }
}

function maskedTaxCertificateError(array $result, string $rawCid): string
{
    $error = implode("\n", $result['errors']);

    expect($error)
        ->toContain('CID:')
        ->toMatch('/\\*{2,}/')
        ->not->toContain($rawCid);

    return $error;
}

// Production break caught: removing TIS-620 conversion or shifting a TXT column would garble or misfile imported certificate data.
test('it previews a TIS-620 central-government tax certificate row with mapped data-tax fields', function () {
    $tis620Record = iconv('UTF-8', 'TIS-620', taxCertificateTxtRecord());

    expect($tis620Record)->not->toBeFalse();

    $path = taxCertificateTxtFile($tis620Record."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path);

        expect($result)->toMatchArray([
            'read' => 1,
            'valid' => 1,
            'skipped' => 0,
            'errors' => [],
        ]);

        $row = $result['rows'][0];

        expect($row)->toMatchArray([
            'line' => 1,
            'running_code1' => 'TC-0001',
            'department_name' => 'กรมทดสอบ',
            'department_tax_id' => '0105550000000',
            'department_addr' => '99 ถนนทดสอบ',
            'cid' => '0000000000000',
            'tax_id' => '0105550000000',
            'fullname' => 'ผู้ทดสอบ ระบบ',
            'addr' => '1 ถนนผู้รับเงิน',
            'running_code2' => '0001',
            'income_type_id' => '2',
            'income_type_name' => 'ภ.ง.ด.1ก พิเศษ',
            'income_year' => 2567,
            'income_money' => '31550.00',
            'withholding_tax' => '12.34',
            'total_money' => '31562.34',
            'sumTaxAmount' => '12.34',
            'sumTaxText' => 'สามหมื่นหนึ่งพันห้าร้อยห้าสิบบาทถ้วน',
            'outcomeType' => '1',
            'outcomeName' => 'กบข.',
            'outcomeAmount' => '1893.00',
            'tax_type_id' => '3',
            'tax_type_name' => 'ออกให้ครั้งเดียว',
            'date_report' => '29012568',
        ]);
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

// Production break caught: treating the valid-UTF-8 byte pair C2 A1 as UTF-8 corrupts the legacy TIS-620 Thai word it represents by default.
test('it defaults an otherwise-ambiguous TIS-620 record to legacy TIS-620 before parsing its CSV fields', function () {
    $record = taxCertificateTxtRecord([
        1 => 'ยก',
        3 => 'Payer address',
        6 => 'Recipient',
        7 => 'Recipient address',
        15 => 'tax text',
    ]);
    $tis620Record = iconv('UTF-8', 'TIS-620', $record);

    expect($tis620Record)->not->toBeFalse()
        ->and(bin2hex($tis620Record))->toContain('c2a1');

    $path = taxCertificateTxtFile($tis620Record."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path);

        expect($result)->toMatchArray(['read' => 1, 'valid' => 1, 'skipped' => 0, 'errors' => []])
            ->and($result['rows'][0]['department_name'])->toBe('ยก');
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

// Production break caught: interpreting explicitly selected native UTF-8 C2 A1 bytes as TIS-620 changes the payer name from ¡ to ยก.
test('it preserves a native UTF-8 C2 A1 character only when UTF-8 is explicit', function () {
    $path = taxCertificateTxtFile(taxCertificateTxtRecord([
        1 => '¡',
        3 => 'Payer address',
        6 => 'Recipient',
        7 => 'Recipient address',
        15 => 'tax text',
    ])."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'UTF-8');

        expect($result)->toMatchArray(['read' => 1, 'valid' => 1, 'skipped' => 0, 'errors' => []])
            ->and($result['rows'][0]['department_name'])->toBe('¡');
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

// Production break caught: accepting an unspecified encoding can silently choose a decoder and expose data from a malformed upload.
test('it rejects an unsupported encoding without exposing certificate identifiers', function () {
    $rawCid = '0000000000000';
    $path = taxCertificateTxtFile(taxCertificateTxtRecord([4 => $rawCid])."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'windows-874');

        expect($result)->toMatchArray([
            'rows' => [],
            'read' => 0,
            'valid' => 0,
            'skipped' => 0,
        ])
            ->and(implode("\n", $result['errors']))->toContain('รหัสอักขระ')
            ->not->toContain($rawCid);
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

// Production break caught: treating fixed-width zero amounts as money creates false withholding, fund, or income values.
test('it represents empty and zero fixed-width amounts as null', function () {
    $path = taxCertificateTxtFile(taxCertificateTxtRecord([
        11 => '',
        12 => '000000000000',
        13 => '0',
        14 => '000000000000',
        17 => '',
    ])."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'utf-8');

        expect($result)->toMatchArray(['read' => 1, 'valid' => 1, 'skipped' => 0, 'errors' => []])
            ->and($result['rows'][0])
            ->toMatchArray([
                'income_money' => null,
                'withholding_tax' => null,
                'total_money' => null,
                'sumTaxAmount' => null,
                'outcomeAmount' => null,
            ]);
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

// Production break caught: accepting a short row can shift every later field into the wrong certificate column.
test('it skips a row that does not contain all twenty TXT columns', function () {
    $fields = explode('$', taxCertificateTxtRecord());
    array_pop($fields);
    $rawCid = $fields[4];
    $path = taxCertificateTxtFile(implode('$', $fields)."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'utf-8');

        expect($result)->toMatchArray(['read' => 1, 'valid' => 0, 'skipped' => 1, 'rows' => []]);

        $error = maskedTaxCertificateError($result, $rawCid);

        expect($error)->toContain('บรรทัด 1')
            ->toContain('20');
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

// Production break caught: accepting a malformed CID can associate a certificate with the wrong person.
test('it skips a row whose CID is not exactly thirteen digits without exposing the raw CID', function () {
    $rawCid = '123456789012';
    $path = taxCertificateTxtFile(taxCertificateTxtRecord([4 => $rawCid])."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'utf-8');

        expect($result)->toMatchArray(['read' => 1, 'valid' => 0, 'skipped' => 1, 'rows' => []]);

        $error = maskedTaxCertificateError($result, $rawCid);

        expect($error)->toContain('บรรทัด 1')
            ->toContain('13');
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

// Production break caught: byte-based masking can split a multibyte malformed CID and make the preview error impossible to JSON encode.
test('it returns a JSON-encodable masked error for a non-ASCII CID', function () {
    $rawCid = 'กขคง';
    $path = taxCertificateTxtFile(taxCertificateTxtRecord([4 => $rawCid])."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'utf-8');

        expect($result)->toMatchArray(['read' => 1, 'valid' => 0, 'skipped' => 1, 'rows' => []]);

        $error = implode("\n", $result['errors']);

        expect($error)->toContain('CID:')
            ->toMatch('/\\*{2,}/')
            ->not->toContain($rawCid)
            ->and(json_encode($result, JSON_THROW_ON_ERROR))->toBeString();
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

// Production break caught: accepting a calendar-invalid Buddhist-era date produces a certificate with a false issue date.
test('it skips a row with an invalid Buddhist-era issue date', function () {
    $rawCid = '0000000000000';
    $path = taxCertificateTxtFile(taxCertificateTxtRecord([19 => '31022567'])."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'utf-8');

        expect($result)->toMatchArray(['read' => 1, 'valid' => 0, 'skipped' => 1, 'rows' => []]);

        $error = maskedTaxCertificateError($result, $rawCid);

        expect($error)->toContain('บรรทัด 1')
            ->toContain('วันที่');
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

dataset('unmapped tax-certificate codes', [
    'income form' => [9, '8', 'ภ.ง.ด.'],
    'fund' => [16, '3', 'กองทุน'],
    'payment method' => [18, '5', 'วิธีออก'],
]);

// Production break caught: accepting an unknown form, fund, or payment code would display an untraceable certificate classification.
test('it skips a row with an unmapped certificate code', function (int $field, string $code, string $errorFragment) {
    $rawCid = '0000000000000';
    $path = taxCertificateTxtFile(taxCertificateTxtRecord([$field => $code])."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'utf-8');

        expect($result)->toMatchArray(['read' => 1, 'valid' => 0, 'skipped' => 1, 'rows' => []]);

        $error = maskedTaxCertificateError($result, $rawCid);

        expect($error)->toContain('บรรทัด 1')
            ->toContain($errorFragment);
    } finally {
        removeTaxCertificateTxtFile($path);
    }
})->with('unmapped tax-certificate codes');

dataset('authorized legacy tax-certificate mappings', [
    'income type 1' => [9, '1', 'income_type_id', 'income_type_name', 'ภ.ง.ด.1ก'],
    'income type 2' => [9, '2', 'income_type_id', 'income_type_name', 'ภ.ง.ด.1ก พิเศษ'],
    'income type 3' => [9, '3', 'income_type_id', 'income_type_name', 'ภ.ง.ด.2'],
    'income type 4' => [9, '4', 'income_type_id', 'income_type_name', 'ภ.ง.ด.3'],
    'income type 5' => [9, '5', 'income_type_id', 'income_type_name', 'ภ.ง.ด.2ก'],
    'income type 6' => [9, '6', 'income_type_id', 'income_type_name', 'ภ.ง.ด.3ก'],
    'income type 7' => [9, '7', 'income_type_id', 'income_type_name', 'ภ.ง.ด.53'],
    'fund 1' => [16, '1', 'outcomeType', 'outcomeName', 'กบข.'],
    'fund 2' => [16, '2', 'outcomeType', 'outcomeName', 'กสจ.'],
    'payment method 1' => [18, '1', 'tax_type_id', 'tax_type_name', 'หัก ณ ที่จ่าย'],
    'payment method 2' => [18, '2', 'tax_type_id', 'tax_type_name', 'ออกให้ตลอดไป'],
    'payment method 3' => [18, '3', 'tax_type_id', 'tax_type_name', 'ออกให้ครั้งเดียว'],
    'payment method 4' => [18, '4', 'tax_type_id', 'tax_type_name', 'อื่นๆ'],
]);

// Production break caught: an incomplete or changed legacy code map would silently render the wrong certificate form, fund, or payment method.
test('it maps every authorized legacy tax-certificate code to its exact output field and label', function (int $field, string $code, string $idField, string $labelField, string $label) {
    $path = taxCertificateTxtFile(taxCertificateTxtRecord([$field => $code])."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'utf-8');

        expect($result)->toMatchArray(['read' => 1, 'valid' => 1, 'skipped' => 0, 'errors' => []]);

        $row = $result['rows'][0];

        expect($row)
            ->toHaveKey('income_type_id')
            ->toHaveKey('income_type_name')
            ->toHaveKey('outcomeType')
            ->toHaveKey('outcomeName')
            ->toHaveKey('tax_type_id')
            ->toHaveKey('tax_type_name')
            ->and($row[$idField])->toBe($code)
            ->and($row[$labelField])->toBe($label);
    } finally {
        removeTaxCertificateTxtFile($path);
    }
})->with('authorized legacy tax-certificate mappings');

// Production break caught: accepting a non-TXT upload bypasses the format boundary the parser is designed to validate.
test('it rejects a file without a TXT extension before parsing it', function () {
    $path = taxCertificateTxtFile(taxCertificateTxtRecord()."\n", 'csv');

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'utf-8');

        expect($result)->toMatchArray([
            'rows' => [],
            'read' => 0,
            'valid' => 0,
            'skipped' => 0,
        ])
            ->and(implode("\n", $result['errors']))->toContain('.txt');
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

// Production break caught: reading an oversized upload can exhaust worker memory and bypass the maximum upload contract.
test('it rejects a TXT file larger than five megabytes before parsing it', function () {
    $path = taxCertificateTxtFile(str_repeat('0', (5 * 1024 * 1024) + 1));

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'utf-8');

        expect($result)->toMatchArray([
            'rows' => [],
            'read' => 0,
            'valid' => 0,
            'skipped' => 0,
        ])
            ->and(implode("\n", $result['errors']))->toContain('5 MB');
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

// Production break caught: accumulating one row error per short invalid line can exhaust memory before the five-megabyte file limit is reached.
test('it bounds preview rows and diagnostics for many short invalid TXT lines', function () {
    $path = taxCertificateTxtFile(str_repeat(taxCertificateTxtRecord([4 => '123'])."\n", 1100));

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'utf-8');

        expect($result['rows'])->toBe([])
            ->and($result['valid'])->toBe(0)
            ->and($result['read'])->toBeLessThanOrEqual(1000)
            ->and($result['skipped'])->toBeLessThanOrEqual(1000)
            ->and($result['errors'])->toHaveCount(100)
            ->and(implode("\n", $result['errors']))->toContain('1000');
    } finally {
        removeTaxCertificateTxtFile($path);
    }
});

// Production break caught: a preview path that writes a tax row turns a user review into an unintended financial-data mutation.
test('it previews a valid TXT row without inserting a tax record', function () {
    Schema::dropIfExists('data_tax');
    Schema::create('data_tax', function (Blueprint $table) {
        $table->string('cid', 13);
        $table->unsignedInteger('income_year');
    });
    $path = taxCertificateTxtFile(taxCertificateTxtRecord()."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->preview($path, 'utf-8');

        expect($result)->toMatchArray(['read' => 1, 'valid' => 1, 'skipped' => 0, 'errors' => []])
            ->and(DB::table('data_tax')->count())->toBe(0);
    } finally {
        removeTaxCertificateTxtFile($path);
        Schema::dropIfExists('data_tax');
    }
});
