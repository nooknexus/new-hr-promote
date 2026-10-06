<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\PayslipSignatory;
use App\Services\TaxCertificatePdfService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;

class TaxCertificate extends Component
{
    #[Locked]
    public array $years = [];

    #[Locked]
    public ?int $selectedYear = null;

    public $availableCertificates = [];
    public $selectedCertificate = null;
    public $selectedIndex = null;
    public $previewCertificate = null;
    public $previewIndex = null;
    public $isLoading = false;
    
    public function mount()
    {
        $pid = Auth::user()->pid ?? null;

        if (!$pid) {
            return;
        }

        $this->years = DB::table('data_tax')
            ->where('CID', $pid)
            ->distinct()
            ->orderByDesc('income_year')
            ->pluck('income_year')
            ->map(static fn ($year): int => (int) $year)
            ->values()
            ->all();
    }
    
    // Helper function to format Thai dates from "ddmmyyyy" to "dd month yyyy"
    private function formatThaiDate($dateString)
    {
        if (strlen($dateString) != 8) {
            return $dateString; // Return as is if not in expected format
        }
        
        $day = substr($dateString, 0, 2);
        $monthNum = substr($dateString, 2, 2);
        $year = substr($dateString, 4, 4);
        
        // Thai month names
        $thaiMonths = [
            '01' => 'มกราคม',
            '02' => 'กุมภาพันธ์',
            '03' => 'มีนาคม',
            '04' => 'เมษายน',
            '05' => 'พฤษภาคม',
            '06' => 'มิถุนายน',
            '07' => 'กรกฎาคม',
            '08' => 'สิงหาคม',
            '09' => 'กันยายน',
            '10' => 'ตุลาคม',
            '11' => 'พฤศจิกายน',
            '12' => 'ธันวาคม'
        ];
        
        $monthName = $thaiMonths[$monthNum] ?? 'เดือนไม่ทราบ';
        
        return "$day $monthName $year";
    }
    
    public function selectYear(int $year): void
    {
        if (!in_array($year, $this->years, true)) {
            return;
        }

        $this->selectedYear = $year;
        $this->selectedCertificate = null;
        $this->selectedIndex = null;
        $this->previewCertificate = null;
        $this->previewIndex = null;
        $this->loadCertificatesForYear();
    }

    private function loadCertificatesForYear(): void
    {
        $this->availableCertificates = [];

        if (!$this->selectedYear) {
            return;
        }

        $this->isLoading = true;

        try {
            $user = Auth::user();
            $pid = $user->pid ?? '';

            $taxRecords = DB::select("
                SELECT
                    income_year AS 'ปีภาษี',
                    running_code1 AS 'เลขที่เอกสาร',
                    total_money AS 'เงินได้',
                    withholding_tax AS 'ภาษีที่ถูกหัก',
                    outcomeName AS 'กองทุน',
                    outcomeAmount AS 'ยอดเงินกองทุน',
                    socialSecurity AS 'ประกันสังคม',
                    date_report,
                    r_update 
                FROM
                    data_tax 
                WHERE CID = ? AND income_year = ?
                ORDER BY income_year DESC, running_code1 DESC
            ", [$pid, $this->selectedYear]);
            
            // Process the data
            foreach ($taxRecords as $record) {
                $formattedDate = $this->formatThaiDate($record->date_report ?? '');
                
                // Initialize fund values
                $gpfAmount = 0;
                $providentFundAmount = 0;
                
                // Determine fund type based on outcomeName
                if ($record->กองทุน == 'กบข.') {
                    $gpfAmount = $record->ยอดเงินกองทุน;
                } elseif ($record->กองทุน == 'กสล.พกส' || $record->กองทุน == 'กสจ.') {
                    // Added 'กสจ.' to also show in provident fund
                    $providentFundAmount = $record->ยอดเงินกองทุน;
                }
                
                // Format the certificate data
                $this->availableCertificates[] = [
                    'taxYear' => $record->ปีภาษี,
                    'certificateNo' => $record->เลขที่เอกสาร,
                    'totalIncome' => $record->เงินได้,
                    'taxWithheld' => $record->ภาษีที่ถูกหัก,
                    'socialSecurity' => $record->ประกันสังคม,
                    'providentFund' => $providentFundAmount,
                    'governmentPensionFund' => $gpfAmount,
                    'issueDate' => $record->r_update,
                    'formattedIssueDate' => $formattedDate,
                    'employeeInfo' => [
                        'name' => $user->name,
                        'pid' => $pid,
                        'position' => $user->position ?? 'ไม่ระบุตำแหน่ง',
                        'department' => $user->department ?? 'ไม่ระบุหน่วยงาน',
                    ],
                ];
            }
        } catch (\Exception $e) {
            session()->flash('error', 'ไม่สามารถโหลดข้อมูลได้: ' . $e->getMessage());
            $this->availableCertificates = [];
        }
        
        $this->isLoading = false;
    }
    
    public function viewCertificate($index)
    {
        if (isset($this->availableCertificates[$index])) {
            $this->selectedCertificate = $this->availableCertificates[$index];
            $this->selectedIndex = $index;
        }
    }
    
    public function closeModal()
    {
        $this->selectedCertificate = null;
        $this->selectedIndex = null;
    }
    
    public function previewDocument($index)
    {
        if (isset($this->availableCertificates[$index])) {
            $certificate = $this->availableCertificates[$index];
            $pid = Auth::user()?->pid;

            if (! $pid) {
                return;
            }

            $record = DB::table('data_tax')
                ->where('CID', $pid)
                ->where('income_year', $certificate['taxYear'])
                ->where('running_code1', $certificate['certificateNo'])
                ->first();

            if (! $record) {
                return;
            }

            $signatory = PayslipSignatory::forTaxIncomeYear((int) $record->income_year);
            $this->previewCertificate = TaxCertificatePdfService::fromTaxRecord($record);
            $this->previewCertificate['signatureDataUri'] = $signatory?->signature_data_uri;
            $this->previewIndex = $index;
        }
    }
    
    public function closeDocumentPreview()
    {
        $this->previewCertificate = null;
        $this->previewIndex = null;
    }
    
    public function printDocument()
    {
        $this->dispatch('print-document');
    }
    
    #[Title('ใบรับรองการหักภาษี')]
    public function render()
    {
        return view('livewire.tax-certificate');
    }
}
