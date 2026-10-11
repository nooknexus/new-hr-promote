<?php

use App\Livewire\Admin\LeaveManagement;
use App\Models\User;
use App\Services\LeaveSheetSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Schema::create('person', function ($table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
        $table->string('office_id', 2)->nullable();
        $table->string('office_name')->nullable();
    });
    Schema::create('leave_types', function ($table) {
        $table->id();
        $table->string('name', 100)->unique();
        $table->string('code', 20)->unique();
        $table->unsignedInteger('max_days')->nullable();
        $table->boolean('is_active')->default(true);
        $table->unsignedInteger('sort_order')->default(0);
    });
    Schema::create('leave_requests', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('cid', 13)->nullable();
        $table->string('external_name', 200)->nullable();
        $table->unsignedBigInteger('leave_type_id');
        $table->date('start_date');
        $table->date('end_date');
        $table->decimal('total_days', 5, 1);
        $table->text('reason')->nullable();
        $table->string('status', 20);
        $table->string('step1_status', 20);
        $table->string('step2_status', 20);
        $table->timestamps();
    });

    Schema::create('leave_sync_runs', function ($table) {
        $table->id();
        $table->unsignedBigInteger('admin_user_id')->nullable();
        $table->string('status', 20);
        foreach (['fetched', 'created', 'updated', 'skipped', 'failed', 'linked'] as $counter) {
            $table->unsignedInteger($counter.'_count')->default(0);
        }
        $table->json('errors')->nullable();
        $table->text('error_summary')->nullable();
        $table->timestamp('started_at');
        $table->timestamp('finished_at')->nullable();
        $table->timestamps();
    });
    // Exercise the full importer on SQLite; only MySQL advisory locking is substituted.
    DB::connection()->getPdo()->sqliteCreateFunction('GET_LOCK', fn () => 1, 2);
    DB::connection()->getPdo()->sqliteCreateFunction('RELEASE_LOCK', fn () => 1, 1);

    DB::table('person')->insert([
        'cid' => '1234567890123', 'pname' => 'นาง', 'fname' => 'สมศรี', 'lname' => 'ใจดี', 'office_name' => 'สสจ.',
    ]);
    $this->sickLeaveId = DB::table('leave_types')->insertGetId(['name' => 'ลาป่วย', 'code' => 'SICK']);
});

function leaveAdmin(bool $admin = true): User
{
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'ผู้ดูแล', 'staff_type' => $admin ? 'SUPERADMIN' : 'STAFF', 'role' => $admin ? 'SUPERADMIN' : null]);
    $user->exists = true;

    return $user;
}

function insertLeave(array $overrides = []): int
{
    return DB::table('leave_requests')->insertGetId([
        'cid' => '1234567890123',
        'external_name' => 'นาง สมศรี ใจดี',
        'leave_type_id' => DB::table('leave_types')->value('id'),
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-02',
        'total_days' => 2,
        'reason' => 'ซิงค์จาก Google Sheet',
        'status' => 'approved',
        'step1_status' => 'approved',
        'step2_status' => 'approved',
        ...$overrides,
    ]);
}

test('only administrators can manage leave records', function () {
    Livewire::actingAs(leaveAdmin(false))->test(LeaveManagement::class)->assertForbidden();
});

test('the register lists leave requests from every source', function () {
    insertLeave();

    Livewire::actingAs(leaveAdmin())
        ->test(LeaveManagement::class)
        ->assertSee('นาง สมศรี ใจดี')
        ->assertSee('ลาป่วย')
        ->assertSee('2 วัน');
});

test('an administrator can add a multi-day leave counted in working days', function () {
    Livewire::actingAs(leaveAdmin())
        ->test(LeaveManagement::class)
        ->set('personSearch', 'สมศรี ใจ')
        ->assertSee('นาง สมศรี ใจดี')
        ->call('selectPerson', '1234567890123')
        ->set('leave_type_id', $this->sickLeaveId)
        ->set('start_date', '2026-09-25') // Friday
        ->set('end_date', '2026-09-29')   // Tuesday
        ->assertSet('duration', 'full')
        ->set('reason', 'ไข้หวัด')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('leave_requests')->first())
        ->cid->toBe('1234567890123')
        ->external_name->toBe('นาง สมศรี ใจดี')
        ->total_days->toEqual(3)
        ->reason->toBe('ไข้หวัด')
        ->status->toBe('approved');
});

test('saving a leave delegates modal closure and feedback to the success toast event', function () {
    Http::fake([
        '*' => Http::response("BEGIN:VCALENDAR\nEND:VCALENDAR"),
    ]);

    Livewire::actingAs(leaveAdmin())
        ->test(LeaveManagement::class)
        ->call('selectPerson', '1234567890123')
        ->set('leave_type_id', $this->sickLeaveId)
        ->set('start_date', '2026-09-24')
        ->set('end_date', '2026-09-24')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('leave-saved', message: 'เพิ่มรายการลาแล้ว')
        ->assertNotDispatched('leave-form-closed');
});

test('an administrator leave excludes a Thai public holiday from its duration', function () {
    Http::fake([
        'https://calendar.google.com/calendar/ical/en.th%23holiday%40group.v.calendar.google.com/public/basic.ics' => Http::response(<<<'ICS'
BEGIN:VCALENDAR
BEGIN:VEVENT
DTSTART;VALUE=DATE:20260728
SUMMARY:King's Birthday
END:VEVENT
END:VCALENDAR
ICS),
    ]);

    Livewire::actingAs(leaveAdmin())
        ->test(LeaveManagement::class)
        ->call('selectPerson', '1234567890123')
        ->set('leave_type_id', $this->sickLeaveId)
        ->set('start_date', '2026-07-27')
        ->set('end_date', '2026-07-29')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) DB::table('leave_requests')->value('total_days'))->toBe(2.0);
});

test('a single-day leave can be recorded as a half day', function () {
    Livewire::actingAs(leaveAdmin())
        ->test(LeaveManagement::class)
        ->call('selectPerson', '1234567890123')
        ->set('leave_type_id', $this->sickLeaveId)
        ->set('start_date', '2026-09-24')
        ->set('end_date', '2026-09-24')
        ->assertSee('ระยะเวลาการลา')
        ->set('duration', 'half')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) DB::table('leave_requests')->value('total_days'))->toBe(0.5);
});

test('overlapping and weekend-only leave is rejected', function () {
    insertLeave();

    Livewire::actingAs(leaveAdmin())
        ->test(LeaveManagement::class)
        ->call('selectPerson', '1234567890123')
        ->set('leave_type_id', $this->sickLeaveId)
        ->set('start_date', '2026-09-02')
        ->set('end_date', '2026-09-03')
        ->call('save')
        ->assertHasErrors('start_date')
        ->set('start_date', '2026-09-26')
        ->set('end_date', '2026-09-27')
        ->call('save')
        ->assertHasErrors('end_date');

    expect(DB::table('leave_requests')->count())->toBe(1);
});

test('an administrator can edit a synced leave for someone missing from person', function () {
    $id = insertLeave(['cid' => '9999999999999', 'external_name' => 'คนนอกทะเบียน']);

    Livewire::actingAs(leaveAdmin())
        ->test(LeaveManagement::class)
        ->call('edit', $id)
        ->assertSet('personName', 'คนนอกทะเบียน')
        ->set('end_date', '2026-09-01')
        ->set('duration', 'half')
        ->set('reason', 'แก้ไขเป็นครึ่งวัน')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('leave_requests')->find($id))
        ->cid->toBe('9999999999999')
        ->end_date->toBe('2026-09-01')
        ->total_days->toEqual(0.5)
        ->reason->toBe('แก้ไขเป็นครึ่งวัน');
});

test('an administrator can cancel a leave and it can no longer be edited', function () {
    $id = insertLeave();

    Livewire::actingAs(leaveAdmin())
        ->test(LeaveManagement::class)
        ->call('prepareCancel', $id)
        ->call('cancelLeave')
        ->assertSee('ยกเลิก')
        ->call('edit', $id)
        ->assertForbidden();

    expect(DB::table('leave_requests')->where('id', $id)->value('status'))->toBe('cancelled');
});

test('the leave register links to the Google Sheet sync page', function () {
    Livewire::actingAs(leaveAdmin())
        ->test(LeaveManagement::class)
        ->assertSeeHtml('href="' . route('admin.leave-sheet-sync') . '"')
        ->assertSee('ดึงประวัติการลา');
});

// TDD_REQUIRED: yes. Missing catalog entries must prevent selecting/saving these types.
test('additional leave types can be selected and saved in the modal', function (string $name) {
    Http::fake(['*' => Http::response("BEGIN:VCALENDAR\nEND:VCALENDAR")]);

    $component = Livewire::actingAs(leaveAdmin())->test(LeaveManagement::class)
        ->call('openCreate')->assertSee($name);
    $typeId = DB::table('leave_types')->where('name', $name)->value('id');
    expect($typeId)->not->toBeNull();

    $component->call('selectPerson', '1234567890123')
        ->set('leave_type_id', $typeId)
        ->set('start_date', '2026-09-24')
        ->set('end_date', '2026-09-24')
        ->call('save')->assertHasNoErrors();

    expect(DB::table('leave_requests')->value('leave_type_id'))->toEqual($typeId);
})->with(['ลาคลอดบุตร', 'ลาไปช่วยเหลือภริยาที่คลอดบุตร', 'ลาอุปสมบท', 'ลาศึกษา']);

test('sheet imports reuse a leave type created for key in without duplicating it', function (string $name) {
    Livewire::actingAs(leaveAdmin())->test(LeaveManagement::class);
    $typeId = DB::table('leave_types')->where('name', $name)->value('id');
    expect($typeId)->not->toBeNull();

    $csv = implode("\n", [
        'เลขประจำตัวประชาชน,ชื่อ + นามสกุล,ประเภทการลา,วันที่(เริ่ม),วันที่(สิ้นสุด),จำนวนวัน,สถานะวันลา,หมายเหตุ',
        "1640100101537,ทดสอบ บุคลากร,{$name},24/9/26,24/9/26,1,อนุมัติ,",
    ]);
    Http::fake(['docs.google.com/*' => Http::sequence()
        ->push($csv)
        ->push(str_replace(',1,อนุมัติ,', ',0.5,อนุมัติ,', $csv))]);
    $service = new LeaveSheetSyncService;
    $result = $service->sync(1, 'https://docs.google.com/spreadsheets/d/test/edit');
    expect($result)->toMatchArray(['created_count' => 1, 'failed_count' => 0, 'skipped_count' => 0]);

    $result = $service->sync(1, 'https://docs.google.com/spreadsheets/d/test/edit');
    expect($result)->toMatchArray(['created_count' => 0, 'updated_count' => 1, 'failed_count' => 0]);
    expect(DB::table('leave_requests')->first())
        ->leave_type_id->toEqual($typeId)->total_days->toEqual(0.5)->status->toBe('approved');
    expect(DB::table('leave_types')->where('name', $name)->count())->toBe(1);
})->with(['ลาคลอดบุตร', 'ลาไปช่วยเหลือภริยาที่คลอดบุตร', 'ลาอุปสมบท', 'ลาศึกษา']);

test('opening the modal preserves a previously synced type identity', function (string $name) {
    $csv = implode("\n", [
        'เลขประจำตัวประชาชน,ชื่อ + นามสกุล,ประเภทการลา,วันที่(เริ่ม),วันที่(สิ้นสุด),จำนวนวัน,สถานะวันลา,หมายเหตุ',
        "1640100101537,ทดสอบ บุคลากร,{$name},24/9/26,24/9/26,1,อนุมัติ,",
    ]);
    Http::fake(['docs.google.com/*' => Http::response($csv)]);
    $result = (new LeaveSheetSyncService)->sync(1, 'https://docs.google.com/spreadsheets/d/test/edit');
    expect($result['created_count'])->toBe(1);
    $typeId = DB::table('leave_types')->where('name', $name)->value('id');

    Livewire::actingAs(leaveAdmin())->test(LeaveManagement::class)->call('openCreate')->assertSee($name);
    Livewire::test(LeaveManagement::class);

    expect(DB::table('leave_types')->where('name', $name)->count())->toBe(1);
    expect(DB::table('leave_types')->where('name', $name)->value('id'))->toEqual($typeId);
})->with(['ลาคลอดบุตร', 'ลาไปช่วยเหลือภริยาที่คลอดบุตร', 'ลาอุปสมบท', 'ลาศึกษา']);

function leaveOfficeUser(?string $role, ?string $office): User
{
    $user = new User;
    $user->forceFill(['id' => 3, 'name' => 'ผู้ทดสอบ', 'staff_type' => 'STAFF', 'role' => $role, 'office' => $office]);
    $user->exists = true;

    return $user;
}

/** @return array{int, int} Leave IDs for a person of office 01 and a person of office 02. */
function seedLeavesInTwoOffices(): array
{
    DB::table('person')->where('cid', '1234567890123')->update(['office_id' => '01']);
    DB::table('person')->insert(['cid' => '2222222222222', 'fname' => 'บุคลากรหน่วยสอง', 'lname' => 'ทดสอบ', 'office_id' => '02']);

    syncOfficesFromPerson();

    return [
        insertLeave(['external_name' => 'ผู้ลาหน่วยหนึ่ง']),
        insertLeave(['cid' => '2222222222222', 'external_name' => 'ผู้ลาหน่วยสอง']),
    ];
}

test('only ADMIN and LEAVE may open leave management', function () {
    foreach (['FINANCE', 'PROMOTE', null] as $role) {
        Livewire::actingAs(leaveOfficeUser($role, '00'))->test(LeaveManagement::class)->assertForbidden();
    }

    Livewire::actingAs(leaveOfficeUser('LEAVE', '00'))->test(LeaveManagement::class)->assertOk();
});

test('ADMIN and LEAVE in office 00 see leave of every office', function (string $role, ?string $office) {
    seedLeavesInTwoOffices();

    Livewire::actingAs(leaveOfficeUser($role, $office))
        ->test(LeaveManagement::class)
        ->assertSee('ผู้ลาหน่วยหนึ่ง')
        ->assertSee('ผู้ลาหน่วยสอง');
})->with([
    'ADMIN without an office' => ['SUPERADMIN', null],
    'ADMIN of another office' => ['SUPERADMIN', '01'],
    'LEAVE of office 00' => ['LEAVE', '00'],
]);

test('LEAVE of another office sees and searches only their own office', function () {
    seedLeavesInTwoOffices();

    Livewire::actingAs(leaveOfficeUser('LEAVE', '01'))
        ->test(LeaveManagement::class)
        ->assertSee('ผู้ลาหน่วยหนึ่ง')
        ->assertDontSee('ผู้ลาหน่วยสอง')
        ->set('personSearch', 'ทดสอบ')
        ->assertDontSee('บุคลากรหน่วยสอง');
});

test('LEAVE without an office sees no leave', function () {
    seedLeavesInTwoOffices();

    Livewire::actingAs(leaveOfficeUser('LEAVE', null))
        ->test(LeaveManagement::class)
        ->assertDontSee('ผู้ลาหน่วยหนึ่ง')
        ->assertDontSee('ผู้ลาหน่วยสอง');
});

test('LEAVE of another office cannot edit, cancel, or create leave for another office', function () {
    [, $otherOfficeLeave] = seedLeavesInTwoOffices();
    $user = leaveOfficeUser('LEAVE', '01');

    Livewire::actingAs($user)->test(LeaveManagement::class)->call('edit', $otherOfficeLeave)->assertNotFound();
    Livewire::actingAs($user)->test(LeaveManagement::class)->call('prepareCancel', $otherOfficeLeave)->assertNotFound();
    Livewire::actingAs($user)->test(LeaveManagement::class)->call('selectPerson', '2222222222222')->assertNotFound();

    Livewire::actingAs($user)
        ->test(LeaveManagement::class)
        ->set('cid', '2222222222222')
        ->set('leave_type_id', $this->sickLeaveId)
        ->set('start_date', '2026-10-05')
        ->set('end_date', '2026-10-05')
        ->call('save')
        ->assertHasErrors(['cid' => 'exists']);

    expect(DB::table('leave_requests')->where('id', $otherOfficeLeave)->value('status'))->toBe('approved')
        ->and(DB::table('leave_requests')->where('cid', '2222222222222')->count())->toBe(1);
});

test('LEAVE can add leave for personnel of their own office', function () {
    seedLeavesInTwoOffices();

    Livewire::actingAs(leaveOfficeUser('LEAVE', '01'))
        ->test(LeaveManagement::class)
        ->call('selectPerson', '1234567890123')
        ->set('leave_type_id', $this->sickLeaveId)
        ->set('start_date', '2026-10-05')
        ->set('end_date', '2026-10-05')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('leave_requests')->where('cid', '1234567890123')->count())->toBe(2);
});

test('the leave register can be filtered by office', function () {
    seedLeavesInTwoOffices();
    DB::table('person')->where('office_id', '01')->update(['office_name' => 'หน่วยบริการหนึ่ง']);
    DB::table('person')->where('office_id', '02')->update(['office_name' => 'หน่วยบริการสอง']);
    syncOfficesFromPerson();

    Livewire::actingAs(leaveOfficeUser('LEAVE', '00'))
        ->test(LeaveManagement::class)
        ->assertSee('ทุกต้นสังกัด')
        ->assertSee('01 — หน่วยบริการหนึ่ง')
        ->assertSee('02 — หน่วยบริการสอง')
        ->set('officeFilter', '02')
        ->assertSee('ผู้ลาหน่วยสอง')
        ->assertDontSee('ผู้ลาหน่วยหนึ่ง')
        ->set('officeFilter', '')
        ->assertSee('ผู้ลาหน่วยหนึ่ง')
        ->assertSee('ผู้ลาหน่วยสอง');
});

test('an office-scoped LEAVE user sees only their own office in the leave office filter', function () {
    seedLeavesInTwoOffices();

    Livewire::actingAs(leaveOfficeUser('LEAVE', '01'))
        ->test(LeaveManagement::class)
        ->assertSee('01 — สสจ.')
        ->assertDontSee('02 — ไม่ระบุชื่อต้นสังกัด')
        ->set('officeFilter', '02')
        ->assertDontSee('ผู้ลาหน่วยสอง');
});

test('the leave register can be filtered by Buddhist year and month including leaves spanning months', function () {
    insertLeave(['external_name' => 'ลากันยายน', 'start_date' => '2026-09-01', 'end_date' => '2026-09-02']);
    insertLeave(['external_name' => 'ลาคร่อมเดือน', 'start_date' => '2026-09-30', 'end_date' => '2026-10-01']);
    insertLeave(['external_name' => 'ลาปีก่อน', 'start_date' => '2025-09-10', 'end_date' => '2025-09-10']);

    Livewire::actingAs(leaveAdmin())
        ->test(LeaveManagement::class)
        ->assertSee('ทุกปี (พ.ศ.)')
        ->assertSeeHtml('<option value="2026">2569</option>')
        ->assertSeeHtml('<option value="2025">2568</option>')
        ->set('yearFilter', '2026')
        ->assertSee('ลากันยายน')
        ->assertSee('ลาคร่อมเดือน')
        ->assertDontSee('ลาปีก่อน')
        ->set('monthFilter', '10')
        ->assertSee('ลาคร่อมเดือน')
        ->assertDontSee('ลากันยายน')
        ->set('yearFilter', '')
        ->set('monthFilter', '9')
        ->assertSee('ลากันยายน')
        ->assertSee('ลาคร่อมเดือน')
        ->assertSee('ลาปีก่อน');
});

test('only ADMIN and office 00 LEAVE users may sync leave from Google Sheets', function (?string $role, ?string $office, bool $allowed) {
    $user = leaveOfficeUser($role, $office);

    expect(\App\Support\AdminAccess::allows($user, 'leave-sync'))->toBe($allowed);

    $response = $this->actingAs($user)->get(route('admin.leave-sheet-sync'));
    $allowed ? $response->assertOk() : $response->assertForbidden();

    if ($allowed) {
        Livewire::actingAs($user)->test(\App\Livewire\Admin\LeaveSheetSync::class)->assertOk();
    } else {
        Livewire::actingAs($user)->test(\App\Livewire\Admin\LeaveSheetSync::class)->assertForbidden();
    }
})->with([
    'ADMIN without an office' => ['SUPERADMIN', null, true],
    'ADMIN of another office' => ['SUPERADMIN', '01', true],
    'LEAVE of office 00' => ['LEAVE', '00', true],
    'LEAVE of another office' => ['LEAVE', '01', false],
    'LEAVE without an office' => ['LEAVE', null, false],
    'FINANCE of office 00' => ['FINANCE', '00', false],
]);

test('the leave register shows the sync button only to users who may sync', function () {
    Livewire::actingAs(leaveOfficeUser('LEAVE', '00'))
        ->test(LeaveManagement::class)
        ->assertSee('ดึงประวัติการลา')
        ->assertSeeHtml(route('admin.leave-sheet-sync'));

    Livewire::actingAs(leaveOfficeUser('LEAVE', '01'))
        ->test(LeaveManagement::class)
        ->assertDontSee('ดึงประวัติการลา')
        ->assertDontSeeHtml(route('admin.leave-sheet-sync'));
});

test('an office-scoped LEAVE user cannot run a sync from an already open component', function () {
    $component = Livewire::actingAs(leaveOfficeUser('LEAVE', '00'))
        ->test(\App\Livewire\Admin\LeaveSheetSync::class)
        ->set('sheetUrl', 'https://docs.google.com/spreadsheets/d/test/edit');
    \Illuminate\Support\Facades\Auth::setUser(leaveOfficeUser('LEAVE', '01'));

    $component->call('syncFromGoogleSheet')->assertForbidden();

    expect(DB::table('leave_sync_runs')->count())->toBe(0);
});

test('the add-leave person search lists only personnel the user may manage', function (?string $role, ?string $office, bool $seesOfficeOne, bool $seesOfficeTwo) {
    DB::table('person')->insert([
        ['cid' => '1111111111111', 'fname' => 'คนหน่วยหนึ่ง', 'lname' => 'ค้นหาร่วม', 'office_id' => '01', 'office_name' => 'หน่วยหนึ่ง'],
        ['cid' => '2222222222222', 'fname' => 'คนหน่วยสอง', 'lname' => 'ค้นหาร่วม', 'office_id' => '02', 'office_name' => 'หน่วยสอง'],
    ]);

    $component = Livewire::actingAs(leaveOfficeUser($role, $office))
        ->test(LeaveManagement::class)
        ->set('personSearch', 'ค้นหาร่วม');

    $seesOfficeOne ? $component->assertSee('คนหน่วยหนึ่ง') : $component->assertDontSee('คนหน่วยหนึ่ง');
    $seesOfficeTwo ? $component->assertSee('คนหน่วยสอง') : $component->assertDontSee('คนหน่วยสอง');
})->with([
    'ADMIN of another office' => ['SUPERADMIN', '01', true, true],
    'LEAVE of office 00' => ['LEAVE', '00', true, true],
    'LEAVE of office 01' => ['LEAVE', '01', true, false],
    'LEAVE without an office' => ['LEAVE', null, false, false],
]);

test('the leave register joins person only when an office condition needs it', function (?string $role, ?string $office, string $officeFilter, bool $joinsPerson) {
    seedLeavesInTwoOffices();
    $component = Livewire::actingAs(leaveOfficeUser($role, $office))->test(LeaveManagement::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $component->set('officeFilter', $officeFilter)->call('$refresh');
    $leaveQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, '"leave_requests" as "requests"'));

    expect($leaveQueries)->not->toBeEmpty()
        ->and($leaveQueries->contains(fn ($sql) => str_contains($sql, '"person" as "owner"')))->toBe($joinsPerson);
})->with([
    'ADMIN without a filter' => ['SUPERADMIN', null, '', false],
    'office 00 without a filter' => ['LEAVE', '00', '', false],
    'office 00 filtering by office' => ['LEAVE', '00', '02', true],
    'office-scoped user' => ['LEAVE', '01', '', true],
]);
