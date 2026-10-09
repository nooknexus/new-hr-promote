<?php

namespace App\Livewire\Admin;

use App\Services\LeaveExcelImportService;
use App\Services\ThaiHolidayService;
use App\Support\AdminAccess;
use App\Support\LeaveTypeCatalog;
use App\Support\OfficeScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class LeaveManagement extends Component
{
    public function boot(): void
    {
        $this->ensureAdministrator();
    }

    use WithFileUploads;
    use WithPagination;

    public mixed $importFile = null;

    public function openImport(): void
    {
        $this->ensureAdministrator();
        abort_if(OfficeScope::officeFor(Auth::user()) === '', 403);
        $this->reset('importFile');
        $this->resetErrorBag('importFile');
        $this->dispatch('leave-import-opened');
    }

    public function closeImport(): void
    {
        $this->reset('importFile');
        $this->resetErrorBag('importFile');
        $this->dispatch('leave-import-closed');
    }

    public function importExcel(LeaveExcelImportService $service): void
    {
        $this->ensureAdministrator();
        abort_if(OfficeScope::officeFor(Auth::user()) === '', 403);
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx', 'max:5120']], [
            'importFile.required' => 'กรุณาเลือกไฟล์ Excel ก่อนนำเข้า',
            'importFile.mimes' => 'รองรับเฉพาะไฟล์ .xlsx',
            'importFile.max' => 'ไฟล์ต้องมีขนาดไม่เกิน 5MB',
        ]);
        try {
            $result = $service->import($this->importFile->getRealPath(), Auth::user());
        } catch (\RuntimeException $error) {
            $this->addError('importFile', $error->getMessage());

            return;
        }
        $this->closeImport();
        $this->resetPage();
        $this->dispatch('leave-saved', message: "นำเข้าแล้ว {$result['created']} รายการ ข้าม {$result['skipped']} รายการ (นอกต้นสังกัด {$result['out_of_office']} รายการ)");
    }

    public const STATUSES = [
        'pending' => 'รออนุมัติ',
        'step1_approved' => 'อนุมัติขั้นที่ 1',
        'approved' => 'อนุมัติแล้ว',
        'rejected' => 'ไม่อนุมัติ',
        'cancelled' => 'ยกเลิก',
    ];

    private const INACTIVE_STATUSES = ['rejected', 'cancelled'];

    private const MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    public string $search = '';

    public string $statusFilter = '';

    public string $leaveTypeFilter = '';

    public string $officeFilter = '';

    public string $yearFilter = '';

    public string $monthFilter = '';

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
        if (in_array($property, ['search', 'statusFilter', 'leaveTypeFilter', 'officeFilter', 'yearFilter', 'monthFilter'], true)) {
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
        $person = $this->scopedPersonnel()->where('cid', $cid)->first(['cid', 'pname', 'fname', 'lname']);
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
        $leave = $this->scopedLeaves()->where('requests.id', $id)->first(['requests.*']);
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
        // The stored owner determines both overlap checks and lock order when editing.
        if ($this->editingId !== null) {
            $existing = $this->scopedLeaves()->where('requests.id', $this->editingId)->first(['requests.cid']);
            abort_unless($existing, 404);
            $this->cid = (string) $existing->cid;
        }
        $message = $this->editingId !== null ? 'ปรับปรุงรายการลาแล้ว' : 'เพิ่มรายการลาแล้ว';
        $saved = DB::transaction(function () use ($days): bool {
            $person = $this->scopedPersonnel()->where('cid', $this->cid)->lockForUpdate()->first();
            abort_if($person === null && ($this->editingId === null || OfficeScope::officeFor(Auth::user()) !== null), 404);
            if ($this->editingId !== null) {
                $current = DB::table('leave_requests')->where('id', $this->editingId)->lockForUpdate()->first(['cid', 'status']);
                abort_if(! $current || (string) $current->cid !== $this->cid || $current->status === 'cancelled', 404);
            }
            if ($this->hasOverlappingLeave()) {
                $this->addError('start_date', 'บุคลากรนี้มีรายการลาที่ทับช่วงวันที่นี้อยู่แล้ว');

                return false;
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
                abort_unless($this->scopedLeaves()->where('requests.id', $this->editingId)->exists(), 404);
                $updated = DB::table('leave_requests')
                    ->where('id', $this->editingId)
                    ->where('status', '!=', 'cancelled')
                    ->update($data);
                abort_if($updated === 0, 404);
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
            }

            return true;
        });
        if (! $saved) {
            return;
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
            $this->scopedLeaves()->where('requests.id', $id)->where('requests.status', '!=', 'cancelled')->exists(),
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

        abort_unless($this->scopedLeaves()->where('requests.id', $this->pendingCancellationId)->exists(), 404);
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
        return app(ThaiHolidayService::class)->workingDaysBetween($this->start_date, $this->end_date);
    }

    #[Title('จัดการข้อมูลการลา')]
    public function render()
    {
        $this->ensureAdministrator();
        $search = trim($this->search);
        $personSearch = trim($this->personSearch);
        $days = $this->workingDays();

        return view('livewire.admin.leave-management', [
            'leaves' => $this->scopedLeaves(filtersByOffice: trim($this->officeFilter) !== '')
                ->leftJoin('leave_types as types', 'types.id', '=', 'requests.leave_type_id')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('requests.cid', 'like', "%{$search}%")
                            ->orWhere('requests.external_name', 'like', "%{$search}%");
                    });
                })
                ->when($this->statusFilter !== '', fn ($query) => $query->where('requests.status', $this->statusFilter))
                ->when($this->leaveTypeFilter !== '', fn ($query) => $query->where('requests.leave_type_id', (int) $this->leaveTypeFilter))
                ->when(trim($this->officeFilter) !== '', fn ($query) => $query->where('owner.office_id', trim($this->officeFilter)))
                ->tap(fn ($query) => $this->applyPeriodFilter($query))
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
                ? $this->scopedPersonnel()
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
            'offices' => OfficeScope::options(Auth::user(), includeInactive: true),
            'years' => $this->scopedLeaves()
                ->selectRaw('DISTINCT SUBSTR(requests.start_date, 1, 4) AS leave_year')
                ->pluck('leave_year')
                ->map(fn ($year) => (int) $year)
                ->filter()
                ->sortDesc()
                ->values(),
            'months' => self::MONTHS,
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
                : ['required', 'digits:13', $this->scopedPersonExistsRule()],
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

    /**
     * Keep leaves that overlap the selected Gregorian year and/or month, so a
     * leave spanning two months appears under both. A month without a year
     * matches leaves starting in that month of any year.
     */
    private function applyPeriodFilter(Builder $query): void
    {
        $year = ctype_digit($this->yearFilter) ? (int) $this->yearFilter : null;
        $month = ctype_digit($this->monthFilter) && isset(self::MONTHS[(int) $this->monthFilter]) ? (int) $this->monthFilter : null;

        if ($year === null) {
            if ($month !== null) {
                $query->whereMonth('requests.start_date', $month);
            }

            return;
        }

        $start = CarbonImmutable::create($year, $month ?? 1, 1);
        $end = $month === null ? $start->endOfYear() : $start->endOfMonth();
        $query->where('requests.start_date', '<=', $end->toDateString())
            ->where('requests.end_date', '>=', $start->toDateString());
    }

    private function scopedPersonnel(): Builder
    {
        return OfficeScope::apply(DB::table('person'), Auth::user());
    }

    /** Leave requests of personnel the current user may manage, aliased as requests. */
    /**
     * Leave requests the current user may manage, aliased as requests.
     *
     * person is joined (as owner) only when an office condition needs it: the
     * two cid columns use different collations, so an unconditioned join cannot
     * use person's primary key and scans every person for every leave.
     */
    private function scopedLeaves(bool $filtersByOffice = false): Builder
    {
        $query = DB::table('leave_requests as requests');

        if (! $filtersByOffice && OfficeScope::officeFor(Auth::user()) === null) {
            return $query;
        }

        $query->leftJoin('person as owner', 'owner.cid', '=', 'requests.cid');

        return OfficeScope::apply($query, Auth::user(), 'owner.office_id');
    }

    private function scopedPersonExistsRule(): Exists
    {
        return Rule::exists('person', 'cid')
            ->where(fn ($query) => OfficeScope::apply($query, Auth::user()));
    }

    private function ensureAdministrator(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'leave'), 403);
    }
}
