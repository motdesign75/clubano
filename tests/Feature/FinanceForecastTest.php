<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

function createFinanceForecastTenant(): array
{
    $tenant = Tenant::create([
        'name' => 'Vorschauverein',
        'slug' => 'vorschauverein-' . Str::random(5),
        'email' => 'vorschau-' . Str::random(5) . '@example.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_TREASURER,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $user];
}

test('finance forecast shows expected contributions invoices and planned expenses', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00'));

    [$tenant, $user] = createFinanceForecastTenant();

    $membership = Membership::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Erwachsene',
        'amount' => 120,
        'interval' => 'jährlich',
    ]);

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mia',
        'last_name' => 'Beitrag',
        'entry_date' => '2026-01-01',
        'membership_id' => $membership->id,
        'membership_amount' => 120,
        'membership_interval' => 'jährlich',
        'next_membership_invoice_on' => '2026-11-01',
        'payment_method' => 'ueberweisung',
    ]);

    $invoice = Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'document_type' => 'invoice',
        'recipient_type' => 'free',
        'recipient_name' => 'Sponsor GmbH',
        'invoice_number' => 'R-2026-001',
        'invoice_date' => '2026-10-01',
        'due_date' => '2026-10-20',
        'status' => 'open',
        'discount' => 0,
        'tax_rate' => 0,
    ]);

    InvoiceItem::withoutGlobalScopes()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Sponsoring',
        'quantity' => 1,
        'unit_price' => 250,
    ]);

    $plan = BudgetPlan::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'year' => 2026,
        'title' => 'Haushaltsplan 2026',
        'status' => 'entwurf',
    ]);

    $expenseAccount = Account::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Miete',
        'number' => '4210',
        'type' => 'ausgabe',
        'tax_area' => 'ideell',
        'active' => true,
        'online' => false,
    ]);

    BudgetPlanItem::withoutGlobalScopes()->create([
        'budget_plan_id' => $plan->id,
        'account_id' => $expenseAccount->id,
        'type' => 'expense',
        'period_amount' => 1200,
        'planning_cycle' => 'yearly',
        'planned_amount' => 1200,
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('finance-forecast.index'))
        ->assertOk()
        ->assertSee('Finanzvorschau')
        ->assertSee('Mia Beitrag')
        ->assertSee('120,00 €')
        ->assertSee('250,00 €')
        ->assertSee('1.200,00 €');

    Carbon::setTestNow();
});

test('finance forecast highlights overdue membership invoices', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00'));

    [$tenant, $user] = createFinanceForecastTenant();

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Ole',
        'last_name' => 'Offen',
        'entry_date' => '2026-01-01',
        'membership_amount' => 80,
        'membership_interval' => 'jährlich',
    ]);

    $invoice = Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'document_type' => 'invoice',
        'member_id' => $member->id,
        'recipient_type' => 'member',
        'recipient_name' => 'Ole Offen',
        'invoice_number' => 'B-2026-001',
        'invoice_date' => '2026-01-01',
        'due_date' => '2026-02-01',
        'period_from' => '2026-01-01',
        'period_to' => '2026-12-31',
        'status' => 'open',
        'discount' => 0,
        'tax_rate' => 0,
    ]);

    InvoiceItem::withoutGlobalScopes()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Mitgliedsbeitrag',
        'quantity' => 1,
        'unit_price' => 80,
    ]);

    $this->actingAs($user)
        ->get(route('finance-forecast.index'))
        ->assertOk()
        ->assertSee('Ueberfaellige Beitraege')
        ->assertSee('B-2026-001')
        ->assertSee('80,00 €');

    Carbon::setTestNow();
});
