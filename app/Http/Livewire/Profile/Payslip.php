<?php

namespace App\Http\Livewire\Profile;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Payslip as PayslipModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\PayslipPdfService;

class Payslip extends Component
{
    use WithPagination;

    public $years, $selectedYear, $payslips, $selectedPayslip;

    protected $listeners = ['show-payslip-detail' => 'openDetailModal'];

    public function mount()
    {
        $this->years = range(date('Y') + 543, 2566);
        $this->selectedYear = end($this->years);
    }

    public function render()
    {
        $this->fetchPayslips();
        return view('livewire.profile.payslip', [
            'payslips' => $this->payslips,
        ]);
    }

    public function selectYear($year)
    {
        $this->selectedYear = $year;
        $this->fetchPayslips();
    }

    protected function fetchPayslips()
    {
        $userId = Auth::id();
        $year = $this->selectedYear - 543; // Convert to AD

        $this->payslips = PayslipModel::where('user_id', $userId)
            ->whereYear('created_at', $year)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function openDetailModal($id)
    {
        $this->selectedPayslip = $this->payslips->find($id);
        $this->dispatchBrowserEvent('show-payslip-detail');
    }

    /**
     * Generate PDF for download
     */
    public function generatePDF($id)
    {
        try {
            Log::info('PDF Generation Started', ['payslip_id' => $id, 'user_id' => auth()->id()]);
            
            // Get user's personal ID (CID)
            $cid = auth()->user()->pid;
            
            // Find the payslip data
            $payslipData = $this->selectedPayslip && $this->selectedPayslip['id'] == $id 
                ? $this->selectedPayslip
                : $this->fetchPayslipData($id);
            
            if (!$payslipData) {
                Log::error('Payslip data not found for PDF generation', ['id' => $id]);
                $this->dispatchBrowserEvent('pdf-generation-error', 'ไม่พบข้อมูลใบรับรองการจ่ายค่าตอบแทน');
                return;
            }
            
            // Generate PDF using PayslipPdfService
            $pdfResult = PayslipPdfService::generatePdf($payslipData, $cid);
            
            // Save PDF to temp directory and get URL
            $url = PayslipPdfService::savePdfToTemp($pdfResult['content'], $pdfResult['filename']);
            
            Log::info('PDF URL generated:', ['url' => $url]);
            
            // Dispatch event to frontend with the PDF URL
            $this->dispatchBrowserEvent('serve-pdf-download', $url);
            
        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'id' => $id
            ]);
            
            $this->dispatchBrowserEvent('pdf-generation-error', 'เกิดข้อผิดพลาดในการสร้าง PDF: ' . $e->getMessage());
        }
    }
}