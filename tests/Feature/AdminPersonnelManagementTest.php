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
    });
});

function personnelAdmin(): User
{
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'ผู้ดูแล', 'staff_type' => 'ADMIN', 'role' => 'ADMIN']);
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
