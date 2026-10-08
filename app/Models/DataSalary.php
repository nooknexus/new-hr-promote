<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataSalary extends Model
{
    use HasFactory;
    
    // Table name
    protected $table = 'data_salary';
    
    // No timestamps (created_at, updated_at)
    public $timestamps = false;
    
    // Composite primary key
    protected $primaryKey = ['yearNO', 'monthNO', 'cid'];
    public $incrementing = false;
    
    // All fields are fillable
    protected $guarded = [];

    /**
     * Payslip months are stored as two digits ("09"), but records migrated from
     * the old system or imported from TXT may hold "9". Normalise for display
     * and ids, and match both forms when looking a month up.
     */
    public static function normalizeMonth(mixed $month): string
    {
        return str_pad(trim((string) $month), 2, '0', STR_PAD_LEFT);
    }

    /** @return array{0: string, 1: string} the two-digit and the unpadded spelling */
    public static function monthVariants(mixed $month): array
    {
        $padded = self::normalizeMonth($month);

        return [$padded, ltrim($padded, '0') ?: '0'];
    }

    /**
     * Split a payslip id ("256909": Buddhist year + month) into the bindings for
     * "yearNO = ? AND monthNO IN (?, ?)".
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function periodBindings(string $id): array
    {
        return [substr($id, 0, 4), ...self::monthVariants(substr($id, 4))];
    }
    
    /**
     * Get the total income amount for this salary record
     */
    public function getTotalIncomeAttribute()
    {
        return $this->summaryIncomeAmount ?? 0;
    }
    
    /**
     * Get the total outcome (deduction) amount for this salary record
     */
    public function getTotalOutcomeAttribute()
    {
        return $this->summaryOutcomeAmount ?? 0;
    }
    
    /**
     * Get the net amount (income - outcome) for this salary record
     */
    public function getNetAmountAttribute()
    {
        return $this->summaryTotalAmount ?? 0;
    }
    
    /**
     * Get the user associated with this salary record
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'cid', 'pid');
    }
}
