<?php

namespace App\Livewire\Admin;

use App\Support\AdminAccess;
use App\Support\OfficeScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Office (ต้นสังกัด) registry. Offices are never deleted: deactivating one
 * hides it from forms while existing personnel and users keep it.
 */
class OfficeManagement extends Component
{
    public string $search = '';

    #[Locked]
    public ?string $editingCode = null;

    #[Locked]
    public ?string $pendingToggleCode = null;

    public string $code = '';
    public string $name = '';
    public int $sort_order = 0;

    public function boot(): void
    {
        $this->ensureAllowed();
    }

    public function openCreate(): void
    {
        $this->ensureAllowed();
        $this->resetForm();
        $this->dispatch('office-form-opened');
    }

    public function edit(string $code): void
    {
        $this->ensureAllowed();
        $office = DB::table('offices')->where('code', $code)->first();
        abort_unless($office, 404);

        $this->resetForm();
        $this->editingCode = (string) $office->code;
        $this->code = (string) $office->code;
        $this->name = (string) $office->name;
        $this->sort_order = (int) $office->sort_order;
        $this->dispatch('office-form-opened');
    }

    public function save(): void
    {
        $this->ensureAllowed();
        $this->code = trim($this->code);
        $this->name = trim($this->name);

        $validated = $this->validate([
            'code' => $this->editingCode === null
                ? ['required', 'digits:2', Rule::unique('offices', 'code')]
                : ['required'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ], [
            'code.required' => 'กรุณาระบุรหัสต้นสังกัด',
            'code.digits' => 'รหัสต้นสังกัดต้องเป็นตัวเลข 2 หลัก',
            'code.unique' => 'มีรหัสต้นสังกัดนี้แล้ว',
            'name.required' => 'กรุณาระบุชื่อต้นสังกัด',
            'name.max' => 'ชื่อต้นสังกัดต้องไม่เกิน 255 ตัวอักษร',
            'sort_order.*' => 'ลำดับการแสดงต้องเป็นตัวเลข 0 ขึ้นไป',
        ]);

        if ($this->editingCode === null) {
            DB::table('offices')->insert([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'sort_order' => $validated['sort_order'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $message = 'เพิ่มต้นสังกัดแล้ว';
        } else {
            // The code is the identity used by person and users, so only the
            // name and order change; person.office_name is a synced copy.
            DB::transaction(function () use ($validated): void {
                $updated = DB::table('offices')->where('code', $this->editingCode)->update([
                    'name' => $validated['name'],
                    'sort_order' => $validated['sort_order'],
                    'updated_at' => now(),
                ]);
                abort_if($updated === 0 && ! DB::table('offices')->where('code', $this->editingCode)->exists(), 404);

                DB::table('person')->where('office_id', $this->editingCode)->update(['office_name' => $validated['name']]);
            });
            $message = 'ปรับปรุงต้นสังกัดแล้ว';
        }

        $this->resetForm();
        $this->dispatch('office-saved', message: $message);
    }

    public function closeForm(): void
    {
        $this->resetForm();
    }

    public function prepareToggle(string $code): void
    {
        $this->ensureAllowed();
        abort_if($code === OfficeScope::ALL_OFFICES, 403);
        abort_unless(DB::table('offices')->where('code', $code)->exists(), 404);

        $this->pendingToggleCode = $code;
        $this->dispatch('office-toggle-opened');
    }

    public function toggleActive(): void
    {
        $this->ensureAllowed();
        abort_unless($this->pendingToggleCode !== null, 404);
        abort_if($this->pendingToggleCode === OfficeScope::ALL_OFFICES, 403);

        $office = DB::table('offices')->where('code', $this->pendingToggleCode)->first();
        abort_unless($office, 404);

        DB::table('offices')->where('code', $office->code)->update([
            'is_active' => ! $office->is_active,
            'updated_at' => now(),
        ]);

        $this->pendingToggleCode = null;
        $this->dispatch('office-saved', message: $office->is_active ? 'ปิดใช้งานต้นสังกัดแล้ว' : 'เปิดใช้งานต้นสังกัดแล้ว');
    }

    public function cancelToggle(): void
    {
        $this->pendingToggleCode = null;
    }

    #[Title('จัดการต้นสังกัด')]
    public function render()
    {
        $this->ensureAllowed();
        $search = trim($this->search);
        $personnelCounts = DB::table('person')->whereNotNull('office_id')->groupBy('office_id')
            ->selectRaw('office_id, COUNT(*) AS total')->pluck('total', 'office_id');
        $userCounts = DB::table('users')->whereNotNull('office')->groupBy('office')
            ->selectRaw('office, COUNT(*) AS total')->pluck('total', 'office');

        return view('livewire.admin.office-management', [
            'offices' => DB::table('offices')
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")))
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
            'personnelCounts' => $personnelCounts,
            'userCounts' => $userCounts,
            'pendingOffice' => $this->pendingToggleCode === null
                ? null
                : DB::table('offices')->where('code', $this->pendingToggleCode)->first(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['editingCode', 'code', 'name', 'sort_order']);
        $this->resetErrorBag();
    }

    private function ensureAllowed(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'offices'), 403);
    }
}
