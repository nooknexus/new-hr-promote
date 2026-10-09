<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Title;
use App\Support\AdminAccess;
use App\Support\OfficeScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class SalaryPromotionManagement extends Component
{
    #[On('salary-import-completed')]
    public function refreshImportedList(): void
    {
        $this->resetPage();
    }

    public function boot(): void
    {
        $this->ensureAdministrator();
    }
    use WithPagination;

    public string $search = '';
    public string $yearFilter = '';
    public string $officeFilter = '';
    public string $promoteFilter = '';
    public string $statusFilter = 'active';
    public string $personSearch = '';
    public string $cid = '';
    public string $personName = '';
    public string $evaluation_date = '';
    public string $promote = 'Y';
    public string $before_salary = '';
    public string $new_salary = '';
    public string $base = '';
    public string $percent = '';
    public string $extra = '';
    public string $office = '';
    public string $department = '';
    public string $ps = '';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?string $loadedFingerprint = null;

    #[Locked]
    public ?int $pendingActionId = null;

    #[Locked]
    public bool $pendingRestore = false;

    public function mount(): void
    {
        $this->ensureAdministrator();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'yearFilter', 'officeFilter', 'promoteFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }

        if ($property === 'evaluation_date') {
            $this->refreshCalculatedSalary(refreshBase: true);
        } elseif (in_array($property, ['percent', 'base'], true)) {
            $this->refreshCalculatedSalary();
        }
    }

    public function openCreate(): void
    {
        $this->ensureAdministrator();
        $this->resetForm();
        $this->dispatch('promotion-form-opened');
    }

    public function selectPerson(string $cid): void
    {
        $this->ensureAdministrator();
        abort_if($this->editingId !== null, 403);
        $person = $this->scopedPersonnel()->where('cid', $cid)->first(['cid', 'pname', 'fname', 'lname', 'office_name']);
        abort_unless($person, 404);

        $this->cid = $person->cid;
        $this->personName = trim(implode(' ', array_filter([$person->pname, $person->fname, $person->lname])));
        $this->office = (string) ($person->office_name ?? '');
        $this->personSearch = '';
        $this->resetErrorBag('cid');
        $this->refreshCalculatedSalary(refreshBase: true);
    }

    public function clearPerson(): void
    {
        $this->ensureAdministrator();
        abort_if($this->editingId !== null, 403);
        $this->cid = '';
        $this->personName = '';
        $this->personSearch = '';
        $this->office = '';
        $this->before_salary = '';
        $this->base = '';
        $this->new_salary = '';
    }

    public function edit(int $id): void
    {
        $this->ensureAdministrator();
        $row = $this->scopedPromotions()
            ->where('promotions.id', $id)
            ->whereNull('promotions.deleted_at')
            ->select('promotions.*', 'people.pname', 'people.fname', 'people.lname')
            ->first();
        abort_unless($row, 404);

        $this->resetForm();
        $this->editingId = (int) $row->id;
        $this->loadedFingerprint = $this->fingerprint($row);
        $this->cid = (string) $row->CID;
        $this->personName = trim(implode(' ', array_filter([$row->pname, $row->fname, $row->lname]))) ?: 'ไม่พบบุคลากร';
        foreach (['evaluation_date', 'promote', 'before_salary', 'new_salary', 'base', 'percent', 'extra', 'office', 'department', 'ps'] as $field) {
            $this->{$field} = (string) ($row->{$field} ?? '');
        }
        $this->dispatch('promotion-form-opened');
    }

    public function save(): void
    {
        $this->ensureAdministrator();
        $original = $this->editingId === null ? null : $this->scopedPromotions()
            ->where('promotions.id', $this->editingId)->whereNull('promotions.deleted_at')->first(['promotions.*']);
        abort_if($this->editingId !== null && ! $original, 404);

        // Recalculate salary amounts from stored records and the editable, validated base.
        $this->refreshCalculatedSalary($original);

        $validated = $this->validate($this->rules($original));
        $cid = $original?->CID ?? $validated['cid'];
        $duplicate = DB::table('data_promote')
            ->where('CID', $cid)
            ->whereDate('evaluation_date', $validated['evaluation_date'])
            ->when($this->editingId !== null, fn (Builder $query) => $query->where('id', '!=', $this->editingId))
            ->exists();
        if ($duplicate) {
            $this->addError('evaluation_date', 'บุคลากรนี้มีรายการในวันประเมินดังกล่าวแล้ว');

            return;
        }

        $before = $this->optionalMoney($validated['before_salary'] ?? null);
        $new = $this->optionalMoney($validated['new_salary'] ?? null);
        $salaryChanged = ! $original
            || $before !== $this->optionalMoney($original->before_salary)
            || $new !== $this->optionalMoney($original->new_salary);
        $increase = $salaryChanged
            ? ($before !== null && $new !== null ? number_format((float) $new - (float) $before, 2, '.', '') : null)
            : $original->sum;

        $values = [
            'evaluation_date' => $validated['evaluation_date'],
            'promote' => $validated['promote'] ?: null,
            'before_salary' => $before,
            'new_salary' => $new,
            'sum' => $increase,
            'base' => $this->optionalMoney($validated['base'] ?? null),
            'percent' => $this->optionalPercent($validated['percent'] ?? null, $original),
            'extra' => $this->optionalMoney($validated['extra'] ?? null),
            'office' => trim($validated['office'] ?? '') ?: null,
            'department' => trim($validated['department'] ?? '') ?: null,
            'ps' => trim($validated['ps'] ?? '') ?: null,
            'update_date' => now(),
        ];

        try {
            DB::transaction(function () use ($original, $cid, $values): void {
                if ($original) {
                    $id = (int) $original->id;
                    $current = DB::table('data_promote')->where('id', $id)->lockForUpdate()->first();
                    abort_unless($current && $current->deleted_at === null, 404);
                    if ($this->loadedFingerprint !== $this->fingerprint($current)) {
                        throw ValidationException::withMessages([
                            'evaluation_date' => 'รายการนี้ถูกแก้ไขจากอีกหน้าต่างแล้ว กรุณาปิดและเปิดรายการใหม่ก่อนบันทึก',
                        ]);
                    }
                    DB::table('data_promote')->where('id', $id)->update($values);
                    $action = 'updated';
                    $before = $current;
                } else {
                    $id = DB::table('data_promote')->insertGetId(['CID' => $cid, ...$values]);
                    $action = 'created';
                    $before = null;
                }
                $this->audit($id, $action, $before, DB::table('data_promote')->find($id));
            });
        } catch (QueryException $exception) {
            if (DB::table('data_promote')->where('CID', $cid)->whereDate('evaluation_date', $values['evaluation_date'])
                ->when($this->editingId !== null, fn (Builder $query) => $query->where('id', '!=', $this->editingId))
                ->exists()) {
                $this->addError('evaluation_date', 'บุคลากรนี้มีรายการในวันประเมินดังกล่าวแล้ว');

                return;
            }
            throw $exception;
        }

        $message = $original ? 'ปรับปรุงข้อมูลการเลื่อนขั้นเงินเดือนแล้ว' : 'เพิ่มข้อมูลการเลื่อนขั้นเงินเดือนแล้ว';
        $this->resetForm();
        $this->dispatch('promotion-form-closed');
        $this->dispatch('promotion-saved', message: $message);
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->dispatch('promotion-form-closed');
    }

    public function prepareArchive(int $id): void
    {
        $this->prepareAction($id, false);
    }

    public function prepareRestore(int $id): void
    {
        $this->prepareAction($id, true);
    }

    public function archive(): void
    {
        $this->changeArchiveState(false);
    }

    public function restore(): void
    {
        $this->changeArchiveState(true);
    }

    public function dismissAction(): void
    {
        $this->pendingActionId = null;
        $this->pendingRestore = false;
        $this->dispatch('promotion-confirm-closed');
    }

    #[Title('จัดการเลื่อนขั้นเงินเดือน')]
    public function render()
    {
        $this->ensureAdministrator();
        $search = trim($this->search);
        $personSearch = trim($this->personSearch);
        $query = $this->scopedPromotions()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('promotions.CID', 'like', "%{$search}%")
                        ->orWhere('people.fname', 'like', "%{$search}%")
                        ->orWhere('people.lname', 'like', "%{$search}%");
                });
            })
            ->when($this->yearFilter !== '', function (Builder $query): void {
                if (ctype_digit($this->yearFilter)) {
                    $year = (int) $this->yearFilter - 544;
                    $query->whereBetween('promotions.evaluation_date', ["{$year}-10-01", ($year + 1) . '-09-30']);
                }
            })
            ->when($this->officeFilter !== '', function (Builder $query): void {
                if ($this->officeFilter === 'unmatched') {
                    $query->whereNull('people.cid');
                } else {
                    $query->where('people.office_id', $this->officeFilter);
                }
            })
            ->when($this->promoteFilter !== '', fn (Builder $query) => $query->where('promotions.promote', $this->promoteFilter))
            ->when($this->statusFilter === 'active', fn (Builder $query) => $query->whereNull('promotions.deleted_at'))
            ->when($this->statusFilter === 'archived', fn (Builder $query) => $query->whereNotNull('promotions.deleted_at'));

        return view('livewire.admin.salary-promotion-management', [
            'promotions' => $query->orderByDesc('promotions.evaluation_date')->orderByDesc('promotions.id')
                ->select(['promotions.*', 'people.pname', 'people.fname', 'people.lname', 'people.office_name'])
                ->paginate(20),
            'previewIncrease' => $this->previewIncrease(),
            'years' => $this->scopedPromotions()
                ->selectRaw('DISTINCT SUBSTR(promotions.evaluation_date, 1, 7) AS evaluation_month_key')
                ->pluck('evaluation_month_key')
                ->map(fn (string $yearMonth) => (int) substr($yearMonth, 0, 4)
                    + ((int) substr($yearMonth, 5, 2) >= 10 ? 544 : 543))
                ->unique()->sortDesc()->values(),
            'offices' => OfficeScope::options(Auth::user(), includeInactive: true),
            'personResults' => $this->cid === '' && mb_strlen($personSearch) >= 2
                ? $this->scopedPersonnel()->where(function (Builder $query) use ($personSearch): void {
                    foreach (preg_split('/\s+/u', $personSearch) as $term) {
                        $query->where(function (Builder $query) use ($term): void {
                            $query->where('cid', 'like', "%{$term}%")
                                ->orWhere('fname', 'like', "%{$term}%")
                                ->orWhere('lname', 'like', "%{$term}%");
                        });
                    }
                })->orderBy('fname')->limit(10)->get(['cid', 'pname', 'fname', 'lname', 'office_name'])
                : collect(),
        ]);
    }

    private function rules(?object $original): array
    {
        $recalculate = ! $original
            || $this->evaluation_date !== (string) $original->evaluation_date
            || $this->base !== (string) ($original->base ?? '')
            || $this->percent !== (string) ($original->percent ?? '');
        $salariesUnchanged = $original
            && $this->optionalMoney($this->before_salary) === $this->optionalMoney($original->before_salary)
            && $this->optionalMoney($this->new_salary) === $this->optionalMoney($original->new_salary);

        return [
            'cid' => $original ? ['nullable'] : ['required', 'digits:13', $this->scopedPersonExistsRule()],
            'evaluation_date' => ['required', 'date_format:Y-m-d'],
            'promote' => [$original ? 'nullable' : 'required', Rule::in(['Y', 'N'])],
            'before_salary' => [$recalculate ? 'required' : 'nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'new_salary' => [
                $recalculate ? 'required' : 'nullable', 'numeric', 'min:0', 'max:9999999.99',
                ...($this->before_salary !== '' && ! $salariesUnchanged ? ['gte:before_salary'] : []),
            ],
            'base' => [$recalculate ? 'required' : 'nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'percent' => [$recalculate ? 'required' : 'nullable', function (string $attribute, mixed $value, \Closure $fail) use ($original, $recalculate): void {
                if (! $recalculate && $original && (string) $original->percent === (string) $value) {
                    return;
                }
                if ($value !== '' && (! is_numeric($value) || (float) $value < 0 || (float) $value > 100)) {
                    $fail('ร้อยละต้องเป็นตัวเลขระหว่าง 0 ถึง 100');
                }
            }],
            'extra' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'office' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'ps' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function prepareAction(int $id, bool $restore): void
    {
        $this->ensureAdministrator();
        $query = $this->scopedPromotions()->where('promotions.id', $id);
        $restore ? $query->whereNotNull('promotions.deleted_at') : $query->whereNull('promotions.deleted_at');
        abort_unless($query->exists(), 404);
        $this->pendingActionId = $id;
        $this->pendingRestore = $restore;
        $this->dispatch('promotion-confirm-opened');
    }

    private function changeArchiveState(bool $restore): void
    {
        $this->ensureAdministrator();
        abort_unless($this->pendingActionId !== null, 404);
        $id = $this->pendingActionId;
        abort_unless($this->scopedPromotions()->where('promotions.id', $id)->exists(), 404);

        DB::transaction(function () use ($id, $restore): void {
            $query = DB::table('data_promote')->where('id', $id)->lockForUpdate();
            $restore ? $query->whereNotNull('deleted_at') : $query->whereNull('deleted_at');
            $before = $query->first();
            abort_unless($before, 404);
            DB::table('data_promote')->where('id', $id)->update([
                'deleted_at' => $restore ? null : now(),
                'deleted_by' => $restore ? null : Auth::id(),
                'update_date' => now(),
            ]);
            $this->audit($id, $restore ? 'restored' : 'archived', $before, DB::table('data_promote')->find($id));
        });

        $this->pendingActionId = null;
        $this->pendingRestore = false;
        $this->dispatch('promotion-confirm-closed');
        $this->dispatch('promotion-saved', message: $restore ? 'กู้คืนรายการแล้ว' : 'ยกเลิกรายการแล้ว');
    }

    private function audit(int $id, string $action, ?object $before, ?object $after): void
    {
        DB::table('data_promote_audits')->insert([
            'promotion_id' => $id,
            'actor_id' => Auth::id(),
            'action' => $action,
            'before_data' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after_data' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => now(),
        ]);
    }

    private function fingerprint(object $row): string
    {
        $values = [];
        foreach ([
            'CID', 'evaluation_date', 'promote', 'ps', 'office', 'department', 'before_salary',
            'base', 'percent', 'sum', 'extra', 'new_salary', 'update_date', 'deleted_at',
        ] as $field) {
            $values[$field] = $row->{$field};
        }

        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function money(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function latestPriorPromotion(string $cid, string $date, ?int $excludedId = null): ?object
    {
        return DB::table('data_promote')
            ->where('CID', $cid)
            ->whereNull('deleted_at')
            ->whereDate('evaluation_date', '<', $date)
            ->when($excludedId !== null, fn (Builder $query) => $query->where('id', '!=', $excludedId))
            ->orderByDesc('evaluation_date')
            ->orderByDesc('id')
            ->first();
    }

    private function refreshCalculatedSalary(?object $original = null, bool $refreshBase = false): void
    {
        if ($this->cid === '' || $this->evaluation_date === '') {
            if ($this->editingId === null) {
                $this->before_salary = $this->base = $this->new_salary = '';
            }

            return;
        }

        if ($this->editingId !== null) {
            $original ??= DB::table('data_promote')->where('id', $this->editingId)->whereNull('deleted_at')->first();
            if (! $original) {
                return;
            }

            $this->cid = (string) $original->CID;

            // Preserve imported/legacy figures when changing only descriptive fields.
            if ($this->evaluation_date === (string) $original->evaluation_date
                && $this->percent === (string) ($original->percent ?? '')
                && $this->base === (string) ($original->base ?? '')) {
                $this->before_salary = (string) ($original->before_salary ?? '');
                $this->base = (string) ($original->base ?? '');
                $this->new_salary = (string) ($original->new_salary ?? '');

                return;
            }
        }

        $prior = $this->latestPriorPromotion($this->cid, $this->evaluation_date, $this->editingId);
        $this->before_salary = (string) ($prior?->new_salary ?? '');
        if ($refreshBase) {
            $this->base = (string) ($prior?->base ?? '');
        }

        if ($this->editingId !== null && ! $prior && $original) {
            $this->before_salary = (string) ($original->before_salary ?? '');
            if ($refreshBase) {
                $this->base = (string) ($original->base ?? '');
            }
        }

        $this->new_salary = is_numeric($this->before_salary) && is_numeric($this->base)
            && is_numeric($this->percent) && (float) $this->percent >= 0 && (float) $this->percent <= 100
                ? $this->money((string) ((float) $this->before_salary + (float) $this->base * (float) $this->percent / 100))
                : '';
    }

    private function previewIncrease(): ?string
    {
        if (! is_numeric($this->before_salary) || ! is_numeric($this->new_salary)) {
            return null;
        }

        $increase = (float) $this->new_salary - (float) $this->before_salary;
        if ($increase < 0) {
            return null;
        }

        return number_format($increase, 2);
    }

    private function optionalMoney(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $this->money($value);
    }

    private function optionalPercent(?string $value, ?object $original): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $original && (string) $original->percent === $value ? $value : $this->money($value);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'loadedFingerprint', 'cid', 'personName', 'personSearch', 'evaluation_date', 'promote',
            'before_salary', 'new_salary', 'base', 'percent', 'extra', 'office', 'department', 'ps',
        ]);
        $this->resetErrorBag();
    }

    private function scopedPersonnel(): Builder
    {
        return OfficeScope::apply(DB::table('person'), Auth::user());
    }

    /** Promotions of personnel the current user may manage, aliased as promotions/people. */
    private function scopedPromotions(): Builder
    {
        $query = DB::table('data_promote as promotions')
            ->leftJoin('person as people', 'people.cid', '=', 'promotions.CID');

        return OfficeScope::apply($query, Auth::user(), 'people.office_id');
    }

    private function scopedPersonExistsRule(): Exists
    {
        return Rule::exists('person', 'cid')
            ->where(fn ($query) => OfficeScope::apply($query, Auth::user()));
    }

    private function ensureAdministrator(): void
    {
        abort_unless(AdminAccess::allows(Auth::user(), 'promotions'), 403);
    }
}
