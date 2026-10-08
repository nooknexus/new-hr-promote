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
