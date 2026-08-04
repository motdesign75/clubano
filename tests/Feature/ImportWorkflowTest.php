<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Contact;
use App\Models\CustomMemberField;
use App\Models\ImportRun;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Tag;
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

    $preview = $this->actingAs($admin)->post(route('import.kontakte.preview'), [
        'csv_file' => $file,
    ]);

    $preview->assertOk();
    $preview->assertSee('Dublettenprüfung');
    $preview->assertSee('Bestehender Sponsor');
    $preview->assertSee('gleiche E-Mail');

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
    expect($run->summary['duplicate_count'])->toBe(1);
    expect($run->summary['duplicate_strategy'])->toBe('skip');
    expect($run->summary['skipped_rows'][0]['incoming'])->toBe('Bestehender Sponsor');
    expect(Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('organization', 'Neue Druckerei')->exists())->toBeTrue();

    $this->actingAs($admin)->get(route('import.report.export', $run))
        ->assertOk()
        ->assertDownload('clubano-importbericht-' . $run->id . '.xlsx');

    $this->actingAs($admin)->post(route('import.mitglieder.undo', $run))
        ->assertRedirect(route('import.kontakte'));

    expect(Contact::withoutGlobalScopes()->whereNull('deleted_at')->where('tenant_id', $tenant->id)->where('organization', 'Neue Druckerei')->exists())->toBeFalse();
    expect(Contact::withoutGlobalScopes()->whereNull('deleted_at')->where('tenant_id', $tenant->id)->where('organization', 'Bestehender Sponsor')->exists())->toBeTrue();
});

test('admin can intentionally create duplicate contacts from import', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake();

    [$tenant, $admin] = createImportTenant('duplicate-create');

    Contact::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'contact_type' => 'organization',
        'organization' => 'Demo Sponsor',
        'email' => 'sponsor@example.test',
        'is_active' => true,
    ]);

    $csv = "Organisation;E-Mail;Ort\nDemo Sponsor;sponsor@example.test;Demostadt\n";
    $file = UploadedFile::fake()->createWithContent('kontakte.csv', $csv);

    $this->actingAs($admin)->post(route('import.kontakte.preview'), [
        'csv_file' => $file,
    ])->assertOk();

    $path = collect(Storage::files('temp'))->first();

    $response = $this->actingAs($admin)->post(route('import.kontakte.confirm'), [
        'path' => $path,
        'duplicate_strategy' => 'create_new',
        'mapping' => [
            0 => 'organization',
            1 => 'email',
            2 => 'city',
        ],
    ]);

    $run = ImportRun::where('tenant_id', $tenant->id)->where('import_type', 'contacts')->firstOrFail();

    $response->assertRedirect(route('import.report', $run));

    expect($run->imported_count)->toBe(1);
    expect($run->skipped_count)->toBe(0);
    expect($run->summary['duplicate_count'])->toBe(1);
    expect($run->summary['duplicate_strategy'])->toBe('create_new');
    expect(Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('email', 'sponsor@example.test')->count())->toBe(2);
});

test('contact import normalizes categories and organization types', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake();

    [$tenant, $admin] = createImportTenant('contact-categories');

    $csv = "Kontaktart;Kategorie;Organisation;Vorname;Nachname;E-Mail;Ort\nFirma;Sponsoring;Demo Bau GmbH;;;bau@example.test;Demostadt\nBehörde;Stadtverwaltung;Rathaus Demostadt;;;amt@example.test;Demostadt\nPerson;Übungsleiter;;Clara;Coach;clara@example.test;Demostadt\n";
    $file = UploadedFile::fake()->createWithContent('kontakte.csv', $csv);

    $preview = $this->actingAs($admin)->post(route('import.kontakte.preview'), [
        'csv_file' => $file,
    ]);

    $preview->assertOk();
    $preview->assertSee('Kontaktart');
    $preview->assertSee('Kategorie');

    $path = collect(Storage::files('temp'))->first();

    $response = $this->actingAs($admin)->post(route('import.kontakte.confirm'), [
        'path' => $path,
        'mapping' => [
            0 => 'contact_type',
            1 => 'category',
            2 => 'organization',
            3 => 'first_name',
            4 => 'last_name',
            5 => 'email',
            6 => 'city',
        ],
    ]);

    $run = ImportRun::where('tenant_id', $tenant->id)->where('import_type', 'contacts')->firstOrFail();

    $response->assertRedirect(route('import.report', $run));

    $sponsor = Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('email', 'bau@example.test')->firstOrFail();
    $authority = Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('email', 'amt@example.test')->firstOrFail();
    $trainer = Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('email', 'clara@example.test')->firstOrFail();

    expect($sponsor->contact_type)->toBe('organization');
    expect($sponsor->category)->toBe('sponsor');
    expect($authority->contact_type)->toBe('organization');
    expect($authority->category)->toBe('authority');
    expect($trainer->contact_type)->toBe('person');
    expect($trainer->category)->toBe('trainer');
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

test('member import can create and assign memberships from contribution data', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake();

    [$tenant, $admin] = createImportTenant('membership-import');

    $csv = "Mitgliedsnummer;Mitgliedschaft;Vorname;Nachname;Beitrag;Zahlungsweise;Zahlungsmethode\nM-10;Aktiv;Mia;Muster;75,00;quartalsweise;SEPA\nM-11;Aktiv;Tom;Test;75,00;quartalsweise;Überweisung\n";
    $file = UploadedFile::fake()->createWithContent('mitglieder.csv', $csv);

    $preview = $this->actingAs($admin)->post(route('import.mitglieder.preview'), [
        'csv_file' => $file,
    ]);

    $preview->assertOk();
    $preview->assertSee('Mitgliedschaften aus Beitragsdaten');

    $path = collect(Storage::files('temp'))->first();

    $response = $this->actingAs($admin)->post(route('import.mitglieder.confirm'), [
        'path' => $path,
        'membership_strategy' => 'create_and_assign',
        'mapping' => [
            0 => 'member_id',
            1 => 'membership_name',
            2 => 'first_name',
            3 => 'last_name',
            4 => 'membership_amount',
            5 => 'membership_interval',
            6 => 'payment_method',
        ],
    ]);

    $run = ImportRun::where('tenant_id', $tenant->id)->where('import_type', 'members')->firstOrFail();
    $membership = Membership::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Aktiv')->firstOrFail();

    $response->assertRedirect(route('import.report', $run));

    expect(Membership::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);
    expect((float) $membership->amount)->toBe(75.0);
    expect($membership->interval)->toBe('vierteljährlich');
    expect($run->summary['membership_strategy'])->toBe('create_and_assign');
    expect($run->summary['created_membership_count'])->toBe(1);

    $member = Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('member_id', 'M-10')->firstOrFail();
    expect($member->membership_id)->toBe($membership->id);
    expect($member->membership_interval)->toBe('vierteljährlich');
    expect($member->payment_method)->toBe('sepa_lastschrift');

    $this->actingAs($admin)->get(route('import.report', $run))
        ->assertOk()
        ->assertSee('Mitgliedschaften aus Import bilden und zuordnen')
        ->assertSee('1 neu angelegt');

    $this->actingAs($admin)->post(route('import.mitglieder.undo', $run))
        ->assertRedirect(route('import.mitglieder'));

    expect(Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(0);
    expect(Membership::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('member import can save unmapped columns as custom member fields', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake();

    [$tenant, $admin] = createImportTenant('custom-fields');

    $csv = "Vorname;Nachname;Trikotgröße;Interne Notiz\nMia;Muster;M;Teamleitung\nTom;Test;L;\n";
    $file = UploadedFile::fake()->createWithContent('mitglieder.csv', $csv);

    $this->actingAs($admin)->post(route('import.mitglieder.preview'), [
        'csv_file' => $file,
    ])->assertOk()
        ->assertSee('Zusatzspalten sichern');

    $path = collect(Storage::files('temp'))->first();

    $response = $this->actingAs($admin)->post(route('import.mitglieder.confirm'), [
        'path' => $path,
        'custom_field_strategy' => 'create_from_unmapped',
        'mapping' => [
            0 => 'first_name',
            1 => 'last_name',
            2 => 'skip',
            3 => 'skip',
        ],
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();
    $response->assertSessionMissing('error');

    $run = ImportRun::where('tenant_id', $tenant->id)->where('import_type', 'members')->firstOrFail();

    $response->assertRedirect(route('import.report', $run));

    expect($run->summary['custom_field_strategy'])->toBe('create_from_unmapped');
    expect($run->summary['created_custom_field_count'])->toBe(2);

    $jerseyField = CustomMemberField::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('label', 'Trikotgröße')
        ->firstOrFail();

    $member = Member::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('first_name', 'Mia')
        ->firstOrFail();

    expect($member->customValues()->where('custom_member_field_id', $jerseyField->id)->value('value'))->toBe('M');

    $this->actingAs($admin)->get(route('import.report', $run))
        ->assertOk()
        ->assertSee('Ignorierte Spalten als eigene Felder sichern')
        ->assertSee('2 eigene Felder neu angelegt');

    $this->actingAs($admin)->post(route('import.mitglieder.undo', $run))
        ->assertRedirect(route('import.mitglieder'));

    expect(Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(0);
    expect(CustomMemberField::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('member import creates and attaches tags from department columns', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake();

    [$tenant, $admin] = createImportTenant('tag-import');

    Tag::create([
        'tenant_id' => $tenant->id,
        'name' => 'Vorstand',
        'color' => '#111827',
    ]);

    $csv = "Vorname;Nachname;Abteilung;Tags\nMia;Muster;Karate;\"Vorstand;Jugend\"\nTom;Test;Fußball;Aktiv\n";
    $file = UploadedFile::fake()->createWithContent('mitglieder.csv', $csv);

    $preview = $this->actingAs($admin)->post(route('import.mitglieder.preview'), [
        'csv_file' => $file,
    ]);

    $preview->assertOk();
    $preview->assertSee('Tags / Abteilungen / Gruppen');

    $path = collect(Storage::files('temp'))->first();

    $response = $this->actingAs($admin)->post(route('import.mitglieder.confirm'), [
        'path' => $path,
        'mapping' => [
            0 => 'first_name',
            1 => 'last_name',
            2 => 'tags',
            3 => 'tags',
        ],
    ]);

    $run = ImportRun::where('tenant_id', $tenant->id)->where('import_type', 'members')->firstOrFail();

    $response->assertRedirect(route('import.report', $run));

    $member = Member::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('first_name', 'Mia')
        ->firstOrFail();

    expect($member->tags()->pluck('name')->sort()->values()->all())->toBe(['Jugend', 'Karate', 'Vorstand']);
    expect(Tag::where('tenant_id', $tenant->id)->where('name', 'Vorstand')->count())->toBe(1);
    expect(Tag::where('tenant_id', $tenant->id)->where('name', 'Fußball')->exists())->toBeTrue();
});

test('admin can download import templates', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [, $admin] = createImportTenant('templates');

    $this->actingAs($admin)->get(route('import.template', 'mitglieder'))
        ->assertOk()
        ->assertDownload('clubano-importvorlage-mitglieder.xlsx');

    $this->actingAs($admin)->get(route('import.template', 'kontakte'))
        ->assertOk()
        ->assertDownload('clubano-importvorlage-kontakte.xlsx');
});

test('member import blocks unsafe mappings before writing data', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake();

    [$tenant, $admin] = createImportTenant('unsafe-members');

    $csv = "Vorname;E-Mail\nMia;mia@example.test\n";
    $file = UploadedFile::fake()->createWithContent('mitglieder.csv', $csv);

    $this->actingAs($admin)->post(route('import.mitglieder.preview'), [
        'csv_file' => $file,
    ])->assertOk();

    $path = collect(Storage::files('temp'))->first();

    $response = $this->actingAs($admin)->post(route('import.mitglieder.confirm'), [
        'path' => $path,
        'mapping' => [
            0 => 'first_name',
            1 => 'email',
        ],
    ]);

    $response->assertRedirect(route('import.mitglieder'));
    $response->assertSessionHas('error', 'Bitte ordne für Mitglieder mindestens Nachname zu.');

    expect(Member::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(0);
    expect(ImportRun::where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('contact import blocks mappings without a usable name', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake();

    [$tenant, $admin] = createImportTenant('unsafe-contacts');

    $csv = "E-Mail;Ort\nkontakt@example.test;Demostadt\n";
    $file = UploadedFile::fake()->createWithContent('kontakte.csv', $csv);

    $this->actingAs($admin)->post(route('import.kontakte.preview'), [
        'csv_file' => $file,
    ])->assertOk();

    $path = collect(Storage::files('temp'))->first();

    $response = $this->actingAs($admin)->post(route('import.kontakte.confirm'), [
        'path' => $path,
        'mapping' => [
            0 => 'email',
            1 => 'city',
        ],
    ]);

    $response->assertRedirect(route('import.kontakte'));
    $response->assertSessionHas('error', 'Bitte ordne für Kontakte mindestens Organisation, Vorname oder Nachname zu.');

    expect(Contact::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(0);
    expect(ImportRun::where('tenant_id', $tenant->id)->count())->toBe(0);
});
