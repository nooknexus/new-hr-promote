<?php

namespace App\Livewire;

use App\Services\LeaveEntitlementService;
use App\Support\LeaveTypeCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LeaveHistory extends Component
{
    #[Locked]
    public array $years = [];

    #[Locked]
    public ?int $selectedYear = null;

    #[Locked]
    public array $leaveHistory = [];

    #[Locked]
    public ?array $annualLeaveEntitlement = null;

    #[Locked]
    public array $leaveTypeTotals = [];

    public function mount(): void
    {
        $pid = Auth::user()->pid ?? null;

        if (!$pid) {
            return;
        }

        // The current fiscal year is always offered, even before any leave is recorded in it.
        $this->years = DB::table('leave_requests')
            ->where('cid', $pid)
            ->pluck('start_date')
            ->map(fn ($startDate): int => $this->fiscalYearForDate((string) $startDate))
            ->push($this->fiscalYearForDate(Carbon::now()->toDateString()))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    public function selectYear(int $year): void
    {
        if (!in_array($year, $this->years, true)) {
            return;
        }

        $this->selectedYear = $year;
        $pid = Auth::user()->pid ?? null;

        if (!$pid) {
            $this->leaveHistory = [];
            $this->annualLeaveEntitlement = null;
            $this->leaveTypeTotals = [];
            return;
        }

        [$startDate, $endDate] = $this->fiscalYearDateRange($year);

        $this->leaveHistory = DB::table('leave_requests as requests')
            ->leftJoin('leave_types as types', 'types.id', '=', 'requests.leave_type_id')
            ->where('requests.cid', $pid)
            ->whereBetween('requests.start_date', [$startDate, $endDate])
            ->orderByDesc('requests.start_date')
            ->orderByDesc('requests.id')
            ->get([
                'requests.id',
                'types.name as leave_type',
                'requests.start_date',
                'requests.end_date',
                'requests.total_days',
                'requests.reason',
                'requests.status',
            ])
            ->map(fn ($leave) => [
                'id' => $leave->id,
                'leave_type' => $leave->leave_type ?: 'ไม่ระบุประเภท',
                'start_date' => $this->formatThaiDate($leave->start_date),
                'end_date' => $this->formatThaiDate($leave->end_date),
                'total_days' => $leave->total_days,
                'reason' => $leave->reason,
                'status' => $this->statusLabel($leave->status),
                'status_style' => $this->statusStyle($leave->status),
            ])
            ->all();

        $leaveTypeNames = array_values(array_unique([
            'ลาพักผ่อน',
            'ลาป่วย',
            'ลากิจ',
            ...DB::table('leave_types')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('name')
                ->all(),
        ]));
        $totalsByType = DB::table('leave_requests as requests')
            ->join('leave_types as types', 'types.id', '=', 'requests.leave_type_id')
            ->where('requests.cid', $pid)
            ->where('requests.status', 'approved')
            ->whereBetween('requests.start_date', [$startDate, $endDate])
            ->select('types.name', DB::raw('SUM(requests.total_days) as total_days'))
            ->groupBy('types.name')
            ->pluck('total_days', 'name');
        $recordedTypeNames = array_column($this->leaveHistory, 'leave_type');
        $this->leaveTypeTotals = collect($leaveTypeNames)
            ->filter(fn (string $name): bool => !in_array($name, LeaveTypeCatalog::ADDITIONAL_TYPES, true)
                || in_array($name, $recordedTypeNames, true))
            ->mapWithKeys(fn (string $name): array => [$name => (float) ($totalsByType[$name] ?? 0)])
            ->all();

        $person = DB::table('person')
            ->where('cid', $pid)
            ->first(['type_name', 'employment_started_at']);

        $annualLeaveUsedByFiscalYear = $this->annualLeaveUsedByFiscalYear($pid, $endDate);
        $usedAnnualLeaveDays = $annualLeaveUsedByFiscalYear[$year] ?? 0.0;
        $firstRecordedFiscalYear = $this->firstRecordedFiscalYear($pid, $endDate);
        $carryoverDays = $this->carryoverFromPreviousFiscalYears(
            $person,
            $year,
            $firstRecordedFiscalYear,
            $annualLeaveUsedByFiscalYear,
        );

        $this->annualLeaveEntitlement = app(LeaveEntitlementService::class)
            ->forPersonnel($person, $year, $usedAnnualLeaveDays, carryoverDays: $carryoverDays);
    }

    /** @return array{string, string} */
    private function fiscalYearDateRange(int $year): array
    {
        $fiscalYearEnd = $year - 543;

        return [($fiscalYearEnd - 1) . '-10-01', $fiscalYearEnd . '-09-30'];
    }

    /** @return array<int, float> */
    private function annualLeaveUsedByFiscalYear(string $pid, string $throughDate): array
    {
        return DB::table('leave_requests as requests')
            ->join('leave_types as types', 'types.id', '=', 'requests.leave_type_id')
            ->where('requests.cid', $pid)
            ->where('types.name', 'ลาพักผ่อน')
            ->where('requests.status', 'approved')
            ->where('requests.start_date', '<=', $throughDate)
            ->get(['requests.start_date', 'requests.total_days'])
            ->reduce(function (array $totals, object $leave): array {
                $fiscalYear = $this->fiscalYearForDate($leave->start_date);
                $totals[$fiscalYear] = ($totals[$fiscalYear] ?? 0.0) + (float) $leave->total_days;

                return $totals;
            }, []);
    }

    private function firstRecordedFiscalYear(string $pid, string $throughDate): ?int
    {
        $firstDate = DB::table('leave_requests')
            ->where('cid', $pid)
            ->where('start_date', '<=', $throughDate)
            ->min('start_date');

        return $firstDate ? $this->fiscalYearForDate($firstDate) : null;
    }

    /** @param array<int, float> $annualLeaveUsedByFiscalYear */
    private function carryoverFromPreviousFiscalYears(
        ?object $person,
        int $selectedYear,
        ?int $firstRecordedFiscalYear,
        array $annualLeaveUsedByFiscalYear,
    ): float {
        if ($person === null || $firstRecordedFiscalYear === null || $firstRecordedFiscalYear > $selectedYear) {
            return 0.0;
        }

        $service = app(LeaveEntitlementService::class);

        // Imported history does not include an opening balance. The first
        // recorded fiscal year can use no more than its permitted carryover
        // quota; each following year receives only the prior year's actual
        // unused balance, capped by that prior year's rule.
        $initialYearEntitlement = $service->forPersonnel($person, $firstRecordedFiscalYear);
        $carryoverDays = (float) $initialYearEntitlement['carryover_cap_days'];

        for ($year = $firstRecordedFiscalYear; $year < $selectedYear; $year++) {
            $previousYearEntitlement = $service->forPersonnel(
                $person,
                $year,
                $annualLeaveUsedByFiscalYear[$year] ?? 0.0,
                carryoverDays: $carryoverDays,
            );
            $carryoverDays = min(
                (float) $previousYearEntitlement['carryover_cap_days'],
                (float) $previousYearEntitlement['remaining_days'],
            );
        }

        return $carryoverDays;
    }

    private function fiscalYearForDate(string $date): int
    {
        $parsedDate = Carbon::parse($date);

        return $parsedDate->year + ($parsedDate->month >= 10 ? 544 : 543);
    }

    private function formatThaiDate(?string $date): string
    {
        if (!$date) {
            return '—';
        }

        $parsedDate = Carbon::parse($date);

        return $parsedDate->format('j/n/') . ($parsedDate->year + 543);
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'รออนุมัติ',
            'step1_approved' => 'อนุมัติขั้นที่ 1',
            'approved' => 'อนุมัติแล้ว',
            'rejected' => 'ไม่อนุมัติ',
            'cancelled' => 'ยกเลิก',
            default => 'ไม่ระบุสถานะ',
        };
    }

    private function statusStyle(?string $status): string
    {
        return match ($status) {
            'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'step1_approved' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'rejected' => 'bg-rose-50 text-rose-700 ring-rose-200',
            default => 'bg-slate-100 text-slate-600 ring-slate-200',
        };
    }

    public function render()
    {
        return view('livewire.leave-history');
    }
}
