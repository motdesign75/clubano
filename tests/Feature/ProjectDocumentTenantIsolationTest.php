<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('project documents can only be downloaded within the same tenant and project', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('local');

    $tenantA = Tenant::create([
        'name' => 'Verein A',
        'slug' => 'verein-a-docs',
        'email' => 'verein-a-docs@example.test',
    ]);

    $tenantB = Tenant::create([
        'name' => 'Verein B',
        'slug' => 'verein-b-docs',
        'email' => 'verein-b-docs@example.test',
    ]);

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $userB = User::factory()->create([
        'tenant_id' => $tenantB->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $projectA = Project::create([
        'tenant_id' => $tenantA->id,
        'name' => 'Projekt A',
        'status' => 'active',
        'owner_id' => $userA->id,
    ]);

    $projectB = Project::create([
        'tenant_id' => $tenantB->id,
        'name' => 'Projekt B',
        'status' => 'active',
        'owner_id' => $userB->id,
    ]);

    $path = UploadedFile::fake()->create('geheim.pdf', 32, 'application/pdf')
        ->store('projects/' . $tenantA->id . '/' . $projectA->id, 'local');

    $document = ProjectDocument::create([
        'tenant_id' => $tenantA->id,
        'project_id' => $projectA->id,
        'user_id' => $userA->id,
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'geheim.pdf',
        'size' => 32000,
        'mime_type' => 'application/pdf',
    ]);

    $ok = $this->actingAs($userA)
        ->get(route('projects.documents.download', [$projectA, $document]));

    $ok->assertOk();

    $wrongTenant = $this->actingAs($userB)
        ->get(route('projects.documents.download', [$projectA, $document]));

    $wrongTenant->assertNotFound();

    $wrongProject = $this->actingAs($userA)
        ->get(route('projects.documents.download', [$projectB, $document]));

    $wrongProject->assertNotFound();
});
