<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\BudgetCategory;
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
