<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon; // Add this import

class Home extends Component
{
    public $salaryHistory = [];
    public $incomeExpenseData = null;
    public $currentSalary = 0;
    public $specialAllowance = 0;
    public $totalDeductions = 0;
    public $netIncome = 0;
    public $promotionHistory = [];
    public $totalIncome = 0;    // Added for total yearly income
    public $taxWithheld = 0;    // Added for tax withheld
    public $currentFiscalLeaveSummary = [
        'fiscal_year' => null,
        'total_days' => 0.0,
        'totals' => [
            'ลาพักผ่อน' => 0.0,
            'ลาป่วย' => 0.0,
            'ลากิจ' => 0.0,
        ],
    ];
    
    public function mount()
    {
        // Get current user's pid (13-digit national ID)
        $pid = Auth::user()->pid ?? ''; // Make sure this field exists in your users table
        
        if (!empty($pid)) {
            // Fetch current salary amount
            $currentSalaryResult = DB::select("
                SELECT 
                    incomeSalaryAmount
                FROM 
                    data_salary 
                WHERE 
                    cid = ? 
                ORDER BY 
                    CONCAT(yearNO, monthNO) DESC 
                LIMIT 1
            ", [$pid]);
            
            if (!empty($currentSalaryResult)) {
                $this->currentSalary = $currentSalaryResult[0]->incomeSalaryAmount;
            }
            
            // Fetch salary history data using the provided SQL query
            $this->salaryHistory = DB::select("
                SELECT 
                    yearNO, monthNO, summaryIncomeAmount, summaryOutcomeAmount, summaryTotalAmount
                FROM 
                    data_salary 
                WHERE 
                    cid = ? 
                ORDER BY 
                    CONCAT(yearNO, monthNO) DESC 
                LIMIT 12
            ", [$pid]);
            
            // Reverse the array to show chronological order (oldest to newest)
            if (!empty($this->salaryHistory)) {
                $this->salaryHistory = array_reverse($this->salaryHistory);
            }
            
            // Fetch income vs expense data for pie chart
            $incomeExpenseResult = DB::select("
                SELECT 
                    summaryIncomeAmount, summaryOutcomeAmount
                FROM 
                    data_salary 
                WHERE 
                    cid = ? 
                ORDER BY 
                    CONCAT(yearNO, monthNO) DESC 
                LIMIT 1
            ", [$pid]);
            
            if (!empty($incomeExpenseResult)) {
                $this->incomeExpenseData = $incomeExpenseResult[0];
            }
            
            // Fetch special allowance total
            $specialAllowanceResult = DB::select("
                SELECT
                    (
                        COALESCE(s.incomeSalaryBackPayAmount, 0) +
                        COALESCE(s.incomeManagerMoneyAmount, 0) +
                        COALESCE(s.incomeManagerMoneyBackPayAmount, 0) +
                        COALESCE(s.incomePositionKingAmount, 0) +
                        COALESCE(s.incomeWarAmount, 0) +
                        COALESCE(s.incomeCourseFeeAmount, 0) +
                        COALESCE(s.incomeBreakLawAmount, 0) +
                        COALESCE(s.incomeSpecialPlaceAmount, 0) +
                        COALESCE(s.incomeSouthAmount, 0) +
                        COALESCE(s.incomePositionMoneyAmount, 0) +
                        COALESCE(s.incomePositionMoneyBackPayAmount, 0) +
                        COALESCE(s.incomeLv8Amount, 0) +
                        COALESCE(s.incomeLv8BackPayAmount, 0) +
                        COALESCE(s.incomeLv1to7Amount, 0) +
                        COALESCE(s.incomeSpecialHightAmount, 0) +
                        COALESCE(s.incomeHouseRentAmount, 0) +
                        COALESCE(s.incomeChildFeeAmount, 0) +
                        COALESCE(s.incomeChildEducationFeeAmount, 0) +
                        COALESCE(s.incomeRewardAmount, 0) +
                        COALESCE(s.incomeAdd1Amount, 0) +
                        COALESCE(s.incomeAdd2Amount, 0) +
                        COALESCE(s.incomeAdd3Amount, 0) +
                        COALESCE(s.incomeAdd4Amount, 0) +
                        COALESCE(s.incomeOtherAmount, 0)
                    ) AS otherIncome
                FROM
                    data_salary s
                WHERE
                    s.cid = ?
                ORDER BY
                    CONCAT(s.yearNO, s.monthNO) DESC
                LIMIT 1
            ", [$pid]);
            
            if (!empty($specialAllowanceResult)) {
                $this->specialAllowance = $specialAllowanceResult[0]->otherIncome;
            }
            
            // Fetch total deductions
            $deductionsResult = DB::select("
                SELECT
                    (
                        CAST(COALESCE(s.outcomeTaxAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeHomeLoanAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeCoopAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeEduLoanAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeGPFAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeGPFAddAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeGHBankAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeGSBankAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeUniverLoanAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeTorPorLoanAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeCivilPenaltyAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeRestoreAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeUtilitieAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeClubAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeTeacherDeadAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeCoopDeadAmount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeAdd1Amount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeAdd2Amount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeAdd3Amount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeAdd4Amount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeAdd5Amount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeAdd6Amount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeAdd7Amount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeAdd8Amount, 0) AS DECIMAL(10,2)) +
                        CAST(COALESCE(s.outcomeOtherAmount, 0) AS DECIMAL(10,2))
                    ) AS totalDeductions
                FROM
                    data_salary s
                WHERE
                    s.cid = ?
                ORDER BY
                    CONCAT(s.yearNO, s.monthNO) DESC
                LIMIT 1
            ", [$pid]);
            
            if (!empty($deductionsResult)) {
                $this->totalDeductions = $deductionsResult[0]->totalDeductions;
            }
            
            // Fetch net income amount
            $netIncomeResult = DB::select("
                SELECT
                    summaryTotalAmount
                FROM
                    data_salary s
                WHERE
                    s.cid = ?
                ORDER BY
                    CONCAT(s.yearNO, s.monthNO) DESC
                LIMIT 1
            ", [$pid]);
            
            if (!empty($netIncomeResult)) {
                $this->netIncome = $netIncomeResult[0]->summaryTotalAmount;
            }
            
            // Fetch total income and tax withheld from the most recent tax certificate
            $taxData = DB::select("
                SELECT
                    total_money AS totalIncome,
                    withholding_tax AS taxWithheld
                FROM
                    data_tax
                WHERE
                    CID = ?
                ORDER BY
                    income_year DESC
                LIMIT 1
            ", [$pid]);
            
            if (!empty($taxData)) {
                $this->totalIncome = (float)$taxData[0]->totalIncome;
                $this->taxWithheld = (float)$taxData[0]->taxWithheld;
            }

            $this->loadCurrentFiscalLeaveSummary($pid);
        }
        
        // Load salary promotion history - get only the latest for each round
        $this->loadPromotionHistory();
        
        // For debugging
        if (empty($this->salaryHistory)) {
            \Log::info('No salary data found for user with pid: ' . $pid);
        }
    }

    public function loadPromotionHistory()
    {
        try {
            $user = Auth::user();
            $pid = $user->pid ?? '';
            
            $seenRounds = [];
            $promotionData = DB::table('data_promote')
                ->where('CID', $pid)
                ->whereNull('deleted_at')
                ->orderByDesc('evaluation_date')
                ->get()
                ->filter(function ($row) use (&$seenRounds) {
                    $round = substr($row->evaluation_date, 0, 7);
                    if (isset($seenRounds[$round])) {
                        return false;
                    }
                    $seenRounds[$round] = true;

                    return true;
                })
                ->take(10);
            
            // Transform data for the chart and filter out records with no percentage
            $this->promotionHistory = collect($promotionData)
                ->map(function($item) {
                    $evaluationDate = Carbon::parse($item->evaluation_date);
                    $yearBE = $evaluationDate->year + 543; // Convert to Buddhist Era
                    
                    return [
                        'round' => match ($evaluationDate->month) { 4 => 1, 10 => 2, default => 3 },
                        'year' => $yearBE,
                        'date' => $evaluationDate->format('Y-m-d'),
                        'amount' => $item->sum,
                        'percentage' => $item->percent,
                        'previousSalary' => $item->before_salary,
                        'newSalary' => $item->new_salary
                    ];
                })
                ->filter(function($item) {
                    // Filter out records with no percentage or percentage = 0
                    return !is_null($item['percentage']) && $item['percentage'] > 0;
                })
                ->values() // Re-index the array after filtering
                ->toArray();
            
            // For debugging
            if (empty($this->promotionHistory)) {
                \Log::info('No promotion data found for user with pid: ' . $pid);
            }
            
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error loading promotion history: ' . $e->getMessage());
            $this->promotionHistory = [];
        }
    }

    private function loadCurrentFiscalLeaveSummary(string $pid): void
    {
        $today = Carbon::today();
        $fiscalYear = $today->year + ($today->month >= 10 ? 544 : 543);
        $fiscalYearEnd = $fiscalYear - 543;
        $startDate = Carbon::create($fiscalYearEnd - 1, 10, 1)->toDateString();
        $endDate = Carbon::create($fiscalYearEnd, 9, 30)->toDateString();

        $totalsByType = DB::table('leave_requests as requests')
            ->join('leave_types as types', 'types.id', '=', 'requests.leave_type_id')
            ->where('requests.cid', $pid)
            ->where('requests.status', 'approved')
            ->whereBetween('requests.start_date', [$startDate, $endDate])
            ->select('types.name', DB::raw('SUM(requests.total_days) as total_days'))
            ->groupBy('types.name')
            ->pluck('total_days', 'name');

        $this->currentFiscalLeaveSummary = [
            'fiscal_year' => $fiscalYear,
            'total_days' => (float) $totalsByType->sum(),
            'totals' => collect(['ลาพักผ่อน', 'ลาป่วย', 'ลากิจ'])
                ->mapWithKeys(fn (string $type): array => [$type => (float) ($totalsByType[$type] ?? 0)])
                ->all(),
        ];
    }

    public function render()
    {
        return view('livewire.home');
    }
}
