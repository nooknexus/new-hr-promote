<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Title;
use App\Services\UserAccessService;
use App\Support\AdminAccess;
use App\Support\OfficeScope;
use App\Support\PersonnelTypeScope;
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

    #[Locked]
    public ?int $typesUserId = null;

    public string $typesUserName = '';

    /** @var list<string> */
    public array $selectedTypes = [];

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

    public function openPersonnelTypes(int $userId): void
    {
        $user = DB::table('users')->where('id', $userId)->first(['id', 'name']);
        abort_unless($user, 404);
        $this->typesUserId = (int) $user->id;
        $this->typesUserName = (string) $user->name;
        $this->selectedTypes = array_map('strval', PersonnelTypeScope::assignedTo((int) $user->id));
        $this->resetErrorBag();
        $this->dispatch('user-types-form-opened');
    }

    public function savePersonnelTypes(UserAccessService $service): void
    {
        abort_unless($this->typesUserId !== null, 404);
        $service->updatePersonnelTypes((int) Auth::id(), $this->typesUserId, $this->selectedTypes);
        $this->cancelPersonnelTypes();
        $this->dispatch('user-access-saved', message: 'บันทึกประเภทการจ้างที่รับผิดชอบแล้ว');
    }

    public function cancelPersonnelTypes(): void
    {
        $this->reset(['typesUserId', 'typesUserName', 'selectedTypes']);
        $this->resetErrorBag();
        $this->dispatch('user-types-form-closed');
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
        $users = DB::table('users')
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
                ->orderBy('name')->orderBy('id')->paginate(20);

        return view('livewire.admin.user-management', [
            'users' => $users,
            'personnelTypes' => PersonnelTypeScope::TYPES,
            'assignedTypes' => DB::table('user_personnel_types')
                ->whereIn('user_id', collect($users->items())->where('role', 'PROMOTE')->pluck('id'))
                ->orderBy('type_id')->get()->groupBy('user_id')
                ->map(fn ($rows) => $rows->pluck('type_id')->map(fn ($id) => (int) $id)->all()),
            // Inactive offices stay listed so existing assignments still display.
            'offices' => OfficeScope::options(Auth::user(), includeInactive: true),
        ]);
    }
}
