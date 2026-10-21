<?php

use App\Livewire\Admin\PersonnelManagement;
use App\Models\User;
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
        $table->string('no_position')->nullable();
        $table->string('position')->nullable();
        $table->string('position_level')->nullable();
    });
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('pid', 13)->nullable();
        $table->string('thaid_pid', 13)->nullable();
        $table->string('no_position')->nullable();
    });
});

function personnelAdmin(): User
{
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'ผู้ดูแล', 'staff_type' => 'ADMIN', 'role' => 'SUPERADMIN']);
    $user->exists = true;

    return $user;
}

function personnelStandardUser(): User
{
    $user = new User;
    $user->forceFill(['id' => 2, 'name' => 'ผู้ใช้งาน', 'staff_type' => 'STAFF']);
    $user->exists = true;

    return $user;
}

test('an administrator can add personnel with their employment start date', function () {
    Livewire::actingAs(personnelAdmin())
        ->test(PersonnelManagement::class)
        ->set('cid', '1234567890123')
        ->set('pname', 'นาง')
        ->set('fname', 'ทดสอบ')
        ->set('lname', 'บุคลากร')
        ->set('type_id', 1)
        ->set('employment_started_at', '2018-10-01')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('person')->where('cid', '1234567890123')->first())
        ->not->toBeNull()
        ->employment_started_at->toBe('2018-10-01');
});

test('temporary employees can be created and edited with the correct personnel type', function () {
    $component = Livewire::actingAs(personnelAdmin())->test(PersonnelManagement::class)
        ->set('cid', '5555555555555')
        ->set('fname', 'ชั่วคราวทดสอบ')
        ->set('lname', 'บุคลากร')
        ->set('type_id', 5)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSeeHtml('<option value="5">ลูกจ้างชั่วคราว</option>');

    expect(DB::table('person')->where('cid', '5555555555555')->first())
        ->toMatchObject(['type_id' => 5, 'type_name' => 'ลูกจ้างชั่วคราว']);

    $component->call('edit', '5555555555555')
        ->assertSet('type_id', 5)
        ->set('fname', 'แก้ไขชั่วคราว')
        ->call('save')->assertHasNoErrors();
    expect(DB::table('person')->where('cid', '5555555555555')->first())
        ->toMatchObject(['fname' => 'แก้ไขชั่วคราว', 'type_id' => 5, 'type_name' => 'ลูกจ้างชั่วคราว']);
});

test('temporary personnel can be filtered within the current office', function () {
    DB::table('person')->insert([
        ['cid' => '5555555555555', 'fname' => 'ชั่วคราวต้นสังกัดหนึ่ง', 'lname' => 'ทดสอบ', 'type_id' => 5, 'type_name' => 'ลูกจ้างชั่วคราว', 'office_id' => '01'],
        ['cid' => '6666666666666', 'fname' => 'ชั่วคราวต่างต้นสังกัด', 'lname' => 'ทดสอบ', 'type_id' => 5, 'type_name' => 'ลูกจ้างชั่วคราว', 'office_id' => '02'],
        ['cid' => '7777777777777', 'fname' => 'ข้าราชการต้นสังกัดหนึ่ง', 'lname' => 'ทดสอบ', 'type_id' => 1, 'type_name' => 'ข้าราชการ', 'office_id' => '01'],
    ]);
    Livewire::actingAs(personnelOfficeAdmin('01'))->test(PersonnelManagement::class)
        ->set('typeFilter', '5')
        ->assertSee('ชั่วคราวต้นสังกัดหนึ่ง')
        ->assertDontSee('ชั่วคราวต่างต้นสังกัด')
        ->assertDontSee('ข้าราชการต้นสังกัดหนึ่ง');
});

test('the personnel form selects readable type and office names without exposing raw codes', function () {
    DB::table('person')->insert([
        'cid' => '1111111111111',
        'fname' => 'ข้อมูลอ้างอิง',
        'office_id' => '00',
        'office_name' => 'สำนักงานสาธารณสุขจังหวัดพิษณุโลก',
    ]);

    Livewire::actingAs(personnelAdmin())
        ->test(PersonnelManagement::class)
        ->assertSeeHtml('id="person-type-name"')
        ->assertSeeHtml('id="person-office-name"')
        ->assertDontSee('รหัสประเภท')
        ->assertDontSee('รหัสหน่วยงาน')
        ->assertSee('ข้าราชการ')
        ->assertSee('สำนักงานสาธารณสุขจังหวัดพิษณุโลก');
});

test('the add-personnel control opens the application modal directly', function () {
    Livewire::actingAs(personnelAdmin())
        ->test(PersonnelManagement::class)
        ->assertSeeHtml("onclick=\"window.portalModal.show('personnelFormModal')\"");
});

test('the edit and delete controls open their application modals directly', function () {
    DB::table('person')->insert([
        'cid' => '1234567890123',
        'fname' => 'ทดสอบ',
        'lname' => 'บุคลากร',
    ]);

    Livewire::actingAs(personnelAdmin())
        ->test(PersonnelManagement::class)
        ->assertSeeHtml("wire:click=\"edit('1234567890123')\" onclick=\"window.portalModal.show('personnelFormModal')\"")
        ->assertSeeHtml("wire:click=\"prepareDelete('1234567890123')\" onclick=\"window.portalModal.show('personnelDeleteModal')\"");
});

test('an administrator can load a personnel record into the edit form', function () {
    DB::table('person')->insert([
        'cid' => '1234567890123',
        'pname' => 'นางสาว',
        'fname' => 'ทดสอบ',
        'lname' => 'บุคลากร',
        'type_id' => 1,
        'type_name' => 'ข้าราชการ',
        'office_id' => '01',
        'office_name' => 'หน่วยบริการหนึ่ง',
        'employment_started_at' => '2018-10-01',
    ]);

    Livewire::actingAs(personnelAdmin())
        ->test(PersonnelManagement::class)
        ->call('edit', '1234567890123')
        ->assertSet('editingCid', '1234567890123')
        ->assertSet('fname', 'ทดสอบ')
        ->assertSet('office_name', 'หน่วยบริการหนึ่ง')
        ->assertSet('employment_started_at', '2018-10-01');
});

test('the personnel form edits position, level and position number', function () {
    DB::table('person')->insert([
        'cid' => '1234567890123',
        'fname' => 'ทดสอบ',
        'lname' => 'บุคลากร',
        'position' => 'นักวิชาการสาธารณสุข',
        'position_level' => 'ปฏิบัติการ',
        'no_position' => '1001',
    ]);

    $component = Livewire::actingAs(personnelAdmin())
        ->test(PersonnelManagement::class)
        ->assertSeeHtml('id="person-position"')
        ->assertSeeHtml('id="person-position-level"')
        ->assertSeeHtml('id="person-no-position"')
        ->assertSeeHtml('<option value="ชำนาญการ"></option>')
        ->assertSee('นักวิชาการสาธารณสุข · ปฏิบัติการ · เลขที่ 1001')
        ->call('edit', '1234567890123')
        ->assertSet('position', 'นักวิชาการสาธารณสุข')
        ->assertSet('position_level', 'ปฏิบัติการ')
        ->assertSet('no_position', '1001')
        ->set('position', '  พยาบาลวิชาชีพ ')
        ->set('position_level', 'ชำนาญการ')
        ->set('no_position', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('person')->where('cid', '1234567890123')->first(['position', 'position_level', 'no_position']))
        ->toMatchObject(['position' => 'พยาบาลวิชาชีพ', 'position_level' => 'ชำนาญการ', 'no_position' => null]);

    $component->set('cid', '9876543210987')
        ->set('fname', 'ใหม่')
        ->set('lname', 'บุคลากร')
        ->set('position', str_repeat('ก', 256))
        ->call('save')
        ->assertHasErrors(['position' => 'max']);
});

test('changing a position number updates the linked account used by payslips', function () {
    DB::table('person')->insert([
        ['cid' => '1234567890123', 'fname' => 'ทดสอบ', 'lname' => 'บุคลากร', 'no_position' => '1001'],
        ['cid' => '2222222222222', 'fname' => 'ซ้ำ', 'lname' => 'บัญชี', 'no_position' => '2002'],
    ]);
    DB::table('users')->insert([
        ['id' => 10, 'pid' => '1234567890123', 'thaid_pid' => null, 'no_position' => '1001'],
        ['id' => 11, 'pid' => '2222222222222', 'thaid_pid' => null, 'no_position' => '2002'],
        ['id' => 12, 'pid' => null, 'thaid_pid' => '2222222222222', 'no_position' => '2002'],
        ['id' => 13, 'pid' => '3333333333333', 'thaid_pid' => null, 'no_position' => 'เดิม'],
    ]);

    $component = Livewire::actingAs(personnelAdmin())->test(PersonnelManagement::class);

    $component->call('edit', '1234567890123')->set('no_position', '1005')->call('save')->assertHasNoErrors();
    expect(DB::table('users')->where('id', 10)->value('no_position'))->toBe('1005');

    // An ambiguous link is left untouched.
    $component->call('edit', '2222222222222')->set('no_position', '2009')->call('save')->assertHasNoErrors();
    expect(DB::table('users')->whereIn('id', [11, 12])->pluck('no_position')->all())->toBe(['2002', '2002']);

    // Saving other fields without changing the number never touches the account.
    DB::table('person')->insert(['cid' => '3333333333333', 'fname' => 'ไม่มีเลข', 'lname' => 'ตำแหน่ง']);
    $component->call('edit', '3333333333333')->set('fname', 'แก้ชื่อ')->call('save')->assertHasNoErrors();
    expect(DB::table('users')->where('id', 13)->value('no_position'))->toBe('เดิม');
});

test('saving selected personnel type and office keeps their codes and names in sync', function () {
    DB::table('person')->insert([
        'cid' => '1111111111111',
        'fname' => 'ข้อมูลอ้างอิง',
        'lname' => 'หน่วยงาน',
        'office_id' => '00',
        'office_name' => 'สำนักงานสาธารณสุขจังหวัดพิษณุโลก',
    ]);
    DB::table('person')->insert([
        'cid' => '1234567890123',
        'fname' => 'ทดสอบ',
        'lname' => 'บุคลากร',
        'type_id' => 2,
        'type_name' => 'ลูกจ้างประจำ',
        'office_id' => '05',
        'office_name' => 'โรงพยาบาลเดิม',
    ]);

    syncOfficesFromPerson();

    Livewire::actingAs(personnelAdmin())
        ->test(PersonnelManagement::class)
        ->call('edit', '1234567890123')
        ->set('type_id', 1)
        ->set('office_id', '00')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('person')->where('cid', '1234567890123')->first(['type_id', 'type_name', 'office_id', 'office_name']))
        ->toMatchObject([
            'type_id' => 1,
            'type_name' => 'ข้าราชการ',
            'office_id' => '00',
            'office_name' => 'สำนักงานสาธารณสุขจังหวัดพิษณุโลก',
        ]);
});

test('saving an edited personnel record dispatches a close-and-toast event', function () {
    DB::table('person')->insert([
        'cid' => '1234567890123',
        'fname' => 'ทดสอบ',
        'lname' => 'บุคลากร',
    ]);

    Livewire::actingAs(personnelAdmin())
        ->test(PersonnelManagement::class)
        ->call('edit', '1234567890123')
        ->set('fname', 'แก้ไขแล้ว')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('personnel-saved', message: 'ปรับปรุงข้อมูลบุคลากรแล้ว');
});

test('an administrator can delete a personnel record after selecting it for deletion', function () {
    DB::table('person')->insert([
        'cid' => '1234567890123',
        'fname' => 'ทดสอบ',
        'lname' => 'บุคลากร',
    ]);

    Livewire::actingAs(personnelAdmin())
        ->test(PersonnelManagement::class)
        ->call('prepareDelete', '1234567890123')
        ->call('deletePersonnel');

    expect(DB::table('person')->where('cid', '1234567890123')->exists())->toBeFalse();
});

test('an administrator can filter the personnel list by office ID', function () {
    DB::table('person')->insert([
        'cid' => '1234567890123',
        'fname' => 'บุคลากรหน่วยหนึ่ง',
        'lname' => 'ทดสอบ',
        'office_id' => '01',
        'office_name' => 'หน่วยบริการหนึ่ง',
    ]);
    DB::table('person')->insert([
        'cid' => '9876543210987',
        'fname' => 'บุคลากรหน่วยสอง',
        'lname' => 'ทดสอบ',
        'office_id' => '02',
        'office_name' => 'หน่วยบริการสอง',
    ]);

    Livewire::actingAs(personnelAdmin())
        ->test(PersonnelManagement::class)
        ->set('officeFilter', '02')
        ->assertSee('บุคลากรหน่วยสอง')
        ->assertDontSee('บุคลากรหน่วยหนึ่ง');
});

test('the personnel list renders Tailwind pagination controls when there is more than one page', function () {
    foreach (range(1, 21) as $index) {
        DB::table('person')->insert([
            'cid' => sprintf('%013d', $index),
            'fname' => 'บุคลากร'.$index,
            'lname' => 'ทดสอบ',
            'office_id' => '01',
            'office_name' => 'หน่วยบริการหนึ่ง',
        ]);
    }

    Livewire::actingAs(personnelAdmin())
        ->test(PersonnelManagement::class)
        ->assertSeeHtml('inline-flex');
});

test('only an administrator can open personnel management and download its Excel template', function () {
    $this->actingAs(personnelStandardUser())
        ->get(route('admin.personnel.index'))
        ->assertForbidden();

    $this->actingAs(personnelAdmin())
        ->get(route('admin.personnel.template'))
        ->assertOk()
        ->assertDownload('personnel-import-template.xlsx');
});

function personnelOfficeAdmin(?string $office): User
{
    $user = personnelAdmin();
    $user->forceFill(['role' => 'LEAVE', 'office' => $office]);

    return $user;
}

function seedPersonnelInTwoOffices(): void
{
    DB::table('person')->insert([
        ['cid' => '1111111111111', 'fname' => 'บุคลากรหน่วยหนึ่ง', 'lname' => 'ทดสอบ', 'office_id' => '01', 'office_name' => 'หน่วยบริการหนึ่ง'],
        ['cid' => '2222222222222', 'fname' => 'บุคลากรหน่วยสอง', 'lname' => 'ทดสอบ', 'office_id' => '02', 'office_name' => 'หน่วยบริการสอง'],
    ]);
    syncOfficesFromPerson();
}

test('an ADMIN sees personnel of every office regardless of their assigned office', function () {
    seedPersonnelInTwoOffices();

    foreach ([null, '01'] as $office) {
        $admin = personnelAdmin();
        $admin->forceFill(['office' => $office]);

        Livewire::actingAs($admin)
            ->test(PersonnelManagement::class)
            ->assertSee('บุคลากรหน่วยหนึ่ง')
            ->assertSee('บุคลากรหน่วยสอง');
    }
});

test('a user assigned to office 00 sees personnel of every office', function () {
    seedPersonnelInTwoOffices();

    Livewire::actingAs(personnelOfficeAdmin('00'))
        ->test(PersonnelManagement::class)
        ->assertSee('บุคลากรหน่วยหนึ่ง')
        ->assertSee('บุคลากรหน่วยสอง');
});

test('a user assigned to another office sees only personnel and offices of their own office', function () {
    seedPersonnelInTwoOffices();

    Livewire::actingAs(personnelOfficeAdmin('01'))
        ->test(PersonnelManagement::class)
        ->assertSee('บุคลากรหน่วยหนึ่ง')
        ->assertDontSee('บุคลากรหน่วยสอง')
        ->assertDontSee('หน่วยบริการสอง')
        ->set('officeFilter', '02')
        ->assertDontSee('บุคลากรหน่วยสอง');
});

test('a user without an assigned office sees no personnel', function () {
    seedPersonnelInTwoOffices();

    Livewire::actingAs(personnelOfficeAdmin(null))
        ->test(PersonnelManagement::class)
        ->assertDontSee('บุคลากรหน่วยหนึ่ง')
        ->assertDontSee('บุคลากรหน่วยสอง');
});

test('an office-scoped user cannot edit or delete personnel of another office', function () {
    seedPersonnelInTwoOffices();

    Livewire::actingAs(personnelOfficeAdmin('01'))
        ->test(PersonnelManagement::class)
        ->call('edit', '2222222222222')
        ->assertNotFound();

    Livewire::actingAs(personnelOfficeAdmin('01'))
        ->test(PersonnelManagement::class)
        ->call('prepareDelete', '2222222222222')
        ->assertNotFound();

    Livewire::actingAs(personnelOfficeAdmin('01'))
        ->test(PersonnelManagement::class)
        ->set('pendingDeletionCid', '2222222222222')
        ->call('deletePersonnel')
        ->assertNotFound();

    expect(DB::table('person')->where('cid', '2222222222222')->exists())->toBeTrue();
});

test('an office-scoped user can only save personnel into their own office', function () {
    seedPersonnelInTwoOffices();

    Livewire::actingAs(personnelOfficeAdmin('01'))
        ->test(PersonnelManagement::class)
        ->call('edit', '1111111111111')
        ->set('office_id', '02')
        ->call('save')
        ->assertHasErrors(['office_id' => 'in']);

    expect(DB::table('person')->where('cid', '1111111111111')->value('office_id'))->toBe('01');

    Livewire::actingAs(personnelOfficeAdmin('01'))
        ->test(PersonnelManagement::class)
        ->set('cid', '3333333333333')
        ->set('fname', 'เพิ่มใหม่')
        ->set('lname', 'ทดสอบ')
        ->set('office_id', '01')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('person')->where('cid', '3333333333333')->value('office_id'))->toBe('01');
});

test('the office filter is labelled ต้นสังกัด and lists only the user own office when scoped', function () {
    seedPersonnelInTwoOffices();

    Livewire::actingAs(personnelOfficeAdmin('01'))
        ->test(PersonnelManagement::class)
        ->assertSee('กรองตามต้นสังกัด')
        ->assertSee('ทุกต้นสังกัด')
        ->assertSeeHtml('<option value="01">')
        ->assertDontSeeHtml('<option value="02">');
});

test('the personnel list can be filtered by employment type within the user office scope', function () {
    DB::table('person')->insert([
        ['cid' => '1111111111111', 'fname' => 'ข้าราชการหน่วยหนึ่ง', 'lname' => 'ทดสอบ', 'type_id' => 1, 'type_name' => 'ข้าราชการ', 'office_id' => '01'],
        ['cid' => '2222222222222', 'fname' => 'พนักงานราชการหน่วยหนึ่ง', 'lname' => 'ทดสอบ', 'type_id' => 3, 'type_name' => 'พนักงานราชการ', 'office_id' => '01'],
        ['cid' => '3333333333333', 'fname' => 'ข้าราชการหน่วยสอง', 'lname' => 'ทดสอบ', 'type_id' => 1, 'type_name' => 'ข้าราชการ', 'office_id' => '02'],
    ]);

    Livewire::actingAs(personnelOfficeAdmin('01'))
        ->test(PersonnelManagement::class)
        ->assertSee('ทุกประเภทการจ้าง')
        ->assertSeeHtml('<option value="3">พนักงานราชการ</option>')
        ->set('typeFilter', '1')
        ->assertSee('ข้าราชการหน่วยหนึ่ง')
        ->assertDontSee('พนักงานราชการหน่วยหนึ่ง')
        ->assertDontSee('ข้าราชการหน่วยสอง')
        ->set('typeFilter', '3')
        ->assertSee('พนักงานราชการหน่วยหนึ่ง')
        ->assertDontSee('ข้าราชการหน่วยหนึ่ง')
        ->set('typeFilter', '')
        ->assertSee('ข้าราชการหน่วยหนึ่ง')
        ->assertSee('พนักงานราชการหน่วยหนึ่ง');
});
