<?php

namespace App\Livewire\Admin;

use App\Services\MonthlyCompensationImportService;
use App\Services\MonthlyCompensationService;
use App\Support\AdminAccess;
use App\Support\CompensationItems;
use App\Support\OfficeScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Monthly compensation keyed by offices for the provincial health office's
 * withholding-tax certificate (tab of /admin/tax-certificates).
 */
class MonthlyCompensationManagement extends Component
{
    use WithFileUploads;
    use WithPagination;

    public const MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    public int $year;
    public int $month;
    public string $search = '';

    #[Locked]
    public ?int $editingId = null;

    public string $cid = '';
    public string $personName = '';
    public string $personSearch = '';

    /** @var array<string, string> */
    public array $amounts = [];
    public string $other_name = '';

    #[Locked]
    public ?int $pendingDeleteId = null;

    #[Locked]
    public ?string $pendingConfirmOffice = null;

    public $importFile;
    public array $importPreview = [];

    #[Locked]
    public ?string $importHash = null;

    public function boot(): void
    {
        $this->ensureAuthorized();
    }

    public function mount(): void
    {
        $this->year = now()->year + 543;
        $this->month = now()->month;
        $this->resetAmounts();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['year', 'month', 'search'], true)) {
            $this->resetPage();
        }
        if (in_array($property, ['year', 'month', 'importFile'], true)) {
            $this->clearImport();
        }
    }

    public function openCreate(): void
    {
        $this->ensureAuthorized();
        $this->resetForm();
        $this->dispatch('compensation-form-opened');
    }

    public function selectPerson(string $cid): void
    {
        $this->ensureAuthorized();
        abort_if($this->editingId !== null, 403);
        $person = $this->eligiblePeople()->where('cid', $cid)->first(['cid', 'pname', 'fname', 'lname']);
        abort_unless($person, 404);
        $this->cid = (string) $person->cid;
        $this->personName = self::fullName($person);
        $this->personSearch = '';
        $this->resetErrorBag('cid');
    }

    public function edit(int $id): void
    {
        $this->ensureAuthorized();
        $row = MonthlyCompensationService::scoped(Auth::user())->where('compensations.id', $id)
            ->first(['compensations.*', 'people.pname', 'people.fname', 'people.lname']);
        abort_unless($row, 404);

        $this->resetForm();
        $this->editingId = (int) $row->id;
        $this->cid = (string) $row->cid;
        $this->personName = self::fullName($row);
        $this->month = (int) $row->month;
        $this->year = (int) $row->income_year;
        foreach (CompensationItems::fields() as $field) {
            $this->amounts[$field] = (float) $row->{$field} == 0.0 ? '' : (string) $row->{$field};
        }
        $this->other_name = (string) ($row->other_name ?? '');
        $this->dispatch('compensation-form-opened');
    }

    public function save(MonthlyCompensationService $service): void
    {
        $this->ensureAuthorized();
        $this->validate([
            'cid' => ['required', 'digits:13'],
            'amounts.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'other_name' => ['nullable', 'string', 'max:255'],
        ], [
            'cid.*' => 'กรุณาเลือกบุคลากร',
            'amounts.*' => 'จำนวนเงินต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป',
        ]);

        try {
            $service->save(Auth::user(), $this->cid, $this->year, $this->month, [...$this->amounts, 'other_name' => $this->other_name]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field === 'values' ? 'amounts' : $field, $message);
                }
            }

            return;
        }

        $message = $this->editingId === null ? 'เพิ่มค่าตอบแทนรายเดือนแล้ว' : 'ปรับปรุงค่าตอบแทนรายเดือนแล้ว';
        $this->resetForm();
        $this->dispatch('compensation-form-closed');
        $this->dispatch('compensation-saved', message: $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->ensureAuthorized();
        abort_unless(MonthlyCompensationService::scoped(Auth::user())->where('compensations.id', $id)->exists(), 404);
        $this->pendingDeleteId = $id;
        $this->dispatch('compensation-delete-opened');
    }

    public function delete(MonthlyCompensationService $service): void
    {
        $this->ensureAuthorized();
        abort_unless($this->pendingDeleteId !== null, 404);
        try {
            $service->delete(Auth::user(), $this->pendingDeleteId);
        } catch (ValidationException $exception) {
            $this->addError('delete', collect($exception->errors())->flatten()->first());

            return;
        }
        $this->pendingDeleteId = null;
        $this->dispatch('compensation-delete-closed');
        $this->dispatch('compensation-saved', message: 'ลบค่าตอบแทนรายเดือนแล้ว');
    }

    public function prepareConfirmYear(string $officeId): void
    {
        $this->ensureAuthorized();
        $scope = OfficeScope::officeFor(Auth::user());
        abort_unless($scope === null || $scope === $officeId, 403);
        $this->pendingConfirmOffice = $officeId;
        $this->resetErrorBag('confirm');
        $this->dispatch('compensation-confirm-opened');
    }

    public function confirmYear(MonthlyCompensationService $service): void
    {
        $this->ensureAuthorized();
        abort_unless($this->pendingConfirmOffice !== null, 404);
        try {
            $service->confirm(Auth::user(), $this->year, $this->pendingConfirmOffice);
        } catch (ValidationException $exception) {
            $this->addError('confirm', collect($exception->errors())->flatten()->first());

            return;
        }
        $this->pendingConfirmOffice = null;
        $this->dispatch('compensation-confirm-closed');
        $this->dispatch('compensation-saved', message: "ยืนยันข้อมูลปี {$this->year} แล้ว");
    }

    public function previewImport(MonthlyCompensationImportService $importer): void
    {
        $this->ensureAuthorized();
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx', 'max:5120']], [
            'importFile.*' => 'กรุณาเลือกไฟล์ Excel .xlsx ขนาดไม่เกิน 5MB',
        ]);
        try {
            $preview = $importer->preview($this->importFile->getRealPath(), Auth::user(), $this->year, $this->month);
        } catch (RuntimeException $exception) {
            $this->clearImport();
            $this->addError('importFile', $exception->getMessage());

            return;
        }
        $this->importHash = $preview['file_hash'];
        $this->importPreview = [
            'insert' => $preview['insert_count'],
            'update' => $preview['update_count'],
            'empty' => $preview['empty_count'],
            'skipped' => array_slice($preview['skipped'], 0, 20),
            'skipped_count' => count($preview['skipped']),
            'errors' => array_slice($preview['errors'], 0, 20),
            'error_count' => count($preview['errors']),
        ];
    }

    public function confirmImport(MonthlyCompensationImportService $importer): void
    {
        $this->ensureAuthorized();
        abort_unless($this->importHash !== null && $this->importFile !== null && ($this->importPreview['error_count'] ?? 1) === 0, 404);
        try {
            $result = $importer->commit($this->importFile->getRealPath(), Auth::user(), $this->year, $this->month, $this->importHash);
        } catch (RuntimeException $exception) {
            $this->addError('importFile', $exception->getMessage());

            return;
        }
        $this->reset('importFile');
        $this->clearImport();
        $this->dispatch('compensation-saved', message: sprintf(
            'นำเข้าค่าตอบแทน %s %d แล้ว เพิ่ม %d ปรับปรุง %d ข้าม %d ไม่มียอด %d รายการ',
            self::MONTHS[$this->month], $this->year, $result['inserted'], $result['updated'], $result['skipped'], $result['empty'],
        ));
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->pendingDeleteId = null;
        $this->pendingConfirmOffice = null;
    }

    public function render()
    {
        $this->ensureAuthorized();
        $user = Auth::user();
        $search = trim($this->search);
        $personSearch = trim($this->personSearch);
        $summary = MonthlyCompensationService::yearSummary($this->year);
        $scope = OfficeScope::officeFor($user);

        return view('livewire.admin.monthly-compensation-management', [
            'rows' => MonthlyCompensationService::scoped($user)
                ->where('compensations.income_year', $this->year)
                ->where('compensations.month', $this->month)
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('compensations.cid', 'like', "%{$search}%")
                    ->orWhere('people.fname', 'like', "%{$search}%")
                    ->orWhere('people.lname', 'like', "%{$search}%")))
                ->orderBy('compensations.office_id')->orderBy('people.fname')
                ->select(['compensations.*', 'people.pname', 'people.fname', 'people.lname'])
                ->paginate(20),
            'items' => CompensationItems::ITEMS,
            'months' => self::MONTHS,
            'years' => range(now()->year + 543, now()->year + 543 - 4),
            'summary' => $scope === null ? $summary : $summary->where('office_id', $scope)->values(),
            'yearLocked' => MonthlyCompensationService::yearLocked($this->year),
            'pendingConfirmOfficeName' => OfficeScope::nameFor($this->pendingConfirmOffice),
            'officeNames' => DB::table('offices')->pluck('name', 'code'),
            'personResults' => $this->editingId === null && $this->cid === '' && mb_strlen($personSearch) >= 2
                ? $this->eligiblePeople()->where(fn ($query) => $query
                    ->where('cid', 'like', "%{$personSearch}%")
                    ->orWhere('fname', 'like', "%{$personSearch}%")
                    ->orWhere('lname', 'like', "%{$personSearch}%"))
                    ->orderBy('fname')->limit(10)->get(['cid', 'pname', 'fname', 'lname', 'type_name'])
                : collect(),
            'selectedType' => $this->cid === '' ? null : DB::table('person')->where('cid', $this->cid)->value('type_id'),
        ]);
    }

    public static function fullName(object $person): string
    {
        return trim(implode(' ', array_filter([$person->pname ?? null, $person->fname ?? null, $person->lname ?? null])));
    }

    private function eligiblePeople()
    {
        return OfficeScope::apply(DB::table('person'), Auth::user())->whereIn('type_id', CompensationItems::TYPES);
    }

    private function resetAmounts(): void
    {
        $this->amounts = array_fill_keys(CompensationItems::fields(), '');
        $this->other_name = '';
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'cid', 'personName', 'personSearch']);
        $this->resetAmounts();
        $this->resetErrorBag();
    }

    private function clearImport(): void
    {
        $this->importPreview = [];
        $this->importHash = null;
        $this->resetErrorBag('importFile');
    }

    private function ensureAuthorized(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'tax-certificates'), 403);
    }
}
