<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaxCertificate extends Component
{
    public $availableCertificates = [];
    public $selectedCertificate = null;
    public $selectedIndex = null;
    public $previewCertificate = null;
    public $previewIndex = null;
    public $isLoading = false;
    
    public function mount()
    {
        $this->loadAvailableCertificates();
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
    
    public function loadAvailableCertificates()
    {
        $this->isLoading = true;
        
        try {
            $user = Auth::user();
            $pid = $user->pid ?? ''; // Get user's personal ID
            
            // Fetch data from database using the provided SQL query with ORDER BY income_year DESC
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
                WHERE CID = ?
                ORDER BY income_year DESC
            ", [$pid]);
            
            $this->availableCertificates = [];
            
            // Process the data
            foreach ($taxRecords as $record) {
                $formattedDate = $this->formatThaiDate($record->date_report);
                
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
            $this->previewCertificate = $this->availableCertificates[$index];
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
    
    public function downloadPDFFile($index)
    {
        if (isset($this->availableCertificates[$index])) {
            // For now just show a notification - in a real app you would generate a PDF
            $this->dispatch('show-pdf-download-message', [
                'year' => $this->availableCertificates[$index]['taxYear']
            ]);
        }
    }
    
    #[Title('ใบรับรองการหักภาษี')]
    public function render()
    {
        return view('livewire.tax-certificate');
    }
}
