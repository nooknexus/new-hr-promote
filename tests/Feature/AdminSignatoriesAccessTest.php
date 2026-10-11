<?php

use App\Livewire\Admin\Signatories;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Schema::dropIfExists('payslip_signatories');
    Schema::create('payslip_signatories', function (Blueprint $table) {
        $table->id();
        $table->unsignedSmallInteger('fiscal_year')->unique();
        $table->string('name');
        $table->string('position_line1');
        $table->string('position_line2')->nullable();
        $table->binary('signature_image')->nullable();
        $table->string('signature_mime')->nullable();
        $table->timestamps();
    });
    $this->signatoryId = DB::table('payslip_signatories')->insertGetId([
        'fiscal_year' => 2569, 'name' => 'ผู้ลงนามทดสอบ', 'position_line1' => 'นายแพทย์สาธารณสุขจังหวัด',
    ]);
});

function signatoryUser(?string $role, ?string $office): User
{
    $user = new User;
    $user->forceFill(['id' => 21, 'name' => 'ผู้ทดสอบ', 'staff_type' => 'STAFF', 'role' => $role, 'office' => $office]);
    $user->exists = true;

    return $user;
}

test('only ADMIN and FINANCE may open signatories', function () {
    foreach (['LEAVE', 'PROMOTE', null] as $role) {
        Livewire::actingAs(signatoryUser($role, '00'))->test(Signatories::class)->assertForbidden();
    }
});

test('ADMIN and FINANCE in office 00 may manage signatories', function (string $role, ?string $office) {
    Livewire::actingAs(signatoryUser($role, $office))
        ->test(Signatories::class)
        ->assertSee('ผู้ลงนามทดสอบ')
        ->assertSee('เพิ่มผู้ลงนาม')
        ->assertSeeHtml('wire:click="edit('.$this->signatoryId.')"')
        ->call('edit', $this->signatoryId)
        ->set('name', 'ผู้ลงนามใหม่')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('payslip_signatories')->value('name'))->toBe('ผู้ลงนามใหม่');
})->with([
    'ADMIN without an office' => ['SUPERADMIN', null],
    'ADMIN of another office' => ['SUPERADMIN', '01'],
    'FINANCE of office 00' => ['FINANCE', '00'],
]);

test('FINANCE outside office 00 sees signatories read-only', function (?string $office) {
    Livewire::actingAs(signatoryUser('FINANCE', $office))
        ->test(Signatories::class)
        ->assertSee('ผู้ลงนามทดสอบ')
        ->assertDontSee('เพิ่มผู้ลงนาม')
        ->assertDontSeeHtml('wire:click="edit(')
        ->assertDontSeeHtml('wire:click="delete(');

    foreach ([['openCreateModal'], ['edit', $this->signatoryId], ['save'], ['delete', $this->signatoryId]] as $call) {
        Livewire::actingAs(signatoryUser('FINANCE', $office))
            ->test(Signatories::class)
            ->call(...$call)
            ->assertForbidden();
    }

    expect(DB::table('payslip_signatories')->where('id', $this->signatoryId)->value('name'))->toBe('ผู้ลงนามทดสอบ');
})->with([
    'another office' => ['01'],
]);

test('FINANCE without an office has no signatories menu or page', function () {
    $user = signatoryUser('FINANCE', null);

    expect(\App\Support\AdminAccess::allows($user, 'signatories'))->toBeFalse()
        ->and(\App\Support\AdminAccess::allows($user, 'payslip'))->toBeTrue();

    $this->actingAs($user);
    expect(\Illuminate\Support\Facades\Blade::render('<x-layouts.app><p>เนื้อหา</p></x-layouts.app>'))
        ->not->toContain(route('admin.signatories'))
        ->toContain(route('admin.payslips.index'));

    $this->get(route('admin.signatories'))->assertForbidden();
    Livewire::actingAs($user)->test(Signatories::class)->assertForbidden();
});
