<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanItem;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class BudgetPlanController extends Controller
{
    public function index()
    {
        $plans = BudgetPlan::query()
            ->with('items.account')
            ->orderByDesc('year')
            ->get();

        $plans->each(function (BudgetPlan $plan) {
            $actuals = $this->actualAmountsForYear($plan->year);
            $plan->budget_summary = $this->buildPlanSummary($plan, $actuals);
        });

        $currentYear = (int) now()->year;
        $nextYear = $currentYear + 1;
        $hasCurrentPlan = $plans->contains(fn (BudgetPlan $plan) => (int) $plan->year === $nextYear);

        return view('budgets.index', compact('plans', 'currentYear', 'nextYear', 'hasCurrentPlan'));
    }

    public function create(Request $request)
    {
        $accounts = $this->budgetAccounts();
        $sourcePlan = null;

        if ($request->filled('copy_from')) {
            $sourcePlan = BudgetPlan::query()
                ->with('items.account')
                ->findOrFail($request->integer('copy_from'));
        }

        $plan = new BudgetPlan([
            'year' => (int) $request->input('year', now()->addYear()->year),
            'title' => 'Haushaltsplan ' . $request->input('year', now()->addYear()->year),
            'status' => 'entwurf',
        ]);

        $items = $sourcePlan
            ? $sourcePlan->items->map(fn (BudgetPlanItem $item) => [
                'account_id' => $item->account_id,
                'type' => $item->type,
                'period_amount' => number_format((float) ($item->period_amount ?? $item->planned_amount), 2, '.', ''),
                'planning_cycle' => $item->planning_cycle ?? 'yearly',
                'planned_amount' => number_format((float) $item->planned_amount, 2, '.', ''),
                'notes' => $item->notes,
            ])->values()->all()
            : [[
                'account_id' => '',
                'type' => 'income',
                'period_amount' => '',
                'planning_cycle' => 'monthly',
                'planned_amount' => '',
                'notes' => '',
            ]];

        $mode = 'create';

        return view('budgets.form', compact('plan', 'accounts', 'items', 'mode', 'sourcePlan'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePlan($request);

        $plan = BudgetPlan::create([
            'year' => $validated['year'],
            'title' => $validated['title'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->syncItems($plan, $validated['items']);

        return redirect()
            ->route('budgets.show', $plan)
            ->with('success', 'Der Haushaltsplan wurde angelegt.');
    }

    public function show(BudgetPlan $budget)
    {
        return view('budgets.show', $this->buildShowData($budget));
    }

    public function pdf(BudgetPlan $budget)
    {
        $data = $this->buildShowData($budget);
        $tenant = auth()->user()->tenant;

        $pdf = Pdf::loadView('budgets.pdf', array_merge($data, [
            'tenant' => $tenant,
        ]))->setPaper('a4');

        return $pdf->download('Haushaltsplan_' . $budget->year . '.pdf');
    }

    public function edit(BudgetPlan $budget)
    {
        $budget->load('items.account');

        $accounts = $this->budgetAccounts();
        $items = $budget->items->map(fn (BudgetPlanItem $item) => [
            'account_id' => $item->account_id,
            'type' => $item->type,
            'period_amount' => number_format((float) ($item->period_amount ?? $item->planned_amount), 2, '.', ''),
            'planning_cycle' => $item->planning_cycle ?? 'yearly',
            'planned_amount' => number_format((float) $item->planned_amount, 2, '.', ''),
            'notes' => $item->notes,
        ])->values()->all();

        if ($items === []) {
            $items = [[
                'account_id' => '',
                'type' => 'income',
                'period_amount' => '',
                'planning_cycle' => 'monthly',
                'planned_amount' => '',
                'notes' => '',
            ]];
        }

        $plan = $budget;
        $mode = 'edit';
        $sourcePlan = null;

        return view('budgets.form', compact('plan', 'accounts', 'items', 'mode', 'sourcePlan'));
    }

    public function update(Request $request, BudgetPlan $budget)
    {
        $validated = $this->validatePlan($request, $budget);

        $budget->update([
            'year' => $validated['year'],
            'title' => $validated['title'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->syncItems($budget, $validated['items']);

        return redirect()
            ->route('budgets.show', $budget)
            ->with('success', 'Der Haushaltsplan wurde aktualisiert.');
    }

    public function duplicate(BudgetPlan $budget)
    {
        $targetYear = $budget->year + 1;

        $exists = BudgetPlan::query()->where('year', $targetYear)->exists();

        if ($exists) {
            return redirect()
                ->route('budgets.index')
                ->with('error', 'Fuer ' . $targetYear . ' gibt es bereits einen Haushaltsplan.');
        }

        return redirect()->route('budgets.create', [
            'year' => $targetYear,
            'copy_from' => $budget->id,
        ]);
    }

    protected function buildShowData(BudgetPlan $budget): array
    {
        $budget->load('items.account');

        $actuals = $this->actualAmountsForYear($budget->year);
        $items = $budget->items->map(function (BudgetPlanItem $item) use ($actuals) {
            $actual = (float) ($actuals[$item->account_id] ?? 0);

            return [
                'id' => $item->id,
                'type' => $item->type,
                'account' => $item->account,
                'period_amount' => (float) ($item->period_amount ?? $item->planned_amount),
                'planning_cycle' => $item->planning_cycle ?? 'yearly',
                'planning_cycle_label' => $item->planning_cycle_label,
                'planned_amount' => (float) $item->planned_amount,
                'actual_amount' => $actual,
                'variance' => $actual - (float) $item->planned_amount,
                'notes' => $item->notes,
            ];
        });

        $summary = $this->buildItemSummary($items);

        return compact('budget', 'items', 'summary');
    }

    protected function validatePlan(Request $request, ?BudgetPlan $budget = null): array
    {
        $tenantId = auth()->user()->tenant_id;

        return $request->validate([
            'year' => [
                'required',
                'integer',
                'between:2000,2100',
                Rule::unique('budget_plans', 'year')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($budget?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['entwurf', 'freigegeben'])],
            'notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->whereIn('type', ['einnahme', 'ausgabe'])),
            ],
            'items.*.type' => ['required', Rule::in(['income', 'expense'])],
            'items.*.period_amount' => ['required', 'numeric', 'min:0'],
            'items.*.planning_cycle' => ['required', Rule::in(array_keys(BudgetPlanItem::PLANNING_CYCLES))],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    protected function syncItems(BudgetPlan $plan, array $items): void
    {
        $normalizedItems = collect($items)
            ->filter(fn (array $item) => !empty($item['account_id']))
            ->values();

        $plan->items()->delete();

        foreach ($normalizedItems as $index => $item) {
            $account = Account::query()->find($item['account_id']);

            if (!$account) {
                continue;
            }

            $plan->items()->create([
                'account_id' => $item['account_id'],
                'type' => $account->type === 'einnahme' ? 'income' : 'expense',
                'period_amount' => $item['period_amount'],
                'planning_cycle' => $item['planning_cycle'],
                'planned_amount' => BudgetPlanItem::annualAmountFor(
                    $item['planning_cycle'],
                    (float) $item['period_amount']
                ),
                'notes' => $item['notes'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    protected function budgetAccounts()
    {
        return Account::query()
            ->where('active', true)
            ->whereIn('type', ['einnahme', 'ausgabe'])
            ->orderBy('type')
            ->orderBy('number')
            ->get();
    }

    protected function actualAmountsForYear(int $year): array
    {
        $income = Transaction::query()
            ->whereYear('date', $year)
            ->where('status', 'abgeschlossen')
            ->whereNotNull('account_from_id')
            ->selectRaw('account_from_id as account_id, SUM(amount) as total')
            ->groupBy('account_from_id')
            ->pluck('total', 'account_id')
            ->map(fn ($value) => (float) $value)
            ->all();

        $expense = Transaction::query()
            ->whereYear('date', $year)
            ->where('status', 'abgeschlossen')
            ->whereNotNull('account_to_id')
            ->selectRaw('account_to_id as account_id, SUM(amount) as total')
            ->groupBy('account_to_id')
            ->pluck('total', 'account_id')
            ->map(fn ($value) => (float) $value)
            ->all();

        return $income + $expense;
    }

    protected function buildPlanSummary(BudgetPlan $plan, array $actuals): array
    {
        $items = $plan->items->map(function (BudgetPlanItem $item) use ($actuals) {
            $actual = (float) ($actuals[$item->account_id] ?? 0);

            return [
                'type' => $item->type,
                'period_amount' => (float) ($item->period_amount ?? $item->planned_amount),
                'planning_cycle' => $item->planning_cycle ?? 'yearly',
                'planned_amount' => (float) $item->planned_amount,
                'actual_amount' => $actual,
                'variance' => $actual - (float) $item->planned_amount,
            ];
        });

        return $this->buildItemSummary($items);
    }

    protected function buildItemSummary(Collection $items): array
    {
        $plannedIncome = $items->where('type', 'income')->sum('planned_amount');
        $plannedExpense = $items->where('type', 'expense')->sum('planned_amount');
        $actualIncome = $items->where('type', 'income')->sum('actual_amount');
        $actualExpense = $items->where('type', 'expense')->sum('actual_amount');

        return [
            'planned_income' => $plannedIncome,
            'planned_expense' => $plannedExpense,
            'planned_result' => $plannedIncome - $plannedExpense,
            'actual_income' => $actualIncome,
            'actual_expense' => $actualExpense,
            'actual_result' => $actualIncome - $actualExpense,
            'variance_result' => ($actualIncome - $actualExpense) - ($plannedIncome - $plannedExpense),
        ];
    }
}
