<?php

namespace App\Livewire\Admin;

use App\Support\AdminAccess;
use App\Services\PersonnelImportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;

class PersonnelManagement extends Component
{
    public function boot(): void
    {
        $this->ensureAdministrator();
    }
    use WithFileUploads;
    use WithPagination;

    private const PERSONNEL_TYPES = [
        1 => 'ข้าราชการ',
        2 => 'ลูกจ้างประจำ',
        3 => 'พนักงานราชการ',
        4 => 'พนักงานกระทรวง',
    ];

    public string $search = '';
    public string $officeFilter = '';

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

    public mixed $importFile = null;
    public ?array $importResult = null;

    public function mount(): void
    {
        $this->ensureAdministrator();
    }

    protected function rules(): array
    {
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
            'office_id' => ['nullable', 'string', 'max:2', 'exists:person,office_id'],
            'office_name' => ['nullable', 'string', 'max:255'],
            'employment_started_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    protected array $messages = [
        'cid.required' => 'กรุณาระบุเลขประจำตัวประชาชน',
        'cid.digits' => 'เลขประจำตัวประชาชนต้องมี 13 หลัก',
        'cid.unique' => 'มีบุคลากรเลขประจำตัวประชาชนนี้ในระบบแล้ว',
        'fname.required' => 'กรุณาระบุชื่อ',
        'lname.required' => 'กรุณาระบุนามสกุล',
        'employment_started_at.date' => 'รูปแบบวันเริ่มงานไม่ถูกต้อง',
        'employment_started_at.before_or_equal' => 'วันเริ่มงานต้องไม่เกินวันนี้',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOfficeFilter(): void
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
        $person = DB::table('person')->where('cid', $cid)->first();
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
        $this->resetErrorBag();
        $this->dispatch('personnel-form-opened');
    }

    public function save(): void
    {
        $this->ensureAdministrator();
        $validated = $this->validate();
        $data = $this->normalisePersonnel($validated);

        if ($this->editingCid !== null) {
            DB::table('person')->where('cid', $this->editingCid)->update($data);
            $message = 'ปรับปรุงข้อมูลบุคลากรแล้ว';
        } else {
            DB::table('person')->insert($data);
            $message = 'เพิ่มข้อมูลบุคลากรแล้ว';
        }

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
        abort_unless(DB::table('person')->where('cid', $cid)->exists(), 404);

        $this->pendingDeletionCid = $cid;
        $this->dispatch('personnel-delete-confirmation-opened');
    }

    public function deletePersonnel(): void
    {
        $this->ensureAdministrator();

        if ($this->pendingDeletionCid === null) {
            return;
        }

        DB::table('person')->where('cid', $this->pendingDeletionCid)->delete();
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
            $this->importResult = $importService->import($this->importFile->getRealPath());
            $this->importFile = null;
        } catch (RuntimeException $exception) {
            $this->addError('importFile', $exception->getMessage());
        }
    }

    public function render()
    {
        $this->ensureAdministrator();
        $search = trim($this->search);
        $officeFilter = trim($this->officeFilter);

        return view('livewire.admin.personnel-management', [
            'personnel' => DB::table('person')
                ->when($officeFilter !== '', fn ($query) => $query->where('office_id', $officeFilter))
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
            'offices' => DB::table('person')
                ->select('office_id', DB::raw('MAX(office_name) as office_name'))
                ->whereNotNull('office_id')
                ->where('office_id', '!=', '')
                ->groupBy('office_id')
                ->orderBy('office_id')
                ->get(),
            'personnelTypes' => self::PERSONNEL_TYPES,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function normalisePersonnel(array $data): array
    {
        foreach (['pname', 'employment_started_at'] as $field) {
            $data[$field] = $data[$field] === '' ? null : $data[$field];
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

        $data['office_name'] = DB::table('person')
            ->where('office_id', $data['office_id'])
            ->value('office_name');

        return $data;
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingCid', 'cid', 'pname', 'fname', 'lname', 'type_id',
            'type_name', 'office_id', 'office_name', 'employment_started_at',
        ]);
        $this->resetErrorBag();
    }

    private function ensureAdministrator(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'personnel'), 403);
    }
}
