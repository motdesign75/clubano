<?php

namespace App\Http\Controllers;

use App\Models\BudgetPlan;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Member;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class FinanceForecastController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;
        $today = now()->startOfDay();
        $start = $today->copy()->startOfMonth();
        $end = $start->copy()->addMonths(11)->endOfMonth();

        $months = collect(range(0, 11))->mapWithKeys(function (int $offset) use ($start) {
            $month = $start->copy()->addMonthsNoOverflow($offset);

            return [$month->format('Y-m') => [
                'key' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M Y'),
                'membership_forecast' => 0.0,
                'open_invoice_income' => 0.0,
                'planned_expenses' => 0.0,
                'open_payables' => 0.0,
            ]];
        });

        $members = Member::query()
            ->where('tenant_id', $tenantId)
            ->notArchived()
            ->whereNull('family_payer_id')
            ->activeAt($today)
            ->with(['membership', 'latestMembershipInvoice'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $membershipForecastRows = $this->buildMembershipForecast($members, $start, $end, $months);
        $openInvoices = $this->openInvoices($tenantId, $months, $end);
        $openPayables = $this->openPayables($tenantId, $months, $end);
        $budgetPlans = $this->budgetPlansForRange($start, $end);
        $plannedExpenses = $this->applyBudgetExpenseForecast($budgetPlans, $months, $start, $end);

        $months = $months->map(function (array $month) {
            $expectedIncome = $month['membership_forecast'] + $month['open_invoice_income'];
            $expectedExpenses = $month['planned_expenses'] + $month['open_payables'];

            return [
                ...$month,
                'expected_income' => round($expectedIncome, 2),
                'expected_expenses' => round($expectedExpenses, 2),
                'saldo' => round($expectedIncome - $expectedExpenses, 2),
            ];
        })->values();

        $overdueMembershipInvoices = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('document_type', 'invoice')
            ->where('status', 'open')
            ->whereNotNull('period_from')
            ->whereDate('due_date', '<', $today)
            ->with(['member', 'payments'])
            ->orderBy('due_date')
            ->get();

        $dueMemberRows = $members
            ->map(fn (Member $member) => [
                'member' => $member,
                'next_date' => $this->nextBillingDateFor($member),
                'amount' => $this->memberContributionAmount($member),
                'interval' => $this->normalizeMembershipInterval($member->membership_interval ?? $member->membership?->interval),
            ])
            ->filter(fn (array $row) => $row['amount'] > 0 && $row['next_date'] && $row['next_date']->lt($today))
            ->sortBy(fn (array $row) => $row['next_date']->timestamp)
            ->values();

        $stats = [
            'expected_income' => $months->sum('expected_income'),
            'expected_expenses' => $months->sum('expected_expenses'),
            'expected_saldo' => $months->sum('saldo'),
            'membership_forecast' => $months->sum('membership_forecast'),
            'open_invoice_income' => $months->sum('open_invoice_income'),
            'planned_expenses' => $months->sum('planned_expenses'),
            'open_payables' => $months->sum('open_payables'),
            'overdue_membership_amount' => $overdueMembershipInvoices->sum(fn (Invoice $invoice) => $invoice->getRemainingAmount()),
            'overdue_membership_count' => $overdueMembershipInvoices->count(),
            'due_member_count' => $dueMemberRows->count(),
            'members_with_contribution' => $membershipForecastRows->count(),
        ];

        return view('finance-forecast.index', [
            'months' => $months,
            'stats' => $stats,
            'membershipForecastRows' => $membershipForecastRows,
            'openInvoices' => $openInvoices,
            'openPayables' => $openPayables,
            'plannedExpenses' => $plannedExpenses,
            'budgetPlans' => $budgetPlans,
            'overdueMembershipInvoices' => $overdueMembershipInvoices,
            'dueMemberRows' => $dueMemberRows,
            'start' => $start,
            'end' => $end,
        ]);
    }

    private function buildMembershipForecast(Collection $members, CarbonInterface $start, CarbonInterface $end, Collection &$months): Collection
    {
        return $members
            ->map(function (Member $member) use ($start, $end, &$months) {
                $amount = $this->memberContributionAmount($member);
                $interval = $this->normalizeMembershipInterval($member->membership_interval ?? $member->membership?->interval);
                $nextDate = $this->nextBillingDateFor($member);

                if ($amount <= 0 || ! $interval || ! $nextDate) {
                    return null;
                }

                while ($nextDate->lt($start)) {
                    $nextDate = $this->advanceBillingDate($nextDate, $interval);
                }

                $occurrences = collect();

                while ($nextDate->lte($end)) {
                    $key = $nextDate->format('Y-m');
                    if ($months->has($key)) {
                        $month = $months->get($key);
                        $month['membership_forecast'] = round($month['membership_forecast'] + $amount, 2);
                        $months->put($key, $month);

                        $occurrences->push($nextDate->copy());
                    }

                    $nextDate = $this->advanceBillingDate($nextDate, $interval);
                }

                if ($occurrences->isEmpty()) {
                    return null;
                }

                return [
                    'member' => $member,
                    'amount' => $amount,
                    'interval' => $interval,
                    'occurrences' => $occurrences,
                    'total' => round($amount * $occurrences->count(), 2),
                ];
            })
            ->filter()
            ->sortBy(fn (array $row) => $row['member']->last_name . ' ' . $row['member']->first_name)
            ->values();
    }

    private function openInvoices(int $tenantId, Collection &$months, CarbonInterface $end): Collection
    {
        $invoices = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('document_type', 'invoice')
            ->where('status', 'open')
            ->whereDate('due_date', '<=', $end)
            ->with(['member', 'contact', 'payments'])
            ->orderBy('due_date')
            ->get();

        foreach ($invoices as $invoice) {
            $key = ($invoice->due_date ?: now())->format('Y-m');
            if (! $months->has($key)) {
                continue;
            }

            $month = $months->get($key);
            $month['open_invoice_income'] = round($month['open_invoice_income'] + $invoice->getRemainingAmount(), 2);
            $months->put($key, $month);
        }

        return $invoices;
    }

    private function openPayables(int $tenantId, Collection &$months, CarbonInterface $end): Collection
    {
        $payables = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('is_booking_receipt', true)
            ->whereNotIn('payable_status', [Document::PAYABLE_PAID, Document::PAYABLE_CANCELLED])
            ->whereNotNull('recognized_amount')
            ->whereDate('payable_due_date', '<=', $end)
            ->orderBy('payable_due_date')
            ->get()
            ->filter(fn (Document $document) => ! in_array($document->derivedPayableStatus(), [Document::PAYABLE_PAID, Document::PAYABLE_CANCELLED], true))
            ->values();

        foreach ($payables as $payable) {
            $key = ($payable->payable_due_date ?: now())->format('Y-m');
            if (! $months->has($key)) {
                continue;
            }

            $month = $months->get($key);
            $month['open_payables'] = round($month['open_payables'] + $payable->payableRemainingAmount(), 2);
            $months->put($key, $month);
        }

        return $payables;
    }

    private function budgetPlansForRange(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return BudgetPlan::query()
            ->whereBetween('year', [$start->year, $end->year])
            ->with(['items.account', 'items.budgetCategory'])
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->get()
            ->groupBy('year')
            ->map(fn (Collection $plans) => $plans->first());
    }

    private function applyBudgetExpenseForecast(Collection $budgetPlans, Collection &$months, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $rows = collect();

        foreach ($budgetPlans as $year => $plan) {
            foreach ($plan->items->where('type', 'expense') as $item) {
                $monthlyAmount = round(((float) $item->planned_amount) / 12, 2);
                if ($monthlyAmount <= 0) {
                    continue;
                }

                foreach ($months->keys() as $key) {
                    [$monthYear] = explode('-', $key);
                    if ((int) $monthYear !== (int) $year) {
                        continue;
                    }

                    $month = $months->get($key);
                    $month['planned_expenses'] = round($month['planned_expenses'] + $monthlyAmount, 2);
                    $months->put($key, $month);
                }

                $rows->push([
                    'item' => $item,
                    'plan' => $plan,
                    'monthly_amount' => $monthlyAmount,
                    'annual_amount' => (float) $item->planned_amount,
                ]);
            }
        }

        return $rows
            ->sortByDesc('annual_amount')
            ->values();
    }

    private function nextBillingDateFor(Member $member): ?CarbonInterface
    {
        if (! $member->membership_id && ! $member->membership_amount) {
            return null;
        }

        if ($member->next_membership_invoice_on) {
            return $member->next_membership_invoice_on->copy()->startOfDay();
        }

        if ($member->latestMembershipInvoice?->period_to) {
            return $member->latestMembershipInvoice->period_to->copy()->addDay()->startOfDay();
        }

        return $member->entry_date?->isFuture()
            ? $member->entry_date->copy()->startOfDay()
            : now()->startOfDay();
    }

    private function memberContributionAmount(Member $member): float
    {
        return round((float) ($member->membership_amount ?? $member->membership?->amount ?? 0), 2);
    }

    private function normalizeMembershipInterval(?string $interval): ?string
    {
        return match (strtolower(trim((string) $interval))) {
            'monatlich', 'monat' => 'monatlich',
            'vierteljährlich', 'vierteljaehrlich', 'quartal', 'quartalsweise' => 'vierteljährlich',
            'halbjährlich', 'halbjaehrlich', 'halbjahr' => 'halbjährlich',
            'jährlich', 'jaehrlich', 'jahr' => 'jährlich',
            default => null,
        };
    }

    private function advanceBillingDate(CarbonInterface $date, string $interval): CarbonInterface
    {
        return match ($interval) {
            'monatlich' => $date->copy()->addMonthNoOverflow(),
            'vierteljährlich' => $date->copy()->addMonthsNoOverflow(3),
            'halbjährlich' => $date->copy()->addMonthsNoOverflow(6),
            default => $date->copy()->addYearNoOverflow(),
        };
    }
}
