<?php

use App\Models\Member;
use App\Models\PublicForm;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;

test('tenant scoped models only expose the current club while operator superadmin keeps platform visibility', function () {
    $tenantA = Tenant::create([
        'name' => 'Verein A',
        'slug' => 'tenant-scope-a',
        'email' => 'scope-a@example.test',
    ]);

    $tenantB = Tenant::create([
        'name' => 'Verein B',
        'slug' => 'tenant-scope-b',
        'email' => 'scope-b@example.test',
    ]);

    $clubAdmin = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $operator = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_SUPERADMIN,
        'email_verified_at' => now(),
    ]);

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenantA->id,
        'first_name' => 'Eigenes',
        'last_name' => 'Mitglied',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'first_name' => 'Fremdes',
        'last_name' => 'Mitglied',
        'entry_date' => now()->subYear()->toDateString(),
    ]);

    Task::withoutGlobalScopes()->create([
        'tenant_id' => $tenantA->id,
        'title' => 'Eigene Aufgabe',
        'status' => 'open',
    ]);

    Task::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'title' => 'Fremde Aufgabe',
        'status' => 'open',
    ]);

    PublicForm::withoutGlobalScopes()->create([
        'tenant_id' => $tenantA->id,
        'title' => 'Eigenes Formular',
        'slug' => 'eigenes-formular',
        'form_type' => 'general',
        'success_message' => 'Danke',
        'is_active' => true,
    ]);

    PublicForm::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'title' => 'Fremdes Formular',
        'slug' => 'fremdes-formular',
        'form_type' => 'general',
        'success_message' => 'Danke',
        'is_active' => true,
    ]);

    $this->actingAs($clubAdmin);

    expect(Member::query()->pluck('first_name')->all())->toBe(['Eigenes'])
        ->and(Task::query()->pluck('title')->all())->toBe(['Eigene Aufgabe'])
        ->and(PublicForm::query()->pluck('title')->all())->toBe(['Eigenes Formular']);

    $this->actingAs($operator);

    expect(Member::query()->count())->toBe(2)
        ->and(Task::query()->count())->toBe(2)
        ->and(PublicForm::query()->count())->toBe(2);
});

test('tenant data models declare an explicit tenant scope', function () {
    $allowedWithoutTenantScope = [
        'CustomMemberValue.php',
        'Tenant.php',
        'User.php',
    ];

    $unprotectedModels = collect(glob(app_path('Models/*.php')))
        ->reject(fn (string $path) => in_array(basename($path), $allowedWithoutTenantScope, true))
        ->filter(function (string $path) {
            $source = file_get_contents($path);

            return str_contains($source, "'tenant_id'")
                && ! str_contains($source, 'BelongsToTenant')
                && ! str_contains($source, 'CurrentTenantScope');
        })
        ->map(fn (string $path) => basename($path))
        ->values()
        ->all();

    expect($unprotectedModels)->toBe([]);
});
