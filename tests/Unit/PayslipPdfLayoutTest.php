<?php

use App\Services\PayslipPdfService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

// PayslipPdfService now looks up PayslipSignatory in the database. The
// project's full migration history isn't sqlite-compatible (see
// database/migrations/2025_06_05_000002_rename_pid_to_thaid_pid.php), so
// running php artisan migrate against the in-memory test database isn't an
// option here — create just the one table this suite needs instead.
beforeEach(function () {
    if (! Schema::hasTable('payslip_signatories')) {
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
    }
});

test('a detailed payslip remains a single A4 page', function () {
    Auth::shouldReceive('user')->andReturn((object) [
        'firstname' => 'สมชาย',
        'lastname' => 'ทดสอบ',
    ]);

    $details = array_map(fn (int $index): array => [
        'name' => "รายการทดสอบ {$index}",
        'amount' => 100,
    ], range(1, 24));

    $result = PayslipPdfService::generatePdf([
        'id' => '202509',
        'month' => 'กันยายน 2568',
        'total_income' => 2400,
        'total_outcome' => 2400,
        'net' => 0,
        'income_details' => $details,
        'expense_details' => $details,
        'person_info' => [],
    ], 'test-cid');

    $path = tempnam(sys_get_temp_dir(), 'payslip-layout-');
    file_put_contents($path, $result['content']);

    try {
        $pdfInfo = new Process(['pdfinfo', $path]);
        $pdfInfo->run();

        expect($pdfInfo->getExitCode())->toBe(0)
            ->and($pdfInfo->getOutput())->toMatch('/^Pages:\s+1$/m');
    } finally {
        unlink($path);
    }
});

test('the modal preview uses a dedicated A4 stage and document styling', function () {
    $preview = file_get_contents(resource_path('views/livewire/profile/payslip.blade.php'));
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($preview)->toContain('payslip-preview-stage')
        ->and($preview)->toContain('height: calc(29.7cm - 2cm)')
        ->and($styles)->toContain('.payslip-preview-stage')
        ->and($styles)->toContain('.pdf-preview .col-6');
});

test('the modal preview centers every signature line in one shared block', function () {
    $preview = file_get_contents(resource_path('views/livewire/profile/payslip.blade.php'));
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($preview)->toContain('payslip-signature-block')
        ->and($preview)->not->toContain('margin-left: 37%')
        ->and($styles)->toContain('.pdf-preview .payslip-signature-block')
        ->and($styles)->toContain('margin-left: auto')
        ->and($styles)->toContain('margin-right: 1cm')
        ->and($styles)->toContain('width: 12mm')
        ->and($preview)->toContain('padding-right: 4.8%')
        ->and($preview)->toContain('padding-left: 4.8%');
});

test('the printable payslip is self styled without Bootstrap', function () {
    $html = view('payslip.print-view', [
        'user' => (object) ['name' => 'สมชาย ทดสอบ'],
        'payslip' => [
            'month' => 'กันยายน 2568',
            'person_info' => [],
            'income_details' => [],
            'expense_details' => [],
            'total_income' => 0,
            'total_outcome' => 0,
            'net' => 0,
        ],
    ])->render();

    expect($html)->not->toContain('bootstrap@')
        ->and($html)->toContain('payslip-document')
        ->and($html)->toContain('@page');
});

test('the downloaded PDF preserves the signature image aspect ratio', function () {
    Auth::shouldReceive('user')->andReturn((object) [
        'firstname' => 'สมชาย',
        'lastname' => 'ทดสอบ',
    ]);

    $result = PayslipPdfService::generatePdf([
        'id' => '202509',
        'month' => 'กันยายน 2568',
        'total_income' => 0,
        'total_outcome' => 0,
        'net' => 0,
        'income_details' => [],
        'expense_details' => [],
        'person_info' => [],
    ], 'test-cid');

    $path = tempnam(sys_get_temp_dir(), 'payslip-signature-');
    file_put_contents($path, $result['content']);

    try {
        $images = new Process(['pdfimages', '-list', $path]);
        $images->run();

        preg_match('/^\s*\d+\s+\d+\s+image\s+150\s+132\s+\S+\s+\d+\s+\d+\s+\S+\s+\S+\s+\d+\s+\d+\s+(\d+)\s+(\d+)/m', $images->getOutput(), $match);

        expect($images->getExitCode())->toBe(0)
            ->and($match)->not->toBeEmpty()
            ->and((int) $match[1])->toBe((int) $match[2])
            ->and((int) $match[1])->toBe(318);
    } finally {
        unlink($path);
    }
});

test('the downloaded PDF centers signature details in its right aligned block', function () {
    Auth::shouldReceive('user')->andReturn((object) [
        'name' => 'สมชาย ทดสอบ',
    ]);

    $result = PayslipPdfService::generatePdf([
        'id' => '202509',
        'month' => 'กันยายน 2568',
        'total_income' => 0,
        'total_outcome' => 0,
        'net' => 0,
        'income_details' => [],
        'expense_details' => [],
        'person_info' => [],
    ], 'test-cid');

    $path = tempnam(sys_get_temp_dir(), 'payslip-signature-block-');
    $bboxPath = "{$path}.html";
    file_put_contents($path, $result['content']);

    try {
        $text = new Process(['pdftotext', '-bbox', $path, $bboxPath]);
        $text->run();
        preg_match('/<word xMin="([^"]+)" yMin="[^"]+" xMax="[^"]+"[^>]*>\(นายมนัส/su', file_get_contents($bboxPath), $match);

        expect($text->getExitCode())->toBe(0)
            ->and($match)->not->toBeEmpty()
            ->and(round((float) $match[1]))->toBe(383.0);
    } finally {
        unlink($path);
        unlink($bboxPath);
    }
});

test('the downloaded PDF uses the account name when first and last names are unavailable', function () {
    Auth::shouldReceive('user')->andReturn((object) [
        'name' => 'นางสาวทดสอบ ใช้ชื่อบัญชี',
    ]);

    $result = PayslipPdfService::generatePdf([
        'id' => '202509',
        'month' => 'กันยายน 2568',
        'total_income' => 0,
        'total_outcome' => 0,
        'net' => 0,
        'income_details' => [],
        'expense_details' => [],
        'person_info' => [],
    ], 'test-cid');

    $path = tempnam(sys_get_temp_dir(), 'payslip-account-name-');
    file_put_contents($path, $result['content']);

    try {
        $text = new Process(['pdftotext', $path, '-']);
        $text->run();

        expect($text->getExitCode())->toBe(0)
            ->and($text->getOutput())->toContain('ชื่อ-นามสกุล: นางสาวทดสอบ')
            ->and($text->getOutput())->toContain('ชื่อบัญชี');
    } finally {
        unlink($path);
    }
});

test('the downloaded payslip has reference-sized title, employee text, totals and footer', function () {
    Auth::shouldReceive('user')->andReturn((object) ['name' => 'สมชาย ทดสอบ']);

    $result = PayslipPdfService::generatePdf([
        'id' => '202609',
        'month' => 'กันยายน 2569',
        'total_income' => 31350,
        'total_outcome' => 6025.79,
        'net' => 25324.21,
        'income_details' => [['name' => 'เงินเดือน', 'amount' => 31350]],
        'expense_details' => [['name' => 'ภาษี', 'amount' => 6025.79]],
        'person_info' => ['position_number' => '241479'],
    ], 'test-cid');

    $path = tempnam(sys_get_temp_dir(), 'payslip-type-');
    file_put_contents($path, $result['content']);

    try {
        $text = new Process(['pdftotext', '-bbox', $path, '-']);
        $text->mustRun();
        $html = $text->getOutput();

        $wordHeight = static function (string $fragment) use ($html): float {
            $found = preg_match('/<word xMin="[^"]+" yMin="([^"]+)" xMax="[^"]+" yMax="([^"]+)"[^>]*>[^<]*'.preg_quote($fragment, '/').'/u', $html, $match);
            expect($found)->toBe(1);

            return (float) $match[2] - (float) $match[1];
        };

        expect($wordHeight('ใบรับรองการ'))->toBeGreaterThan(26)
            ->and($wordHeight('ชื่อ-นามสกุล:'))->toBeGreaterThan(24)
            ->and($wordHeight('รวมรายรับทั้งหมด'))->toBeGreaterThan(24)
            ->and($wordHeight('รับสุทธิ:'))->toBeGreaterThan(24)
            ->and($wordHeight('วันที่พิมพ'))->toBeGreaterThan(16);
    } finally {
        unlink($path);
    }
});

test('preview and printable payslip use the same reference typography scale', function () {
    $previewStyles = file_get_contents(resource_path('css/app.css'));
    $printView = file_get_contents(resource_path('views/payslip/print-view.blade.php'));

    foreach (['pdf-preview' => $previewStyles, 'payslip-document' => $printView] as $scope => $styles) {
        expect($styles)->toMatch('/\\.'.$scope.'\\s*\\{[^}]*font-size:\\s*20px;/s')
            ->toMatch('/\\.'.$scope.' h4\\s*\\{[^}]*font-size:\\s*26px;/s')
            ->toMatch('/\\.'.$scope.' h5\\s*\\{[^}]*font-size:\\s*22px;/s')
            ->toMatch('/\\.'.$scope.' \\.table\\s*\\{[^}]*font-size:\\s*17px;/s')
            ->toMatch('/\\.'.$scope.' \\.payslip-signature-block\\s*\\{[^}]*font-size:\\s*17px;/s');
    }
});
