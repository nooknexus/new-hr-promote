<?php

namespace App\Livewire\Admin;

use App\Services\UserAccessService;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class UserManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';
    public string $officeFilter = '';
    public string $role = '';
    public string $office = '';
    public string $editingName = '';

    #[Locked]
    public ?int $editingId = null;

    public function boot(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'users'), 403);
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

    public function edit(int $userId): void
    {
        $user = DB::table('users')->where('id', $userId)->first(['id', 'name', 'role', 'office']);
        abort_unless($user, 404);

        $this->editingId = (int) $user->id;
        $this->editingName = (string) $user->name;
        $this->role = (string) ($user->role ?? '');
        $this->office = (string) ($user->office ?? '');
        $this->resetErrorBag();
        $this->dispatch('user-access-form-opened');
    }

    public function save(UserAccessService $service): void
    {
        abort_unless($this->editingId !== null, 404);
        $data = $this->validate([
            'role' => ['nullable', Rule::in(['ADMIN', 'LEAVE', 'PROMOTE', 'FINANCE'])],
            'office' => ['nullable', 'string', 'max:2', Rule::exists('person', 'office_id')],
        ]);

        $service->update(
            (int) Auth::id(),
            $this->editingId,
            $data['role'] ?: null,
            $data['office'] ?: null,
        );
        $this->closeForm();
        $this->dispatch('user-access-saved', message: 'บันทึกสิทธิ์ผู้ใช้แล้ว');
    }

    public function closeForm(): void
    {
        $this->reset(['editingId', 'editingName', 'role', 'office']);
        $this->resetErrorBag();
        $this->dispatch('user-access-form-closed');
    }

    public function render()
    {
        $search = trim($this->search);

        return view('livewire.admin.user-management', [
            'users' => DB::table('users')
                ->select(['id', 'name', 'email', 'role', 'office'])
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
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
