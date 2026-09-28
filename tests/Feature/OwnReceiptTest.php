<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function createOwnReceiptContext(): array
{
    $tenant = Tenant::create([
        'name' => 'Eigenbeleg Verein ' . Str::random(5),
        'slug' => 'eigenbeleg-' . Str::random(8),
        'email' => 'eigenbeleg-' . Str::random(5) . '@example.test',
        'address' => 'Musterstrasse 1',
        'zip' => '31157',
        'city' => 'Sarstedt',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_TREASURER,
        'email_verified_at' => now(),
    ]);

    $bank = Account::create([
        'tenant_id' => $tenant->id,
        'number' => '1200',
        'name' => 'Bank',
        'type' => 'bank',
    ]);

    $income = Account::create([
        'tenant_id' => $tenant->id,
        'number' => '4040',
        'name' => 'Spenden',
        'type' => 'einnahme',
    ]);

    $expense = Account::create([
        'tenant_id' => $tenant->id,
        'number' => '6300',
        'name' => 'Verwaltungskosten',
        'type' => 'ausgabe',
    ]);

    return [$tenant, $user, $bank, $income, $expense];
}

test('own receipt form distinguishes income and expense reasons', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [, $user, $bank, $income] = createOwnReceiptContext();

    $transaction = Transaction::create([
        'tenant_id' => $user->tenant_id,
        'created_by' => $user->id,
        'date' => now()->toDateString(),
        'description' => 'Barspende Mitgliedernachmittag',
        'amount' => 25,
        'account_from_id' => $income->id,
        'account_to_id' => $bank->id,
        'status' => 'entwurf',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.own-receipt', $transaction))
        ->assertOk()
        ->assertSee('Art des Eigenbelegs')
        ->assertSee('Einnahme')
        ->assertSee('Ausgabe')
        ->assertSee('Wofür wurde das Geld eingenommen?');
});

test('own receipt stores the selected receipt direction', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('local');

    [, $user, $bank, $income] = createOwnReceiptContext();

    $transaction = Transaction::create([
        'tenant_id' => $user->tenant_id,
        'created_by' => $user->id,
        'date' => now()->toDateString(),
        'description' => 'Barspende Mitgliedernachmittag',
        'amount' => 25,
        'account_from_id' => $income->id,
        'account_to_id' => $bank->id,
        'status' => 'entwurf',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.own-receipt.store', $transaction), [
            'issuer_name' => 'Max Mustermann',
            'issuer_role' => 'Schatzmeister',
            'receipt_direction' => 'income',
            'expense_reason' => 'Barspende beim Mitgliedernachmittag',
            'missing_receipt_reason' => 'Für die Barspende wurde kein externer Beleg ausgestellt.',
            'location' => 'Sarstedt',
            'notes' => '',
            'approved_by' => 'Vorstand',
        ])
        ->assertRedirect(route('transactions.own-receipt', $transaction));

    $transaction->refresh();

    expect($transaction->receipt_kind)->toBe('eigenbeleg')
        ->and($transaction->receipt_file)->toStartWith('private:receipts/' . $user->tenant_id . '/eigenbelege/')
        ->and($transaction->receipt_meta['receipt_direction'])->toBe('income')
        ->and($transaction->receipt_meta['expense_reason'])->toBe('Barspende beim Mitgliedernachmittag');
});
