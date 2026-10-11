<?php

namespace App\Services;

use App\Models\User;
use App\Support\CompensationItems;
use App\Support\ThaiBahtText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues the provincial health office's yearly withholding-tax certificates
 * (data_tax.source = pho) from every keyed month of the year, then locks it.
 * Reissuing keeps each person's certificate number. Reopening unlocks the year
 * and clears every office's confirmation; issued certificates stay available
 * until the year is issued again.
 */
class CompensationCertificateIssuer
{
    public const SOURCE = 'pho';

    /** The certificate's fund slot only fits a short name; กสล.พกส is the name legacy data already uses. */
    public const FUND_CERTIFICATE_NAME = 'กสล.พกส';

    public function __construct(private MonthlyCompensationService $compensations) {}

    /** Offices with data for the year that have not confirmed it. */
    public function unconfirmedOffices(int $year): array
    {
        return MonthlyCompensationService::yearSummary($year)
            ->whereNull('confirmed_at')->pluck('office_id')->all();
    }

    /** @return int number of certificates issued */
    public function issue(User $actor, int $year, string $dateReport, bool $skipUnconfirmed = false): int
    {
        abort_unless(MonthlyCompensationService::isProvincialOffice($actor), 403);
        $payer = DB::table('tax_payer_settings')->orderBy('id')->first();
        if ($payer === null) {
            throw ValidationException::withMessages(['issue' => 'กรุณาตั้งค่าข้อมูลผู้จ่ายเงินก่อนออกหนังสือรับรอง']);
        }
        if (MonthlyCompensationService::yearLocked($year)) {
            throw ValidationException::withMessages(['issue' => "ปี {$year} ออกหนังสือรับรองแล้ว ต้องปลดล็อกปีก่อนออกใหม่"]);
        }
        if (! preg_match('/^\d{8}$/', $dateReport)) {
            throw ValidationException::withMessages(['issue' => 'วันที่ออกหนังสือรับรองไม่ถูกต้อง']);
        }
        if (! $skipUnconfirmed && $this->unconfirmedOffices($year) !== []) {
            throw ValidationException::withMessages(['issue' => 'ยังมีต้นสังกัดที่ไม่ยืนยันข้อมูล']);
        }

        return DB::transaction(function () use ($actor, $year, $dateReport, $payer): int {
            DB::table('compensation_years')->updateOrInsert(['income_year' => $year], ['updated_at' => now()]);
            DB::table('compensation_years')->where('income_year', $year)->lockForUpdate()->first();

            $income = implode(' + ', array_map(fn (string $field) => "COALESCE({$field}, 0)", CompensationItems::incomeFields()));
            $totals = DB::table('monthly_compensations')
                ->where('income_year', $year)
                ->groupBy('cid')
                ->selectRaw("cid, SUM({$income}) AS income, SUM(withholding_tax) AS tax,
                    SUM(social_security) AS social_security, SUM(provident_fund) AS provident_fund")
                ->havingRaw("SUM({$income}) > 0")
                ->orderBy('cid')
                ->get();

            $numbers = DB::table('data_tax')->where('income_year', (string) $year)->where('source', self::SOURCE)
                ->pluck('running_code1', 'cid')->all();
            $next = $this->nextSequences($year, $numbers);
            // A person's number uses the office that keyed their latest month of the year.
            $offices = DB::table('monthly_compensations')->where('income_year', $year)
                ->orderBy('month')->pluck('office_id', 'cid')->all();
            $people = DB::table('person')->whereIn('cid', $totals->pluck('cid'))->get(['cid', 'pname', 'fname', 'lname'])->keyBy('cid');

            $rows = [];
            foreach ($totals as $total) {
                // YY (Buddhist year) + office code + 3-digit running number per office, e.g. 6701001.
                // Seven digits keep it apart from the Comptroller General's six-digit numbers.
                $number = $numbers[$total->cid] ?? $this->newNumber($year, (string) $offices[$total->cid], $next);
                $person = $people[$total->cid] ?? null;
                $tax = self::money($total->tax);
                $fund = (float) $total->provident_fund;
                $rows[] = [
                    'cid' => $total->cid,
                    'income_year' => (string) $year,
                    'source' => self::SOURCE,
                    'running_code1' => $number,
                    'running_code2' => $number,
                    'department_name' => $payer->name,
                    'department_tax_id' => $payer->tax_id,
                    'department_addr' => $payer->address,
                    'tax_id' => null,
                    'fullname' => trim(implode(' ', array_filter([$person?->pname, $person?->fname, $person?->lname]))) ?: null,
                    'addr' => $this->addressFor($total->cid) ?? $payer->address,
                    'income_type_id' => '2',
                    'income_type_name' => 'ภ.ง.ด.1ก พิเศษ',
                    'income_money' => self::money($total->income),
                    'total_money' => self::money($total->income),
                    'withholding_tax' => $tax,
                    'sumTaxAmount' => $tax,
                    'sumTaxText' => ThaiBahtText::format($tax),
                    'outcomeType' => $fund > 0 ? '2' : null,
                    'outcomeName' => $fund > 0 ? self::FUND_CERTIFICATE_NAME : null,
                    'outcomeAmount' => $fund > 0 ? self::money($fund) : null,
                    'socialSecurity' => (float) $total->social_security > 0 ? self::money($total->social_security) : null,
                    'tax_type_id' => '1',
                    'tax_type_name' => 'หัก ณ ที่จ่าย',
                    'date_report' => $dateReport,
                    'r_update' => now(),
                ];
            }

            // A person whose year total dropped to zero after a reopen no longer gets a certificate.
            DB::table('data_tax')->where('income_year', (string) $year)->where('source', self::SOURCE)
                ->whereNotIn('cid', $totals->pluck('cid'))->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('data_tax')->upsert($chunk, ['cid', 'income_year', 'source'], array_keys($chunk[0]));
            }

            DB::table('compensation_years')->where('income_year', $year)->update([
                'locked' => true, 'date_report' => $dateReport, 'issued_by' => $actor->id,
                'issued_at' => now(), 'updated_at' => now(),
            ]);
            $this->compensations->audit($actor, 'issued', $year, rowCount: count($rows));

            return count($rows);
        });
    }

    /** Unlock an issued year: every office must confirm again before reissuing. */
    public function reopen(User $actor, int $year): void
    {
        abort_unless(MonthlyCompensationService::isProvincialOffice($actor), 403);

        DB::transaction(function () use ($actor, $year): void {
            DB::table('compensation_years')->where('income_year', $year)->update(['locked' => false, 'updated_at' => now()]);
            DB::table('compensation_year_confirmations')->where('income_year', $year)->delete();
            $this->compensations->audit($actor, 'reopened', $year);
        });
    }

    /** The person's own address from their account, if any. */
    private function addressFor(string $cid): ?string
    {
        $address = DB::table('users')
            ->where(fn ($query) => $query->where('pid', $cid)->orWhere('thaid_pid', $cid))
            ->whereNotNull('address')->where('address', '!=', '')
            ->orderBy('id')->value('address');

        return $address === null ? null : mb_substr(trim((string) $address), 0, 255);
    }

    /**
     * Next running number per office from the year's existing certificate numbers.
     *
     * @param  array<string, string>  $numbers
     * @return array<string, int> office code => next running number
     */
    private function nextSequences(int $year, array $numbers): array
    {
        $next = [];
        foreach ($numbers as $number) {
            if (preg_match(sprintf('/^%02d(\d{2})(\d{3})$/', $year % 100), (string) $number, $match)) {
                $next[$match[1]] = max($next[$match[1]] ?? 1, (int) $match[2] + 1);
            }
        }

        return $next;
    }

    /** @param  array<string, int>  $next */
    private function newNumber(int $year, string $office, array &$next): string
    {
        $sequence = $next[$office] ?? 1;
        if (! preg_match('/^\d{2}$/', $office) || $sequence > 999) {
            throw ValidationException::withMessages(['issue' => "ออกเลขหนังสือรับรองของต้นสังกัด {$office} ไม่ได้ (เลขลำดับเกิน 999 หรือรหัสต้นสังกัดไม่ถูกต้อง)"]);
        }
        $next[$office] = $sequence + 1;

        return sprintf('%02d%s%03d', $year % 100, $office, $sequence);
    }

    private static function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
