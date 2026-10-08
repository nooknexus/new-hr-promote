<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Schema::dropIfExists('data_tax');
    Schema::create('data_tax', function (Blueprint $table) {
        $table->string('CID', 13);
        $table->unsignedInteger('income_year');
        $table->string('running_code1');
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
        $table->decimal('total_money', 15, 2)->nullable();
        $table->decimal('withholding_tax', 15, 2)->nullable();
        $table->decimal('sumTaxAmount', 15, 2)->nullable();
        $table->string('sumTaxText')->nullable();
        $table->string('outcomeType')->nullable();
        $table->string('outcomeName')->nullable();
        $table->decimal('outcomeAmount', 15, 2)->nullable();
        $table->string('tax_type_id')->nullable();
        $table->string('tax_type_name')->nullable();
        $table->decimal('socialSecurity', 15, 2)->nullable();
        $table->string('date_report')->nullable();
        $table->string('r_update')->nullable();
    });

    Schema::dropIfExists('payslip_signatories');
    Schema::create('payslip_signatories', function (Blueprint $table) {
        $table->id();
        $table->unsignedSmallInteger('fiscal_year')->unique();
        $table->string('name');
        $table->string('position_line1');
        $table->string('position_line2')->nullable();
        $table->binary('signature_image')->nullable();
        $table->string('signature_mime')->nullable();
        $table->timestamps();
    });
});

/** @return array<string, float|int|string|null> */
function importedTaxCertificateRow(array $overrides = []): array
{
    return array_replace([
        'CID' => '1571200051606',
        'income_year' => 2567,
        'running_code1' => 'TAX-2567-01',
        'department_name' => 'PAYER-A',
        'department_tax_id' => '0105550000000',
        'department_addr' => 'PAYER-ADDR',
        'tax_id' => '0123456789012',
        'fullname' => 'RECIPIENT-A',
        'addr' => 'RECIPIENT-ADDR',
        'running_code2' => '0001',
        'income_type_id' => '3',
        'income_type_name' => 'FORM-3',
        'income_money' => 987654.32,
        'withholding_tax' => 12345.67,
        'total_money' => 987654.32,
        'sumTaxAmount' => 12345.67,
        'sumTaxText' => 'TAX-TEXT-12345',
        'outcomeType' => '2',
        'outcomeName' => 'FUND-2',
        'outcomeAmount' => 4567.89,
        'tax_type_id' => '3',
        'tax_type_name' => 'PAYMENT-3',
        'socialSecurity' => null,
        'date_report' => '29012568',
        'r_update' => '29012568',
    ], $overrides);
}

// Production break caught: preview and download substitute hard-coded or profile values for the imported certificate record.
test('the authenticated user preview and download render imported certificate details', function () {
    $user = new User;
    $user->forceFill([
        'id' => 1,
        'name' => 'PROFILE-NAME-ONLY',
        'pid' => '1571200051606',
        'department' => 'PROFILE-DEPARTMENT-ONLY',
    ]);
    $user->exists = true;

    DB::table('data_tax')->insert(importedTaxCertificateRow());
    $this->actingAs($user);

    $preview = Livewire::test(\App\Livewire\TaxCertificate::class)
        ->call('selectYear', 2567)
        ->call('previewDocument', 0)
        ->html();

    expect($preview)
        ->toContain('PAYER-A')
        ->toContain('PAYER-ADDR')
        ->toContain('0-1055-50000-00-0')
        ->toContain('RECIPIENT-A')
        ->toContain('RECIPIENT-ADDR')
        ->toContain('1-5712-00051-60-6')
        ->toContain('0-1234-56789-01-2')
        ->toContain('FORM-3')
        ->toContain('FUND-2')
        ->toContain('4,567.89')
        ->toContain('PAYMENT-3')
        ->toContain('TAX-TEXT-12345')
        ->toContain('29 มกราคม 2568')
        ->not->toContain('PROFILE-NAME-ONLY')
        ->not->toContain('PROFILE-DEPARTMENT-ONLY');

    $response = $this->get(route('tax.certificate.download', ['taxYear' => 2567, 'certificateNo' => 'TAX-2567-01']))
        ->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'imported-tax-certificate-');
    file_put_contents($path, $response->getContent());

    try {
        $text = new Process(['pdftotext', '-layout', $path, '-']);
        $text->mustRun();

        expect($text->getOutput())
            ->toContain('PAYER-A')
            ->toContain('PAYER-ADDR')
            ->toContain('RECIPIENT-A')
            ->toContain('RECIPIENT-ADDR')
            ->toContain('FORM-3')
            ->toContain('FUND-2')
            ->toContain('PAYMENT-3')
            ->toContain('TAX-TEXT-12345')
            ->toContain('29 มกราคม 2568')
            ->toMatch('/987,654\s+32/')
            ->toMatch('/12,345\s+67/');
    } finally {
        unlink($path);
    }
});

test('the preview signs with the signatory for the fiscal year of its issue date', function () {
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'ทดสอบ เอกสาร', 'pid' => '1571200051606']);
    $user->exists = true;
    $this->actingAs($user);

    DB::table('data_tax')->insert([
        'CID' => $user->pid,
        'income_year' => 2567,
        'running_code1' => '151302',
        'total_money' => 315500,
        'withholding_tax' => 0,
        'date_report' => '29012568',
    ]);

    $signature = file_get_contents(public_path('img/sign.png'));
    DB::table('payslip_signatories')->insert([
        'fiscal_year' => 2567,
        'name' => 'ผู้ลงนามปีภาษีเดิม',
        'position_line1' => 'ผู้จ่ายเงิน',
        'signature_image' => $signature,
        'signature_mime' => 'image/png',
    ]);

    expect(Livewire::test(\App\Livewire\TaxCertificate::class)
        ->call('selectYear', 2567)
        ->call('previewDocument', 0)
        ->html())->not->toContain('tax-certificate-signature-image');

    DB::table('payslip_signatories')->insert([
        'fiscal_year' => 2568,
        'name' => 'ผู้ลงนามปีงบที่ออกเอกสาร',
        'position_line1' => 'ผู้จ่ายเงิน',
        'signature_image' => $signature,
        'signature_mime' => 'image/png',
    ]);

    expect(Livewire::test(\App\Livewire\TaxCertificate::class)
        ->call('selectYear', 2567)
        ->call('previewDocument', 0)
        ->html())->toContain('tax-certificate-signature-image')
        ->toContain('src="data:image/png;base64,'.base64_encode($signature).'"');
});

test('a tampered preview year cannot expose a signatory for a different tax record', function () {
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'ทดสอบ เอกสาร', 'pid' => '1571200051606']);
    $user->exists = true;
    $this->actingAs($user);

    DB::table('data_tax')->insert([
        'CID' => $user->pid,
        'income_year' => 2567,
        'running_code1' => '151302',
    ]);
    DB::table('payslip_signatories')->insert([
        'fiscal_year' => 2568,
        'name' => 'ผู้ลงนามปีอื่น',
        'position_line1' => 'ผู้จ่ายเงิน',
        'signature_image' => file_get_contents(public_path('img/sign.png')),
        'signature_mime' => 'image/png',
    ]);

    Livewire::test(\App\Livewire\TaxCertificate::class)
        ->set('availableCertificates', [[
            'taxYear' => 2568,
            'certificateNo' => '151302',
            'totalIncome' => 0,
            'taxWithheld' => 0,
            'governmentPensionFund' => 0,
            'providentFund' => 0,
            'formattedIssueDate' => '',
            'employeeInfo' => ['name' => $user->name, 'pid' => $user->pid, 'department' => ''],
        ]])
        ->call('previewDocument', 0)
        ->assertSet('previewCertificate', null);
});

test('the downloaded PDF signs with the signatory for the fiscal year of its issue date', function () {
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'ทดสอบ เอกสาร', 'pid' => '1571200051606']);
    $user->exists = true;

    DB::table('data_tax')->insert([
        'CID' => $user->pid,
        'income_year' => 2567,
        'running_code1' => '151302',
        'total_money' => 315500,
        'withholding_tax' => 0,
        'date_report' => '29012568',
    ]);

    $signature = file_get_contents(public_path('img/sign.png'));
    DB::table('payslip_signatories')->insert([
        'fiscal_year' => 2567,
        'name' => 'ผู้ลงนามปีภาษีเดิม',
        'position_line1' => 'ผู้จ่ายเงิน',
        'signature_image' => $signature,
        'signature_mime' => 'image/png',
    ]);

    $imageCount = static function (string $pdf): int {
        $path = tempnam(sys_get_temp_dir(), 'tax-signatory-');
        file_put_contents($path, $pdf);

        try {
            $images = new Process(['pdfimages', '-list', $path]);
            $images->mustRun();

            return preg_match_all('/^\s*\d+\s+\d+\s+image\s+/m', $images->getOutput());
        } finally {
            unlink($path);
        }
    };

    $url = route('tax.certificate.download', ['taxYear' => 2567, 'certificateNo' => '151302']);
    $withoutMatch = $this->actingAs($user)->get($url)->assertOk();
    expect($imageCount($withoutMatch->getContent()))->toBe(0);

    DB::table('payslip_signatories')->insert([
        'fiscal_year' => 2568,
        'name' => 'ผู้ลงนามปีงบที่ออกเอกสาร',
        'position_line1' => 'ผู้จ่ายเงิน',
        'signature_image' => $signature,
        'signature_mime' => 'image/png',
    ]);

    $withMatch = $this->get($url)->assertOk();
    expect($imageCount($withMatch->getContent()))->toBe(1);
});

test('a user downloads their own certificate as a PDF with its document number', function () {
    $user = new User;
    $user->forceFill([
        'id' => 1,
        'name' => 'ภาณุพงษ์ ดงเย็น',
        'pid' => '1571200051606',
        'department' => 'สำนักงานสาธารณสุขจังหวัดพิษณุโลก',
    ]);
    $user->exists = true;

    DB::table('data_tax')->insert([
        'CID' => '1571200051606',
        'income_year' => 2567,
        'running_code1' => '151302',
        'total_money' => 315500,
        'withholding_tax' => 0,
        'outcomeName' => 'กบข.',
        'outcomeAmount' => 18930,
        'date_report' => '29012568',
        'r_update' => '29012568',
    ]);

    $response = $this->actingAs($user)
        ->get(route('tax.certificate.download', ['taxYear' => 2567, 'certificateNo' => '151302']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="tax_certificate_2567_151302.pdf"')
        ->assertSee('%PDF');

    expect($response->headers->get('content-length'))->toBe((string) strlen($response->getContent()))
        ->and($response->headers->get('x-content-type-options'))->toBe('nosniff');
});

test('a user cannot download another users tax certificate', function () {
    $user = new User;
    $user->forceFill(['id' => 1, 'pid' => '1571200051606']);
    $user->exists = true;

    DB::table('data_tax')->insert([
        'CID' => '9999999999999',
        'income_year' => 2567,
        'running_code1' => '151302',
    ]);

    $this->actingAs($user)
        ->get(route('tax.certificate.download', ['taxYear' => 2567, 'certificateNo' => '151302']))
        ->assertNotFound();
});

test('the tax certificate signatory follows the fiscal year of the issue date', function () {
    expect(\App\Models\PayslipSignatory::taxCertificateFiscalYear('29012568', 2567))->toBe(2568)
        ->and(\App\Models\PayslipSignatory::taxCertificateFiscalYear('15102568', 2567))->toBe(2569)
        ->and(\App\Models\PayslipSignatory::taxCertificateFiscalYear('30092568', 2567))->toBe(2568)
        ->and(\App\Models\PayslipSignatory::taxCertificateFiscalYear(null, 2567))->toBe(2568)
        ->and(\App\Models\PayslipSignatory::taxCertificateFiscalYear('', 2567))->toBe(2568)
        ->and(\App\Models\PayslipSignatory::taxCertificateFiscalYear('31022568', 2567))->toBe(2568);
});
