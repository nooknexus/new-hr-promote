<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Title;
use App\Services\TaxCertificateTxtImportService;
use App\Support\AdminAccess;
use App\Support\OfficeScope;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class TaxCertificateManagement extends Component
{
    use WithFileUploads;
    use WithPagination;

    /** @var array<string, string> */
    private const INCOME_TYPES = [
        '1' => 'ภ.ง.ด.1ก',
        '2' => 'ภ.ง.ด.1ก พิเศษ',
        '3' => 'ภ.ง.ด.2',
        '4' => 'ภ.ง.ด.3',
        '5' => 'ภ.ง.ด.2ก',
        '6' => 'ภ.ง.ด.3ก',
        '7' => 'ภ.ง.ด.53',
    ];

    /** @var array<string, string> */
    private const OUTCOME_TYPES = [
        '1' => 'กบข.',
        '2' => 'กสจ.',
    ];

    /** @var array<string, string> */
    private const TAX_TYPES = [
        '1' => 'หัก ณ ที่จ่าย',
        '2' => 'ออกให้ตลอดไป',
        '3' => 'ออกให้ครั้งเดียว',
        '4' => 'อื่นๆ',
    ];

    /** @var array<int, string> */
    private const MONEY_FIELDS = [
        'income_money',
        'withholding_tax',
        'total_money',
        'sumTaxAmount',
        'outcomeAmount',
    ];

    /** @var array<int, string> */
    private const NULLABLE_TEXT_FIELDS = [
        'department_name',
        'department_tax_id',
        'department_addr',
        'tax_id',
        'fullname',
        'addr',
        'running_code2',
        'sumTaxText',
    ];

    /** @var array<int, string> */
    private const FORM_FIELDS = [
        'cid',
        'income_year',
        'running_code1',
        'department_name',
        'department_tax_id',
        'department_addr',
        'tax_id',
        'fullname',
        'addr',
        'running_code2',
        'income_type_id',
        'income_money',
        'withholding_tax',
        'total_money',
        'sumTaxAmount',
        'sumTaxText',
        'outcomeType',
        'outcomeAmount',
        'tax_type_id',
        'date_report',
    ];

    public string $yearFilter = '';

    public string $cidFilter = '';

    public string $nameFilter = '';

    public string $cid = '';

    public string $income_year = '';

    public string $running_code1 = '';

    public string $department_name = '';

    public string $department_tax_id = '';

    public string $department_addr = '';

    public string $tax_id = '';

    public string $fullname = '';

    public string $addr = '';

    public string $running_code2 = '';

    public string $income_type_id = '1';

    public string $income_money = '';

    public string $withholding_tax = '';

    public string $total_money = '';

    public string $sumTaxAmount = '';

    public string $sumTaxText = '';

    public string $outcomeType = '1';

    public string $outcomeAmount = '';

    public string $tax_type_id = '1';

    public string $date_report = '';

    #[Locked]
    public ?string $editingCid = null;

    #[Locked]
    public ?int $editingIncomeYear = null;

    #[Locked]
    public ?string $pendingDeletionCid = null;

    #[Locked]
    public ?int $pendingDeletionIncomeYear = null;

    public mixed $importFile = null;

    public string $importEncoding = 'tis-620';

    #[Locked]
    public bool $importPreviewReady = false;

    /** @var array<string, int|array<int, string>> */
    #[Locked]
    public array $importPreview = [];

    /** @var array<string, int|array<int, string>> */
    #[Locked]
    public array $importResult = [];

    #[Locked]
    public ?string $importPreviewHash = null;

    #[Locked]
    public ?string $importPreviewEncoding = null;

    public function boot(): void
    {
        $this->ensureAuthorized();
    }

    public function updatedYearFilter(): void
    {
        $this->ensureAuthorized();
        $this->resetPage();
    }

    public function updatedCidFilter(): void
    {
        $this->ensureAuthorized();
        $this->resetPage();
    }

    public function updatedNameFilter(): void
    {
        $this->ensureAuthorized();
        $this->resetPage();
    }

    public function updatedImportFile(): void
    {
        $this->ensureAuthorized();
        $this->clearImportPreview();
        $this->resetErrorBag('importFile');
    }

    public function updatedImportEncoding(): void
    {
        $this->ensureAuthorized();
        $this->clearImportPreview();
        $this->resetErrorBag('importFile');
    }

    public function openCreateModal(): void
    {
        $this->ensureAuthorized();
        $this->resetForm();
        $this->dispatch('tax-certificate-form-opened');
    }

    public function edit(string $cid, int $incomeYear): void
    {
        $this->ensureAuthorized();
        $record = $this->scopedTaxes()
            // taxes.* keeps legacy schemas working; the alias normalises an uppercase CID column.
            ->select(['taxes.*', 'taxes.cid as cid'])
            ->where('taxes.cid', $cid)
            ->where('taxes.income_year', $incomeYear)
            ->first();

        abort_unless($record, 404);

        $this->resetForm();
        $this->editingCid = (string) $record->cid;
        $this->editingIncomeYear = (int) $record->income_year;

        foreach (self::FORM_FIELDS as $field) {
            $this->{$field} = (string) ($record->{$field} ?? '');
        }

        $this->dispatch('tax-certificate-form-opened');
    }

    public function save(): void
    {
        $this->ensureAuthorized();
        $validated = $this->validate($this->rules());
        $values = $this->normaliseForm($validated);

        try {
            if ($this->editingCid !== null) {
                abort_unless($this->editingIncomeYear !== null, 404);

                if ($values['cid'] !== $this->editingCid || $values['income_year'] !== $this->editingIncomeYear) {
                    $this->addError('cid', 'ไม่สามารถเปลี่ยนเลขประจำตัวประชาชนหรือปีภาษีของรายการเดิมได้');

                    return;
                }

                $existing = $this->scopedTaxes()
                    ->where('taxes.cid', $this->editingCid)
                    ->where('taxes.income_year', $this->editingIncomeYear)
                    ->first(['taxes.cid']);
                abort_unless($existing, 404);

                $updateValues = $values;
                unset($updateValues['cid'], $updateValues['income_year']);
                DB::table('data_tax')
                    ->where('cid', $this->editingCid)
                    ->where('income_year', $this->editingIncomeYear)
                    ->update($updateValues);
                $message = 'ปรับปรุงใบรับรองภาษีแล้ว';
            } else {
                $exists = DB::table('data_tax')
                    ->where('cid', $values['cid'])
                    ->where('income_year', $values['income_year'])
                    ->exists();

                if ($exists) {
                    $this->addError('income_year', 'มีใบรับรองภาษีของบุคลากรและปีภาษีนี้แล้ว');

                    return;
                }

                DB::table('data_tax')->insert($values);
                $message = 'เพิ่มใบรับรองภาษีแล้ว';
            }
        } catch (QueryException) {
            $this->addError('form', 'ไม่สามารถบันทึกใบรับรองภาษีได้ กรุณาลองใหม่อีกครั้ง');

            return;
        }

        $this->resetForm();
        $this->dispatch('tax-certificate-form-closed');
        $this->dispatch('tax-certificate-saved', message: $message);
    }

    public function confirmDelete(?string $cid = null, ?int $incomeYear = null): void
    {
        $this->ensureAuthorized();

        if ($cid !== null && $cid !== '') {
            abort_unless($incomeYear !== null, 404);
            $record = $this->scopedTaxes()
                ->select(['taxes.cid as cid', 'taxes.income_year'])
                ->where('taxes.cid', $cid)
                ->where('taxes.income_year', $incomeYear)
                ->first();
            abort_unless($record, 404);
            $this->pendingDeletionCid = (string) $record->cid;
            $this->pendingDeletionIncomeYear = (int) $record->income_year;
        } else {
            abort_unless($this->editingCid !== null && $this->editingIncomeYear !== null, 404);
            $this->pendingDeletionCid = $this->editingCid;
            $this->pendingDeletionIncomeYear = $this->editingIncomeYear;
        }

        $this->dispatch('tax-certificate-form-closed');
        $this->dispatch('tax-certificate-delete-confirm-opened');
    }

    public function delete(): void
    {
        $this->ensureAuthorized();
        abort_unless($this->pendingDeletionCid !== null && $this->pendingDeletionIncomeYear !== null, 404);
        abort_unless($this->scopedTaxes()
            ->where('taxes.cid', $this->pendingDeletionCid)
            ->where('taxes.income_year', $this->pendingDeletionIncomeYear)
            ->exists(), 404);

        try {
            $deleted = DB::table('data_tax')
                ->where('cid', $this->pendingDeletionCid)
                ->where('income_year', $this->pendingDeletionIncomeYear)
                ->delete();
        } catch (QueryException) {
            $this->addError('delete', 'ไม่สามารถลบใบรับรองภาษีได้ กรุณาลองใหม่อีกครั้ง');

            return;
        }
        abort_unless($deleted === 1, 404);

        $this->resetForm();
        $this->clearPendingDeletion();
        $this->dispatch('tax-certificate-form-closed');
        $this->dispatch('tax-certificate-delete-confirm-closed');
        $this->dispatch('tax-certificate-deleted', message: 'ลบใบรับรองภาษีแล้ว');
    }

    public function previewImport(TaxCertificateTxtImportService $importService): void
    {
        $this->ensureAuthorized();
        $this->clearImportPreview();
        $this->resetErrorBag('importFile');
        $this->validateImportInput();

        $path = $this->temporaryImportPath();
        if ($path === null) {
            $this->addError('importFile', 'ไม่สามารถอ่านไฟล์นำเข้าได้');

            return;
        }

        try {
            $preview = $importService->preview($path, $this->importEncoding, OfficeScope::officeFor(Auth::user()));
        } catch (Throwable) {
            $this->addError('importFile', 'ไม่สามารถอ่านไฟล์นำเข้าได้');

            return;
        }

        $this->importPreview = [
            'read' => (int) ($preview['read'] ?? 0),
            'valid' => (int) ($preview['valid'] ?? 0),
            'skipped' => (int) ($preview['skipped'] ?? 0),
            'errors' => $this->maskImportErrors($preview['errors'] ?? []),
        ];
        $this->importPreviewReady = $this->importPreview['valid'] > 0;
        $this->importPreviewHash = hash_file('sha256', $path) ?: null;
        $this->importPreviewEncoding = $this->importEncoding;
    }

    public function confirmImport(TaxCertificateTxtImportService $importService): void
    {
        $this->ensureAuthorized();

        if (! $this->importPreviewReady
            || $this->importFile === null
            || $this->importPreviewHash === null
            || $this->importPreviewEncoding !== $this->importEncoding) {
            $this->clearImportPreview();
            $this->addError('importFile', 'กรุณาตรวจสอบตัวอย่างข้อมูลก่อนยืนยันนำเข้า');

            return;
        }

        $this->validateImportInput();
        $path = $this->temporaryImportPath();
        if ($path === null || hash_file('sha256', $path) !== $this->importPreviewHash) {
            $this->clearImportPreview();
            $this->addError('importFile', 'ไฟล์เปลี่ยนหลังแสดงตัวอย่าง กรุณาตรวจสอบอีกครั้ง');

            return;
        }

        try {
            $result = $importService->commit($path, $this->importEncoding, OfficeScope::officeFor(Auth::user()));
        } catch (Throwable) {
            $this->addError('importFile', 'ไม่สามารถนำเข้าข้อมูลภาษีได้');

            return;
        }

        $this->importResult = [
            'read' => (int) ($result['read'] ?? 0),
            'inserted' => (int) ($result['inserted'] ?? 0),
            'updated' => (int) ($result['updated'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'errors' => $this->maskImportErrors($result['errors'] ?? []),
        ];

        if ($this->importResult['failed'] > 0) {
            $this->addError('importFile', 'ไม่สามารถนำเข้าข้อมูลภาษีได้');

            return;
        }

        $message = sprintf(
            'นำเข้าใบรับรองภาษีแล้ว เพิ่มใหม่ %d ปรับปรุง %d ข้าม %d รายการ',
            $this->importResult['inserted'],
            $this->importResult['updated'],
            $this->importResult['skipped'],
        );
        $this->importFile = null;
        $this->clearImportPreview();
        $this->dispatch('tax-certificate-import-confirm-closed');
        $this->dispatch('tax-certificate-import-saved', message: $message);
    }

    public function closeModal(): void
    {
        $this->ensureAuthorized();
        $this->resetForm();
        $this->clearPendingDeletion();
        $this->importFile = null;
        $this->clearImportPreview();
        $this->resetErrorBag();
        $this->dispatch('tax-certificate-form-closed');
        $this->dispatch('tax-certificate-delete-confirm-closed');
        $this->dispatch('tax-certificate-import-confirm-closed');
    }

    #[Title('จัดการใบรับรองภาษี')]
    public function render()
    {
        $this->ensureAuthorized();
        $year = trim($this->yearFilter);
        $cid = trim($this->cidFilter);
        $name = trim($this->nameFilter);

        return view('livewire.admin.tax-certificate-management', [
            'certificates' => $this->scopedTaxes()
                ->select([
                    'taxes.cid as cid',
                    'taxes.income_year',
                    'taxes.running_code1',
                    'taxes.fullname',
                    'taxes.income_money',
                    'taxes.withholding_tax',
                    'taxes.outcomeName',
                    'taxes.outcomeAmount',
                    'taxes.date_report',
                    'people.pname as person_pname',
                    'people.fname as person_fname',
                    'people.lname as person_lname',
                ])
                ->when($year !== '' && ctype_digit($year), fn (Builder $query) => $query->where('taxes.income_year', (int) $year))
                ->when($cid !== '', fn (Builder $query) => $query->where('taxes.cid', 'like', "%{$cid}%"))
                ->when($name !== '', function (Builder $query) use ($name): void {
                    $query->where(function (Builder $query) use ($name): void {
                        $query->where('taxes.fullname', 'like', "%{$name}%")
                            ->orWhere('people.pname', 'like', "%{$name}%")
                            ->orWhere('people.fname', 'like', "%{$name}%")
                            ->orWhere('people.lname', 'like', "%{$name}%");
                    });
                })
                ->orderByDesc('taxes.income_year')
                ->orderBy('taxes.cid')
                ->paginate(20),
            'years' => $this->scopedTaxes()->select('taxes.income_year')->distinct()->orderByDesc('taxes.income_year')->pluck('income_year'),
            'incomeTypes' => self::INCOME_TYPES,
            'outcomeTypes' => self::OUTCOME_TYPES,
            'taxTypes' => self::TAX_TYPES,
        ]);
    }

    /** @return array<string, array<int, Closure|string|Rule>> */
    private function rules(): array
    {
        $rules = [
            'cid' => $this->editingCid !== null
                ? ['required', 'digits:13']
                : ['required', 'digits:13', $this->scopedPersonRule()],
            'income_year' => ['required', 'integer', 'digits:4', 'between:2400,3000'],
            'running_code1' => ['required', 'string', 'max:255'],
            'department_name' => ['nullable', 'string', 'max:255'],
            'department_tax_id' => ['nullable', 'string', 'max:255'],
            'department_addr' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'fullname' => ['nullable', 'string', 'max:255'],
            'addr' => ['nullable', 'string', 'max:255'],
            'running_code2' => ['nullable', 'string', 'max:255'],
            'income_type_id' => ['required', Rule::in(array_keys(self::INCOME_TYPES))],
            'sumTaxText' => ['nullable', 'string', 'max:255'],
            'outcomeType' => ['required', Rule::in(array_keys(self::OUTCOME_TYPES))],
            'tax_type_id' => ['required', Rule::in(array_keys(self::TAX_TYPES))],
            'date_report' => [
                'required',
                'regex:/^\\d{8}$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $this->isBuddhistDate((string) $value)) {
                        $fail('วันที่ออกใบรับรองไม่ถูกต้อง');
                    }
                },
            ],
        ];

        foreach (self::MONEY_FIELDS as $field) {
            $rules[$field] = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'];
        }

        return $rules;
    }

    /** @param array<string, mixed> $validated
     *  @return array<string, int|string|null> */
    private function normaliseForm(array $validated): array
    {
        $values = $validated;
        $values['cid'] = trim((string) $values['cid']);
        $values['income_year'] = (int) $values['income_year'];
        $values['running_code1'] = trim((string) $values['running_code1']);
        $values['date_report'] = trim((string) $values['date_report']);

        foreach (self::NULLABLE_TEXT_FIELDS as $field) {
            $value = trim((string) ($values[$field] ?? ''));
            $values[$field] = $value === '' ? null : $value;
        }

        foreach (self::MONEY_FIELDS as $field) {
            $value = trim((string) ($values[$field] ?? ''));
            $values[$field] = $value === '' ? null : $value;
        }

        $values['income_type_name'] = self::INCOME_TYPES[$values['income_type_id']];
        $values['outcomeName'] = self::OUTCOME_TYPES[$values['outcomeType']];
        $values['tax_type_name'] = self::TAX_TYPES[$values['tax_type_id']];
        $values['r_update'] = now();

        return $values;
    }

    private function validateImportInput(): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:txt', 'max:5120'],
            'importEncoding' => ['required', Rule::in(['tis-620', 'utf-8'])],
        ], [
            'importFile.required' => 'กรุณาเลือกไฟล์ TXT',
            'importFile.mimes' => 'รองรับเฉพาะไฟล์ .txt',
            'importFile.max' => 'ไฟล์ต้องมีขนาดไม่เกิน 5 MB',
            'importEncoding.in' => 'กรุณาเลือกรหัสอักขระของไฟล์ที่รองรับ',
        ]);
    }

    private function temporaryImportPath(): ?string
    {
        if ($this->importFile === null) {
            return null;
        }

        $path = $this->importFile->getRealPath();

        return is_string($path) && is_file($path) && is_readable($path) ? $path : null;
    }

    private function clearImportPreview(): void
    {
        $this->importPreviewReady = false;
        $this->importPreview = [];
        $this->importPreviewHash = null;
        $this->importPreviewEncoding = null;
    }

    private function clearPendingDeletion(): void
    {
        $this->pendingDeletionCid = null;
        $this->pendingDeletionIncomeYear = null;
    }

    private function resetForm(): void
    {
        foreach (self::FORM_FIELDS as $field) {
            $this->{$field} = '';
        }

        $this->income_type_id = '1';
        $this->outcomeType = '1';
        $this->tax_type_id = '1';
        $this->editingCid = null;
        $this->editingIncomeYear = null;
        $this->resetErrorBag();
    }

    /** @param array<int, mixed> $errors
     *  @return array<int, string> */
    private function maskImportErrors(array $errors): array
    {
        return array_map(function (mixed $error): string {
            return preg_replace_callback('/(?<!\\d)\\d{5,}(?!\\d)/', function (array $matches): string {
                return $this->maskCid($matches[0]);
            }, (string) $error) ?? 'ไม่สามารถอ่านรายละเอียดข้อผิดพลาดได้';
        }, array_slice($errors, 0, 100));
    }

    private function maskCid(string $value): string
    {
        $value = trim($value);

        if (strlen($value) <= 4) {
            return str_repeat('*', max(4, strlen($value)));
        }

        return substr($value, 0, 4).str_repeat('*', strlen($value) - 4);
    }

    private function isBuddhistDate(string $date): bool
    {
        if (! preg_match('/^\\d{8}$/', $date)) {
            return false;
        }

        $day = (int) substr($date, 0, 2);
        $month = (int) substr($date, 2, 2);
        $year = (int) substr($date, 4, 4);

        return $year >= 2400 && $year <= 3000 && checkdate($month, $day, $year - 543);
    }

    /** Tax certificates of personnel the current user may manage, aliased as taxes/people. */
    private function scopedTaxes(): Builder
    {
        $query = DB::table('data_tax as taxes')->leftJoin('person as people', 'people.cid', '=', 'taxes.cid');

        return OfficeScope::apply($query, Auth::user(), 'people.office_id');
    }

    /** Office-scoped users may only add certificates for personnel of their office. */
    private function scopedPersonRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (OfficeScope::officeFor(Auth::user()) === null) {
                return;
            }
            if (! OfficeScope::apply(DB::table('person'), Auth::user())->where('cid', (string) $value)->exists()) {
                $fail('เพิ่มได้เฉพาะบุคลากรในหน่วยงานของตนเอง');
            }
        };
    }

    private function ensureAuthorized(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'tax-certificates'), 403);
    }
}
