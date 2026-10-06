<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayslipSignatory extends Model
{
    protected $fillable = [
        'fiscal_year',
        'name',
        'position_line1',
        'position_line2',
        'signature_image',
        'signature_mime',
    ];

    protected $hidden = [
        'signature_image',
    ];

    /**
     * The payslip's "ชื่อ-นามสกุล" line wraps the signer's name in
     * parentheses, matching the document's original hard-coded text.
     */
    public function getDisplayNameAttribute(): string
    {
        return '(' . $this->name . ')';
    }

    /**
     * The signature image as a data URI for <img src>, or null when this
     * signatory has no image on file.
     */
    public function getSignatureDataUriAttribute(): ?string
    {
        if (empty($this->signature_image)) {
            return null;
        }

        $mime = $this->signature_mime ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($this->signature_image);
    }

    /**
     * The Thai government fiscal year (Buddhist Era) that a given Gregorian
     * year/month falls in: 1 Oct (year) - 30 Sep (year + 1).
     */
    public static function fiscalYearFor(int $gregorianYear, int $month): int
    {
        $beYear = $month >= 10 ? $gregorianYear + 1 : $gregorianYear;

        return $beYear + 543;
    }

    /**
     * Resolve the fiscal year for a payslip period id in "YYYYMM" form
     * (Gregorian year), as used by the payslip's own 'id' field.
     */
    public static function fiscalYearForPeriodId(string $periodId): int
    {
        return self::fiscalYearFor(
            (int) substr($periodId, 0, 4),
            (int) substr($periodId, 4, 2)
        );
    }

    /**
     * The signatory on record for a fiscal year, falling back to the most
     * recent signatory on file when that exact fiscal year has no record.
     */
    public static function forFiscalYear(int $fiscalYear): ?self
    {
        return static::where('fiscal_year', $fiscalYear)->first()
            ?? static::orderByDesc('fiscal_year')->first();
    }

    /** Tax certificates must use only the signatory for their exact income year. */
    public static function forTaxIncomeYear(int $incomeYear): ?self
    {
        return static::where('fiscal_year', $incomeYear)->first();
    }

    /**
     * The signatory that applied when a payslip period ("YYYYMM") was paid.
     */
    public static function forPeriodId(string $periodId): ?self
    {
        return self::forFiscalYear(self::fiscalYearForPeriodId($periodId));
    }
}
