<?php

use App\Livewire\LeaveHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Schema::create('person', function ($table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
        $table->unsignedTinyInteger('type_id')->nullable();
        $table->string('type_name')->nullable();
        $table->string('office_id', 2)->nullable();
        $table->string('office_name')->nullable();
        $table->date('employment_started_at')->nullable();
    });
    Schema::create('leave_types', function ($table) {
        $table->id();
        $table->string('name', 100);
        $table->string('code', 20);
        $table->boolean('is_active')->default(true);
        $table->unsignedInteger('sort_order')->default(0);
    });
    Schema::create('leave_requests', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('cid', 13)->nullable();
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
});

test('leave history is stored and read from the application database', function () {
    $user = new User;
    $user->forceFill(['id' => 1, 'pid' => '1234567890123']);
    $user->exists = true;
    $leaveTypeId = DB::table('leave_types')->insertGetId([
        'name' => 'ลาป่วย',
        'code' => 'SICK',
        'is_active' => true,
        'sort_order' => 0,
    ]);
    DB::table('leave_requests')->insert([
        'user_id' => $user->id,
        'cid' => '1234567890123',
        'leave_type_id' => $leaveTypeId,
        'start_date' => '2025-10-01',
        'end_date' => '2025-10-01',
        'total_days' => 1,
        'reason' => 'ทดสอบ',
        'status' => 'approved',
        'step1_status' => 'approved',
        'step2_status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(LeaveHistory::class)
        ->assertSet('years', [2569])
        ->call('selectYear', 2569)
        ->assertSet('leaveHistory.0.leave_type', 'ลาป่วย');
});

test('fiscal year selector displays only the Buddhist calendar year', function () {
    $user = new User;
    $user->forceFill(['id' => 1, 'pid' => '1234567890123']);
    $user->exists = true;
    $leaveTypeId = DB::table('leave_types')->insertGetId([
        'name' => 'ลาป่วย',
        'code' => 'SICK',
    ]);
    DB::table('leave_requests')->insert([
        'user_id' => $user->id,
        'cid' => '1234567890123',
        'leave_type_id' => $leaveTypeId,
        'start_date' => '2025-10-01',
        'end_date' => '2025-10-01',
        'total_days' => 1,
        'status' => 'approved',
        'step1_status' => 'approved',
        'step2_status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(LeaveHistory::class)
        ->assertSee('ปี2569')
        ->assertDontSee('ปีงบประมาณ 2569');
});

test('leave history summarizes approved annual, sick, and personal leave days', function () {
    $user = new User;
    $user->forceFill(['id' => 1, 'pid' => '1234567890123']);
    $user->exists = true;

    $leaveTypeIds = [];
    foreach ([
        'ลาพักผ่อน' => 'VACATION',
        'ลาป่วย' => 'SICK',
        'ลากิจ' => 'PERSONAL',
    ] as $name => $code) {
        $leaveTypeIds[$name] = DB::table('leave_types')->insertGetId([
            'name' => $name,
            'code' => $code,
        ]);
    }
    foreach ([
        ['ลาพักผ่อน', 2.0],
        ['ลาป่วย', 1.0],
        ['ลากิจ', 3.0],
    ] as [$type, $days]) {
        DB::table('leave_requests')->insert([
            'user_id' => $user->id,
            'cid' => '1234567890123',
            'leave_type_id' => $leaveTypeIds[$type],
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-01',
            'total_days' => $days,
            'status' => 'approved',
            'step1_status' => 'approved',
            'step2_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($user);

    Livewire::test(LeaveHistory::class)
        ->call('selectYear', 2569)
        ->assertSet('leaveTypeTotals', [
            'ลาพักผ่อน' => 2.0,
            'ลาป่วย' => 1.0,
            'ลากิจ' => 3.0,
        ]);
});

test('leave history shows annual leave entitlement from the linked personnel record', function () {
    $user = new User;
    $user->forceFill(['id' => 1, 'pid' => '1234567890123']);
    $user->exists = true;

    DB::table('person')->insert([
        'cid' => '1234567890123',
        'fname' => 'ทดสอบ',
        'lname' => 'บุคลากร',
        'type_name' => 'พนักงานราชการ',
        'employment_started_at' => '2024-01-01',
    ]);
    $annualLeaveTypeId = DB::table('leave_types')->insertGetId([
        'name' => 'ลาพักผ่อน',
        'code' => 'VACATION',
        'is_active' => true,
        'sort_order' => 0,
    ]);
    DB::table('leave_requests')->insert([
        'user_id' => $user->id,
        'cid' => '1234567890123',
        'leave_type_id' => $annualLeaveTypeId,
        'start_date' => '2025-10-01',
        'end_date' => '2025-10-02',
        'total_days' => 2,
        'reason' => 'ทดสอบ',
        'status' => 'approved',
        'step1_status' => 'approved',
        'step2_status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(LeaveHistory::class)
        ->call('selectYear', 2569)
        ->assertSet('annualLeaveEntitlement.available', true)
        ->assertSet('annualLeaveEntitlement.current_year_days', 10.0)
        ->assertSet('annualLeaveEntitlement.carryover_cap_days', 5.0)
        ->assertSet('annualLeaveEntitlement.maximum_days', 15.0)
        ->assertSet('annualLeaveEntitlement.used_days', 2.0);
});

test('leave history calculates civil servant annual-leave carryover from the previous fiscal year', function () {
    $user = new User;
    $user->forceFill(['id' => 1, 'pid' => '1234567890123']);
    $user->exists = true;

    DB::table('person')->insert([
        'cid' => '1234567890123',
        'fname' => 'ทดสอบ',
        'lname' => 'บุคลากร',
        'type_name' => 'ข้าราชการ',
        'employment_started_at' => '2020-08-24',
    ]);
    $annualLeaveTypeId = DB::table('leave_types')->insertGetId([
        'name' => 'ลาพักผ่อน',
        'code' => 'VACATION',
        'is_active' => true,
        'sort_order' => 0,
    ]);
    foreach ([
        ['2024-10-01', '2024-10-11', 11.0],
        ['2025-10-01', '2025-10-02', 2.0],
    ] as [$startDate, $endDate, $totalDays]) {
        DB::table('leave_requests')->insert([
            'user_id' => $user->id,
            'cid' => '1234567890123',
            'leave_type_id' => $annualLeaveTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'status' => 'approved',
            'step1_status' => 'approved',
            'step2_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($user);

    Livewire::test(LeaveHistory::class)
        ->call('selectYear', 2569)
        ->assertSet('annualLeaveEntitlement.carryover_cap_days', 10.0)
        ->assertSet('annualLeaveEntitlement.carryover_actual_days', 9.0)
        ->assertSet('annualLeaveEntitlement.total_entitled_days', 19.0)
        ->assertSet('annualLeaveEntitlement.remaining_days', 17.0);
});

test('leave history limits carried annual leave to the previous fiscal year cap', function () {
    Carbon::setTestNow('2026-09-28');

    try {
        $user = new User;
        $user->forceFill(['id' => 1, 'pid' => '1234567890123']);
        $user->exists = true;

        DB::table('person')->insert([
            'cid' => '1234567890123',
            'fname' => 'ทดสอบ',
            'lname' => 'บุคลากร',
            'type_name' => 'ข้าราชการ',
            'employment_started_at' => '2015-10-01',
        ]);
        $annualLeaveTypeId = DB::table('leave_types')->insertGetId([
            'name' => 'ลาพักผ่อน',
            'code' => 'VACATION',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        DB::table('leave_requests')->insert([
            'user_id' => $user->id,
            'cid' => '1234567890123',
            'leave_type_id' => $annualLeaveTypeId,
            'start_date' => '2024-10-01',
            'end_date' => '2024-10-01',
            'total_days' => 1.0,
            'status' => 'approved',
            'step1_status' => 'approved',
            'step2_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('leave_requests')->insert([
            'user_id' => $user->id,
            'cid' => '1234567890123',
            'leave_type_id' => $annualLeaveTypeId,
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-01',
            'total_days' => 1.0,
            'status' => 'approved',
            'step1_status' => 'approved',
            'step2_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(LeaveHistory::class)
            ->call('selectYear', 2569)
            ->assertSet('annualLeaveEntitlement.carryover_actual_days', 10.0)
            ->assertSet('annualLeaveEntitlement.total_entitled_days', 20.0);
    } finally {
        Carbon::setTestNow();
    }
});

// TDD_REQUIRED: yes. Including empty additional types or filtering by approved totals breaks this seam.
test('additional summary cards depend on the users records in the selected fiscal year', function (string $name) {
    $user = new User;
    $user->forceFill(['id' => 1, 'pid' => '1234567890123']);
    $user->exists = true;
    $this->actingAs($user);
    $typeId = DB::table('leave_types')->insertGetId(['name' => $name, 'code' => 'EXTRA']);
    foreach ([
        ['1234567890123', '2025-09-30', 'approved', 3],
        ['9999999999999', '2025-10-01', 'approved', 5],
        ['1234567890123', '2026-09-30', 'pending', 2],
        ['1234567890123', '2026-10-01', 'approved', 4],
    ] as [$cid, $date, $status, $days]) {
        DB::table('leave_requests')->insert([
            'cid' => $cid, 'leave_type_id' => $typeId, 'start_date' => $date, 'end_date' => $date,
            'total_days' => $days, 'status' => $status, 'step1_status' => $status, 'step2_status' => $status,
        ]);
    }
    $component = Livewire::test(LeaveHistory::class)->call('selectYear', 2569)
        ->assertSet('leaveTypeTotals.'.$name, 0.0)->assertSee($name)
        ->call('selectYear', 2570)->assertSet('leaveTypeTotals.'.$name, 4.0);

    DB::table('leave_requests')->where('cid', $user->pid)->where('start_date', '2026-09-30')->delete();
    $component->call('selectYear', 2569)
        ->assertSet('leaveTypeTotals', ['ลาพักผ่อน' => 0.0, 'ลาป่วย' => 0.0, 'ลากิจ' => 0.0])
        ->assertDontSee($name);
})->with(['ลาคลอดบุตร', 'ลาไปช่วยเหลือภริยาที่คลอดบุตร', 'ลาอุปสมบท', 'ลาศึกษา']);
