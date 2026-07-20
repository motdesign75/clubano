<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Member;
use App\Models\Protocol;
use App\Models\PublicForm;
use App\Models\PublicFormSubmission;
use App\Models\Tenant;
use App\Models\User;

function createTenantWithUser(string $role, string $suffix): array
{
    $tenant = Tenant::create([
        'name' => 'Verein ' . strtoupper($suffix),
        'slug' => 'verein-' . $suffix,
        'email' => $suffix . '@example.test',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => $role,
    ]);

    return [$tenant, $user];
}

test('members index hides management actions for viewers and shows them for staff', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $viewer] = createTenantWithUser(User::ROLE_VIEWER, 'members-viewer');

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mia',
        'last_name' => 'Muster',
        'email' => 'mia@example.test',
        'entry_date' => now()->subMonth()->toDateString(),
    ]);

    $viewerResponse = $this->actingAs($viewer)->get(route('members.index'));

    $viewerResponse->assertOk();
    $viewerResponse->assertDontSee('Neues Mitglied');
    $viewerResponse->assertDontSee('Serienaktionen fuer die aktuelle Auswahl');
    $viewerResponse->assertDontSee('Datenauskunft');
    $viewerResponse->assertDontSee('Bearbeiten');
    $viewerResponse->assertDontSee('Archivieren');

    [$staffTenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'members-staff');

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $staffTenant->id,
        'first_name' => 'Theo',
        'last_name' => 'Team',
        'email' => 'theo@example.test',
        'entry_date' => now()->subMonth()->toDateString(),
    ]);

    $staffResponse = $this->actingAs($staff)->get(route('members.index'));

    $staffResponse->assertOk();
    $staffResponse->assertSee('Neues Mitglied');
    $staffResponse->assertSee('Serienaktionen fuer die aktuelle Auswahl');
    $staffResponse->assertSee('Datenauskunft');
    $staffResponse->assertSee('Bearbeiten');
    $staffResponse->assertSee('Archivieren');
});

test('member detail hides sensitive finance data for viewers and shows it for admins', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $viewer] = createTenantWithUser(User::ROLE_VIEWER, 'member-detail-viewer');

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Fina',
        'last_name' => 'Finster',
        'email' => 'fina@example.test',
        'entry_date' => now()->subMonth()->toDateString(),
        'payment_method' => 'sepa_lastschrift',
        'iban' => 'DE02120300000000202051',
        'bic' => 'BYLADEM1001',
    ]);

    $viewerResponse = $this->actingAs($viewer)->get(route('members.show', $member));

    $viewerResponse->assertOk();
    $viewerResponse->assertSee('Zahlungs- und SEPA-Daten sind nur für Admins sichtbar.', false);
    $viewerResponse->assertDontSee('DE02120300000000202051');
    $viewerResponse->assertDontSee('BYLADEM1001');
    $viewerResponse->assertDontSee('Beitragsrechnung anstoßen');

    [$adminTenant, $admin] = createTenantWithUser(User::ROLE_ADMIN, 'member-detail-admin');

    $adminMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $adminTenant->id,
        'first_name' => 'Ada',
        'last_name' => 'Admin',
        'email' => 'ada@example.test',
        'entry_date' => now()->subMonth()->toDateString(),
        'payment_method' => 'sepa_lastschrift',
        'iban' => 'DE02120300000000202051',
        'bic' => 'BYLADEM1001',
    ]);

    $adminResponse = $this->actingAs($admin)->get(route('members.show', $adminMember));

    $adminResponse->assertOk();
    $adminResponse->assertSee('DE02120300000000202051');
    $adminResponse->assertSee('BYLADEM1001');
});

test('forms pages hide management actions for viewers and show them for staff', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $viewer] = createTenantWithUser(User::ROLE_VIEWER, 'forms-viewer');

    $form = PublicForm::create([
        'tenant_id' => $tenant->id,
        'title' => 'Kontaktformular',
        'slug' => 'kontaktformular',
        'form_type' => 'general',
        'success_message' => 'ok',
        'is_active' => true,
    ]);

    PublicFormSubmission::create([
        'public_form_id' => $form->id,
        'tenant_id' => $tenant->id,
        'full_name' => 'Paula Publikum',
        'email' => 'paula@example.test',
        'status' => 'active',
        'answers' => ['frage' => 'Antwort'],
    ]);

    $viewerIndex = $this->actingAs($viewer)->get(route('forms.index'));
    $viewerIndex->assertOk();
    $viewerIndex->assertDontSee('Neues Formular');
    $viewerIndex->assertDontSee('Bearbeiten');
    $viewerIndex->assertDontSee('Einbetten');
    $viewerIndex->assertDontSee('Loeschen');

    $viewerSubmissions = $this->actingAs($viewer)->get(route('forms.submissions', $form));
    $viewerSubmissions->assertOk();
    $viewerSubmissions->assertDontSee('CSV exportieren');
    $viewerSubmissions->assertDontSee('Stornieren');
    $viewerSubmissions->assertDontSee('Löschen');

    [$staffTenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'forms-staff');

    $staffForm = PublicForm::create([
        'tenant_id' => $staffTenant->id,
        'title' => 'Beitrittsformular',
        'slug' => 'beitrittsformular',
        'form_type' => 'membership',
        'success_message' => 'ok',
        'is_active' => true,
    ]);

    PublicFormSubmission::create([
        'public_form_id' => $staffForm->id,
        'tenant_id' => $staffTenant->id,
        'full_name' => 'Tina Team',
        'email' => 'tina@example.test',
        'status' => 'active',
        'answers' => ['frage' => 'Antwort'],
    ]);

    $staffIndex = $this->actingAs($staff)->get(route('forms.index'));
    $staffIndex->assertOk();
    $staffIndex->assertSee('Neues Formular');
    $staffIndex->assertSee('Bearbeiten');

    $staffSubmissions = $this->actingAs($staff)->get(route('forms.submissions', $staffForm));
    $staffSubmissions->assertOk();
    $staffSubmissions->assertSee('CSV exportieren');
    $staffSubmissions->assertSee('Stornieren');
    $staffSubmissions->assertSee('Löschen');
});

test('protocol pages hide management actions for viewers and show them for staff', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $viewer] = createTenantWithUser(User::ROLE_VIEWER, 'protocol-viewer');

    $protocol = Protocol::create([
        'tenant_id' => $tenant->id,
        'user_id' => $viewer->id,
        'title' => 'Vorstandssitzung',
        'type' => 'Vorstand',
        'content' => '<p>Wichtige Punkte</p>',
    ]);

    $viewerIndex = $this->actingAs($viewer)->get(route('protocols.index'));
    $viewerIndex->assertOk();
    $viewerIndex->assertDontSee('Neues Protokoll');
    $viewerIndex->assertDontSee('Bearbeiten');
    $viewerIndex->assertDontSee('Versenden');
    $viewerIndex->assertDontSee('Archivieren');
    $viewerIndex->assertDontSee('Loeschen');

    $viewerShow = $this->actingAs($viewer)->get(route('protocols.show', $protocol));
    $viewerShow->assertOk();
    $viewerShow->assertDontSee('Bearbeiten');
    $viewerShow->assertDontSee('Versenden');

    [$staffTenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'protocol-staff');

    $staffProtocol = Protocol::create([
        'tenant_id' => $staffTenant->id,
        'user_id' => $staff->id,
        'title' => 'Teamsitzung',
        'type' => 'Team',
        'content' => '<p>Nächste Schritte</p>',
    ]);

    $staffIndex = $this->actingAs($staff)->get(route('protocols.index'));
    $staffIndex->assertOk();
    $staffIndex->assertSee('Neues Protokoll');
    $staffIndex->assertSee('Bearbeiten');
    $staffIndex->assertSee('Versenden');
    $staffIndex->assertSee('Archivieren');
    $staffIndex->assertSee('Loeschen');

    $staffShow = $this->actingAs($staff)->get(route('protocols.show', $staffProtocol));
    $staffShow->assertOk();
    $staffShow->assertSee('Bearbeiten');
    $staffShow->assertSee('Versenden');
});
