<?php

use App\Livewire\Admin\UserManagement;
use App\Models\User;
use App\Services\UserAccessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('hname')->nullable();
        $table->string('pid')->nullable();
        $table->string('thaid_pid')->nullable();
        $table->string('role')->nullable();
        $table->string('office')->nullable();
        $table->boolean('is_active')->default(true);
        $table->rememberToken();
        $table->timestamps();
    });
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('office_id', 2)->nullable();
        $table->string('office_name')->nullable();
    });
    Schema::create('user_access_audits', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('actor_id');
        $table->unsignedBigInteger('target_user_id');
        $table->string('before_role')->nullable();
        $table->string('after_role')->nullable();
        $table->string('before_office')->nullable();
        $table->string('after_office')->nullable();
        $table->boolean('before_active')->nullable();
        $table->boolean('after_active')->nullable();
        $table->string('before_personnel_types')->nullable();
        $table->string('after_personnel_types')->nullable();
        $table->timestamp('created_at');
    });
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'ผู้ดูแล', 'email' => 'admin@example.test', 'hname' => 'สำนักงานสาธารณสุขจังหวัด', 'pid' => null, 'role' => 'ADMIN', 'office' => null],
        ['id' => 2, 'name' => 'บุคลากร', 'email' => 'person@example.test', 'hname' => 'โรงพยาบาลทดสอบ', 'pid' => '1234567890123', 'role' => null, 'office' => null],
    ]);
    DB::table('person')->insert([
        'cid' => '1234567890123', 'office_id' => '01', 'office_name' => 'สำนักงานหนึ่ง',
    ]);
    syncOfficesFromPerson();
});

test('only ADMIN may open user management and identity fields are not editable', function () {
    $this->actingAs(User::find(2))->get('/admin/users')->assertForbidden();
    Livewire::actingAs(User::find(2))->test(UserManagement::class)->assertForbidden();

    $this->actingAs(User::find(1))
        ->get('/admin/users')
        ->assertOk()
        ->assertSee('จัดการผู้ใช้')
        ->assertSee('บุคลากร');

    Livewire::actingAs(User::find(1))->test(UserManagement::class)
        ->assertDontSeeHtml('wire:model="pid"')
        ->assertDontSeeHtml('wire:model="email"')
        ->assertDontSee('ยังไม่จำกัดสิทธิ์ตามต้นสังกัด');
});

test('role and office changes are audited without altering OAuth identifiers', function () {
    app(UserAccessService::class)->update(1, 2, 'LEAVE', '01');

    $user = DB::table('users')->where('id', 2)->first();
    $audit = DB::table('user_access_audits')->first();
    expect($user->role)->toBe('LEAVE')
        ->and($user->office)->toBe('01')
        ->and($user->pid)->toBe('1234567890123')
        ->and($audit->actor_id)->toBe(1)
        ->and($audit->target_user_id)->toBe(2)
        ->and($audit->before_role)->toBeNull()
        ->and($audit->after_role)->toBe('LEAVE')
        ->and($audit->after_office)->toBe('01');

    app(UserAccessService::class)->update(1, 2, 'LEAVE', '01');
    expect(DB::table('user_access_audits')->count())->toBe(1);
});

test('unknown roles and offices are rejected and the final ADMIN cannot be removed', function () {
    $service = app(UserAccessService::class);
    expect(fn () => $service->update(1, 2, 'ROOT', '01'))->toThrow(ValidationException::class)
        ->and(fn () => $service->update(1, 2, 'LEAVE', '99'))->toThrow(ValidationException::class)
        ->and(fn () => $service->update(1, 1, 'LEAVE', null))->toThrow(ValidationException::class);
    expect(DB::table('users')->where('id', 1)->value('role'))->toBe('ADMIN')
        ->and(DB::table('user_access_audits')->count())->toBe(0);

    $service->update(1, 2, 'ADMIN', null);
    $service->update(1, 1, 'LEAVE', null);
    expect(fn () => $service->update(2, 2, null, null))->toThrow(ValidationException::class);
    expect(DB::table('users')->where('role', 'ADMIN')->count())->toBe(1);
});

test('role and office can be changed directly from the user row', function () {
    $component = Livewire::actingAs(User::find(1))->test(UserManagement::class)
        ->assertDontSee('แก้ไขสิทธิ์')
        ->assertSeeHtml('wire:change="updateRole(2, $event.target.value)"')
        ->call('updateRole', 2, 'FINANCE')->assertHasNoErrors()->assertDispatched('user-access-saved');
    expect(User::find(2)->role)->toBe('FINANCE')->and(User::find(2)->office)->toBeNull();
    $component->call('updateOffice', 2, '01')->assertHasNoErrors();
    expect(User::find(2)->role)->toBe('FINANCE')->and(User::find(2)->office)->toBe('01');
});

test('account suspension requires confirmation and closes the application modal after saving', function () {
    $component = Livewire::actingAs(User::find(1))->test(UserManagement::class)
        ->call('requestStatusChange', 2, false)->assertDispatched('user-status-form-opened');
    expect(User::find(2)->is_active)->toBeTrue();
    $component->call('confirmStatusChange')->assertHasNoErrors()
        ->assertDispatched('user-status-form-closed')->assertDispatched('user-access-saved');
    expect(User::find(2)->is_active)->toBeFalse();
    $component->call('requestStatusChange', 2, true)->call('cancelStatusChange');
    expect(User::find(2)->is_active)->toBeFalse();
});

test('inline edits validate input and recheck administrator access on every action', function () {
    $component = Livewire::actingAs(User::find(1))->test(UserManagement::class);
    $component->call('updateRole', 2, 'ROOT')->assertHasErrors('role');
    $component->call('updateOffice', 2, '99')->assertHasErrors('office');
    expect(User::find(2)->role)->toBeNull()->and(User::find(2)->office)->toBeNull();
    DB::table('users')->where('id', 1)->update(['role' => null]);
    $component->call('updateRole', 2, 'ADMIN')->assertForbidden();
});

test('an inline edit keeps another fields latest value instead of the displayed stale value', function () {
    $component = Livewire::actingAs(User::find(1))->test(UserManagement::class);
    DB::table('users')->where('id', 2)->update(['role' => 'PROMOTE']);
    $component->call('updateOffice', 2, '01')->assertHasNoErrors();
    expect(User::find(2)->role)->toBe('PROMOTE');
    $component->call('updateRole', 2, 'FINANCE')->assertHasNoErrors();
    expect(User::find(2)->office)->toBe('01');
});

test('the confirmation target cannot be replaced by a client property update', function () {
    $component = Livewire::actingAs(User::find(1))->test(UserManagement::class)->call('requestStatusChange', 2, false);
    expect(fn () => $component->set('pendingUserId', 1))
        ->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
    expect(User::find(1)->is_active)->toBeTrue()->and(User::find(2)->is_active)->toBeTrue();
});

test('the confirmation status cannot be replaced by a client property update', function () {
    $component = Livewire::actingAs(User::find(1))->test(UserManagement::class)->call('requestStatusChange', 2, false);
    expect(fn () => $component->set('pendingActive', true))
        ->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
    expect(User::find(2)->is_active)->toBeTrue();
});

test('the rendered account modal events close confirmation before showing the saved toast', function () {
    $component = Livewire::actingAs(User::find(1))->test(UserManagement::class);
    $script = preg_replace('/<\/?script[^>]*>/', '', implode("\n", $component->effects['scripts']));
    $runner = <<<'JS'
const handlers = {}, modal = {open: false}; let message;
globalThis.$wire = {on: (name, fn) => handlers[name] = fn};
globalThis.document = {getElementById: id => { if(id !== 'userStatusModal') throw Error('wrong modal'); return modal; }};
globalThis.window = {portalModal: {show: id => document.getElementById(id).open = true, hide: target => { if(target !== modal) throw Error('wrong target'); modal.open = false; }}, portalToast: {show: value => { if(modal.open) throw Error('toast before close'); message = value; }}};
JS;
    $runner .= "\n".$script;
    $runner .= "\nhandlers['user-status-form-opened'](); if(!modal.open) throw Error('not open'); handlers['user-status-form-closed'](); handlers['user-access-saved']({message:'saved'}); if(modal.open || message !== 'saved') throw Error('missing feedback');";
    $process = new \Symfony\Component\Process\Process(['node', '--eval', $runner], base_path());
    $process->run();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
});

test('the user list filters by role and office', function () {
    DB::table('users')->where('id', 2)->update(['role' => 'LEAVE', 'office' => '01']);
    Livewire::actingAs(User::find(1))
        ->test(UserManagement::class)
        ->set('roleFilter', 'LEAVE')
        ->set('officeFilter', '01')
        ->assertSee('บุคลากร')
        ->assertSee('โรงพยาบาลทดสอบ')
        ->assertDontSee('สำนักงานสาธารณสุขจังหวัด');
});

test('the account register shows the provider unit name instead of the email', function () {
    DB::table('users')->insert([
        'id' => 3, 'name' => 'ไม่มีหน่วยบริการ', 'email' => 'nohname@example.test', 'hname' => null,
        'pid' => null, 'role' => null, 'office' => null,
    ]);

    Livewire::actingAs(User::find(1))
        ->test(UserManagement::class)
        ->assertSee('โรงพยาบาลทดสอบ')
        ->assertSee('ไม่ระบุหน่วยบริการ')
        ->assertDontSee('person@example.test')
        ->assertDontSee('nohname@example.test')
        ->set('search', 'โรงพยาบาลทดสอบ')
        ->assertSee('บุคลากร')
        ->assertDontSee('สำนักงานสาธารณสุขจังหวัด');
});

test('the user list paginates beyond twenty accounts', function () {
    foreach (range(1, 23) as $number) {
        DB::table('users')->insert([
            'name' => sprintf('A%02d', $number),
            'email' => "a{$number}@example.test",
        ]);
    }

    Livewire::actingAs(User::find(1))
        ->test(UserManagement::class)
        ->assertSee('A01')
        ->assertDontSee('A23')
        ->call('nextPage')
        ->assertSee('A23');
});

test('ADMIN assigns the personnel types a PROMOTE user is responsible for, with an audit', function () {
    DB::table('users')->where('id', 2)->update(['role' => 'PROMOTE', 'office' => '01']);

    Livewire::actingAs(User::find(1))
        ->test(UserManagement::class)
        ->assertSee('ประเภทการจ้าง: ยังไม่กำหนด')
        ->call('openPersonnelTypes', 2)
        ->assertSet('typesUserName', 'บุคลากร')
        ->assertDispatched('user-types-form-opened')
        ->set('selectedTypes', ['3', '1'])
        ->call('savePersonnelTypes')
        ->assertHasNoErrors()
        ->assertDispatched('user-types-form-closed')
        ->assertDispatched('user-access-saved', message: 'บันทึกประเภทการจ้างที่รับผิดชอบแล้ว')
        ->assertSee('ประเภทการจ้าง: ข้าราชการ, พนักงานราชการ');

    expect(DB::table('user_personnel_types')->where('user_id', 2)->orderBy('type_id')->pluck('type_id')->all())->toBe([1, 3]);
    $audit = DB::table('user_access_audits')->first();
    expect($audit->before_personnel_types)->toBe('')
        ->and($audit->after_personnel_types)->toBe('1,3')
        ->and($audit->after_role)->toBe('PROMOTE');

    app(UserAccessService::class)->updatePersonnelTypes(1, 2, [3, 1]);
    expect(DB::table('user_access_audits')->count())->toBe(1);

    app(UserAccessService::class)->updatePersonnelTypes(1, 2, []);
    expect(DB::table('user_personnel_types')->where('user_id', 2)->count())->toBe(0);
});

test('only PROMOTE rows offer personnel type assignment', function () {
    Livewire::actingAs(User::find(1))
        ->test(UserManagement::class)
        ->assertDontSee('ประเภทการจ้าง:')
        ->assertDontSeeHtml('openPersonnelTypes(2)');
});

test('invalid personnel types are rejected and only an active ADMIN may assign them', function () {
    $service = app(UserAccessService::class);

    expect(fn () => $service->updatePersonnelTypes(1, 2, [5]))->toThrow(ValidationException::class)
        ->and(fn () => $service->updatePersonnelTypes(1, 2, ['x']))->toThrow(ValidationException::class);
    expect(fn () => $service->updatePersonnelTypes(2, 2, [1]))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(DB::table('user_personnel_types')->count())->toBe(0);

    Livewire::actingAs(User::find(2))->test(UserManagement::class)->assertForbidden();
});
