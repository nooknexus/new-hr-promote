<?php

namespace App\Livewire\Admin;

use App\Services\SalaryPromotionImportService;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class SalaryPromotionImport extends Component
{
    use WithFileUploads;

    public mixed $importFile = null;

    #[Locked]
    public bool $previewReady = false;

    #[Locked]
    public array $previewCounts = [];

    #[Locked]
    public array $warnings = [];

    #[Locked]
    public array $importErrors = [];

    #[Locked]
    public ?string $previewHash = null;

    public function boot(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'promotions'), 403);
    }

    public function updatedImportFile(): void
    {
        $this->clearPreview();
    }

    public function previewUpload(SalaryPromotionImportService $importService): void
    {
        $this->clearPreview();
        $this->resetErrorBag('importFile');
        $this->validateFile();

        try {
            $preview = $importService->preview($this->importFile->getRealPath());
        } catch (ValidationException $exception) {
            $this->addError('importFile', $exception->validator->errors()->first('file') ?: 'ไม่สามารถอ่านไฟล์ Excel ได้');

            return;
        }

        $this->previewCounts = [
            'insert_count' => $preview['insert_count'],
            'update_count' => $preview['update_count'],
            'unmatched_person_count' => $preview['unmatched_person_count'],
            'unmatched_user_count' => $preview['unmatched_user_count'],
        ];
        $this->warnings = array_slice($preview['warnings'], 0, 10);
        $this->importErrors = array_slice($preview['errors'], 0, 20);
        $this->previewReady = $preview['errors'] === [] && $preview['rows'] !== [];
        $this->previewHash = $this->previewReady ? hash_file('sha256', $this->importFile->getRealPath()) : null;
    }

    public function confirmImport(SalaryPromotionImportService $importService): void
    {
        if (! $this->previewReady || $this->importFile === null || $this->previewHash === null) {
            $this->addError('importFile', 'กรุณาตรวจสอบตัวอย่างข้อมูลก่อนยืนยัน');

            return;
        }

        $this->validateFile();
        $path = $this->importFile->getRealPath();
        if (hash_file('sha256', $path) !== $this->previewHash) {
            $this->clearPreview();
            $this->addError('importFile', 'ไฟล์เปลี่ยนหลังแสดงตัวอย่าง กรุณาตรวจสอบอีกครั้ง');

            return;
        }

        try {
            $result = $importService->commit($path, (int) Auth::id());
        } catch (ValidationException $exception) {
            $this->clearPreview();
            $this->addError('importFile', $exception->validator->errors()->first('file') ?: 'ไม่สามารถนำเข้าไฟล์ Excel ได้');

            return;
        }

        $this->importFile = null;
        $this->clearPreview();
        $this->dispatch('salary-import-confirm-closed');
        $this->dispatch('salary-import-completed');
        $this->dispatch('salary-import-saved', message: "นำเข้าเลื่อนขั้นเงินเดือนแล้ว เพิ่มใหม่ {$result['insert_count']} ปรับปรุง {$result['update_count']} รายการ");
    }

    public function cancelImport(): void
    {
        $this->importFile = null;
        $this->clearPreview();
        $this->resetErrorBag('importFile');
        $this->dispatch('salary-import-confirm-closed');
    }

    private function validateFile(): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'importFile.required' => 'กรุณาเลือกไฟล์ Excel',
            'importFile.mimes' => 'รองรับเฉพาะไฟล์ .xlsx',
            'importFile.max' => 'ไฟล์ต้องมีขนาดไม่เกิน 10 MB',
        ]);
    }

    private function clearPreview(): void
    {
        $this->previewReady = false;
        $this->previewCounts = [];
        $this->warnings = [];
        $this->importErrors = [];
        $this->previewHash = null;
    }

    public function render()
    {
        return view('livewire.admin.salary-promotion-import');
    }
}
