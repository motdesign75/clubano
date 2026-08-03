<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Contact;
use App\Models\ImportRun;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createImportTenant(string $suffix): array
{
    $tenant = Tenant::create([
        'name' => 'Importverein ' . strtoupper($suffix),
        'slug' => 'importverein-' . $suffix,
        'email' => $suffix . '@example.test',
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    return [$tenant, $admin];
}

test('admin can preview and import members from semicolon csv', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake();

    [$tenant, $admin] = createImportTenant('members');

    $csv = "Vorname;Nachname;E-Mail;Eintritt\nMia;Muster;mia@example.test;01.01.2026\nTom;Test;tom@example.test;02.01.2026\n";
    $file = UploadedFile::fake()->createWithContent('mitglieder.csv', $csv);

    $preview = $this->actingAs($admin)->post(route('import.mitglieder.preview'), [
        'csv_file' => $file,
    ]);

    $preview->assertOk();
    $preview->assertSee('Import prüfen');
    $preview->assertSee('Mia');
    $preview->assertSee(';');

    $path = collect(Storage::files('temp'))->first();

    $response = $this->actingAs($admin)->post(route('import.mitglieder.confirm'), [
        'path' => $path,
        'mapping' => [
            0 => 'first_name',
            1 => 'last_name',
            2 => 'email',
            3 => 'entry_date',
        ],
    ]);

    $response->assertRedirect(route('import.mitglieder'));

    expect(Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(2);
    expect(ImportRun::where('tenant_id', $tenant->id)->where('import_type', 'members')->value('imported_count'))->toBe(2);
});

test('admin can import contacts and undo the import', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake();

    [$tenant, $admin] = createImportTenant('contacts');

    Contact::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'contact_type' => 'organization',
        'organization' => 'Bestehender Sponsor',
        'email' => 'sponsor@example.test',
        'is_active' => true,
    ]);

    $csv = "Organisation;Kategorie;E-Mail;Ort\nBestehender Sponsor;sponsor;sponsor@example.test;Altstadt\nNeue Druckerei;supplier;druck@example.test;Demostadt\n";
    $file = UploadedFile::fake()->createWithContent('kontakte.csv', $csv);

    $this->actingAs($admin)->post(route('import.kontakte.preview'), [
        'csv_file' => $file,
    ])->assertOk();

    $path = collect(Storage::files('temp'))->first();

    $response = $this->actingAs($admin)->post(route('import.kontakte.confirm'), [
        'path' => $path,
        'mapping' => [
            0 => 'organization',
            1 => 'category',
            2 => 'email',
            3 => 'city',
        ],
    ]);

    $response->assertRedirect(route('import.kontakte'));

    $run = ImportRun::where('tenant_id', $tenant->id)->where('import_type', 'contacts')->firstOrFail();

    expect($run->imported_count)->toBe(1);
    expect($run->skipped_count)->toBe(1);
    expect(Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('organization', 'Neue Druckerei')->exists())->toBeTrue();

    $this->actingAs($admin)->post(route('import.mitglieder.undo', $run))
        ->assertRedirect(route('import.kontakte'));

    expect(Contact::withoutGlobalScopes()->whereNull('deleted_at')->where('tenant_id', $tenant->id)->where('organization', 'Neue Druckerei')->exists())->toBeFalse();
    expect(Contact::withoutGlobalScopes()->whereNull('deleted_at')->where('tenant_id', $tenant->id)->where('organization', 'Bestehender Sponsor')->exists())->toBeTrue();
});
