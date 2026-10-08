<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Title;
use App\Services\UserAccessService;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class UserManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';
    public string $officeFilter = '';
    public string $pendingName = '';

    #[Locked]
    public ?int $pendingUserId = null;

    #[Locked]
    public bool $pendingActive = false;

    public function boot(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'users'), 403);
        abort_unless(DB::table('users')->where('id', Auth::id())->where('role', 'ADMIN')->where('is_active', true)->exists(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedOfficeFilter(): void
    {
        $this->resetPage();
    }

    public function updateRole(int $userId, string $role, UserAccessService $service): void
    {
        $service->updateRole((int) Auth::id(), $userId, $role === '' ? null : $role);
        $this->resetErrorBag();
        $this->dispatch('user-access-saved', message: 'บันทึกสิทธิ์ผู้ใช้แล้ว');
    }

    public function updateOffice(int $userId, string $office, UserAccessService $service): void
    {
        $service->updateOffice((int) Auth::id(), $userId, $office === '' ? null : $office);
        $this->resetErrorBag();
        $this->dispatch('user-access-saved', message: 'บันทึกต้นสังกัดผู้ใช้แล้ว');
    }

    public function requestStatusChange(int $userId, bool $active): void
    {
        $user = DB::table('users')->where('id', $userId)->first(['id', 'name']);
        abort_unless($user, 404);
        $this->pendingUserId = (int) $user->id;
        $this->pendingName = (string) $user->name;
        $this->pendingActive = $active;
        $this->resetErrorBag();
        $this->dispatch('user-status-form-opened');
    }

    public function confirmStatusChange(UserAccessService $service): void
    {
        abort_unless($this->pendingUserId !== null, 404);
        $active = $this->pendingActive;
        $service->setActive((int) Auth::id(), $this->pendingUserId, $active);
        $this->cancelStatusChange();
        $this->dispatch('user-access-saved', message: $active ? 'เปิดการใช้งานบัญชีแล้ว' : 'ปิดการใช้งานบัญชีแล้ว');
    }

    public function cancelStatusChange(): void
    {
        $this->reset(['pendingUserId', 'pendingName', 'pendingActive']);
        $this->resetErrorBag();
        $this->dispatch('user-status-form-closed');
    }

    #[Title('จัดการผู้ใช้')]
    public function render()
    {
        $search = trim($this->search);

        return view('livewire.admin.user-management', [
            'users' => DB::table('users')
                ->select(['id', 'name', 'hname', 'role', 'office', 'is_active'])
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('hname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                }))
                ->when($this->roleFilter === 'none', fn ($query) => $query->whereNull('role'))
                ->when($this->roleFilter !== '' && $this->roleFilter !== 'none', fn ($query) => $query->where('role', $this->roleFilter))
                ->when($this->officeFilter === 'none', fn ($query) => $query->whereNull('office'))
                ->when($this->officeFilter !== '' && $this->officeFilter !== 'none', fn ($query) => $query->where('office', $this->officeFilter))
                ->orderBy('name')->orderBy('id')->paginate(20),
            'offices' => DB::table('person')
                ->whereNotNull('office_id')->where('office_id', '!=', '')
                ->select('office_id', DB::raw('MAX(office_name) as office_name'))
                ->groupBy('office_id')->orderBy('office_id')->get(),
        ]);
    }
}
