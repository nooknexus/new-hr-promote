<?php

use App\Services\SalaryPromotionImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Support\SalaryPromotionWorkbook;

beforeEach(function () {
    Schema::create('data_promote', function (Blueprint $table) {
        $table->id();
        $table->string('CID', 13);
        $table->date('evaluation_date');
        $table->dateTime('deleted_at')->nullable();
    });
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('no_position')->nullable();
    });
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('pid', 13)->nullable();
        $table->string('thaid_pid', 13)->nullable();
        $table->string('no_position')->nullable();
    });
});

test('preview accepts the supplied Thai workbook and preserves salary and leading-zero position values without writes', function () {
    $file = new SalaryPromotionWorkbook;
    $result = app(SalaryPromotionImportService::class)->preview($file->path);

    expect($result['insert_count'])->toBe(1)
        ->and($result['update_count'])->toBe(0)
        ->and($result['unmatched_person_count'])->toBe(1)
        ->and($result['unmatched_user_count'])->toBe(1)
        ->and($result['rows'][0]['cid'])->toBe('3650200600388')
        ->and($result['rows'][0]['evaluation_date'])->toBe('2022-10-01')
        ->and($result['rows'][0]['no_position'])->toBe('01พล00139')
        ->and($result['rows'][0]['before_salary'])->toBe('13780.00')
        ->and($result['rows'][0]['base'])->toBe('13780.00')
        ->and($result['rows'][0]['sum'])->toBe('610.00')
        ->and($result['rows'][0]['new_salary'])->toBe('14390.00')
        ->and($result['warnings'])->not->toBeEmpty()
        ->and($result['errors'])->toBe([])
        ->and(DB::table('data_promote')->count())->toBe(0);
});

test('preview accepts whitespace in headers and an Excel date cell', function () {
    $file = new SalaryPromotionWorkbook(configure: function (Worksheet $sheet): void {
        $sheet->setCellValue('A1', " เลขบัตรประชาชน\u{00A0}");
        $sheet->setCellValue('C2', ExcelDate::PHPToExcel(new DateTimeImmutable('2022-10-01')));
        $sheet->getStyle('C2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
    });

    expect(app(SalaryPromotionImportService::class)->preview($file->path)['rows'][0]['evaluation_date'])
        ->toBe('2022-10-01');
});

test('preview rejects missing or repeated recognized headers', function () {
    $missing = new SalaryPromotionWorkbook(configure: fn (Worksheet $sheet) => $sheet->setCellValue('C1', 'ไม่ใช่รอบการประเมิน'));
    $duplicate = new SalaryPromotionWorkbook(configure: fn (Worksheet $sheet) => $sheet->setCellValue('B1', 'เลขบัตรประชาชน'));

    expect(fn () => app(SalaryPromotionImportService::class)->preview($missing->path))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(SalaryPromotionImportService::class)->preview($duplicate->path))
        ->toThrow(ValidationException::class);
});

test('preview reports duplicate row keys', function () {
    $duplicate = new SalaryPromotionWorkbook([SalaryPromotionWorkbook::ROW, SalaryPromotionWorkbook::ROW]);

    expect(implode(' ', app(SalaryPromotionImportService::class)->preview($duplicate->path)['errors']))
        ->toContain('ซ้ำ');
});

test('preview reports archived promotion matches', function () {
    $file = new SalaryPromotionWorkbook;
    DB::table('data_promote')->insert([
        'CID' => '3650200600388', 'evaluation_date' => '2022-10-01', 'deleted_at' => now(),
    ]);

    expect(implode(' ', app(SalaryPromotionImportService::class)->preview($file->path)['errors']))
        ->toContain('ยกเลิก');
});

test('preview rejects formula cells in required numeric columns', function () {
    $formula = new SalaryPromotionWorkbook(configure: fn (Worksheet $sheet) => $sheet->setCellValue('M2', '=1+1'));

    expect(implode(' ', app(SalaryPromotionImportService::class)->preview($formula->path)['errors']))
        ->toContain('สูตร');
});

test('preview rejects an invalid citizen identifier', function () {
    $row = SalaryPromotionWorkbook::ROW;
    $row[0] = '123';
    $file = new SalaryPromotionWorkbook([$row]);

    expect(implode(' ', app(SalaryPromotionImportService::class)->preview($file->path)['errors']))
        ->toContain('13 หลัก');
});
