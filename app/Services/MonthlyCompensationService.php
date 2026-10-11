<?php

namespace App\Services;

use App\Models\User;
use App\Support\AdminAccess;
use App\Support\CompensationItems;
use App\Support\OfficeScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly compensation keyed by offices (ต้นสังกัด) and the yearly confirm /
 * issue / reopen workflow of the provincial health office (สสจ.).
 *
 * A month belongs to the office that keyed it (monthly_compensations.office_id),
 * so an office keeps its months after the person moves. Offices confirm a year
 * once their data is complete, which locks their rows; issuing locks the year.
 */
class MonthlyCompensationService
{
    /** สสจ. work: ADMIN or office 00 with tax-certificate access. */
    public static function isProvincialOffice(?User $user): bool
    {
        return AdminAccess::allows($user, 'tax-certificates') && OfficeScope::officeFor($user) === null;
    }

    /** Monthly rows of the offices the user may manage, aliased as compensations/people. */
    public static function scoped(?User $user): Builder
    {
        $query = DB::table('monthly_compensations as compensations')
            ->leftJoin('person as people', 'people.cid', '=', 'compensations.cid');

        return OfficeScope::apply($query, $user, 'compensations.office_id');
    }

    public static function yearLocked(int $year): bool
    {
        return (bool) DB::table('compensation_years')->where('income_year', $year)->value('locked');
    }

    public static function officeConfirmed(int $year, string $officeId): bool
    {
        return DB::table('compensation_year_confirmations')->where('income_year', $year)->where('office_id', $officeId)->exists();
    }

    /** Why the office's rows for the year cannot be changed, or null when they can. */
    public static function editBlockedReason(int $year, string $officeId): ?string
    {
        if (self::yearLocked($year)) {
            return "ปี {$year} ออกหนังสือรับรองแล้ว แก้ไขไม่ได้จนกว่าสำนักงานสาธารณสุขจังหวัดจะปลดล็อก";
        }
        if (self::officeConfirmed($year, $officeId)) {
            return "ต้นสังกัดยืนยันข้อมูลปี {$year} แล้ว แก้ไขไม่ได้จนกว่าสำนักงานสาธารณสุขจังหวัดจะปลดการยืนยัน";
        }

        return null;
    }

    /**
     * Create or replace one person's month. A new month is recorded under the
     * person's current office; an existing month keeps the office that keyed it.
     *
     * @param  array<string, mixed>  $values  CompensationItems fields plus other_name
     * @return int the monthly_compensations id
     */
    public function save(User $actor, string $cid, int $year, int $month, array $values): int
    {
        abort_unless(AdminAccess::allows($actor, 'tax-certificates'), 403);

        return DB::transaction(function () use ($actor, $cid, $year, $month, $values): int {
            $person = OfficeScope::apply(DB::table('person'), $actor)->where('cid', $cid)->first(['cid', 'type_id', 'office_id']);
            $existing = DB::table('monthly_compensations')->where('cid', $cid)->where('income_year', $year)
                ->where('month', $month)->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless(self::scoped($actor)->where('compensations.id', $existing->id)->exists(), 404);
                $person ??= DB::table('person')->where('cid', $cid)->first(['cid', 'type_id', 'office_id']);
            }
            if ($person === null) {
                throw ValidationException::withMessages(['cid' => 'ไม่พบบุคลากรในต้นสังกัดของท่าน']);
            }

            $officeId = (string) ($existing->office_id ?? $person->office_id ?? '');
            if ($officeId === '') {
                throw ValidationException::withMessages(['cid' => 'บุคลากรนี้ยังไม่มีต้นสังกัด']);
            }
            if (($reason = self::editBlockedReason($year, $officeId)) !== null) {
                throw ValidationException::withMessages(['month' => $reason]);
            }

            $problems = CompensationItems::problems($values, $person->type_id === null ? null : (int) $person->type_id);
            if ($problems !== []) {
                throw ValidationException::withMessages(['values' => $problems]);
            }

            $row = $this->rowValues($values) + ['updated_by' => $actor->id, 'updated_at' => now()];
            if ($existing !== null) {
                DB::table('monthly_compensations')->where('id', $existing->id)->update($row);
                $id = (int) $existing->id;
            } else {
                $id = (int) DB::table('monthly_compensations')->insertGetId($row + [
                    'cid' => $cid, 'income_year' => $year, 'month' => $month, 'office_id' => $officeId,
                    'created_by' => $actor->id, 'created_at' => now(),
                ]);
            }
            $this->audit($actor, $existing ? 'updated' : 'created', $year, $month, $officeId, $id);

            return $id;
        });
    }

    public function delete(User $actor, int $id): void
    {
        abort_unless(AdminAccess::allows($actor, 'tax-certificates'), 403);

        DB::transaction(function () use ($actor, $id): void {
            $row = self::scoped($actor)->where('compensations.id', $id)->lockForUpdate()->first(['compensations.*']);
            abort_unless($row, 404);
            if (($reason = self::editBlockedReason((int) $row->income_year, (string) $row->office_id)) !== null) {
                throw ValidationException::withMessages(['month' => $reason]);
            }
            DB::table('monthly_compensations')->where('id', $id)->delete();
            $this->audit($actor, 'deleted', (int) $row->income_year, (int) $row->month, (string) $row->office_id, $id);
        });
    }

    /** An office declares its year complete, which locks its rows. */
    public function confirm(User $actor, int $year, string $officeId): void
    {
        abort_unless(AdminAccess::allows($actor, 'tax-certificates'), 403);
        $scope = OfficeScope::officeFor($actor);
        abort_unless($scope === null || $scope === $officeId, 403);
        if (self::yearLocked($year)) {
            throw ValidationException::withMessages(['confirm' => "ปี {$year} ออกหนังสือรับรองแล้ว"]);
        }
        if (! DB::table('monthly_compensations')->where('income_year', $year)->where('office_id', $officeId)->exists()) {
            throw ValidationException::withMessages(['confirm' => "ต้นสังกัดยังไม่มีข้อมูลปี {$year}"]);
        }

        DB::table('compensation_year_confirmations')->insertOrIgnore([
            'income_year' => $year, 'office_id' => $officeId, 'confirmed_by' => $actor->id, 'confirmed_at' => now(),
        ]);
        $this->audit($actor, 'confirmed', $year, null, $officeId);
    }

    /** สสจ. reopens one office's confirmed year for corrections. */
    public function unconfirm(User $actor, int $year, string $officeId): void
    {
        abort_unless(self::isProvincialOffice($actor), 403);
        if (self::yearLocked($year)) {
            throw ValidationException::withMessages(['confirm' => "ปี {$year} ออกหนังสือรับรองแล้ว ต้องปลดล็อกปีก่อน"]);
        }
        DB::table('compensation_year_confirmations')->where('income_year', $year)->where('office_id', $officeId)->delete();
        $this->audit($actor, 'unconfirmed', $year, null, $officeId);
    }

    /**
     * Offices holding data for the year with their confirmation and totals.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function yearSummary(int $year): \Illuminate\Support\Collection
    {
        $income = implode(' + ', array_map(fn (string $field) => "COALESCE({$field}, 0)", CompensationItems::incomeFields()));

        return DB::table('monthly_compensations as compensations')
            ->leftJoin('offices', 'offices.code', '=', 'compensations.office_id')
            ->leftJoin('compensation_year_confirmations as confirmations', function ($join) {
                $join->on('confirmations.office_id', '=', 'compensations.office_id')
                    ->on('confirmations.income_year', '=', 'compensations.income_year');
            })
            ->where('compensations.income_year', $year)
            ->groupBy('compensations.office_id', 'offices.name', 'confirmations.confirmed_at')
            ->orderBy('compensations.office_id')
            ->selectRaw("compensations.office_id, offices.name AS office_name, confirmations.confirmed_at,
                COUNT(DISTINCT compensations.month) AS months, COUNT(DISTINCT compensations.cid) AS people,
                SUM({$income}) AS income, SUM(compensations.withholding_tax) AS tax")
            ->get();
    }

    /** @param  array<string, mixed>  $values */
    public function rowValues(array $values): array
    {
        $row = [];
        foreach (CompensationItems::fields() as $field) {
            $row[$field] = number_format((float) ($values[$field] ?? 0), 2, '.', '');
        }
        $row['other_name'] = (float) $row['other_amount'] != 0.0 ? trim((string) ($values['other_name'] ?? '')) : null;

        return $row;
    }

    public function audit(User $actor, string $action, int $year, ?int $month = null, ?string $officeId = null, ?int $compensationId = null, ?int $rowCount = null): void
    {
        DB::table('compensation_audits')->insert([
            'actor_id' => $actor->id, 'action' => $action, 'income_year' => $year, 'month' => $month,
            'office_id' => $officeId, 'compensation_id' => $compensationId, 'row_count' => $rowCount, 'created_at' => now(),
        ]);
    }
}
