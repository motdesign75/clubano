<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetPlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_plan_id',
        'account_id',
        'type',
        'period_amount',
        'planning_cycle',
        'planned_amount',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'period_amount' => 'decimal:2',
        'planned_amount' => 'decimal:2',
    ];

    public const PLANNING_CYCLES = [
        'monthly' => 12,
        'quarterly' => 4,
        'half_yearly' => 2,
        'yearly' => 1,
    ];

    public const PLANNING_CYCLE_LABELS = [
        'monthly' => 'Monatlich',
        'quarterly' => 'Vierteljaehrlich',
        'half_yearly' => 'Halbjaehrlich',
        'yearly' => 'Jaehrlich',
    ];

    public function plan()
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public static function annualAmountFor(string $cycle, float $periodAmount): float
    {
        $factor = self::PLANNING_CYCLES[$cycle] ?? 1;

        return round($periodAmount * $factor, 2);
    }

    public function getPlanningCycleLabelAttribute(): string
    {
        return self::PLANNING_CYCLE_LABELS[$this->planning_cycle] ?? 'Jaehrlich';
    }
}
