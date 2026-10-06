<?php

namespace App\Livewire\Admin;

use App\Support\AdminAccess;
use App\Support\LeaveTypeCatalog;
use App\Services\ThaiHolidayService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveManagement extends Component
{
    public function boot(): void
    {
        $this->ensureAdministrator();
    }
    use WithPagination;

    public const STATUSES = [
        'pending' => 'รออนุมัติ',
        'step1_approved' => 'อนุมัติขั้นที่ 1',
        'approved' => 'อนุมัติแล้ว',
        'rejected' => 'ไม่อนุมัติ',
        'cancelled' => 'ยกเลิก',
    ];

    private const INACTIVE_STATUSES = ['rejected', 'cancelled'];

    public string $search = '';
    public string $statusFilter = '';
    public string $leaveTypeFilter = '';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $pendingCancellationId = null;

    public string $personSearch = '';
    public string $cid = '';
    public string $personName = '';
    public ?int $leave_type_id = null;
    public string $start_date = '';
    public string $end_date = '';
    public string $duration = 'full';
    public string $reason = '';

    public function mount(): void
    {
        $this->ensureAdministrator();
        LeaveTypeCatalog::ensureAdditionalTypes();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'leaveTypeFilter'], true)) {
            $this->resetPage();
        }

        if (in_array($property, ['start_date', 'end_date'], true) && $this->workingDays() !== 1) {
            $this->duration = 'full';
        }
    }

    public function openCreate(): void
    {
        $this->ensureAdministrator();
        $this->resetForm();
        $this->dispatch('leave-form-opened');
    }

    public function selectPerson(string $cid): void
    {
        $this->ensureAdministrator();
        $person = DB::table('person')->where('cid', $cid)->first(['cid', 'pname', 'fname', 'lname']);
        abort_unless($person, 404);

        $this->cid = $person->cid;
        $this->personName = $this->displayName($person);
        $this->personSearch = '';
        $this->resetErrorBag('cid');
    }

    public function clearPerson(): void
    {
        abort_if($this->editingId !== null, 403);
        $this->reset(['cid', 'personName', 'personSearch']);
    }

    public function edit(int $id): void
    {
        $this->ensureAdministrator();
        $leave = DB::table('leave_requests')->where('id', $id)->first();
        abort_unless($leave, 404);
        abort_if($leave->status === 'cancelled', 403);

        $this->resetForm();
        $this->editingId = $leave->id;
        $this->cid = (string) $leave->cid;
        $this->personName = (string) $leave->external_name;
        $this->leave_type_id = (int) $leave->leave_type_id;
        $this->start_date = CarbonImmutable::parse($leave->start_date)->toDateString();
        $this->end_date = CarbonImmutable::parse($leave->end_date)->toDateString();
        $this->duration = (float) $leave->total_days === 0.5 ? 'half' : 'full';
        $this->reason = (string) $leave->reason;
        $this->dispatch('leave-form-opened');
    }

    public function save(): void
    {
        $this->ensureAdministrator();
        $this->validate($this->formRules(), $this->formMessages());

        $days = $this->workingDays();
        if ($days === 0) {
            $this->addError('end_date', 'ช่วงวันที่เลือกไม่มีวันทำการ (จันทร์–ศุกร์)');

            return;
        }
        if ($this->hasOverlappingLeave()) {
            $this->addError('start_date', 'บุคลากรนี้มีรายการลาที่ทับช่วงวันที่นี้อยู่แล้ว');

            return;
        }

        $data = [
            'leave_type_id' => $this->leave_type_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'total_days' => $days === 1 && $this->duration === 'half' ? '0.5' : number_format($days, 1, '.', ''),
            'reason' => trim($this->reason) !== '' ? trim($this->reason) : null,
            'updated_at' => now(),
        ];

        if ($this->editingId !== null) {
            $updated = DB::table('leave_requests')
                ->where('id', $this->editingId)
                ->where('status', '!=', 'cancelled')
                ->update($data);
            abort_if($updated === 0, 404);
            $message = 'ปรับปรุงรายการลาแล้ว';
        } else {
            DB::table('leave_requests')->insert([
                ...$data,
                'cid' => $this->cid,
                'external_name' => $this->personName,
                'status' => 'approved',
                'step1_status' => 'approved',
                'step2_status' => 'approved',
                'created_at' => now(),
            ]);
            $message = 'เพิ่มรายการลาแล้ว';
        }

        $this->resetForm();
        $this->dispatch('leave-saved', message: $message);
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->dispatch('leave-form-closed');
    }

    public function prepareCancel(int $id): void
    {
        $this->ensureAdministrator();
        abort_unless(
            DB::table('leave_requests')->where('id', $id)->where('status', '!=', 'cancelled')->exists(),
            404,
        );

        $this->pendingCancellationId = $id;
        $this->dispatch('leave-cancel-opened');
    }

    public function cancelLeave(): void
    {
        $this->ensureAdministrator();

        if ($this->pendingCancellationId === null) {
            return;
        }

        DB::table('leave_requests')
            ->where('id', $this->pendingCancellationId)
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        $this->pendingCancellationId = null;
        $this->dispatch('leave-cancel-closed');
        $this->dispatch('leave-saved', message: 'ยกเลิกรายการลาแล้ว');
    }

    public function dismissCancel(): void
    {
        $this->pendingCancellationId = null;
        $this->dispatch('leave-cancel-closed');
    }

    /** Working days excluding weekends and Thai public holidays, inclusive. */
    public function workingDays(): int
    {
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $this->start_date);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $this->end_date);
        } catch (\Throwable) {
            return 0;
        }

        if (!$start || !$end || $start->gt($end) || $start->diffInDays($end) > 366) {
            return 0;
        }

        $holidays = app(ThaiHolidayService::class)->dateKeysBetween($start, $end);
        $days = 0;
        foreach (CarbonPeriod::create($start, $end) as $date) {
            $days += $date->isWeekend() || isset($holidays[$date->toDateString()]) ? 0 : 1;
        }

        return $days;
    }

    public function render()
    {
        $this->ensureAdministrator();
        $search = trim($this->search);
        $personSearch = trim($this->personSearch);
        $days = $this->workingDays();

        return view('livewire.admin.leave-management', [
            'leaves' => DB::table('leave_requests as requests')
                ->leftJoin('leave_types as types', 'types.id', '=', 'requests.leave_type_id')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('requests.cid', 'like', "%{$search}%")
                            ->orWhere('requests.external_name', 'like', "%{$search}%");
                    });
                })
                ->when($this->statusFilter !== '', fn ($query) => $query->where('requests.status', $this->statusFilter))
                ->when($this->leaveTypeFilter !== '', fn ($query) => $query->where('requests.leave_type_id', (int) $this->leaveTypeFilter))
                ->orderByDesc('requests.start_date')
                ->orderByDesc('requests.id')
                ->select([
                    'requests.id', 'requests.cid', 'requests.external_name', 'requests.start_date',
                    'requests.end_date', 'requests.total_days', 'requests.reason', 'requests.status',
                    'types.name as leave_type',
                ])
                ->paginate(20),
            'leaveTypes' => DB::table('leave_types')
                ->orderByDesc('is_active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']),
            'personResults' => $this->cid === '' && mb_strlen($personSearch) >= 2
                ? DB::table('person')
                    ->where(function ($query) use ($personSearch) {
                        // Every word must match, so "สมชาย ใจดี" finds first + last name.
                        foreach (preg_split('/\s+/u', $personSearch) as $term) {
                            $query->where(function ($query) use ($term) {
                                $query->where('cid', 'like', "%{$term}%")
                                    ->orWhere('fname', 'like', "%{$term}%")
                                    ->orWhere('lname', 'like', "%{$term}%");
                            });
                        }
                    })
                    ->orderBy('fname')
                    ->orderBy('lname')
                    ->limit(10)
                    ->get(['cid', 'pname', 'fname', 'lname', 'office_name'])
                : collect(),
            'workingDays' => $days,
            'statuses' => self::STATUSES,
        ]);
    }

    private function formRules(): array
    {
        return [
            // Synced leave rows may belong to people missing from person, and
            // the owner of an existing row is not editable.
            'cid' => $this->editingId !== null
                ? ['nullable']
                : ['required', 'digits:13', Rule::exists('person', 'cid')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'duration' => ['required', Rule::in(['full', 'half'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function formMessages(): array
    {
        return [
            'cid.required' => 'กรุณาเลือกรายชื่อบุคลากร',
            'cid.exists' => 'ไม่พบบุคลากรที่เลือก',
            'leave_type_id.required' => 'กรุณาเลือกประเภทการลา',
            'leave_type_id.exists' => 'ไม่พบประเภทการลาที่เลือก',
            'start_date.required' => 'กรุณาเลือกวันที่เริ่มต้น',
            'end_date.required' => 'กรุณาเลือกวันที่สิ้นสุด',
            'end_date.after_or_equal' => 'วันที่สิ้นสุดต้องไม่อยู่ก่อนวันที่เริ่มต้น',
            'reason.max' => 'หมายเหตุต้องไม่เกิน 1,000 ตัวอักษร',
        ];
    }

    private function hasOverlappingLeave(): bool
    {
        return DB::table('leave_requests')
            ->where('cid', $this->cid)
            ->whereNotIn('status', self::INACTIVE_STATUSES)
            ->where('start_date', '<=', $this->end_date)
            ->where('end_date', '>=', $this->start_date)
            ->when($this->editingId !== null, fn ($query) => $query->where('id', '!=', $this->editingId))
            ->exists();
    }

    private function displayName(object $person): string
    {
        return trim(implode(' ', array_filter([$person->pname, $person->fname, $person->lname])));
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'personSearch', 'cid', 'personName', 'leave_type_id',
            'start_date', 'end_date', 'duration', 'reason',
        ]);
        $this->resetErrorBag();
    }

    private function ensureAdministrator(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'leave'), 403);
    }
}
