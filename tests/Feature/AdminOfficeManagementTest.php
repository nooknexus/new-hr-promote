<?php

use App\Livewire\Admin\OfficeManagement;
use App\Livewire\Admin\PersonnelManagement;
use App\Models\User;
use App\Services\UserAccessService;
use App\Support\AdminAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Schema::create('person', function (Blueprint $table) {
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
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('role')->nullable();
        $table->string('office', 2)->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    Schema::create('user_access_audits', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('actor_id');
        $table->unsignedBigInteger('target_user_id');
        foreach (['before_role', 'after_role', 'before_office', 'after_office'] as $column) {
            $table->string($column)->nullable();
        }
        $table->boolean('before_active')->nullable();
        $table->boolean('after_active')->nullable();
        $table->timestamp('created_at');
    });

    DB::table('person')->insert([
        ['cid' => '1111111111111', 'fname' => 'คนหน่วยหนึ่ง', 'lname' => 'ทดสอบ', 'office_id' => '01', 'office_name' => 'หน่วยหนึ่ง'],
        ['cid' => '2222222222222', 'fname' => 'คนหน่วยสอง', 'lname' => 'ทดสอบ', 'office_id' => '02', 'office_name' => 'หน่วยสอง'],
    ]);
    seedOffices(['00' => 'สำนักงานสาธารณสุขจังหวัด', '01' => 'หน่วยหนึ่ง', '02' => 'หน่วยสอง']);
});

function officeUser(?string $role, ?string $office, int $id = 50): User
{
    $user = new User;
    $user->forceFill(['id' => $id, 'name' => 'ผู้ทดสอบ', 'role' => $role, 'office' => $office, 'is_active' => true]);
    $user->exists = true;

    return $user;
}

test('the offices migration registers every office from person and users', function () {
    Schema::drop('offices');
    DB::table('users')->insert(['id' => 1, 'name' => 'ผู้ใช้', 'role' => 'LEAVE', 'office' => '07']);

    (require database_path('migrations/2026_10_09_100000_create_offices_table.php'))->up();
    (require database_path('migrations/2026_10_09_100000_create_offices_table.php'))->up();

    expect(DB::table('offices')->orderBy('code')->pluck('name', 'code')->all())
        ->toBe(['01' => 'หน่วยหนึ่ง', '02' => 'หน่วยสอง', '07' => '07']);
});

test('ADMIN and office 00 staff may manage offices; others may not', function (?string $role, ?string $office, bool $allowed) {
    $user = officeUser($role, $office);

    expect(AdminAccess::allows($user, 'offices'))->toBe($allowed);
    $allowed
        ? Livewire::actingAs($user)->test(OfficeManagement::class)->assertOk()->assertSee('หน่วยสอง')
        : Livewire::actingAs($user)->test(OfficeManagement::class)->assertForbidden();

    $this->actingAs($user);
    $html = Blade::render('<x-layouts.app><p>เนื้อหา</p></x-layouts.app>');
    $allowed ? expect($html)->toContain(route('admin.offices.index')) : expect($html)->not->toContain(route('admin.offices.index'));
})->with([
    'ADMIN of another office' => ['SUPERADMIN', '01', true],
    'LEAVE of office 00' => ['LEAVE', '00', true],
    'FINANCE of office 00' => ['FINANCE', '00', true],
    'LEAVE of another office' => ['LEAVE', '01', false],
    'no role in office 00' => [null, '00', false],
]);

test('a new office is selectable immediately and can receive its first person', function () {
    $admin = officeUser('SUPERADMIN', '00');

    Livewire::actingAs($admin)
        ->test(OfficeManagement::class)
        ->set('code', '90')
        ->set('name', 'โรงพยาบาลส่งเสริมสุขภาพตำบลใหม่')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('office-saved', message: 'เพิ่มต้นสังกัดแล้ว');

    Livewire::actingAs($admin)
        ->test(PersonnelManagement::class)
        ->assertSee('90 — โรงพยาบาลส่งเสริมสุขภาพตำบลใหม่')
        ->set('cid', '3333333333333')
        ->set('fname', 'บุคลากรคนแรก')
        ->set('lname', 'ทดสอบ')
        ->set('office_id', '90')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('person')->where('cid', '3333333333333')->first(['office_id', 'office_name']))
        ->toMatchObject(['office_id' => '90', 'office_name' => 'โรงพยาบาลส่งเสริมสุขภาพตำบลใหม่']);

    DB::table('users')->insert(['id' => 1, 'name' => 'ผู้ดูแล', 'role' => 'SUPERADMIN', 'office' => '00']);
    DB::table('users')->insert(['id' => 2, 'name' => 'เจ้าหน้าที่', 'role' => 'LEAVE', 'office' => null]);
    app(UserAccessService::class)->updateOffice(1, 2, '90');

    expect(DB::table('users')->where('id', 2)->value('office'))->toBe('90');
});

test('office codes are two digits, unique and fixed after creation', function () {
    $component = Livewire::actingAs(officeUser('SUPERADMIN', '00'))->test(OfficeManagement::class);

    $component->set('code', '1')->set('name', 'สั้น')->call('save')->assertHasErrors(['code' => 'digits']);
    $component->set('code', '01')->set('name', 'ซ้ำ')->call('save')->assertHasErrors(['code' => 'unique']);

    $component->call('edit', '01')->assertSet('code', '01')->assertSeeHtml('id="office-code"');
    expect($component->get('editingCode'))->toBe('01');
});

test('renaming an office updates the synced person office names', function () {
    Livewire::actingAs(officeUser('SUPERADMIN', '00'))
        ->test(OfficeManagement::class)
        ->call('edit', '01')
        ->set('name', 'หน่วยหนึ่ง (ชื่อใหม่)')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('offices')->where('code', '01')->value('name'))->toBe('หน่วยหนึ่ง (ชื่อใหม่)')
        ->and(DB::table('person')->where('cid', '1111111111111')->value('office_name'))->toBe('หน่วยหนึ่ง (ชื่อใหม่)')
        ->and(DB::table('person')->where('cid', '2222222222222')->value('office_name'))->toBe('หน่วยสอง');
});

test('a deactivated office leaves forms but stays in filters and keeps existing people', function () {
    $admin = officeUser('SUPERADMIN', '00');

    Livewire::actingAs($admin)
        ->test(OfficeManagement::class)
        ->call('prepareToggle', '02')
        ->call('toggleActive')
        ->assertDispatched('office-saved', message: 'ปิดใช้งานต้นสังกัดแล้ว');

    expect(DB::table('offices')->where('code', '02')->value('is_active'))->toBeFalsy();

    $personnel = Livewire::actingAs($admin)->test(PersonnelManagement::class);
    expect($personnel->viewData('formOffices')->pluck('office_id')->all())->toBe(['00', '01'])
        ->and($personnel->viewData('offices')->pluck('office_id')->all())->toBe(['00', '01', '02']);
    $personnel->assertSee('คนหน่วยสอง');

    // Moving someone else into the closed office is rejected.
    $personnel->set('cid', '4444444444444')->set('fname', 'ใหม่')->set('lname', 'ทดสอบ')
        ->set('office_id', '02')->call('save')->assertHasErrors(['office_id' => 'in']);

    // The current member still edits other fields while keeping the closed office.
    $editing = Livewire::actingAs($admin)->test(PersonnelManagement::class)->call('edit', '2222222222222');
    expect($editing->viewData('formOffices')->pluck('office_id')->all())->toContain('02');
    $editing->assertSee('02 — หน่วยสอง (ปิดใช้งาน)')
        ->set('fname', 'แก้ชื่อ')->call('save')->assertHasNoErrors();
    expect(DB::table('person')->where('cid', '2222222222222')->value('office_id'))->toBe('02');
});

test('office 00 cannot be deactivated', function () {
    Livewire::actingAs(officeUser('SUPERADMIN', '00'))
        ->test(OfficeManagement::class)
        ->assertDontSeeHtml("prepareToggle('00')")
        ->call('prepareToggle', '00')
        ->assertForbidden();
});

test('users cannot be moved into an inactive office but keep one they already have', function () {
    DB::table('offices')->where('code', '02')->update(['is_active' => false]);
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'ผู้ดูแล', 'role' => 'SUPERADMIN', 'office' => '00'],
        ['id' => 2, 'name' => 'เจ้าหน้าที่', 'role' => 'LEAVE', 'office' => '02'],
        ['id' => 3, 'name' => 'อีกคน', 'role' => 'LEAVE', 'office' => '01'],
    ]);

    expect(fn () => app(UserAccessService::class)->updateOffice(1, 3, '02'))->toThrow(ValidationException::class);

    app(UserAccessService::class)->update(1, 2, 'PROMOTE', '02');
    expect(DB::table('users')->where('id', 2)->first(['role', 'office']))->toMatchObject(['role' => 'PROMOTE', 'office' => '02']);
});
