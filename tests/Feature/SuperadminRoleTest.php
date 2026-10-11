<?php

use App\Livewire\Admin\OfficeManagement;
use App\Livewire\Admin\UserManagement;
use App\Models\User;
use App\Services\UserAccessService;
use App\Support\AdminAccess;
use App\Support\OfficeScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('hname')->nullable();
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
        foreach (['before_role', 'after_role', 'before_office', 'after_office', 'before_personnel_types', 'after_personnel_types'] as $column) {
            $table->string($column)->nullable();
        }
        $table->boolean('before_active')->nullable();
        $table->boolean('after_active')->nullable();
        $table->timestamp('created_at');
    });
    seedOffices(['00' => 'สสจ.', '01' => 'หน่วยหนึ่ง']);
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'ผู้ดูแลสูงสุด', 'role' => 'SUPERADMIN', 'office' => '00'],
        ['id' => 2, 'name' => 'ผู้ดูแล', 'role' => 'ADMIN', 'office' => '01'],
        ['id' => 3, 'name' => 'บุคลากร', 'role' => null, 'office' => '01'],
    ]);
});

test('ADMIN reaches only offices, users and signatories, for every office', function () {
    $admin = User::find(2);

    foreach (['offices', 'users', 'signatories'] as $ability) {
        expect(AdminAccess::allows($admin, $ability))->toBeTrue();
    }
    foreach (['personnel', 'leave', 'leave-sync', 'promotions', 'payslip', 'tax-certificates'] as $ability) {
        expect(AdminAccess::allows($admin, $ability))->toBeFalse()
            ->and(AdminAccess::allows(User::find(1), $ability))->toBeTrue();
    }
    expect(OfficeScope::officeFor($admin))->toBeNull()
        ->and(AdminAccess::roleLabel($admin))->toBe('ผู้ดูแลระบบ')
        ->and(AdminAccess::roleLabel(User::find(1)))->toBe('ผู้ดูแลระบบสูงสุด');

    Livewire::actingAs($admin)->test(OfficeManagement::class)->assertOk();
    Livewire::actingAs($admin)->test(UserManagement::class)->assertOk();

    $this->actingAs($admin);
    $html = Blade::render('<x-layouts.app><p>เนื้อหา</p></x-layouts.app>');
    expect($html)->toContain(route('admin.offices.index'))->toContain(route('admin.users.index'))->toContain(route('admin.signatories'))
        ->not->toContain(route('admin.personnel.index'))->not->toContain(route('admin.tax-certificates.index'));
});

test('only a SUPERADMIN grants SUPERADMIN or changes a SUPERADMIN account', function () {
    $service = app(UserAccessService::class);

    expect(fn () => $service->updateRole(2, 3, 'SUPERADMIN'))->toThrow(HttpException::class)
        ->and(fn () => $service->updateRole(2, 1, 'LEAVE'))->toThrow(HttpException::class)
        ->and(fn () => $service->updateOffice(2, 1, '01'))->toThrow(HttpException::class)
        ->and(fn () => $service->setActive(2, 1, false))->toThrow(HttpException::class);

    $service->updateRole(2, 3, 'ADMIN');
    $service->updateRole(1, 3, 'SUPERADMIN');
    expect(DB::table('users')->where('id', 3)->value('role'))->toBe('SUPERADMIN');

    Livewire::actingAs(User::find(2))->test(UserManagement::class)
        ->assertViewHas('roles', fn (array $roles) => ! in_array('SUPERADMIN', $roles, true))
        ->assertSeeHtml('id="user-role-1" wire:change="updateRole(1, $event.target.value)" wire:loading.attr="disabled" disabled');
    Livewire::actingAs(User::find(1))->test(UserManagement::class)
        ->assertViewHas('roles', fn (array $roles) => in_array('SUPERADMIN', $roles, true));
});

test('the last active SUPERADMIN cannot be demoted or disabled', function () {
    $service = app(UserAccessService::class);

    expect(fn () => $service->updateRole(1, 1, 'ADMIN'))->toThrow(ValidationException::class);

    DB::table('users')->insert(['id' => 4, 'name' => 'สูงสุดอีกคน', 'role' => 'SUPERADMIN', 'office' => '00']);
    $service->updateRole(4, 1, 'ADMIN');
    expect(fn () => $service->setActive(1, 4, false))->toThrow(HttpException::class)
        ->and(fn () => $service->updateRole(4, 4, null))->toThrow(ValidationException::class);
});

test('existing administrators become SUPERADMIN and rollback refuses limited admins', function () {
    DB::table('users')->where('id', 1)->update(['role' => 'ADMIN']);
    DB::table('users')->where('id', 2)->update(['role' => 'LEAVE']);
    $migration = require database_path('migrations/2026_10_11_150000_move_admins_to_superadmin.php');

    $migration->up();
    expect(DB::table('users')->pluck('role', 'id')->all())->toBe([1 => 'SUPERADMIN', 2 => 'LEAVE', 3 => null]);

    DB::table('users')->where('id', 2)->update(['role' => 'ADMIN']);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    DB::table('users')->where('id', 2)->update(['role' => 'LEAVE']);
    $migration->down();
    expect(DB::table('users')->where('id', 1)->value('role'))->toBe('ADMIN');
});
