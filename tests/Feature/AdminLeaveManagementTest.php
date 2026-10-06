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
    $user->forceFill(['id' => 1, 'name' => 'ผู้ดูแล', 'staff_type' => $admin ? 'ADMIN' : 'STAFF', 'role' => $admin ? 'ADMIN' : null]);
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
