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
        $table->string('pid')->nullable();
        $table->string('thaid_pid')->nullable();
        $table->string('role')->nullable();
        $table->string('office')->nullable();
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
        $table->timestamp('created_at');
    });
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'ผู้ดูแล', 'email' => 'admin@example.test', 'pid' => null, 'role' => 'ADMIN', 'office' => null],
        ['id' => 2, 'name' => 'บุคลากร', 'email' => 'person@example.test', 'pid' => '1234567890123', 'role' => null, 'office' => null],
    ]);
    DB::table('person')->insert([
        'cid' => '1234567890123', 'office_id' => '01', 'office_name' => 'สำนักงานหนึ่ง',
    ]);
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
        ->assertSee('ยังไม่จำกัดสิทธิ์ตามหน่วยงาน');
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

test('saving from the edit modal closes it and emits a toast event', function () {
    Livewire::actingAs(User::find(1))
        ->test(UserManagement::class)
        ->call('edit', 2)
        ->assertSet('editingId', 2)
        ->set('role', 'FINANCE')
        ->set('office', '01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('user-access-form-closed')
        ->assertDispatched('user-access-saved');
    expect(DB::table('users')->where('id', 2)->value('role'))->toBe('FINANCE');
});

test('the user list filters by role and office', function () {
    DB::table('users')->where('id', 2)->update(['role' => 'LEAVE', 'office' => '01']);
    Livewire::actingAs(User::find(1))
        ->test(UserManagement::class)
        ->set('roleFilter', 'LEAVE')
        ->set('officeFilter', '01')
        ->assertSee('บุคลากร')
        ->assertDontSee('admin@example.test');
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
