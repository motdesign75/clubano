<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function createDocumentFolderTenant(): array
{
    $tenant = Tenant::create([
        'name' => 'Ordnerverein ' . Str::random(5),
        'slug' => 'ordnerverein-' . Str::random(8),
        'email' => 'ordner-' . Str::random(5) . '@example.test',
        'license_mode' => 'gifted',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);

    return [$tenant, $user];
}

test('documents can be organized in folders and subfolders', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('local');

    [$tenant, $user] = createDocumentFolderTenant();

    $this->actingAs($user)
        ->post(route('documents.folders.store'), [
            'name' => 'Finanzen',
        ])
        ->assertRedirect();

    $parent = DocumentFolder::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Finanzen')
        ->firstOrFail();

    $this->actingAs($user)
        ->post(route('documents.folders.store'), [
            'name' => 'Belege 2026',
            'parent_id' => $parent->id,
        ])
        ->assertRedirect();

    $child = DocumentFolder::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Belege 2026')
        ->firstOrFail();

    $this->actingAs($user)
        ->post(route('documents.store'), [
            'title' => 'Mietvertrag Vereinsheim',
            'category' => Document::CATEGORY_CONTRACTS,
            'status' => Document::STATUS_ACTIVE,
            'folder_id' => $child->id,
            'file' => UploadedFile::fake()->create('mietvertrag.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('documents.index'));

    $document = Document::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    expect($document->folder_id)->toBe($child->id);

    $this->actingAs($user)
        ->get(route('documents.index', ['folder' => $child->id]))
        ->assertOk()
        ->assertSee('Belege 2026')
        ->assertSee('Mietvertrag Vereinsheim')
        ->assertSee('Finanzen / Belege 2026');

    $this->actingAs($user)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertSee('Finanzen / Belege 2026');
});
