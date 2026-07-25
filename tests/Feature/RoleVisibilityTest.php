<?php

use App\Http\Middleware\EnsureTenantIsSubscribed;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Member;
use App\Models\Protocol;
use App\Models\PublicForm;
use App\Models\PublicFormSubmission;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('dashboard shows a calm guided cockpit', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'dashboard-cockpit');

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mira',
        'last_name' => 'Muster',
        'email' => 'mira-dashboard@example.test',
        'entry_date' => now()->startOfMonth()->toDateString(),
    ]);

    Event::create([
        'tenant_id' => $tenant->id,
        'user_id' => $staff->id,
        'title' => 'Vorstandsrunde',
        'start' => now()->addDays(2),
        'end' => now()->addDays(2)->addHour(),
        'location' => 'Vereinsheim',
        'is_public' => false,
    ]);

    PublicForm::create([
        'tenant_id' => $tenant->id,
        'title' => 'Kontaktformular',
        'slug' => 'dashboard-kontaktformular',
        'form_type' => 'general',
        'success_message' => 'ok',
        'is_active' => true,
    ]);

    $response = $this->actingAs($staff)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Heute zuerst');
    $response->assertSee('Nächster Schritt');
    $response->assertSee('Hinweise');
    $response->assertSee('Nächste Termine');
    $response->assertSee('Vorstandsrunde');
    $response->assertSee('Entwicklung im Verein');
});

test('events index shows the calmer calendar cockpit', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'events-cockpit');

    $category = EventCategory::create([
        'tenant_id' => $tenant->id,
        'name' => 'Training',
        'color' => '#0f766e',
    ]);

    Event::create([
        'tenant_id' => $tenant->id,
        'user_id' => $staff->id,
        'title' => 'Abendtraining',
        'description' => 'Training in der Halle.',
        'location' => 'Sporthalle',
        'start' => now()->addDays(3)->setTime(18, 0),
        'end' => now()->addDays(3)->setTime(20, 0),
        'category_id' => $category->id,
        'responsible_user_id' => $staff->id,
        'is_public' => false,
    ]);

    $response = $this->actingAs($staff)->get(route('events.index', [
        'view' => 'month',
        'month' => now()->format('Y-m'),
    ]));

    $response->assertOk();
    $response->assertSee('Vereinskalender');
    $response->assertSee('Kalenderwerkzeuge');
    $response->assertSee('Agenda');
    $response->assertSee('Abendtraining');
    $response->assertSee('Termin oder Serie planen');
    $response->assertSee('Serientermin');
});

test('event create shows the guided event editor', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [, $staff] = createTenantWithUser(User::ROLE_STAFF, 'events-editor');

    $response = $this->actingAs($staff)->get(route('events.create'));

    $response->assertOk();
    $response->assertSee('Event-Editor');
    $response->assertSee('Worum geht es?');
    $response->assertSee('Wann und wo?');
    $response->assertSee('Veröffentlichen');
    $response->assertSee('Anmeldung aktivieren');
    $response->assertSee('Als Serie anlegen');
    $response->assertSee('Termin speichern');
});

test('documents area stores searchable linked documents', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);
    Storage::fake('local');

    [$tenant, $staff] = createTenantWithUser(User::ROLE_STAFF, 'documents-store');

    $member = Member::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Dora',
        'last_name' => 'Dokument',
        'email' => 'dora@example.test',
        'entry_date' => now()->subMonth()->toDateString(),
    ]);

    $storeResponse = $this->actingAs($staff)->post(route('documents.store'), [
        'title' => 'Versicherungspolice 2026',
        'category' => Document::CATEGORY_CONTRACTS,
        'status' => Document::STATUS_REVIEW,
        'description' => 'Muss vor Ablauf geprüft werden.',
        'tags' => 'Versicherung, Vertrag',
        'expires_at' => now()->addDays(14)->toDateString(),
        'member_id' => $member->id,
        'file' => UploadedFile::fake()->create('police.pdf', 120, 'application/pdf'),
    ]);

    $storeResponse->assertRedirect(route('documents.index'));

    $document = Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

    Storage::disk('local')->assertExists($document->path);
    expect($document->title)->toBe('Versicherungspolice 2026');
    expect($document->member_id)->toBe($member->id);
    expect($document->tags)->toBe(['Versicherung', 'Vertrag']);

    $indexResponse = $this->actingAs($staff)->get(route('documents.index', ['search' => 'Versicherungspolice']));

    $indexResponse->assertOk();
    $indexResponse->assertSee('Dokumentenzentrale');
    $indexResponse->assertSee('Versicherungspolice 2026');
    $indexResponse->assertSee('Dora Dokument');

    $dueResponse = $this->actingAs($staff)->get(route('documents.index', ['due' => 'soon']));

    $dueResponse->assertOk();
    $dueResponse->assertSee('Versicherungspolice 2026');

    $downloadResponse = $this->actingAs($staff)->get(route('documents.download', $document));

    $downloadResponse->assertOk();
});

test('documents management actions are hidden from viewers', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [$tenant, $viewer] = createTenantWithUser(User::ROLE_VIEWER, 'documents-viewer');

    $document = Document::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'uploaded_by' => $viewer->id,
        'title' => 'Satzung',
        'category' => Document::CATEGORY_CLUB,
        'status' => Document::STATUS_ACTIVE,
        'disk' => 'local',
        'path' => 'documents/test/satzung.pdf',
        'original_name' => 'satzung.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1000,
    ]);

    $indexResponse = $this->actingAs($viewer)->get(route('documents.index'));

    $indexResponse->assertOk();
    $indexResponse->assertSee('Satzung');
    $indexResponse->assertDontSee('Dokument ablegen');

    $showResponse = $this->actingAs($viewer)->get(route('documents.show', $document));

    $showResponse->assertOk();
    $showResponse->assertDontSee('Bearbeiten');
    $showResponse->assertDontSee('Archivieren');
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

test('superadmin can use the platform cockpit without opening a club dashboard', function () {
    $this->withoutMiddleware(EnsureTenantIsSubscribed::class);

    [, $superadmin] = createTenantWithUser(User::ROLE_SUPERADMIN, 'platform-admin');
    [$clubTenant, $clubAdmin] = createTenantWithUser(User::ROLE_ADMIN, 'new-club');

    Member::withoutGlobalScopes()->create([
        'tenant_id' => $clubTenant->id,
        'first_name' => 'Neues',
        'last_name' => 'Mitglied',
        'email' => 'mitglied@example.test',
        'entry_date' => now()->subMonth()->toDateString(),
    ]);

    Event::withoutGlobalScopes()->create([
        'tenant_id' => $clubTenant->id,
        'title' => 'Probetraining',
        'start' => now()->addWeek(),
        'end' => now()->addWeek()->addHour(),
    ]);

    $this->actingAs($superadmin)
        ->get('/')
        ->assertRedirect(route('admin.dashboard'));

    $dashboard = $this->actingAs($superadmin)->get(route('admin.dashboard'));

    $dashboard->assertOk();
    $dashboard->assertSee('Admin-Cockpit');
    $dashboard->assertSee($clubTenant->name);
    $dashboard->assertSee('360-Grad-Sicht');
    $dashboard->assertSee('Alle Vereine');

    $detail = $this->actingAs($superadmin)->get(route('admin.tenants.show', $clubTenant));

    $detail->assertOk();
    $detail->assertSee($clubTenant->name);
    $detail->assertSee('Mitglieder aktiv');
    $detail->assertSee('Probetraining');
    $detail->assertSee($clubAdmin->email);
});

test('operator superadmin is not tied to a club account', function () {
    $operator = User::factory()->create([
        'tenant_id' => null,
        'role' => User::ROLE_SUPERADMIN,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($operator)
        ->get('/')
        ->assertRedirect(route('admin.dashboard'));

    $this->actingAs($operator)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Admin-Cockpit');

    $this->actingAs($operator)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));

    $this->actingAs($operator)
        ->get(route('admin.account'))
        ->assertOk()
        ->assertSee('Betreiberkonto')
        ->assertSee('Kennwort ändern');
});
