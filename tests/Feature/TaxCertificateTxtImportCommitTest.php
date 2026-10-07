<?php

use App\Services\TaxCertificateTxtImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** @param array<int, string> $overrides */
function taxCertificateCommitRecord(array $overrides = []): string
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

function taxCertificateCommitFile(string $contents): string
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'tax-certificate-commit-');

    if ($temporaryPath === false) {
        throw new RuntimeException('Unable to create a temporary tax certificate fixture.');
    }

    $path = $temporaryPath.'.txt';

    if (! rename($temporaryPath, $path)) {
        @unlink($temporaryPath);

        throw new RuntimeException('Unable to name a temporary tax certificate fixture.');
    }

    file_put_contents($path, $contents);

    return $path;
}

function removeTaxCertificateCommitFile(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }
}

function createTaxCertificateCommitTable(): void
{
    Schema::dropIfExists('data_tax');
    Schema::create('data_tax', function (Blueprint $table) {
        $table->string('cid', 13);
        $table->unsignedInteger('income_year');
        $table->string('running_code1')->nullable();
        $table->string('department_name')->nullable();
        $table->string('department_tax_id')->nullable();
        $table->string('department_addr')->nullable();
        $table->string('tax_id')->nullable();
        $table->string('fullname')->nullable();
        $table->string('addr')->nullable();
        $table->string('running_code2')->nullable();
        $table->string('income_type_id')->nullable();
        $table->string('income_type_name')->nullable();
        $table->decimal('income_money', 15, 2)->nullable();
        $table->decimal('withholding_tax', 15, 2)->nullable();
        $table->decimal('total_money', 15, 2)->nullable();
        $table->decimal('sumTaxAmount', 15, 2)->nullable();
        $table->string('sumTaxText')->nullable();
        $table->string('outcomeType')->nullable();
        $table->string('outcomeName')->nullable();
        $table->decimal('outcomeAmount', 15, 2)->nullable();
        $table->string('tax_type_id')->nullable();
        $table->string('tax_type_name')->nullable();
        $table->string('date_report')->nullable();
        $table->decimal('socialSecurity', 15, 2)->nullable();
        $table->dateTime('r_update')->nullable();
        $table->unique(['cid', 'income_year']);
    });
}

beforeEach(function () {
    createTaxCertificateCommitTable();
});

afterEach(function () {
    Schema::dropIfExists('data_tax');
});

// Production break caught: replacing source rows or omitting source-backed columns would erase unrelated certificates or leave imported details stale.
test('it upserts valid source identities without deleting certificates absent from the file', function () {
    $existingCid = '0000000000001';
    $newCid = '0000000000002';
    $unrelatedCid = '0000000000009';

    DB::table('data_tax')->insert([
        'cid' => $existingCid,
        'income_year' => 2567,
        'running_code1' => 'OLD-ROW',
        'fullname' => 'ข้อมูลเดิม',
        'socialSecurity' => 321.25,
        'r_update' => '2000-01-01 00:00:00',
    ]);
    DB::table('data_tax')->insert([
        'cid' => $unrelatedCid,
        'income_year' => 2566,
        'running_code1' => 'KEEP-ME',
        'fullname' => 'ข้อมูลที่ไม่อยู่ในไฟล์',
        'socialSecurity' => 987.65,
        'r_update' => '2000-01-01 00:00:00',
    ]);

    $contents = implode("\n", [
        taxCertificateCommitRecord([
            0 => 'UPDATED-ROW',
            4 => $existingCid,
            6 => 'ผู้ทดสอบ อัปเดต',
        ]),
        taxCertificateCommitRecord([
            0 => 'NEW-ROW',
            4 => $newCid,
            6 => 'ผู้ทดสอบ ใหม่',
        ]),
    ])."\n";
    $tis620Contents = iconv('UTF-8', 'TIS-620', $contents);

    expect($tis620Contents)->not->toBeFalse();

    $path = taxCertificateCommitFile($tis620Contents);

    try {
        $result = (new TaxCertificateTxtImportService)->commit($path);

        expect($result)->toMatchArray([
            'read' => 2,
            'inserted' => 1,
            'updated' => 1,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ]);

        $inserted = (array) DB::table('data_tax')
            ->where('cid', $newCid)
            ->where('income_year', 2567)
            ->first();
        $updated = (array) DB::table('data_tax')
            ->where('cid', $existingCid)
            ->where('income_year', 2567)
            ->first();
        $unrelated = (array) DB::table('data_tax')
            ->where('cid', $unrelatedCid)
            ->where('income_year', 2566)
            ->first();

        expect($inserted)->toMatchArray([
            'cid' => $newCid,
            'income_year' => 2567,
            'running_code1' => 'NEW-ROW',
            'department_name' => 'กรมทดสอบ',
            'department_tax_id' => '0105550000000',
            'department_addr' => '99 ถนนทดสอบ',
            'tax_id' => '0105550000000',
            'fullname' => 'ผู้ทดสอบ ใหม่',
            'addr' => '1 ถนนผู้รับเงิน',
            'running_code2' => '0001',
            'income_type_id' => '2',
            'income_type_name' => 'ภ.ง.ด.1ก พิเศษ',
            'sumTaxText' => 'สามหมื่นหนึ่งพันห้าร้อยห้าสิบบาทถ้วน',
            'outcomeType' => '1',
            'outcomeName' => 'กบข.',
            'tax_type_id' => '3',
            'tax_type_name' => 'ออกให้ครั้งเดียว',
            'date_report' => '29012568',
            'socialSecurity' => null,
        ])
            ->and((float) $inserted['income_money'])->toBe(31550.0)
            ->and((float) $inserted['withholding_tax'])->toBe(12.34)
            ->and((float) $inserted['total_money'])->toBe(31562.34)
            ->and((float) $inserted['sumTaxAmount'])->toBe(12.34)
            ->and((float) $inserted['outcomeAmount'])->toBe(1893.0)
            ->and($inserted['r_update'])->not->toBeNull();

        expect($updated['running_code1'])->toBe('UPDATED-ROW')
            ->and($updated['fullname'])->toBe('ผู้ทดสอบ อัปเดต')
            ->and((float) $updated['socialSecurity'])->toBe(321.25)
            ->and($updated['r_update'])->not->toBe('2000-01-01 00:00:00');

        expect($unrelated)->toMatchArray([
            'running_code1' => 'KEEP-ME',
            'fullname' => 'ข้อมูลที่ไม่อยู่ในไฟล์',
        ])
            ->and((float) $unrelated['socialSecurity'])->toBe(987.65)
            ->and($unrelated['r_update'])->toBe('2000-01-01 00:00:00');
    } finally {
        removeTaxCertificateCommitFile($path);
    }
});

// Production break caught: accepting a repeated identity as a later update silently makes the last source row win.
test('it rejects a duplicate source identity without overwriting the first row', function () {
    $cid = '0000000000003';
    $path = taxCertificateCommitFile(implode("\n", [
        taxCertificateCommitRecord([0 => 'FIRST-ROW', 4 => $cid, 6 => 'ชื่อแถวแรก']),
        taxCertificateCommitRecord([0 => 'SECOND-ROW', 4 => $cid, 6 => 'ชื่อแถวหลัง']),
    ])."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->commit($path, 'utf-8');
        $stored = (array) DB::table('data_tax')
            ->where('cid', $cid)
            ->where('income_year', 2567)
            ->first();
        $errors = implode("\n", $result['errors']);

        expect($result)->toMatchArray([
            'read' => 2,
            'inserted' => 1,
            'updated' => 0,
            'skipped' => 1,
            'failed' => 0,
        ])
            ->and($errors)->toContain('บรรทัด 2')
            ->toContain('ซ้ำ')
            ->not->toContain($cid)
            ->and($stored['running_code1'])->toBe('FIRST-ROW')
            ->and($stored['fullname'])->toBe('ชื่อแถวแรก');
    } finally {
        removeTaxCertificateCommitFile($path);
    }
});

// Production break caught: aborting after an invalid source row loses valid certificates later in the same upload, while writing it stores an untrusted identity.
test('it skips an invalid row while committing later valid rows', function () {
    $invalidCid = '123456789012';
    $validCid = '0000000000004';
    $path = taxCertificateCommitFile(implode("\n", [
        taxCertificateCommitRecord([4 => $invalidCid]),
        taxCertificateCommitRecord([0 => 'VALID-AFTER-INVALID', 4 => $validCid]),
    ])."\n");

    try {
        $result = (new TaxCertificateTxtImportService)->commit($path, 'utf-8');
        $errors = implode("\n", $result['errors']);

        expect($result)->toMatchArray([
            'read' => 2,
            'inserted' => 1,
            'updated' => 0,
            'skipped' => 1,
            'failed' => 0,
        ])
            ->and($errors)->toContain('13')
            ->not->toContain($invalidCid)
            ->and(DB::table('data_tax')->where('cid', $invalidCid)->count())->toBe(0)
            ->and(DB::table('data_tax')->where('cid', $validCid)->count())->toBe(1);
    } finally {
        removeTaxCertificateCommitFile($path);
    }
});

// Production break caught: reusing the bounded preview rows drops valid certificates after the first thousand source rows.
test('it commits valid rows beyond the preview row limit', function () {
    $records = [];

    for ($row = 1; $row <= 1001; $row++) {
        $records[] = taxCertificateCommitRecord([
            0 => 'STREAM-'.$row,
            4 => str_pad((string) $row, 13, '0', STR_PAD_LEFT),
        ]);
    }

    $path = taxCertificateCommitFile(implode("\n", $records)."\n");

    try {
        $service = new TaxCertificateTxtImportService;
        $preview = $service->preview($path, 'utf-8');
        $result = $service->commit($path, 'utf-8');

        expect($preview['read'])->toBe(1000)
            ->and($preview['valid'])->toBe(1000)
            ->and(implode("\n", $preview['errors']))->toContain('1000');

        expect($result)->toMatchArray([
            'read' => 1001,
            'inserted' => 1001,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ])
            ->and(DB::table('data_tax')->count())->toBe(1001)
            ->and(DB::table('data_tax')->where('cid', '0000000001001')->count())->toBe(1);
    } finally {
        removeTaxCertificateCommitFile($path);
    }
});
