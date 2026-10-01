<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BudgetCategoryController extends Controller
{
    public function index()
    {
        BudgetCategory::ensureDefaultsForTenant(auth()->user()->tenant_id);

        $categories = BudgetCategory::query()
            ->withCount(['accounts', 'budgetItems'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('budget-categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategory($request);
        $validated['tenant_id'] = auth()->user()->tenant_id;
        $validated['active'] = $request->boolean('active', true);

        BudgetCategory::create($validated);

        return redirect()
            ->route('budget-categories.index')
            ->with('success', 'Der Haushaltsbereich wurde angelegt.');
    }

    public function update(Request $request, BudgetCategory $budgetCategory)
    {
        $this->authorizeCategory($budgetCategory);

        $validated = $this->validateCategory($request, $budgetCategory);
        $validated['active'] = $request->boolean('active');

        $budgetCategory->update($validated);

        return redirect()
            ->route('budget-categories.index')
            ->with('success', 'Der Haushaltsbereich wurde aktualisiert.');
    }

    public function destroy(BudgetCategory $budgetCategory)
    {
        $this->authorizeCategory($budgetCategory);

        if ($budgetCategory->accounts()->exists() || $budgetCategory->budgetItems()->exists()) {
            return redirect()
                ->route('budget-categories.index')
                ->with('error', 'Dieser Bereich wird bereits genutzt und kann nicht gelöscht werden. Du kannst ihn stattdessen deaktivieren.');
        }

        $budgetCategory->delete();

        return redirect()
            ->route('budget-categories.index')
            ->with('success', 'Der Haushaltsbereich wurde gelöscht.');
    }

    protected function validateCategory(Request $request, ?BudgetCategory $category = null): array
    {
        $tenantId = auth()->user()->tenant_id;

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('budget_categories', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($category?->id),
            ],
            'color' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    protected function authorizeCategory(BudgetCategory $category): void
    {
        if ((string) $category->tenant_id !== (string) auth()->user()->tenant_id) {
            abort(403, 'Kein Zugriff auf diesen Haushaltsbereich.');
        }
    }
}
