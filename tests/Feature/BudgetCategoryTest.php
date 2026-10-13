<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\BudgetCategory;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanItem;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

function createBudgetCategoryTenant(string $suffix): array
{
    $tenant = Tenant::create([
        'name' => 'Haushaltsverein ' . $suffix,
        'slug' => 'haushaltsverein-' . $suffix . '-' . Str::random(5),
        'email' => 'haushalt-' . $suffix . '-' . Str::random(5) . '@example.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_TREASURER,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $user];
}

test('treasurers can create budget categories for planning areas', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createBudgetCategoryTenant('create');

    $this->actingAs($user)
        ->post(route('budget-categories.store'), [
            'name' => 'Veranstaltungen',
            'color' => 'amber',
            'sort_order' => 20,
            'active' => 1,
        ])
        ->assertRedirect(route('budget-categories.index'));

    expect(BudgetCategory::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Veranstaltungen')
        ->exists())->toBeTrue();
});

test('budget plans store categories and show area results', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createBudgetCategoryTenant('results');

    $category = BudgetCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Veranstaltungen',
        'color' => 'amber',
        'sort_order' => 10,
        'active' => true,
    ]);

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bank',
        'number' => '1200',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Erlöse Sommerfest',
        'number' => '8400',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'budget_category_id' => $category->id,
        'active' => true,
        'online' => false,
    ]);

    $expense = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kosten Sommerfest',
        'number' => '6400',
        'type' => 'ausgabe',
        'tax_area' => 'ideell',
        'budget_category_id' => $category->id,
        'active' => true,
        'online' => false,
    ]);

    $response = $this->actingAs($user)->post(route('budgets.store'), [
        'year' => 2027,
        'title' => 'Haushaltsplan 2027',
        'status' => 'entwurf',
        'notes' => null,
        'items' => [
            [
                'account_id' => $income->id,
                'budget_category_id' => $category->id,
                'type' => 'income',
                'period_amount' => '200',
                'planning_cycle' => 'monthly',
                'notes' => 'Tickets und Verkauf',
            ],
            [
                'account_id' => $expense->id,
                'budget_category_id' => $category->id,
                'type' => 'expense',
                'period_amount' => '600',
                'planning_cycle' => 'yearly',
                'notes' => 'Einkauf',
            ],
        ],
    ]);

    $response->assertRedirect();

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'date' => '2027-06-01',
        'description' => 'Sommerfest Verkauf',
        'amount' => 1000,
        'account_from_id' => $income->id,
        'account_to_id' => $bank->id,
        'status' => 'abgeschlossen',
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'date' => '2027-06-02',
        'description' => 'Sommerfest Einkauf',
        'amount' => 250,
        'account_from_id' => $bank->id,
        'account_to_id' => $expense->id,
        'status' => 'abgeschlossen',
    ]);

    $itemCategories = BudgetPlanItem::query()->pluck('budget_category_id')->unique()->values()->all();

    expect($itemCategories)->toBe([$category->id]);

    $this->actingAs($user)
        ->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee('Ergebnis nach Haushaltsbereich')
        ->assertSee('Veranstaltungen')
        ->assertSee('1.800,00')
        ->assertSee('750,00');
});

test('transactions store a direct budget category or fall back to the account category', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createBudgetCategoryTenant('transactions');

    $membership = BudgetCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Mitgliedschaft',
        'color' => 'blue',
        'sort_order' => 10,
        'active' => true,
    ]);

    $events = BudgetCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Veranstaltungen',
        'color' => 'amber',
        'sort_order' => 20,
        'active' => true,
    ]);

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bank',
        'number' => '1200',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Mitgliedsbeiträge',
        'number' => '8000',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'budget_category_id' => $membership->id,
        'active' => true,
        'online' => false,
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date' => '2027-01-15',
            'description' => 'Beitrag ohne manuelle Kategorie',
            'amount' => '50.00',
            'account_from_id' => $income->id,
            'account_to_id' => $bank->id,
            'status' => 'abgeschlossen',
            'tax_area' => 'ideell',
        ])
        ->assertRedirect(route('transactions.index'));

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'date' => '2027-02-15',
            'description' => 'Beitrag mit abweichender Kategorie',
            'amount' => '75.00',
            'account_from_id' => $income->id,
            'account_to_id' => $bank->id,
            'status' => 'abgeschlossen',
            'tax_area' => 'ideell',
            'budget_category_id' => $events->id,
        ])
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::withoutGlobalScopes()
        ->where('description', 'Beitrag ohne manuelle Kategorie')
        ->value('budget_category_id'))->toBe($membership->id);

    expect(Transaction::withoutGlobalScopes()
        ->where('description', 'Beitrag mit abweichender Kategorie')
        ->value('budget_category_id'))->toBe($events->id);
});

test('budget plan area results prefer the transaction category over the account category', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createBudgetCategoryTenant('override');

    $membership = BudgetCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Mitgliedschaft',
        'color' => 'blue',
        'sort_order' => 10,
        'active' => true,
    ]);

    $events = BudgetCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Veranstaltungen',
        'color' => 'amber',
        'sort_order' => 20,
        'active' => true,
    ]);

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bank',
        'number' => '1200',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Mitgliedsbeiträge',
        'number' => '8000',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'budget_category_id' => $membership->id,
        'active' => true,
        'online' => false,
    ]);

    $planResponse = $this->actingAs($user)->post(route('budgets.store'), [
        'year' => 2028,
        'title' => 'Haushaltsplan 2028',
        'status' => 'entwurf',
        'notes' => null,
        'items' => [
            [
                'account_id' => $income->id,
                'budget_category_id' => $events->id,
                'type' => 'income',
                'period_amount' => '100',
                'planning_cycle' => 'yearly',
                'notes' => 'Event-Anteil aus Beiträgen',
            ],
        ],
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'date' => '2028-03-01',
        'description' => 'Event-Anteil',
        'amount' => 250,
        'account_from_id' => $income->id,
        'account_to_id' => $bank->id,
        'budget_category_id' => $events->id,
        'status' => 'abgeschlossen',
    ]);

    $this->actingAs($user)
        ->get($planResponse->headers->get('Location'))
        ->assertOk()
        ->assertSee('Veranstaltungen')
        ->assertSee('250,00');
});

test('new budget plans are prepared from previous year transactions with budget areas', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createBudgetCategoryTenant('prefill');

    $events = BudgetCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Veranstaltungen',
        'color' => 'amber',
        'sort_order' => 10,
        'active' => true,
    ]);

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bank',
        'number' => '1200',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $income = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Erlöse Sommerfest',
        'number' => '8400',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $expense = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Kosten Sommerfest',
        'number' => '6400',
        'type' => 'ausgabe',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'date' => '2026-06-01',
        'description' => 'Sommerfest Verkauf',
        'amount' => 1200,
        'account_from_id' => $income->id,
        'account_to_id' => $bank->id,
        'budget_category_id' => $events->id,
        'status' => 'abgeschlossen',
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'date' => '2026-06-02',
        'description' => 'Sommerfest Einkauf',
        'amount' => 300,
        'account_from_id' => $bank->id,
        'account_to_id' => $expense->id,
        'budget_category_id' => $events->id,
        'status' => 'abgeschlossen',
    ]);

    $this->actingAs($user)
        ->get(route('budgets.create', ['year' => 2027]))
        ->assertOk()
        ->assertSee('Aus Buchungen 2026 vorbereitet')
        ->assertSee('Clubano hat 2 Position(en)')
        ->assertSee('1200.00')
        ->assertSee('300.00')
        ->assertSee('Aus erfassten Buchungen 2026 uebernommen.');
});

test('existing budget plans can import newly assigned actual budget areas', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createBudgetCategoryTenant('import-actuals');

    $membership = BudgetCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Mitgliedschaft',
        'color' => 'blue',
        'sort_order' => 10,
        'active' => true,
    ]);

    $events = BudgetCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Veranstaltungen',
        'color' => 'amber',
        'sort_order' => 20,
        'active' => true,
    ]);

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bank',
        'number' => '1200',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $membershipIncome = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Mitgliedsbeiträge',
        'number' => '8000',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'budget_category_id' => $membership->id,
        'active' => true,
        'online' => false,
    ]);

    $eventIncome = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Erlöse Sommerfest',
        'number' => '8400',
        'type' => 'einnahme',
        'tax_area' => 'zweckbetrieb',
        'active' => true,
        'online' => false,
    ]);

    $plan = BudgetPlan::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'year' => 2027,
        'title' => 'Haushaltsplan 2027',
        'status' => 'entwurf',
    ]);

    $plan->items()->create([
        'account_id' => $membershipIncome->id,
        'budget_category_id' => $membership->id,
        'type' => 'income',
        'period_amount' => '1000.00',
        'planning_cycle' => 'yearly',
        'planned_amount' => '1000.00',
        'sort_order' => 0,
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'date' => '2027-06-01',
        'description' => 'Sommerfest Verkauf',
        'amount' => 450,
        'account_from_id' => $eventIncome->id,
        'account_to_id' => $bank->id,
        'budget_category_id' => $events->id,
        'status' => 'entwurf',
    ]);

    $this->actingAs($user)
        ->post(route('budgets.import-actuals', $plan))
        ->assertRedirect(route('budgets.show', $plan))
        ->assertSessionHas('success');

    $plan->refresh()->load('items');

    expect($plan->items)->toHaveCount(2);
    expect($plan->items->last()->account_id)->toBe($eventIncome->id);
    expect($plan->items->last()->budget_category_id)->toBe($events->id);
    expect((float) $plan->items->last()->planned_amount)->toBe(450.0);
});

test('budget actual import updates existing uncategorized plan items', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createBudgetCategoryTenant('import-update');

    $events = BudgetCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Veranstaltungen',
        'color' => 'amber',
        'sort_order' => 20,
        'active' => true,
    ]);

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bank',
        'number' => '1200',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $eventIncome = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Erlöse Kartoffelmarkt',
        'number' => '8002',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $plan = BudgetPlan::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'year' => 2027,
        'title' => 'Haushaltsplan 2027',
        'status' => 'entwurf',
    ]);

    $plan->items()->create([
        'account_id' => $eventIncome->id,
        'budget_category_id' => null,
        'type' => 'income',
        'period_amount' => '140.00',
        'planning_cycle' => 'yearly',
        'planned_amount' => '140.00',
        'notes' => 'Aus Ist-Buchungen 2026 uebernommen.',
        'sort_order' => 0,
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'date' => '2027-05-01',
        'description' => 'Kartoffelmarkt',
        'amount' => 140,
        'account_from_id' => $eventIncome->id,
        'account_to_id' => $bank->id,
        'budget_category_id' => $events->id,
        'status' => 'entwurf',
    ]);

    $this->actingAs($user)
        ->post(route('budgets.import-actuals', $plan))
        ->assertRedirect(route('budgets.show', $plan))
        ->assertSessionHas('success');

    $plan->refresh()->load('items');

    expect($plan->items)->toHaveCount(1);
    expect($plan->items->first()->budget_category_id)->toBe($events->id);
});

test('existing budget plans can refresh categories from previous year actuals', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $user] = createBudgetCategoryTenant('previous-year-refresh');

    $events = BudgetCategory::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Veranstaltungen',
        'color' => 'amber',
        'sort_order' => 20,
        'active' => true,
    ]);

    $bank = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bank',
        'number' => '1200',
        'type' => 'bank',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $eventIncome = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Erlöse Kartoffelmarkt',
        'number' => '8002',
        'type' => 'einnahme',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    $plan = BudgetPlan::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'year' => 2027,
        'title' => 'Haushaltsplan 2027',
        'status' => 'entwurf',
    ]);

    $plan->items()->create([
        'account_id' => $eventIncome->id,
        'budget_category_id' => null,
        'type' => 'income',
        'period_amount' => '140.00',
        'planning_cycle' => 'yearly',
        'planned_amount' => '140.00',
        'notes' => 'Aus erfassten Buchungen 2026 uebernommen.',
        'sort_order' => 0,
    ]);

    Transaction::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'created_by' => $user->id,
        'date' => '2026-05-01',
        'description' => 'Kartoffelmarkt',
        'amount' => 140,
        'account_from_id' => $eventIncome->id,
        'account_to_id' => $bank->id,
        'budget_category_id' => $events->id,
        'status' => 'entwurf',
    ]);

    $this->actingAs($user)
        ->post(route('budgets.import-actuals', $plan))
        ->assertRedirect(route('budgets.show', $plan))
        ->assertSessionHas('success');

    $plan->refresh()->load('items');

    expect($plan->items)->toHaveCount(1);
    expect($plan->items->first()->budget_category_id)->toBe($events->id);
});
