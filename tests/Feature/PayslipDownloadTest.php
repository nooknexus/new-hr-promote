<?php

use App\Livewire\Profile\Payslip;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Schema::dropIfExists('data_salary');
    (require database_path('migrations/2025_06_06_000001_create_data_salary_table.php'))->up();

    Schema::dropIfExists('person');
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13);
        $table->string('type_name')->nullable();
        $table->string('office_name')->nullable();
    });

    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('pid', 13)->nullable();
        $table->string('no_position')->nullable();
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

    $user = new User;
    $user->forceFill(['id' => 1, 'pid' => '1111111111111', 'name' => 'ทดสอบ ดาวน์โหลด']);
    $user->exists = true;
    $this->actingAs($user);

    DB::table('data_salary')->insert([
        'yearNO' => '2568',
        'monthNO' => '07',
        'cid' => '1111111111111',
        'incomeSalaryAmount' => '31350',
        'summaryIncomeAmount' => '31350',
        'summaryOutcomeAmount' => '1000',
        'summaryTotalAmount' => '30350',
    ]);
});

test('payslip year buttons expose the selected Buddhist year after switching years', function () {
    DB::table('data_salary')->insert(['yearNO' => '2567', 'monthNO' => '07', 'cid' => '1111111111111']);
    $component = Livewire::test(Payslip::class);
    $selectedYear = function () use ($component): string {
        $document = new DOMDocument;
        @$document->loadHTML(mb_convert_encoding($component->html(), 'HTML-ENTITIES', 'UTF-8'));
        $buttons = (new DOMXPath($document))->query('//button[@aria-pressed="true"]');
        expect($buttons->length)->toBe(1);
        return trim($buttons->item(0)->textContent);
    };
    expect($selectedYear())->toBe('ปี2568');
    $component->call('selectYear', 2024)->assertSet('selectedYear', 2024);
    expect($selectedYear())->toBe('ปี2567');
});

test('the preview download control directly requests the authenticated PDF route', function () {
    $url = route('payslip.download', ['id' => '256807']);

    Livewire::test(Payslip::class)
        ->set('selectedPayslip', [
            'id' => '256807',
            'month' => 'กรกฎาคม 2568',
            'total_income' => 31350,
            'total_outcome' => 1000,
            'net' => 30350,
            'income_details' => [['name' => 'เงินเดือน', 'amount' => 31350]],
            'expense_details' => [['name' => 'ภาษี', 'amount' => 1000]],
            'person_info' => [],
        ])
        ->assertSee('href="'.$url.'"', false)
        ->assertDontSee('wire:click="generatePDF', false);
});

test('an authenticated employee downloads a valid PDF for their own pay period', function () {
    $response = $this->get(route('payslip.download', ['id' => '256807']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="payslip_2568_07.pdf"');

    $content = $response->getContent();
    expect($content)->toStartWith('%PDF-');

    $path = tempnam(sys_get_temp_dir(), 'payslip-download-');
    file_put_contents($path, $content);

    try {
        $pdfInfo = new Process(['pdfinfo', $path]);
        $pdfInfo->mustRun();
        expect($pdfInfo->getOutput())->toMatch('/^Pages:\s+1$/m');
    } finally {
        unlink($path);
    }
});

test('the PDF download route does not expose another employees pay period', function () {
    DB::table('data_salary')->insert([
        'yearNO' => '2568',
        'monthNO' => '08',
        'cid' => '2222222222222',
        'incomeSalaryAmount' => '31350',
    ]);

    $this->get(route('payslip.download', ['id' => '256808']))->assertNotFound();
});

test('the payslip page starts with the shared page header', function () {
    Livewire::test(Payslip::class)
        ->assertSeeHtml('<h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">สลิปเงินเดือน</h1>');
});

test('payslips stored with single-digit months are listed, ordered and downloadable', function () {
    foreach (['9', '12', '1'] as $month) {
        DB::table('data_salary')->insert([
            'yearNO' => '2569',
            'monthNO' => $month,
            'cid' => '1111111111111',
            'incomeSalaryAmount' => '31350',
            'summaryIncomeAmount' => '31350',
            'summaryOutcomeAmount' => '1000',
            'summaryTotalAmount' => '30350',
        ]);
    }

    Livewire::test(Payslip::class)
        ->call('selectYear', 2026)
        ->assertSet('payslips.0.id', '256912')
        ->assertSet('payslips.0.month', 'ธันวาคม 2569')
        ->assertSet('payslips.1.id', '256909')
        ->assertSet('payslips.1.month', 'กันยายน 2569')
        ->assertSet('payslips.2.id', '256901');

    $this->get(route('payslip.download', ['id' => '256909']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('TXT and Excel payslip months normalise to two digits', function () {
    expect(\App\Models\DataSalary::normalizeMonth('9'))->toBe('09')
        ->and(\App\Models\DataSalary::normalizeMonth(' 9 '))->toBe('09')
        ->and(\App\Models\DataSalary::normalizeMonth('12'))->toBe('12')
        ->and(\App\Models\DataSalary::monthVariants('09'))->toBe(['09', '9'])
        ->and(\App\Models\DataSalary::periodBindings('256909'))->toBe(['2569', '09', '9']);
});

test('a user without any payslip sees guidance instead of an empty year', function () {
    $user = new User;
    $user->forceFill(['id' => 2, 'pid' => '2222222222222', 'name' => 'ไม่มี สลิป']);
    $user->exists = true;
    $this->actingAs($user);

    Livewire::test(Payslip::class)
        ->assertSet('years', [])
        ->assertSet('selectedYear', null)
        ->assertSee('ยังไม่มีข้อมูลสลิปเงินเดือนของท่านในระบบ')
        ->assertSee('กรุณาติดต่อเจ้าหน้าที่การเงินของหน่วยงาน')
        ->assertDontSee('ปี2569')
        ->assertDontSee('ใบรับรองการจ่ายค่าตอบแทน (ปี');
});
