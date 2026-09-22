<?php

namespace App\Http\Livewire\Profile;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Payslip as PayslipModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        $this->dispatchBrowserEvent('serve-pdf-download', route('payslip.download', ['id' => $id]));
    }
}
