<?php

use App\Livewire\Admin\LeaveManagement;
use App\Livewire\Admin\LeaveSheetSync;
use App\Livewire\Admin\PersonnelManagement;
use App\Livewire\Admin\SalaryPromotionManagement;
use App\Livewire\Admin\Signatories;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
        $table->string('office_id')->nullable();
        $table->string('office_name')->nullable();
    });
    Schema::create('data_promote', function (Blueprint $table) {
        $table->id();
        $table->string('CID');
        $table->date('evaluation_date');
        $table->string('promote')->nullable();
        $table->dateTime('deleted_at')->nullable();
    });
    Schema::create('leave_requests', function (Blueprint $table) {
        $table->id();
        $table->string('cid')->nullable();
        $table->string('external_name')->nullable();
        $table->unsignedInteger('leave_type_id')->nullable();
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->decimal('total_days')->nullable();
        $table->string('reason')->nullable();
        $table->string('status')->nullable();
    });
    Schema::create('leave_types', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('code', 20)->unique();
        $table->boolean('is_active')->default(true);
        $table->unsignedInteger('sort_order')->default(0);
    });
    Schema::create('leave_sync_runs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('admin_user_id')->nullable();
    });
    Schema::create('payslip_signatories', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('fiscal_year');
        $table->string('name');
    });
});

function roleLivewireUser(?string $role, string $staffType = 'STAFF'): User
{
    $user = new User;
    $user->forceFill(['id' => 42, 'name' => 'ผู้ทดสอบ', 'role' => $role, 'staff_type' => $staffType, 'office' => '00']);
    $user->exists = true;

    return $user;
}

test('direct component entry is allowed only for the matching role', function () {
    foreach ([
        [PersonnelManagement::class, 'SUPERADMIN'],
        [LeaveManagement::class, 'LEAVE'],
        [LeaveSheetSync::class, 'LEAVE'],
        [SalaryPromotionManagement::class, 'PROMOTE'],
        [Signatories::class, 'FINANCE'],
    ] as [$component, $role]) {
        Livewire::actingAs(roleLivewireUser($role))->test($component)->assertOk();
        Livewire::actingAs(roleLivewireUser(null, 'SUPERADMIN'))->test($component)->assertForbidden();
    }
});

test('role revocation denies a signatory action even after the component opened', function () {
    $component = Livewire::actingAs(roleLivewireUser('FINANCE'))->test(Signatories::class);
    Auth::setUser(roleLivewireUser('PROMOTE'));

    $component->call('openCreateModal')->assertForbidden();
});

test('role revocation also denies write upload and streamed sync actions', function () {
    foreach ([
        [PersonnelManagement::class, 'SUPERADMIN', null, 'importExcel'],
        [LeaveManagement::class, 'LEAVE', 'PROMOTE', 'save'],
        [LeaveSheetSync::class, 'LEAVE', 'PROMOTE', 'syncFromGoogleSheet'],
        [SalaryPromotionManagement::class, 'PROMOTE', 'FINANCE', 'save'],
    ] as [$class, $allowedRole, $revokedRole, $action]) {
        $component = Livewire::actingAs(roleLivewireUser($allowedRole))->test($class);
        Auth::setUser(roleLivewireUser($revokedRole));
        $component->call($action)->assertForbidden();
    }
});
