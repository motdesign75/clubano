<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Member;
use App\Models\Protocol;
use App\Models\PublicForm;
use App\Models\PublicFormSubmission;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\Task;
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
    $viewerResponse->assertDontSee('Serienaktionen für die aktuelle Auswahl');
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
    $staffResponse->assertSee('Serienaktionen für die aktuelle Auswahl');
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
    $viewerIndex->assertDontSee('Löschen');

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
    $viewerIndex->assertDontSee('Löschen');

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
    $staffIndex->assertSee('Löschen');

    $staffShow = $this->actingAs($staff)->get(route('protocols.show', $staffProtocol));
    $staffShow->assertOk();
    $staffShow->assertSee('Bearbeiten');
    $staffShow->assertSee('Versenden');
});

test('protocol mail form excludes archived members from recipients', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'protocol-mail-archived');

    $activeMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Aktive',
        'last_name' => 'Person',
        'email' => 'aktive@example.test',
        'entry_date' => now()->subMonth()->toDateString(),
    ]);

    $archivedMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Archivierte',
        'last_name' => 'Person',
        'email' => 'archivierte@example.test',
        'entry_date' => now()->subMonth()->toDateString(),
        'archived_at' => now()->subDay(),
    ]);

    $protocol = Protocol::create([
        'tenant_id' => $tenant->id,
        'user_id' => $staff->id,
        'title' => 'Versandtest',
        'type' => 'Team',
        'content' => '<p>Versand</p>',
    ]);

    $response = $this->actingAs($staff)->get(route('protocols.mail.form', $protocol));

    $response->assertOk();
    $response->assertSee($activeMember->email);
    $response->assertDontSee($archivedMember->email);
});

test('protocol create ignores archived members as participants', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'protocol-create-archived');

    $activeMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Aktive',
        'last_name' => 'Teilnehmerin',
        'email' => 'aktive-teilnehmerin@example.test',
        'entry_date' => now()->subMonth()->toDateString(),
    ]);

    $archivedMember = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Archivierter',
        'last_name' => 'Teilnehmer',
        'email' => 'archivierter-teilnehmer@example.test',
        'entry_date' => now()->subMonth()->toDateString(),
        'archived_at' => now()->subDay(),
    ]);

    $formResponse = $this->actingAs($staff)->get(route('protocols.create'));

    $formResponse->assertOk();
    $formResponse->assertSee($activeMember->full_name);
    $formResponse->assertDontSee($archivedMember->full_name);

    $storeResponse = $this->actingAs($staff)->post(route('protocols.store'), [
        'title' => 'Teilnehmertest',
        'type' => 'Team',
        'location' => 'Clubhaus',
        'content' => '<p>Inhalt</p>',
        'participant_ids' => [
            $activeMember->id,
            $archivedMember->id,
        ],
    ]);

    $storeResponse->assertRedirect(route('protocols.index'));

    $protocol = Protocol::where('tenant_id', $tenant->id)
        ->where('title', 'Teilnehmertest')
        ->firstOrFail();

    $participantIds = $protocol->participants()
        ->pluck('members.id')
        ->all();

    expect($participantIds)->toContain($activeMember->id);
    expect($participantIds)->not->toContain($archivedMember->id);
});

test('protocol index paginates and can be searched', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'protocol-search');

    foreach (range(1, 16) as $number) {
        Protocol::create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'title' => 'Protokoll ' . sprintf('%02d', $number),
            'type' => 'Team',
            'content' => '<p>Inhalt ' . $number . '</p>',
            'created_at' => now()->subMinutes($number),
            'updated_at' => now()->subMinutes($number),
        ]);
    }

    $indexResponse = $this->actingAs($staff)->get(route('protocols.index'));

    $indexResponse->assertOk();
    $indexResponse->assertSee('1-15 von 16 Protokollen');
    $indexResponse->assertSee('Protokoll 01');
    $indexResponse->assertDontSee('Protokoll 16');

    $searchResponse = $this->actingAs($staff)->get(route('protocols.index', ['search' => 'Protokoll 16']));

    $searchResponse->assertOk();
    $searchResponse->assertSee('1-1 von 1 Protokollen');
    $searchResponse->assertSee('Protokoll 16');
    $searchResponse->assertDontSee('Protokoll 01');
});

test('template index paginates and can be searched', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'template-search');

    foreach (range(1, 16) as $number) {
        Template::create([
            'tenant_id' => $tenant->id,
            'name' => 'Vorlage ' . sprintf('%02d', $number),
            'subject' => 'Betreff ' . $number,
            'body' => '<p>Inhalt ' . $number . '</p>',
            'type' => Template::TYPE_MAIL,
        ]);
    }

    $indexResponse = $this->actingAs($staff)->get(route('templates.index'));

    $indexResponse->assertOk();
    $indexResponse->assertSee('1-15 von 16 Vorlagen');
    $indexResponse->assertSee('Vorlage 01');
    $indexResponse->assertDontSee('Vorlage 16');

    $searchResponse = $this->actingAs($staff)->get(route('templates.index', ['search' => 'Vorlage 16']));

    $searchResponse->assertOk();
    $searchResponse->assertSee('1-1 von 1 Vorlagen');
    $searchResponse->assertSee('Vorlage 16');
    $searchResponse->assertDontSee('Vorlage 01');
});

test('template create shows the guided template editor', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [, $staff] = createTenantWithUser(User::ROLE_STAFF, 'template-editor');

    $response = $this->actingAs($staff)->get(route('templates.create'));

    $response->assertOk();
    $response->assertSee('Vorlageneditor');
    $response->assertSee('Text gestalten');
    $response->assertSee('Platzhalter');
    $response->assertSee('So wirkt die Vorlage');
    $response->assertSee('Vorlage speichern');
    $response->assertSee('link image table', false);
    $response->assertSee('paste_data_images', false);
});

test('tasks index shows the calmer guided task overview', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'tasks-overview');

    Task::create([
        'tenant_id' => $tenant->id,
        'title' => 'Belege prüfen',
        'description' => 'Die offenen Belege noch einmal prüfen.',
        'status' => 'open',
        'assignee_id' => $staff->id,
        'created_by' => $staff->id,
        'plan_end' => now()->subDay()->toDateString(),
        'priority' => 2,
        'percent_done' => 0,
        'type' => 'task',
    ]);

    Task::create([
        'tenant_id' => $tenant->id,
        'title' => 'Einladung vorbereiten',
        'status' => 'in_progress',
        'assignee_id' => $staff->id,
        'created_by' => $staff->id,
        'plan_end' => now()->addDays(3)->toDateString(),
        'priority' => 3,
        'percent_done' => 40,
        'type' => 'task',
    ]);

    $response = $this->actingAs($staff)->get(route('tasks.index'));

    $response->assertOk();
    $response->assertSee('Was ist als Nächstes dran?');
    $response->assertSee('Nächster sinnvoller Schritt');
    $response->assertSee('Das mache ich jetzt');
    $response->assertSee('Belege prüfen');
    $response->assertSee('Aufmerksamkeit');
    $response->assertSee('Archivierter Blick');
});

test('task quick action starts the next task', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'tasks-quick-action');

    $task = Task::create([
        'tenant_id' => $tenant->id,
        'title' => 'Sponsoren anrufen',
        'status' => 'open',
        'created_by' => $staff->id,
        'plan_end' => now()->toDateString(),
        'priority' => 2,
        'percent_done' => 0,
        'type' => 'task',
    ]);

    $response = $this->actingAs($staff)->patch(route('tasks.quick-action', $task), [
        'action' => 'start',
    ]);

    $response->assertRedirect(route('tasks.index'));

    $task->refresh();

    expect($task->status)->toBe('in_progress');
    expect($task->percent_done)->toBe(10);
    expect($task->assignee_id)->toBe($staff->id);
});
