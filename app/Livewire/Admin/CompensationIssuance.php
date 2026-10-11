<?php

namespace App\Livewire\Admin;

use App\Services\CompensationCertificateIssuer;
use App\Services\MonthlyCompensationService;
use App\Support\OfficeScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The provincial health office's yearly issue of withholding-tax certificates
 * from monthly compensation, plus its payer settings (tab of
 * /admin/tax-certificates, ADMIN and office 00 only).
 */
class CompensationIssuance extends Component
{
    public int $year;
    public string $dateReport = '';

    public string $payerName = '';
    public string $payerTaxId = '';
    public string $payerAddress = '';

    #[Locked]
    public ?string $pendingUnconfirmOffice = null;

    public function boot(): void
    {
        $this->ensureAuthorized();
    }

    public function mount(): void
    {
        $this->year = now()->year + 543 - (now()->month <= 3 ? 1 : 0);
        $this->dateReport = now()->format('Y-m-d');
        $payer = DB::table('tax_payer_settings')->orderBy('id')->first();
        $this->payerName = (string) ($payer->name ?? '');
        $this->payerTaxId = (string) ($payer->tax_id ?? '');
        $this->payerAddress = (string) ($payer->address ?? '');
    }

    public function savePayer(): void
    {
        $this->ensureAuthorized();
        $validated = $this->validate([
            'payerName' => ['required', 'string', 'max:50'],
            'payerTaxId' => ['required', 'digits:13'],
            'payerAddress' => ['required', 'string', 'max:255'],
        ], [
            'payerName.*' => 'กรุณาระบุชื่อผู้จ่ายเงิน ไม่เกิน 50 ตัวอักษร',
            'payerTaxId.*' => 'เลขประจำตัวผู้เสียภาษีต้องเป็นตัวเลข 13 หลัก',
            'payerAddress.*' => 'กรุณาระบุที่อยู่ ไม่เกิน 255 ตัวอักษร',
        ]);
        $values = [
            'name' => trim($validated['payerName']), 'tax_id' => $validated['payerTaxId'],
            'address' => trim($validated['payerAddress']), 'updated_by' => Auth::id(), 'updated_at' => now(),
        ];
        $id = DB::table('tax_payer_settings')->orderBy('id')->value('id');
        $id === null
            ? DB::table('tax_payer_settings')->insert($values + ['created_at' => now()])
            : DB::table('tax_payer_settings')->where('id', $id)->update($values);
        $this->dispatch('issuance-saved', message: 'บันทึกข้อมูลผู้จ่ายเงินแล้ว');
    }

    public function prepareIssue(): void
    {
        $this->ensureAuthorized();
        $this->resetErrorBag();
        $this->dispatch('issuance-confirm-opened');
    }

    public function issue(CompensationCertificateIssuer $issuer): void
    {
        $this->ensureAuthorized();
        $this->validate(['dateReport' => ['required', 'date_format:Y-m-d']], ['dateReport.*' => 'กรุณาระบุวันที่ออกหนังสือรับรอง']);
        $date = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $this->dateReport);
        try {
            $count = $issuer->issue(Auth::user(), $this->year, $date->format('dm').($date->year + 543), skipUnconfirmed: true);
        } catch (ValidationException $exception) {
            $this->addError('issue', collect($exception->errors())->flatten()->first());

            return;
        }
        $this->dispatch('issuance-confirm-closed');
        $this->dispatch('issuance-saved', message: "ออกหนังสือรับรองปี {$this->year} แล้ว {$count} ฉบับ");
    }

    public function prepareReopen(): void
    {
        $this->ensureAuthorized();
        $this->dispatch('issuance-reopen-opened');
    }

    public function reopen(CompensationCertificateIssuer $issuer): void
    {
        $this->ensureAuthorized();
        $issuer->reopen(Auth::user(), $this->year);
        $this->dispatch('issuance-reopen-closed');
        $this->dispatch('issuance-saved', message: "ปลดล็อกปี {$this->year} แล้ว ทุกต้นสังกัดต้องยืนยันข้อมูลใหม่");
    }

    public function prepareUnconfirm(string $officeId): void
    {
        $this->ensureAuthorized();
        $this->pendingUnconfirmOffice = $officeId;
        $this->dispatch('issuance-unconfirm-opened');
    }

    public function unconfirm(MonthlyCompensationService $service): void
    {
        $this->ensureAuthorized();
        abort_unless($this->pendingUnconfirmOffice !== null, 404);
        try {
            $service->unconfirm(Auth::user(), $this->year, $this->pendingUnconfirmOffice);
        } catch (ValidationException $exception) {
            $this->addError('unconfirm', collect($exception->errors())->flatten()->first());

            return;
        }
        $office = $this->pendingUnconfirmOffice;
        $this->pendingUnconfirmOffice = null;
        $this->dispatch('issuance-unconfirm-closed');
        $this->dispatch('issuance-saved', message: 'ปลดการยืนยันของ'.(OfficeScope::nameFor($office) ?? "ต้นสังกัด {$office}").'แล้ว');
    }

    public function render()
    {
        $this->ensureAuthorized();
        $summary = MonthlyCompensationService::yearSummary($this->year);
        $state = DB::table('compensation_years')->where('income_year', $this->year)->first();

        return view('livewire.admin.compensation-issuance', [
            'summary' => $summary,
            'unconfirmed' => $summary->whereNull('confirmed_at')->count(),
            'state' => $state,
            'issuedCount' => DB::table('data_tax')->where('income_year', (string) $this->year)
                ->where('source', CompensationCertificateIssuer::SOURCE)->count(),
            'years' => range(now()->year + 543, now()->year + 543 - 4),
            'hasPayer' => DB::table('tax_payer_settings')->exists(),
            'pendingUnconfirmOfficeName' => OfficeScope::nameFor($this->pendingUnconfirmOffice),
        ]);
    }

    private function ensureAuthorized(): void
    {
        abort_unless(MonthlyCompensationService::isProvincialOffice(Auth::user()), 403);
    }
}
