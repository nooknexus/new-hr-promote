<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Title;
use App\Models\PayslipSignatory;
use App\Support\AdminAccess;
use App\Support\OfficeScope;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class Signatories extends Component
{
    use WithFileUploads;

    public function boot(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'signatories'), 403);
    }

    public bool $showFormModal = false;

    public ?int $editingId = null;
    public ?int $fiscal_year = null;
    public string $name = '';
    public string $position_line1 = '';
    public string $position_line2 = '';
    public $signatureUpload = null;
    public bool $hasExistingSignature = false;

    protected function rules(): array
    {
        return [
            'fiscal_year' => [
                'required', 'integer', 'digits:4',
                'unique:payslip_signatories,fiscal_year,' . ($this->editingId ?? 'NULL') . ',id',
            ],
            'name' => ['required', 'string', 'max:255'],
            'position_line1' => ['required', 'string', 'max:255'],
            'position_line2' => ['nullable', 'string', 'max:255'],
            'signatureUpload' => ['nullable', 'image', 'max:2048'],
        ];
    }

    protected array $messages = [
        'fiscal_year.required' => 'กรุณาระบุปีงบประมาณ',
        'fiscal_year.digits' => 'ปีงบประมาณต้องเป็นตัวเลข 4 หลัก (พ.ศ.)',
        'fiscal_year.unique' => 'มีข้อมูลผู้ลงนามของปีงบประมาณนี้อยู่แล้ว',
        'name.required' => 'กรุณาระบุชื่อ-นามสกุลผู้ลงนาม',
        'position_line1.required' => 'กรุณาระบุตำแหน่งอย่างน้อย 1 บรรทัด',
        'signatureUpload.image' => 'ไฟล์ต้องเป็นรูปภาพ (PNG/JPG)',
        'signatureUpload.max' => 'ไฟล์รูปลายเซ็นต้องไม่เกิน 2MB',
    ];

    public function currentFiscalYear(): int
    {
        return PayslipSignatory::fiscalYearFor((int) now()->year, (int) now()->month);
    }

    public function fiscalYearLabel(int $fiscalYear): string
    {
        $startYear = $fiscalYear - 544; // Gregorian year the fiscal year starts in
        $endYear = $fiscalYear - 543;

        return "1 ต.ค. {$startYear} - 30 ก.ย. {$endYear} (ปีงบประมาณ {$fiscalYear})";
    }

    /**
     * Signatories are shared by every office, so only unrestricted users (ADMIN or
     * office 00) may change them; other FINANCE users have read-only access.
     */
    public function canManage(): bool
    {
        return OfficeScope::officeFor(Auth::user()) === null;
    }

    private function ensureCanManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    public function openCreateModal(): void
    {
        $this->ensureCanManage();
        $this->resetForm();
        $this->fiscal_year = $this->currentFiscalYear();
        $this->showFormModal = true;
    }

    public function edit(int $id): void
    {
        $this->ensureCanManage();
        $signatory = PayslipSignatory::findOrFail($id);

        $this->editingId = $signatory->id;
        $this->fiscal_year = $signatory->fiscal_year;
        $this->name = $signatory->name;
        $this->position_line1 = $signatory->position_line1;
        $this->position_line2 = $signatory->position_line2 ?? '';
        $this->hasExistingSignature = ! empty($signatory->signature_image);
        $this->signatureUpload = null;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->ensureCanManage();
        $validated = $this->validate();

        $data = [
            'fiscal_year' => $validated['fiscal_year'],
            'name' => $validated['name'],
            'position_line1' => $validated['position_line1'],
            'position_line2' => $validated['position_line2'] ?: null,
        ];

        if ($this->signatureUpload) {
            $data['signature_image'] = file_get_contents($this->signatureUpload->getRealPath());
            $data['signature_mime'] = $this->signatureUpload->getMimeType();
        }

        if ($this->editingId) {
            PayslipSignatory::findOrFail($this->editingId)->update($data);
        } else {
            PayslipSignatory::create($data);
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->ensureCanManage();
        PayslipSignatory::findOrFail($id)->delete();
    }

    public function closeModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId', 'fiscal_year', 'name', 'position_line1',
            'position_line2', 'signatureUpload', 'hasExistingSignature',
        ]);
        $this->resetErrorBag();
    }

    #[Title('ผู้ลงนามใบรับรอง')]
    public function render()
    {
        return view('livewire.admin.signatories', [
            'signatories' => PayslipSignatory::orderByDesc('fiscal_year')->get(),
        ]);
    }
}
