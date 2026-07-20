<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;

test('projects index only shows projects from the authenticated users tenant', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    $tenantA = Tenant::create([
        'name' => 'Verein A',
        'slug' => 'verein-a',
        'email' => 'a@example.test',
    ]);

    $tenantB = Tenant::create([
        'name' => 'Verein B',
        'slug' => 'verein-b',
        'email' => 'b@example.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $ownProject = Project::create([
        'tenant_id' => $tenantA->id,
        'name' => 'Eigenes Projekt',
        'description' => 'Nur fuer Verein A',
        'status' => 'active',
        'owner_id' => $user->id,
    ]);

    $foreignProject = Project::create([
        'tenant_id' => $tenantB->id,
        'name' => 'Fremdes Projekt',
        'description' => 'Darf hier nie auftauchen',
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->get(route('projects.index'));

    $response->assertOk();
    $response->assertSee($ownProject->name);
    $response->assertDontSee($foreignProject->name);
});
