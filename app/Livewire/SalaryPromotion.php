<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\SalaryPromotionPdfService;
use Carbon\Carbon;

class SalaryPromotion extends Component
{
    public $years = [];
    public $selectedYear;
    public $promotions = [];
    public $selectedPromotion = null;

    public function mount()
    {
        // Get current user's pid (13-digit national ID)
        $pid = Auth::user()->pid ?? '';
        
        if (empty($pid)) {
            return;
        }
        
        // Get available fiscal years with promotions from database
        $promotionYears = $this->getAvailableFiscalYears($pid);
        
        // Set years array and default selected year
        if (!empty($promotionYears)) {
            $this->years = $promotionYears;
            $this->selectedYear = $this->years[0] ?? null;
            
            if ($this->selectedYear) {
                $this->loadPromotions();
            }
        } else {
            // If no data is found, show current fiscal year
            $currentMonth = Carbon::now()->month;
            $currentYear = Carbon::now()->year + 543; // Convert to Buddhist Era
            
            // If current month is October or later, we're in the next fiscal year
            $currentFiscalYear = ($currentMonth >= 10) ? $currentYear + 1 : $currentYear;
            
            $this->years = [$currentFiscalYear];
            $this->selectedYear = $currentFiscalYear;
        }
    }

    /** Get fiscal years with active promotion data for the user. */
    private function getAvailableFiscalYears($pid)
    {
        return DB::table('data_promote')
            ->where('CID', $pid)
            ->whereNull('deleted_at')
            ->pluck('evaluation_date')
            ->map(function ($date) {
                $evaluationDate = Carbon::parse($date);

                return $evaluationDate->year + ($evaluationDate->month >= 10 ? 544 : 543);
            })
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    public function selectYear($year)
    {
        $this->selectedYear = $year;
        $this->loadPromotions();
    }

    public function loadPromotions()
    {
        // Get current user's pid
        $pid = Auth::user()->pid ?? '';
        
        if (empty($pid)) {
            return;
        }
        
        $calendarYear = (int) $this->selectedYear - 543;
        $startDate = ($calendarYear - 1) . '-10-01';
        $endDate = "{$calendarYear}-09-30";

        $promotionData = DB::table('data_promote')
            ->where('CID', $pid)
            ->whereNull('deleted_at')
            ->whereBetween('evaluation_date', [$startDate, $endDate])
            ->orderBy('evaluation_date')
            ->orderBy('id')
            ->select([
                'id', 'evaluation_date', 'update_date', 'before_salary as old_salary',
                'new_salary', 'sum as increase_amount', 'percent as increase_percent',
                'base', 'promote', 'department', 'office', 'ps', 'extra',
            ])
            ->get();

        $roundCounts = [];
        foreach ($promotionData as $item) {
            $date = Carbon::parse($item->evaluation_date);
            $key = $date->format('Y-m');
            $roundCounts[$key] = ($roundCounts[$key] ?? 0) + 1;
            $item->round_number = $roundCounts[$key];
            $item->round_month = match ($date->month) {
                4 => 'รอบ1 เมษายน',
                10 => 'รอบ2 ตุลาคม',
                default => 'รอบพิเศษ',
            };
        }
        
        $person = DB::table('person')->where('cid', $pid)->first();
        $position = trim((string) ($person->position ?? '')) ?: '-';
        $level = trim((string) ($person->position_level ?? '')) ?: '-';

        // Process the data to format it properly for the view
        $this->promotions = $promotionData->reverse()->values()->map(function($item) use ($position, $level) {
            $evaluationDate = Carbon::parse($item->evaluation_date);
            $calendarYearBE = $evaluationDate->year + 543; // Convert to Buddhist Era
            
            return [
                'id' => (int) $item->id,
                'round' => SalaryPromotionPdfService::roundLabel($evaluationDate),
                'position' => $position,
                'level' => $level,
                'round_number' => $item->round_number,
                'round_type' => $item->round_month,
                'old_salary' => $item->old_salary,
                'new_salary' => $item->new_salary,
                'increase_amount' => $item->increase_amount,
                'increase_percent' => $item->increase_percent,
                'effective_date' => $this->getEffectiveDate($evaluationDate),
                'evaluation_date' => $evaluationDate->format('Y-m-d'),
                'update_date' => $item->update_date ?? $evaluationDate->format('Y-m-d H:i:s'), // Add update_date with fallback
                // Add the new fields
                'base' => $item->base ?? null,
                'promote' => $item->promote ?? null,
                'department' => $item->department ?? null,
                'office' => $item->office ?? null,
                'ps' => $item->ps ?? null,
                'extra' => $item->extra ?? null,
                'details' => [
                    'performance_score' => 'ตามเกณฑ์การประเมิน',
                    'promotion_type' => $this->getPromotionType($item->round_month),
                    'approver' => 'นายแพทย์สาธารณสุขจังหวัด',
                    'approval_date' => $this->formatThaiDate($evaluationDate->format('Y-m-d')),
                    'remarks' => $item->ps ?? $this->getDefaultRemarks($evaluationDate, $item->round_month)
                ]
            ];
        })->toArray();
    }
    
    /**
     * Get the effective date based on evaluation date
     */
    private function getEffectiveDate($evaluationDate)
    {
        $year = $evaluationDate->year + 543; // Buddhist Era
        $month = $evaluationDate->month;
        
        if ($month == 4) {
            return "1 เมษายน {$year}";
        } elseif ($month == 10) {
            return "1 ตุลาคม {$year}";
        } else {
            // For special rounds, use the evaluation date itself
            return $evaluationDate->format('d') . ' ' . $this->getThaiMonth($month) . ' ' . $year;
        }
    }
    
    /**
     * Get Thai month name from month number
     */
    private function getThaiMonth($month)
    {
        $thaiMonths = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 
            4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
            7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
            10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
        ];
        
        return $thaiMonths[$month] ?? '';
    }
    
    /**
     * Get the promotion type based on round month
     */
    private function getPromotionType($roundMonth)
    {
        if ($roundMonth == 'พิเศษ') {
            return 'พิเศษ';
        } else {
            return 'ปกติ';
        }
    }
    
    /**
     * Format date to Thai date format
     */
    private function formatThaiDate($dateString)
    {
        if (empty($dateString)) return '';
        
        $date = Carbon::parse($dateString);
        $day = $date->day;
        $month = $this->getThaiMonth($date->month);
        $year = $date->year + 543; // Buddhist Era
        
        return "{$day} {$month} {$year}";
    }
    
    /**
     * Get default remarks if none provided
     */
    private function getDefaultRemarks($evaluationDate, $roundMonth)
    {
        $year = $evaluationDate->year + 543; // Buddhist Era
        
        if ($roundMonth == 'เมษายน') {
            $prevYear = $year - 1;
            return "เลื่อนขั้นเงินเดือนตามผลการประเมิน (รอบ 1 ต.ค. {$prevYear} - 31 มี.ค. {$year})";
        } elseif ($roundMonth == 'ตุลาคม') {
            return "เลื่อนขั้นเงินเดือนตามผลการประเมิน (รอบ 1 เม.ย. - 30 ก.ย. {$year})";
        } else {
            return "เลื่อนขั้นเงินเดือนกรณีพิเศษ";
        }
    }

    public function viewPromotionDetail($promotionId)
    {
        // Find the selected promotion from the promotions array
        $this->selectedPromotion = collect($this->promotions)->firstWhere('id', $promotionId);
        
        // Dispatch event to show modal
        $this->dispatch('show-promotion-detail');
    }

    public function downloadPromotion($promotionId)
    {
        // Find the selected promotion
        $this->selectedPromotion = collect($this->promotions)->firstWhere('id', $promotionId);
        
        // Show preview modal first
        $this->dispatch('show-pdf-preview');
    }

    public function render()
    {
        return view('livewire.salary-promotion');
    }
}
