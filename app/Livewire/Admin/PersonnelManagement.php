<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Title;
use App\Support\AdminAccess;
use App\Support\OfficeScope;
use App\Support\PersonnelTypeScope;
use App\Services\PersonnelImportService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;

class PersonnelManagement extends Component
{
    // Personnel registration includes types outside salary-promotion permissions.
    private const PERSONNEL_TYPES = PersonnelTypeScope::TYPES + [5 => 'ลูกจ้างชั่วคราว'];

    public function boot(): void
    {
        $this->ensureAdministrator();
    }
    use WithFileUploads;
    use WithPagination;

    public string $search = '';
    public string $officeFilter = '';
    public string $typeFilter = '';

    public ?string $editingCid = null;
    public ?string $pendingDeletionCid = null;

    public string $cid = '';
    public string $pname = '';
    public string $fname = '';
    public string $lname = '';
    public ?int $type_id = null;
    public string $type_name = '';
    public string $office_id = '';
    public string $office_name = '';
    public ?string $employment_started_at = null;
    public string $position = '';
    public string $position_level = '';
    public string $no_position = '';

    public mixed $importFile = null;
    public ?array $importResult = null;

    public function mount(): void
    {
        $this->ensureAdministrator();
    }

    protected function rules(): array
    {
        $scope = $this->officeScope();

        return [
            'cid' => [
                'required',
                'digits:13',
                Rule::unique('person', 'cid')->ignore($this->editingCid, 'cid'),
            ],
            'pname' => ['nullable', 'string', 'max:255'],
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['required', 'string', 'max:255'],
            'type_id' => ['nullable', 'integer', Rule::in(array_keys(self::PERSONNEL_TYPES))],
            'type_name' => ['nullable', 'string', 'max:255'],
            'office_id' => $scope === null
                ? ['nullable', 'string', Rule::in($this->formOffices()->pluck('office_id')->all())]
                : ['required', 'string', Rule::in([$scope])],
            'office_name' => ['nullable', 'string', 'max:255'],
            'employment_started_at' => ['nullable', 'date', 'before_or_equal:today'],
            'position' => ['nullable', 'string', 'max:255'],
            'position_level' => ['nullable', 'string', 'max:255'],
            'no_position' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected array $messages = [
        'cid.required' => 'กรุณาระบุเลขประจำตัวประชาชน',
        'cid.digits' => 'เลขประจำตัวประชาชนต้องมี 13 หลัก',
        'cid.unique' => 'มีบุคลากรเลขประจำตัวประชาชนนี้ในระบบแล้ว',
        'office_id.required' => 'กรุณาเลือกต้นสังกัด',
        'office_id.in' => 'เลือกได้เฉพาะต้นสังกัดที่เปิดใช้งานและอยู่ในสิทธิ์ของท่าน',
        'fname.required' => 'กรุณาระบุชื่อ',
        'lname.required' => 'กรุณาระบุนามสกุล',
        'employment_started_at.date' => 'รูปแบบวันเริ่มงานไม่ถูกต้อง',
        'employment_started_at.before_or_equal' => 'วันเริ่มงานต้องไม่เกินวันนี้',
        'position.max' => 'ตำแหน่งต้องไม่เกิน 255 ตัวอักษร',
        'position_level.max' => 'ระดับต้องไม่เกิน 255 ตัวอักษร',
        'no_position.max' => 'เลขที่ตำแหน่งต้องไม่เกิน 255 ตัวอักษร',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOfficeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->ensureAdministrator();
        $this->resetForm();
        $this->dispatch('personnel-form-opened');
    }

    public function edit(string $cid): void
    {
        $this->ensureAdministrator();
        $person = $this->scopedPersonnel()->where('cid', $cid)->first();
        abort_unless($person, 404);

        $this->editingCid = $person->cid;
        $this->cid = $person->cid;
        $this->pname = $person->pname ?? '';
        $this->fname = $person->fname ?? '';
        $this->lname = $person->lname ?? '';
        $this->type_id = $person->type_id === null ? null : (int) $person->type_id;
        $this->type_name = $person->type_name ?? '';
        $this->office_id = $person->office_id ?? '';
        $this->office_name = $person->office_name ?? '';
        $this->employment_started_at = $person->employment_started_at;
        $this->position = $person->position ?? '';
        $this->position_level = $person->position_level ?? '';
        $this->no_position = $person->no_position ?? '';
        $this->resetErrorBag();
        $this->dispatch('personnel-form-opened');
    }

    public function save(): void
    {
        $this->ensureAdministrator();
        $validated = $this->validate();
        $data = $this->normalisePersonnel($validated);

        $message = DB::transaction(function () use ($data): string {
            if ($this->editingCid !== null) {
                $previousNoPosition = $this->scopedPersonnel()->where('cid', $this->editingCid)->lockForUpdate()->first(['no_position']);
                abort_unless($previousNoPosition, 404);
                $this->scopedPersonnel()->where('cid', $this->editingCid)->update($data);
                $this->syncUserPositionNumber($data['cid'], $previousNoPosition->no_position, $data['no_position']);

                return 'ปรับปรุงข้อมูลบุคลากรแล้ว';
            }

            DB::table('person')->insert($data);
            $this->syncUserPositionNumber($data['cid'], null, $data['no_position']);

            return 'เพิ่มข้อมูลบุคลากรแล้ว';
        });

        $this->resetForm();
        $this->dispatch('personnel-saved', message: $message);
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->dispatch('personnel-form-closed');
    }

    public function prepareDelete(string $cid): void
    {
        $this->ensureAdministrator();
        abort_unless($this->scopedPersonnel()->where('cid', $cid)->exists(), 404);

        $this->pendingDeletionCid = $cid;
        $this->dispatch('personnel-delete-confirmation-opened');
    }

    public function deletePersonnel(): void
    {
        $this->ensureAdministrator();

        if ($this->pendingDeletionCid === null) {
            return;
        }

        abort_unless($this->scopedPersonnel()->where('cid', $this->pendingDeletionCid)->exists(), 404);
        $this->scopedPersonnel()->where('cid', $this->pendingDeletionCid)->delete();
        $this->pendingDeletionCid = null;
        session()->flash('personnel-status', 'ลบข้อมูลบุคลากรแล้ว');
        $this->dispatch('personnel-delete-confirmation-closed');
    }

    public function cancelDelete(): void
    {
        $this->pendingDeletionCid = null;
        $this->dispatch('personnel-delete-confirmation-closed');
    }

    public function importExcel(PersonnelImportService $importService): void
    {
        $this->ensureAdministrator();
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ], [
            'importFile.required' => 'กรุณาเลือกไฟล์ Excel ก่อนนำเข้า',
            'importFile.mimes' => 'รองรับเฉพาะไฟล์ .xlsx',
            'importFile.max' => 'ไฟล์ต้องมีขนาดไม่เกิน 5MB',
        ]);

        try {
            $this->importResult = $importService->import($this->importFile->getRealPath(), $this->officeScope());
            $this->importFile = null;
        } catch (RuntimeException $exception) {
            $this->addError('importFile', $exception->getMessage());
        }
    }

    #[Title('จัดการข้อมูลบุคลากร')]
    public function render()
    {
        $this->ensureAdministrator();
        $search = trim($this->search);
        $officeFilter = trim($this->officeFilter);
        $typeFilter = trim($this->typeFilter);

        return view('livewire.admin.personnel-management', [
            'personnel' => $this->scopedPersonnel()
                ->when($officeFilter !== '', fn ($query) => $query->where('office_id', $officeFilter))
                ->when(isset(self::PERSONNEL_TYPES[(int) $typeFilter]), fn ($query) => $query->where('type_id', (int) $typeFilter))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('cid', 'like', "%{$search}%")
                            ->orWhere('fname', 'like', "%{$search}%")
                            ->orWhere('lname', 'like', "%{$search}%")
                            ->orWhere('type_name', 'like', "%{$search}%")
                            ->orWhere('office_name', 'like', "%{$search}%");
                    });
                })
                ->orderBy('fname')
                ->orderBy('lname')
                ->paginate(20),
            'offices' => OfficeScope::options(Auth::user(), includeInactive: true),
            'formOffices' => $this->formOffices(),
            'personnelTypes' => self::PERSONNEL_TYPES,
            'positionLevels' => array_keys(config('salary_ceilings.1', [])),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function normalisePersonnel(array $data): array
    {
        foreach (['pname', 'employment_started_at'] as $field) {
            $data[$field] = $data[$field] === '' ? null : $data[$field];
        }
        foreach (['position', 'position_level', 'no_position'] as $field) {
            $data[$field] = trim((string) $data[$field]) === '' ? null : trim($data[$field]);
        }

        $data['type_id'] = $data['type_id'] === null ? null : (int) $data['type_id'];
        $data['type_name'] = $data['type_id'] === null
            ? null
            : self::PERSONNEL_TYPES[$data['type_id']];

        if ($data['office_id'] === null || $data['office_id'] === '') {
            $data['office_id'] = null;
            $data['office_name'] = null;

            return $data;
        }

        // offices is the source of truth; person.office_name is a synced copy.
        $data['office_name'] = OfficeScope::nameFor($data['office_id']);

        return $data;
    }

    /**
     * Payslips read the position number from users.no_position, so keep the
     * linked account in step when the number changes here, as the salary
     * promotion import does. An unchanged number never touches the account,
     * and an ambiguous link (several accounts for one CID) is left alone.
     */
    private function syncUserPositionNumber(string $cid, ?string $previous, ?string $current): void
    {
        if ($previous === $current) {
            return;
        }

        $users = DB::table('users')->where('pid', $cid)->orWhere('thaid_pid', $cid)->limit(2)->pluck('id');
        if ($users->count() === 1) {
            DB::table('users')->where('id', $users->first())->update(['no_position' => $current]);
        }
    }

    /**
     * Offices selectable in the add/edit modal: the active offices the user may
     * manage, plus the current office of the person being edited even when it
     * has since been deactivated, so editing other fields never forces a move.
     */
    private function formOffices(): Collection
    {
        $offices = OfficeScope::options(Auth::user());

        if ($this->editingCid === null) {
            return $offices;
        }

        $currentOffice = (string) $this->scopedPersonnel()->where('cid', $this->editingCid)->value('office_id');
        if ($currentOffice === '' || $offices->contains('office_id', $currentOffice)) {
            return $offices;
        }

        $inactive = OfficeScope::options(Auth::user(), includeInactive: true)->firstWhere('office_id', $currentOffice);

        return $inactive === null ? $offices : $offices->push($inactive);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingCid', 'cid', 'pname', 'fname', 'lname', 'type_id',
            'type_name', 'office_id', 'office_name', 'employment_started_at',
            'position', 'position_level', 'no_position',
        ]);
        $this->resetErrorBag();
    }

    private function officeScope(): ?string
    {
        return OfficeScope::officeFor(Auth::user());
    }

    private function scopedPersonnel(): Builder
    {
        return OfficeScope::apply(DB::table('person'), Auth::user());
    }

    private function ensureAdministrator(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'personnel'), 403);
    }
}
