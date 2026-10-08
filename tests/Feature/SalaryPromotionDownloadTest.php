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
