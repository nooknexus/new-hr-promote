<?php

use App\Livewire\Admin\SalaryPromotionManagement;
use App\Livewire\SalaryPromotion;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
        $table->string('office_id', 2)->nullable();
        $table->string('office_name')->nullable();
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
        $table->unsignedBigInteger('deleted_by')->nullable();
    });
    Schema::create('data_promote_audits', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('promotion_id');
        $table->unsignedBigInteger('actor_id');
        $table->string('action', 20);
        $table->json('before_data')->nullable();
        $table->json('after_data')->nullable();
        $table->timestamp('created_at');
    });
    DB::table('person')->insert([
        'cid' => '1234567890123', 'pname' => 'นาง', 'fname' => 'สมศรี',
        'lname' => 'ใจดี', 'office_id' => '01', 'office_name' => 'สสจ.',
    ]);
});

function promotionUser(bool $admin = true): User
{
    $user = new User;
    $user->forceFill([
        'id' => $admin ? 9 : 10,
        'name' => 'ผู้ทดสอบ',
        'pid' => '1234567890123',
        'staff_type' => $admin ? 'ADMIN' : 'STAFF',
        'role' => $admin ? 'ADMIN' : null,
    ]);
    $user->exists = true;

    return $user;
}

function insertPromotion(array $overrides = []): int
{
    return DB::table('data_promote')->insertGetId([
        'CID' => '1234567890123',
        'evaluation_date' => '2025-10-01',
        'promote' => 'Y',
        'before_salary' => '30000.00',
        'new_salary' => '31500.00',
        'sum' => '1500.00',
        'percent' => '5.00',
        'base' => '30000.00',
        'extra' => '0.00',
        ...$overrides,
    ]);
}

test('non administrators cannot open or invoke salary promotion management', function () {
    $this->actingAs(promotionUser(false))->get('/admin/salary-promotions')->assertForbidden();
    Livewire::actingAs(promotionUser(false))->test(SalaryPromotionManagement::class)->assertForbidden();
});

test('administrator can open the management route and its navigation entry', function () {
    $this->actingAs(promotionUser())
        ->get('/admin/salary-promotions')
        ->assertOk()
        ->assertSee('จัดการข้อมูลการเลื่อนขั้นเงินเดือน')
        ->assertSee('จัดการเลื่อนขั้นเงินเดือน');
});

test('add promotion button opens the application modal immediately on click', function () {
    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->assertSeeHtml('wire:click="openCreate" onclick="window.portalModal.show(\'promotionFormModal\')"');
});

test('edit and archive buttons open their respective modals immediately on click', function () {
    $id = insertPromotion();

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->assertSeeHtml("wire:click=\"edit({$id})\" onclick=\"window.portalModal.show('promotionFormModal')\"")
        ->assertSeeHtml("wire:click=\"prepareArchive({$id})\" onclick=\"window.portalModal.show('promotionConfirmModal')\"");
});

test('restore button opens confirmation modal immediately on click', function () {
    $id = insertPromotion(['deleted_at' => now()]);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->set('statusFilter', 'archived')
        ->assertSeeHtml("wire:click=\"prepareRestore({$id})\" onclick=\"window.portalModal.show('promotionConfirmModal')\"");
});

test('administrator can create a promotion for a registered person and it is audited', function () {
    insertPromotion(['evaluation_date' => '2025-10-01', 'new_salary' => '30000.00', 'base' => '30000.00']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('selectPerson', '1234567890123')
        ->set('evaluation_date', '2026-04-01')
        ->set('promote', 'Y')
        ->set('percent', '4.00')
        ->set('extra', '0.00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('promotion-saved');

    expect(DB::table('data_promote')->where('evaluation_date', '2026-04-01')->first())
        ->CID->toBe('1234567890123')
        ->sum->toBe('1200.00')
        ->office->toBe('สสจ.');
    expect(DB::table('data_promote_audits')->value('action'))->toBe('created');
});

test('new promotion pulls prior new salary and calculation base from the latest active earlier round', function () {
    insertPromotion(['evaluation_date' => '2025-04-01', 'new_salary' => '31500.00', 'base' => '30000.00']);
    insertPromotion(['evaluation_date' => '2025-10-01', 'new_salary' => '32500.00', 'base' => '31000.00']);
    insertPromotion(['evaluation_date' => '2026-04-01', 'new_salary' => '99999.00', 'base' => '99999.00', 'deleted_at' => now()]);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->set('evaluation_date', '2026-10-01')
        ->call('selectPerson', '1234567890123')
        ->assertSet('before_salary', '32500.00')
        ->assertSet('base', '31000.00')
        ->set('evaluation_date', '2025-09-01')
        ->assertSet('before_salary', '31500.00')
        ->assertSet('base', '30000.00');
});

test('a first promotion cannot be saved without a prior salary and calculation base', function () {
    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('selectPerson', '1234567890123')
        ->set('evaluation_date', '2026-10-01')
        ->set('percent', '4.00')
        ->call('save')
        ->assertHasErrors('before_salary');

    expect(DB::table('data_promote')->count())->toBe(0);
});

test('an administrator can change the inherited base and the calculated salary uses the changed amount', function () {
    insertPromotion(['evaluation_date' => '2025-10-01', 'new_salary' => '31500.00', 'base' => '30000.00']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->set('evaluation_date', '2026-04-01')
        ->call('selectPerson', '1234567890123')
        ->set('percent', '3.25')
        ->assertSet('new_salary', '32475.00')
        ->set('base', '32000.00')
        ->assertSet('new_salary', '32540.00')
        ->set('before_salary', '100000.00')
        ->set('new_salary', '199999.00')
        ->call('save')
        ->assertHasNoErrors();

    $saved = DB::table('data_promote')->where('evaluation_date', '2026-04-01')->first();
    expect($saved)->not->toBeNull()
        ->before_salary->toBe('31500.00')
        ->base->toBe('32000.00')
        ->percent->toBe('3.25')
        ->sum->toBe('1040.00')
        ->new_salary->toBe('32540.00');
});

test('the base is editable while salary amounts are read only and new salary is last', function () {
    $html = Livewire::actingAs(promotionUser())->test(SalaryPromotionManagement::class)->html();

    expect(preg_match('/id="promotion-before_salary"[^>]*readonly/', $html))->toBe(1)
        ->and(preg_match('/id="promotion-base"[^>]*readonly/', $html))->toBe(0)
        ->and(preg_match('/id="promotion-base"[^>]*wire:model.live="base"/', $html))->toBe(1)
        ->and(preg_match('/id="promotion-new_salary"[^>]*readonly/', $html))->toBe(1)
        ->and(strpos($html, 'id="promotion-before_salary"'))->toBeLessThan(strpos($html, 'id="promotion-base"'))
        ->and(strpos($html, 'id="promotion-base"'))->toBeLessThan(strpos($html, 'id="promotion-percent"'))
        ->and(strpos($html, 'id="promotion-percent"'))->toBeLessThan(strpos($html, 'id="promotion-extra"'))
        ->and(strpos($html, 'id="promotion-extra"'))->toBeLessThan(strpos($html, 'id="promotion-new_salary"'));
});

test('changing the base on an existing promotion recalculates its new salary', function () {
    insertPromotion(['evaluation_date' => '2025-04-01', 'new_salary' => '31500.00', 'base' => '30000.00']);
    $current = insertPromotion(['evaluation_date' => '2025-10-01', 'before_salary' => '31500.00', 'new_salary' => '32700.00', 'base' => '30000.00', 'sum' => '1200.00', 'percent' => '4.00']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('edit', $current)
        ->set('base', '35000.00')
        ->assertSet('new_salary', '32900.00')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('data_promote')->find($current))
        ->before_salary->toBe('31500.00')
        ->base->toBe('35000.00')
        ->new_salary->toBe('32900.00')
        ->sum->toBe('1400.00');
});

test('clearing the calculation base cannot erase an existing calculated salary', function () {
    $current = insertPromotion(['evaluation_date' => '2025-10-01', 'new_salary' => '32700.00', 'base' => '30000.00']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('edit', $current)
        ->set('base', '')
        ->call('save')
        ->assertHasErrors('base');

    expect(DB::table('data_promote')->find($current))
        ->base->toBe('30000.00')
        ->new_salary->toBe('32700.00');
});

test('changing a legacy base requires a numeric percentage before recalculation', function () {
    $current = insertPromotion(['percent' => 'ตามเกณฑ์', 'new_salary' => '32700.00', 'base' => '30000.00']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('edit', $current)
        ->set('base', '35000.00')
        ->call('save')
        ->assertHasErrors('percent');

    expect(DB::table('data_promote')->find($current))
        ->base->toBe('30000.00')
        ->new_salary->toBe('32700.00');
});

test('changing percentage on an existing promotion recalculates without changing earlier records', function () {
    $previous = insertPromotion(['evaluation_date' => '2025-04-01', 'new_salary' => '31500.00', 'base' => '30000.00']);
    $current = insertPromotion(['evaluation_date' => '2025-10-01', 'before_salary' => '31500.00', 'new_salary' => '32700.00', 'base' => '30000.00', 'sum' => '1200.00', 'percent' => '4.00']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('edit', $current)
        ->set('percent', '5.00')
        ->assertSet('new_salary', '33000.00')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('data_promote')->find($current))
        ->before_salary->toBe('31500.00')
        ->base->toBe('30000.00')
        ->new_salary->toBe('33000.00')
        ->sum->toBe('1500.00');
    expect(DB::table('data_promote')->find($previous)->new_salary)->toBe('31500.00');
});

test('duplicate person and evaluation date is rejected without changing existing data', function () {
    insertPromotion(['evaluation_date' => '2025-04-01']);
    insertPromotion();

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('selectPerson', '1234567890123')
        ->set('evaluation_date', '2025-10-01')
        ->set('percent', '4.00')
        ->call('save')
        ->assertHasErrors('evaluation_date');

    expect(DB::table('data_promote')->count())->toBe(2);
});

test('a negative percentage is rejected instead of producing a salary decrease', function () {
    insertPromotion(['evaluation_date' => '2025-10-01']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('selectPerson', '1234567890123')
        ->set('evaluation_date', '2026-04-01')
        ->set('percent', '-4')
        ->call('save')
        ->assertHasErrors('percent');

    expect(DB::table('data_promote')->count())->toBe(1);
});

test('a previous calculation base outside the supported money range is rejected', function () {
    insertPromotion(['evaluation_date' => '2025-10-01', 'base' => '10000000.00']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('selectPerson', '1234567890123')
        ->set('evaluation_date', '2026-04-01')
        ->set('percent', '4.00')
        ->call('save')
        ->assertHasErrors('base');

    expect(DB::table('data_promote')->count())->toBe(1);
});

test('the form previews the calculated increase before saving', function () {
    insertPromotion(['evaluation_date' => '2025-10-01', 'new_salary' => '30000.00', 'base' => '30000.00']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('selectPerson', '1234567890123')
        ->set('evaluation_date', '2026-04-01')
        ->set('percent', '4.00')
        ->assertSee('ยอดเพิ่มขึ้น 1,200.00 บาท');
});

test('legacy promotion without matching personnel stays visible and editable without recalculating its increase', function () {
    $id = insertPromotion([
        'CID' => '9999999999999', 'sum' => '1234.00', 'office' => 'หน่วยงานเก่า',
    ]);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->assertSee('9999999999999')
        ->assertSee('ไม่พบบุคลากร')
        ->call('edit', $id)
        ->set('ps', 'ตรวจสอบแล้ว')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('data_promote')->find($id))
        ->sum->toBe('1234.00')
        ->ps->toBe('ตรวจสอบแล้ว');
    expect(DB::table('data_promote_audits')->value('action'))->toBe('updated');
});

test('archive and restore retain the row and write audit records', function () {
    $id = insertPromotion();

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('prepareArchive', $id)
        ->call('archive')
        ->assertDispatched('promotion-saved')
        ->set('statusFilter', 'archived')
        ->assertSee('1234567890123')
        ->call('prepareRestore', $id)
        ->call('restore')
        ->assertDispatched('promotion-saved');

    expect(DB::table('data_promote')->find($id)->deleted_at)->toBeNull();
    expect(DB::table('data_promote_audits')->pluck('action')->all())->toBe(['archived', 'restored']);
});

test('admin year and office filters use fiscal year and registered office identity', function () {
    insertPromotion(['evaluation_date' => '2025-09-30', 'new_salary' => '11111.00']);
    insertPromotion(['evaluation_date' => '2025-10-01', 'new_salary' => '22222.00']);
    insertPromotion(['CID' => '9999999999999', 'evaluation_date' => '2025-11-01', 'new_salary' => '33333.00']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->set('yearFilter', '2569')
        ->assertSee('22,222.00')
        ->assertSee('33,333.00')
        ->assertDontSee('11,111.00')
        ->set('officeFilter', '01')
        ->assertSee('22,222.00')
        ->assertDontSee('33,333.00');
});

test('legacy nonnumeric percent can be left untouched while updating a note', function () {
    $id = insertPromotion(['percent' => 'ตามเกณฑ์']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('edit', $id)
        ->set('ps', 'แก้ไขหมายเหตุ')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('data_promote')->find($id))
        ->percent->toBe('ตามเกณฑ์')
        ->ps->toBe('แก้ไขหมายเหตุ');
});

test('legacy promotion with missing salary fields can be annotated without fabricating amounts', function () {
    $id = insertPromotion([
        'before_salary' => null, 'new_salary' => null, 'sum' => null,
    ]);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('edit', $id)
        ->set('ps', 'ข้อมูลนำเข้ายังไม่ครบ')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('data_promote')->find($id))
        ->before_salary->toBeNull()
        ->new_salary->toBeNull()
        ->sum->toBeNull()
        ->ps->toBe('ข้อมูลนำเข้ายังไม่ครบ');
});

test('legacy undecided promotion can be annotated without changing its result', function () {
    $id = insertPromotion(['promote' => null]);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('edit', $id)
        ->set('ps', 'รอหลักฐานเพิ่มเติม')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('data_promote')->find($id))
        ->promote->toBeNull()
        ->ps->toBe('รอหลักฐานเพิ่มเติม');
});

test('legacy salary decrease can be annotated without changing the stored figures', function () {
    $id = insertPromotion(['before_salary' => '30000.00', 'new_salary' => '29000.00', 'sum' => '-1000.00']);

    Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('edit', $id)
        ->set('ps', 'ข้อมูลเดิม')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('data_promote')->find($id))
        ->sum->toBe('-1000.00')
        ->new_salary->toBe('29000.00');
});

test('an edit opened before another administrator changes the row cannot overwrite that change', function () {
    $id = insertPromotion();
    $form = Livewire::actingAs(promotionUser())
        ->test(SalaryPromotionManagement::class)
        ->call('edit', $id);

    DB::table('data_promote')->where('id', $id)->update(['ps' => 'แก้ไขจากอีกหน้าต่าง']);
    $form->set('ps', 'ข้อมูลเก่าของฉัน')
        ->call('save')
        ->assertHasErrors('evaluation_date');

    expect(DB::table('data_promote')->find($id)->ps)->toBe('แก้ไขจากอีกหน้าต่าง');
    expect(DB::table('data_promote_audits')->count())->toBe(0);
});

test('user promotion history uses fiscal years and omits archived rows', function () {
    $april = insertPromotion(['evaluation_date' => '2025-04-01']);
    $october = insertPromotion(['evaluation_date' => '2025-10-01']);

    Livewire::actingAs(promotionUser(false))
        ->test(SalaryPromotion::class)
        ->assertSet('years', [2569, 2568])
        ->assertSet('selectedYear', 2569)
        ->assertCount('promotions', 1)
        ->call('selectYear', 2568)
        ->assertCount('promotions', 1);

    DB::table('data_promote')->where('id', $october)->update(['deleted_at' => now()]);
    Livewire::actingAs(promotionUser(false))
        ->test(SalaryPromotion::class)
        ->assertSet('years', [2568])
        ->assertSet('promotions.0.id', $april);
});

function promotionOfficeUser(?string $role, ?string $office): User
{
    $user = new User;
    $user->forceFill(['id' => 11, 'name' => 'ผู้ทดสอบ', 'staff_type' => 'STAFF', 'role' => $role, 'office' => $office]);
    $user->exists = true;

    return $user;
}

/** @return array{int, int} Promotion IDs for a person of office 01 and a person of office 02. */
function seedPromotionsInTwoOffices(): array
{
    DB::table('person')->insert(['cid' => '2222222222222', 'fname' => 'บุคลากรหน่วยสอง', 'lname' => 'ทดสอบ', 'office_id' => '02', 'office_name' => 'รพ.สอง']);

    return [insertPromotion(), insertPromotion(['CID' => '2222222222222'])];
}

test('only ADMIN and PROMOTE may open salary promotion management', function () {
    foreach (['LEAVE', 'FINANCE', null] as $role) {
        Livewire::actingAs(promotionOfficeUser($role, '00'))->test(SalaryPromotionManagement::class)->assertForbidden();
    }

    Livewire::actingAs(promotionOfficeUser('PROMOTE', '00'))->test(SalaryPromotionManagement::class)->assertOk();
});

test('ADMIN and PROMOTE in office 00 see promotions of every office', function (string $role, ?string $office) {
    seedPromotionsInTwoOffices();

    Livewire::actingAs(promotionOfficeUser($role, $office))
        ->test(SalaryPromotionManagement::class)
        ->assertSee('สมศรี')
        ->assertSee('บุคลากรหน่วยสอง');
})->with([
    'ADMIN without an office' => ['ADMIN', null],
    'ADMIN of another office' => ['ADMIN', '01'],
    'PROMOTE of office 00' => ['PROMOTE', '00'],
]);

test('PROMOTE of another office sees only promotions and personnel of their own office', function () {
    seedPromotionsInTwoOffices();

    Livewire::actingAs(promotionOfficeUser('PROMOTE', '01'))
        ->test(SalaryPromotionManagement::class)
        ->assertSee('สมศรี')
        ->assertDontSee('บุคลากรหน่วยสอง')
        ->assertDontSee('รพ.สอง')
        ->set('personSearch', 'ทดสอบ')
        ->assertDontSee('บุคลากรหน่วยสอง');
});

test('PROMOTE without an office sees no promotions', function () {
    seedPromotionsInTwoOffices();

    Livewire::actingAs(promotionOfficeUser('PROMOTE', null))
        ->test(SalaryPromotionManagement::class)
        ->assertDontSee('สมศรี')
        ->assertDontSee('บุคลากรหน่วยสอง');
});

test('PROMOTE of another office cannot edit, archive, or create promotions for another office', function () {
    [, $otherOffice] = seedPromotionsInTwoOffices();
    $user = promotionOfficeUser('PROMOTE', '01');

    Livewire::actingAs($user)->test(SalaryPromotionManagement::class)->call('edit', $otherOffice)->assertNotFound();
    Livewire::actingAs($user)->test(SalaryPromotionManagement::class)->call('prepareArchive', $otherOffice)->assertNotFound();
    Livewire::actingAs($user)->test(SalaryPromotionManagement::class)->call('selectPerson', '2222222222222')->assertNotFound();

    Livewire::actingAs($user)
        ->test(SalaryPromotionManagement::class)
        ->set('cid', '2222222222222')
        ->set('evaluation_date', '2026-04-01')
        ->set('before_salary', '30000')
        ->set('base', '30000')
        ->set('percent', '3')
        ->set('new_salary', '30900')
        ->call('save')
        ->assertHasErrors(['cid' => 'exists']);

    expect(DB::table('data_promote')->where('CID', '2222222222222')->count())->toBe(1)
        ->and(DB::table('data_promote')->where('id', $otherOffice)->value('deleted_at'))->toBeNull();
});

test('salary promotion management starts with the page header above the importer', function () {
    $html = $this->actingAs(promotionUser())->get('/admin/salary-promotions')->assertOk()->getContent();

    expect(strpos($html, 'จัดการข้อมูลการเลื่อนขั้นเงินเดือน'))->toBeLessThan(strpos($html, 'นำเข้า Excel'));
});

test('the promotion register office filter uses ต้นสังกัด labels with code and name', function () {
    seedPromotionsInTwoOffices();

    Livewire::actingAs(promotionOfficeUser('PROMOTE', '00'))
        ->test(SalaryPromotionManagement::class)
        ->assertSee('ทุกต้นสังกัด')
        ->assertDontSee('ทุกหน่วยงาน')
        ->assertSee('01 — สสจ.')
        ->assertSee('02 — รพ.สอง');

    Livewire::actingAs(promotionOfficeUser('PROMOTE', '01'))
        ->test(SalaryPromotionManagement::class)
        ->assertSee('01 — สสจ.')
        ->assertDontSee('02 — รพ.สอง');
});

test('user promotion history labels each round by Thai month and Buddhist year only', function () {
    insertPromotion(['evaluation_date' => '2025-04-01']);
    insertPromotion(['evaluation_date' => '2025-04-02']);
    insertPromotion(['evaluation_date' => '2024-10-01']);

    Livewire::actingAs(promotionUser(false))
        ->test(SalaryPromotion::class)
        ->call('selectYear', 2568)
        ->assertSet('promotions.0.round', 'เมษายน 2568')
        ->assertSet('promotions.0.round_number', 2)
        ->assertSet('promotions.1.round', 'เมษายน 2568')
        ->assertSet('promotions.1.round_number', 1)
        ->assertSet('promotions.2.round', 'ตุลาคม 2567')
        ->assertSee('เมษายน 2568')
        ->assertDontSee('รอบ1 เมษายน')
        ->assertDontSee('รอบ2 ตุลาคม');
});

test('user promotion history shows months outside April and October as a special round without a count', function () {
    insertPromotion(['evaluation_date' => '2024-05-01']);
    insertPromotion(['evaluation_date' => '2024-04-01']);

    Livewire::actingAs(promotionUser(false))
        ->test(SalaryPromotion::class)
        ->call('selectYear', 2567)
        ->assertSet('promotions.0.round', 'รอบพิเศษ')
        ->assertSet('promotions.1.round', 'เมษายน 2567')
        ->assertSee('รอบพิเศษ')
        ->assertSee('ครั้งที่ 1')
        ->assertDontSee('ครั้งที่ 2')
        ->assertDontSeeHtml('<span class="badge badge-warning">พิเศษ</span>')
        ->assertDontSee('พฤษภาคม 2567');
});
