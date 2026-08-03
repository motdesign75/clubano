<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Contact;
use App\Models\ImportRun;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
        'source_profile' => 'wiso',
        'import_goal' => 'Erstimport',
    ]);

    $preview->assertOk();
    $preview->assertSee('Import prüfen');
    $preview->assertSee('Import-Bereitschaft');
    $preview->assertSee('WISO MeinVerein');
    $preview->assertSee('Mia');
    $preview->assertSee(';');

    $path = collect(Storage::files('temp'))->first();

    $response = $this->actingAs($admin)->post(route('import.mitglieder.confirm'), [
        'path' => $path,
        'source_profile' => 'wiso',
        'original_filename' => 'mitglieder.csv',
        'import_goal' => 'Erstimport',
        'mapping' => [
            0 => 'first_name',
            1 => 'last_name',
            2 => 'email',
            3 => 'entry_date',
        ],
    ]);

    $run = ImportRun::where('tenant_id', $tenant->id)->where('import_type', 'members')->firstOrFail();

    $response->assertRedirect(route('import.report', $run));
    expect(Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(2);
    expect($run->imported_count)->toBe(2);
    expect($run->summary['source_profile'])->toBe('wiso');
    expect($run->summary['import_goal'])->toBe('Erstimport');

    $this->actingAs($admin)->get(route('import.report', $run))
        ->assertOk()
        ->assertSee('Importbericht')
        ->assertSee('WISO MeinVerein')
        ->assertSee('Nächste Schritte');
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
        'source_profile' => 'campai',
        'mapping' => [
            0 => 'organization',
            1 => 'category',
            2 => 'email',
            3 => 'city',
        ],
    ]);

    $run = ImportRun::where('tenant_id', $tenant->id)->where('import_type', 'contacts')->firstOrFail();

    $response->assertRedirect(route('import.report', $run));

    expect($run->imported_count)->toBe(1);
    expect($run->skipped_count)->toBe(1);
    expect(Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('organization', 'Neue Druckerei')->exists())->toBeTrue();

    $this->actingAs($admin)->post(route('import.mitglieder.undo', $run))
        ->assertRedirect(route('import.kontakte'));

    expect(Contact::withoutGlobalScopes()->whereNull('deleted_at')->where('tenant_id', $tenant->id)->where('organization', 'Neue Druckerei')->exists())->toBeFalse();
    expect(Contact::withoutGlobalScopes()->whereNull('deleted_at')->where('tenant_id', $tenant->id)->where('organization', 'Bestehender Sponsor')->exists())->toBeTrue();
});

test('admin can preview and import members from xlsx', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake();

    [$tenant, $admin] = createImportTenant('xlsx');

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['Vorname', 'Nachname', 'E-Mail', 'Beitrag', 'Zahlungsweise'],
        ['Lena', 'Excel', 'lena@example.test', '75,00', 'vierteljährlich'],
    ]);

    $tempPath = tempnam(sys_get_temp_dir(), 'clubano-xlsx-') . '.xlsx';
    (new Xlsx($spreadsheet))->save($tempPath);

    $file = new UploadedFile(
        $tempPath,
        'mitglieder.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $preview = $this->actingAs($admin)->post(route('import.mitglieder.preview'), [
        'csv_file' => $file,
        'source_profile' => 'excel',
    ]);

    $preview->assertOk();
    $preview->assertSee('XLSX');
    $preview->assertSee('Lena');

    $path = collect(Storage::files('temp'))->first();

    $response = $this->actingAs($admin)->post(route('import.mitglieder.confirm'), [
        'path' => $path,
        'original_filename' => 'mitglieder.xlsx',
        'mapping' => [
            0 => 'first_name',
            1 => 'last_name',
            2 => 'email',
            3 => 'membership_amount',
            4 => 'membership_interval',
        ],
    ]);

    $run = ImportRun::where('tenant_id', $tenant->id)->where('import_type', 'members')->firstOrFail();

    $response->assertRedirect(route('import.report', $run));

    $member = Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($member->first_name)->toBe('Lena');
    expect((float) $member->membership_amount)->toBe(75.0);
    expect($member->membership_interval)->toBe('vierteljährlich');
    expect($run->summary['file_type'])->toBe('xlsx');
});
