<?php

use App\Livewire\Profile\Index;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('name_en')->nullable();
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->date('birthdate')->nullable();
        $table->text('address')->nullable();
        foreach (['pid', 'thaid_pid', 'cid_hash', 'provider_id', 'account_id', 'hcode', 'hname', 'role', 'office', 'password'] as $field) {
            $table->string($field)->nullable();
        }
        $table->timestamps();
    });
    $this->owner = User::forceCreate([
        'name' => 'ชื่อเดิม', 'name_en' => 'Old Name', 'email' => 'owner@example.test',
        'email_verified_at' => now(), 'address' => 'ที่อยู่เดิม', 'birthdate' => '1990-01-01',
        'pid' => '1234567890123', 'thaid_pid' => '1234567890123', 'cid_hash' => 'identity-hash',
        'provider_id' => 'provider-owner', 'account_id' => 'account-owner', 'hcode' => '00001',
        'hname' => 'หน่วยบริการเดิม', 'role' => 'LEAVE', 'office' => '01', 'password' => 'old-password',
    ]);
    $this->other = User::forceCreate(['name' => 'อีกคน', 'email' => 'other@example.test']);
    $this->actingAs($this->owner);
});

// TDD_REQUIRED: yes; observable seams are modal opening, persisted owner fields, validation, and actor isolation.
test('the profile edit button opens a prefilled modal on the profile page', function () {
    Livewire::test(Index::class)
        ->assertSeeHtml('wire:click="openEdit"')
        ->assertSeeHtml('id="profileEditModal"')
        ->assertDontSee('อัพเดทข้อมูลจาก ThaID')
        ->assertDontSee('อัพเดทข้อมูลจาก Provider ID')
        ->call('openEdit')
        ->assertSet('editName', 'ชื่อเดิม')
        ->assertSet('editNameEn', 'Old Name')
        ->assertSet('editEmail', 'owner@example.test')
        ->assertSet('editAddress', 'ที่อยู่เดิม')
        ->assertDispatched('profile-edit-opened');
});

test('saving a profile updates only owner editable fields and emits modal success without redirect', function () {
    $protected = DB::table('users')->where('id', $this->owner->id)->first();
    $otherBefore = DB::table('users')->where('id', $this->other->id)->first();
    Livewire::test(Index::class)->call('openEdit')
        ->set('editName', '  ชื่อใหม่  ')->set('editNameEn', ' New Name ')
        ->set('editEmail', ' new@example.test ')->set('editAddress', ' ที่อยู่ใหม่ ')
        ->call('saveProfile')->assertHasNoErrors()
        ->assertDispatched('profile-saved', message: 'บันทึกข้อมูลส่วนตัวแล้ว')
        ->assertSet('editingUserId', null)->assertSee('ชื่อใหม่')->assertSee('new@example.test')
        ->assertNoRedirect();
    $saved = DB::table('users')->where('id', $this->owner->id)->first();
    expect($saved->name)->toBe('ชื่อใหม่')->and($saved->name_en)->toBe('New Name')
        ->and($saved->email)->toBe('new@example.test')->and($saved->address)->toBe('ที่อยู่ใหม่')
        ->and($saved->email_verified_at)->toBeNull();
    foreach (['pid', 'thaid_pid', 'cid_hash', 'provider_id', 'account_id', 'hcode', 'hname', 'birthdate', 'role', 'office', 'password'] as $field) {
        expect($saved->{$field})->toBe($protected->{$field});
    }
    expect(DB::table('users')->where('id', $this->other->id)->first())->toEqual($otherBefore);
});

test('unchanged email stays verified and optional profile fields may be cleared', function () {
    $verified = $this->owner->email_verified_at;
    Livewire::test(Index::class)->call('openEdit')
        ->set('editNameEn', '')->set('editAddress', '')
        ->call('saveProfile')->assertHasNoErrors();
    $saved = $this->owner->fresh();
    expect($saved->email_verified_at->equalTo($verified))->toBeTrue()
        ->and($saved->name_en)->toBeNull()->and($saved->address)->toBeNull();
});

test('invalid profile input is rejected without writing or closing the modal', function (string $field, string $value) {
    $before = DB::table('users')->where('id', $this->owner->id)->first();
    Livewire::test(Index::class)->call('openEdit')->set($field, $value)
        ->call('saveProfile')->assertHasErrors($field)->assertNotDispatched('profile-saved');
    expect(DB::table('users')->where('id', $this->owner->id)->first())->toEqual($before);
})->with([
    ['editName', '   '], ['editName', str_repeat('a', 256)],
    ['editNameEn', str_repeat('a', 256)], ['editEmail', 'invalid-email'],
    ['editEmail', 'other@example.test'], ['editAddress', str_repeat('a', 5001)],
]);

test('cancelling and reopening reloads persisted values and clears errors', function () {
    Livewire::test(Index::class)->call('openEdit')->set('editName', '')
        ->call('saveProfile')->assertHasErrors('editName')
        ->call('closeEdit')->assertSet('editingUserId', null)
        ->call('openEdit')->assertHasNoErrors()->assertSet('editName', 'ชื่อเดิม');
    expect($this->owner->fresh()->name)->toBe('ชื่อเดิม');
});

test('a session change cannot save another owners already opened form', function () {
    $component = Livewire::test(Index::class)->call('openEdit')->set('editName', 'ข้อมูลของคนแรก');
    Auth::setUser($this->other);
    $component->call('saveProfile')->assertForbidden();
    expect($this->owner->fresh()->name)->toBe('ชื่อเดิม')->and($this->other->fresh()->name)->toBe('อีกคน');
});

test('saving without an opened edit form is forbidden', function () {
    Livewire::test(Index::class)->set('editName', 'ปลอม')->call('saveProfile')->assertForbidden();
    expect($this->owner->fresh()->name)->toBe('ชื่อเดิม');
});

test('guests cannot open the profile component or save a stale form', function () {
    $component = Livewire::test(Index::class)->call('openEdit');
    Auth::forgetGuards();
    $component->call('saveProfile')->assertForbidden();
    Livewire::test(Index::class)->assertForbidden();
});

test('the profile route redirects a guest to login', function () {
    Auth::forgetGuards();
    $this->get('/profile')->assertRedirect(route('login'));
});

test('legacy edit links return to the owners profile and reject other user ids', function () {
    $this->get('/profile/'.$this->owner->id.'/edit')->assertRedirect(route('profile.index'));
    $this->get('/profile/'.$this->other->id.'/edit')->assertForbidden();
});

test('the edit target cannot be replaced by a client payload', function () {
    $component = Livewire::test(Index::class)->call('openEdit');
    expect(fn () => $component->set('editingUserId', $this->other->id))
        ->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
    expect($this->owner->fresh()->name)->toBe('ชื่อเดิม')->and($this->other->fresh()->name)->toBe('อีกคน');
});

test('the rendered profile events open the modal and close it before showing the saved toast', function () {
    $component = Livewire::test(Index::class);
    $component->assertSee('การแก้ไขนี้มีผลเฉพาะครั้งนี้เท่านั้น เมื่อท่านเข้าสู่ระบบใหม่ ข้อมูลจะถูกแทนที่ด้วยข้อมูลจากแอพฯภาครัฐ ให้ท่านอัพเดทข้อมูลจากต้นทางให้เป็นปัจจุบัน');
    $scripts = implode("\n", $component->effects['scripts']);
    $script = preg_replace('/<\/?script[^>]*>/', '', $scripts);
    $runner = <<<'JS'
const handlers = {};
const modal = { open: false };
const notice = { open: false };
let toastMessage;
globalThis.$wire = { on: (name, handler) => { handlers[name] = handler; } };
globalThis.document = { getElementById: (id) => {
    if (id === 'profileEditModal') return modal;
    if (id === 'profileEditNoticeModal') return notice;
    throw Error('wrong modal');
} };
globalThis.window = {
    portalModal: {
        show: (id) => { document.getElementById(id).open = true; },
        hide: (target) => { if (target !== modal) throw Error('wrong target'); target.open = false; },
    },
    portalToast: { show: (message) => {
        if (modal.open) throw Error('toast shown before modal closed');
        toastMessage = message;
    } },
};
JS;
    $runner .= "\n".$script;
    $runner .= <<<'JS'
handlers['profile-edit-opened']();
if (!modal.open) throw Error('modal did not open');
handlers['profile-saved']({ message: 'saved' });
if (modal.open || toastMessage !== 'saved') throw Error('save feedback missing');
if (!notice.open) throw Error('government source notice not shown after save');
console.log('profile-modal-events-pass');
JS;
    $process = new \Symfony\Component\Process\Process(['node', '--eval', $runner], base_path());
    $process->run();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getOutput())->toContain('profile-modal-events-pass');
});


test('removed profile refresh actions cannot be invoked directly', function (string $action) {
    \Illuminate\Support\Facades\Http::preventStrayRequests();
    $component = Livewire::test(Index::class);
    expect(fn () => $component->call($action))
        ->toThrow(\Livewire\Exceptions\MethodNotFoundException::class);
})->with(['updateFromThaID', 'updateFromProviderID']);
