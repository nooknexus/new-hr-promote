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
