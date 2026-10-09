<?php

use App\Livewire\SalaryPromotion;
use App\Models\User;
use App\Services\SalaryPromotionPdfService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('fname')->nullable();
        $table->string('position')->nullable();
        $table->string('position_level')->nullable();
    });
    Schema::create('data_promote', function (Blueprint $table) {
        $table->increments('id');
        $table->string('CID');
        $table->date('evaluation_date');
        $table->string('promote', 2)->nullable();
        $table->string('ps')->nullable();
        $table->string('office')->nullable();
        $table->string('department')->nullable();
        $table->string('before_salary', 10)->nullable();
        $table->string('base', 100)->nullable();
        $table->string('percent', 10)->nullable();
        $table->string('sum', 10)->nullable();
        $table->string('extra', 10)->nullable();
        $table->string('new_salary', 10)->nullable();
        $table->dateTime('update_date')->nullable();
        $table->dateTime('deleted_at')->nullable();
    });
    DB::table('person')->insert([
        'cid' => '1234567890123', 'fname' => 'สมศรี', 'position' => 'พยาบาลวิชาชีพ', 'position_level' => 'ชำนาญการ',
    ]);
});

function promotionOwner(): User
{
    $user = new User;
    $user->forceFill(['id' => 31, 'name' => 'นางสมศรี ใจดี', 'pid' => '1234567890123']);
    $user->exists = true;

    return $user;
}

function insertOwnPromotion(array $overrides = []): int
{
    return DB::table('data_promote')->insertGetId([
        'CID' => '1234567890123',
        'evaluation_date' => '2025-04-01',
        'promote' => 'Y',
        'office' => 'โรงพยาบาลทดสอบ',
        'before_salary' => '30000.00',
        'base' => '30000.00',
        'percent' => '5.00',
        'sum' => '1500.00',
        'extra' => '0.00',
        'new_salary' => '31500.00',
        'update_date' => '2025-04-02 09:30:00',
        ...$overrides,
    ]);
}

test('promotion year buttons expose the selected fiscal year after switching years', function () {
    insertOwnPromotion();
    insertOwnPromotion(['evaluation_date' => '2024-04-01']);
    $component = Livewire::actingAs(promotionOwner())->test(SalaryPromotion::class);
    $selectedYear = function () use ($component): string {
        $document = new DOMDocument;
        @$document->loadHTML(mb_convert_encoding($component->html(), 'HTML-ENTITIES', 'UTF-8'));
        $buttons = (new DOMXPath($document))->query('//button[@aria-pressed="true"]');
        expect($buttons->length)->toBe(1);
        return trim($buttons->item(0)->textContent);
    };
    expect($selectedYear())->toBe('ปี2568');
    $component->call('selectYear', 2567)->assertSet('selectedYear', 2567);
    expect($selectedYear())->toBe('ปี2567');
});

test('a user downloads their own salary promotion notice as a PDF', function () {
    $id = insertOwnPromotion();

    $response = $this->actingAs(promotionOwner())
        ->get(route('profile.salary-promotion.download', ['promotion' => $id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))->toContain("salary_promotion_2025-04-01_{$id}.pdf")
        ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');

    $path = tempnam(sys_get_temp_dir(), 'promotion-pdf-');
    file_put_contents($path, $response->getContent());
    try {
        $text = new Process(['pdftotext', '-layout', $path, '-']);
        $text->mustRun();
        expect($text->getOutput())->toContain('31,500.00')->toContain('30,000.00')->toContain('1,500.00');
    } finally {
        unlink($path);
    }
});

test('a user cannot download another person\'s or an archived salary promotion', function () {
    $other = insertOwnPromotion(['CID' => '9999999999999']);
    $archived = insertOwnPromotion(['evaluation_date' => '2024-10-01', 'deleted_at' => now()]);

    $this->actingAs(promotionOwner())
        ->get(route('profile.salary-promotion.download', ['promotion' => $other]))
        ->assertNotFound();
    $this->get(route('profile.salary-promotion.download', ['promotion' => $archived]))
        ->assertNotFound();
});

test('the salary promotion notice uses the person position and level, not fixed text', function () {
    $notice = SalaryPromotionPdfService::fromPromotion(
        DB::table('data_promote')->find(insertOwnPromotion(['promote' => 'N', 'ps' => 'ลาเกินกำหนด', 'update_date' => null])),
        'นางสมศรี ใจดี',
        DB::table('person')->first(),
    );

    expect($notice)->toMatchArray([
        'round' => 'เมษายน 2568',
        'position' => 'พยาบาลวิชาชีพ',
        'level' => 'ชำนาญการ',
        'result' => 'ไม่ได้เลื่อนเงินเดือน ลาเกินกำหนด',
        'dataAsOf' => '-',
    ]);
});

test('the preview shows a download link and the person position instead of placeholder text', function () {
    $id = insertOwnPromotion();
    Carbon::setTestNow('2025-06-01');

    try {
        Livewire::actingAs(promotionOwner())
            ->test(SalaryPromotion::class)
            ->call('downloadPromotion', $id)
            ->assertSee('พยาบาลวิชาชีพ')
            ->assertSee('ชำนาญการ')
            ->assertDontSee('นักวิชาการคอมพิวเตอร์')
            ->assertDontSee('DEBUG')
            ->assertSeeHtml('href="'.route('profile.salary-promotion.download', ['promotion' => $id]).'"');
    } finally {
        Carbon::setTestNow();
    }
});

test('the salary promotion page starts with the shared page header', function () {
    Livewire::actingAs(promotionOwner())
        ->test(SalaryPromotion::class)
        ->assertSeeHtml('<h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">การเลื่อนขั้นเงินเดือน</h1>');
});

test('the promotion history table lists only the round, count, percent and actions', function () {
    insertOwnPromotion();
    Carbon::setTestNow('2025-06-01');

    try {
        Livewire::actingAs(promotionOwner())
            ->test(SalaryPromotion::class)
            ->assertSeeHtmlInOrder(['<th>รอบการเลื่อนขั้น</th>', '<th>ครั้งที่</th>', '<th>ร้อยละ</th>', '<th>การดำเนินการ</th>'])
            ->assertDontSeeHtml('<th>เงินเดือนเดิม</th>')
            ->assertDontSeeHtml('<th>เงินเดือนใหม่</th>')
            ->assertDontSeeHtml('<th>เพิ่มขึ้น</th>')
            ->assertSee('5.00%')
            ->assertDontSee('31,500.00');
    } finally {
        Carbon::setTestNow();
    }
});

test('a user without any salary promotion is told to contact personnel staff', function () {
    Livewire::actingAs(promotionOwner())
        ->test(SalaryPromotion::class)
        ->assertSet('years', [])
        ->assertSet('selectedYear', null)
        ->assertSee('ยังไม่มีข้อมูลการเลื่อนขั้นเงินเดือนของท่านในระบบ')
        ->assertSee('กรุณาติดต่อเจ้าหน้าที่ฝ่ายบุคคลของหน่วยงาน')
        ->assertDontSeeHtml('<th>รอบการเลื่อนขั้น</th>');
});
