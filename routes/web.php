<?php

use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\WebhookController;
use App\Http\Controllers\Settings\EmailSettingsController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TemplateDispatchLogController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\MailTrackingController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventCategoryController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\SepaExportController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\UpdateNoticeController;



// Hilfsvariablen: Namespace + Guard-Helfer
$C = '\\App\\Http\\Controllers\\';
$when = function (string $class, callable $cb) {
    if (class_exists($class)) {
        $cb($class);
    }
};

/**
 * Stripe Webhook (Cashier)
 * WICHTIG:
 * - außerhalb von auth
 * - POST
 * - CSRF-Excludes sind bei dir in bootstrap/app.php gesetzt (validateCsrfTokens)
 *
 * Hinweis: Cashier WebhookController ist NICHT invokable -> handleWebhook verwenden
 */
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');

// Startseite → Betreiber direkt ins Cockpit, Vereinsnutzer ins Dashboard
Route::get('/', function () {
    if (auth()->check() && auth()->user()->isSuperAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('dashboard');
});

// Öffentlich sichtbare Seiten
Route::view('/impressum', 'impressum')->name('impressum');
Route::get('/veranstaltungen/{eventId}', [EventController::class, 'publicShow'])
    ->whereNumber('eventId')
    ->name('events.public.show');
Route::get('/veranstaltungen/{eventId}/bild', [EventController::class, 'image'])
    ->whereNumber('eventId')
    ->name('events.image');
Route::get('/vereine/{tenantSlug}/veranstaltungen', [EventController::class, 'publicList'])
    ->name('events.public.index');
Route::get('/vereine/{tenantSlug}/veranstaltungen/embed', [EventController::class, 'publicEmbed'])
    ->name('events.public.embed');
Route::get('/einladungen/{token}', [EventController::class, 'invitationResponse'])
    ->name('events.invitations.public.show');
Route::post('/einladungen/{token}', [EventController::class, 'storeInvitationResponse'])
    ->name('events.invitations.public.store');
Route::get('/dokumentation', [DocumentationController::class, 'index'])->name('docs.index');
Route::get('/dokumentation/assets/{filename}', [DocumentationController::class, 'asset'])->name('docs.asset');
Route::get('/dokumentation/{path}', [DocumentationController::class, 'show'])
    ->where('path', '.*')
    ->name('docs.show');

// Lizenzmodell muss OHNE Paywall erreichbar bleiben
Route::middleware(['auth'])->group(function () {
    Route::get('/lizenz', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/lizenz/kaufen', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::post('/update-hinweis/ausblenden', [UpdateNoticeController::class, 'dismiss'])->name('update-notice.dismiss');
});

// Dashboard (Controller, falls vorhanden; sonst Fallback-View)
// 🔥 Mit Paywall schützen
if (class_exists($C.'EventController')) {
    Route::get('/dashboard', [$C.'EventController', 'dashboardEvents'])
        ->middleware(['auth', 'verified', 'tenant.subscribed'])
        ->name('dashboard');
} else {
    Route::get('/dashboard', fn () => view('dashboard'))
        ->middleware(['auth', 'verified', 'tenant.subscribed'])
        ->name('dashboard');
}

// Authentifizierte UND lizenzpflichtige Bereiche
Route::middleware(['auth', 'tenant.subscribed'])->group(function () use ($when, $C) {

    // --- Billing (Tenant-basiert, Cashier) ---
    $when($C.'BillingController', function($cls){
        // NEU: Pläne anzeigen (Starter/Basic/Enterprise)
        Route::get('/billing/plans', [$cls, 'plans'])->name('billing.plans');

        // Bestehend: Subscription starten (Price-ID kommt aus der Plan-Auswahl)
        Route::post('/billing/subscribe/{priceId}', [$cls, 'subscribe'])->name('billing.subscribe');

        // Bestehend: Customer Portal
        Route::get('/billing/portal', [$cls, 'portal'])->name('billing.portal');
    });

    // Projekte – Übersicht, Anlegen, Anzeigen, Bearbeiten, Löschen
    $when($C.'ProjectIndexController', function($cls){
        Route::get('/projects', $cls)->name('projects.index');
    });

    $when($C.'ProjectController', function($cls){
        Route::get('/projects/create', [$cls, 'create'])->name('projects.create');
        Route::post('/projects', [$cls, 'store'])->name('projects.store');
        Route::get('/projects/{project}/edit', [$cls, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [$cls, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [$cls, 'destroy'])->name('projects.destroy');
    });

    $when($C.'ProjectShowController', function($cls){
        Route::get('/projects/{project}', $cls)->name('projects.show');
    });

    // Projekt-Aufgaben (Tasks)
    $when($C.'TaskController', function($cls){
        Route::get('/aufgaben', [$cls, 'index'])->name('tasks.index');
        Route::get('/aufgaben/neu', [$cls, 'create'])->name('tasks.create');
        Route::post('/aufgaben', [$cls, 'store'])->name('tasks.store');
        Route::get('/aufgaben/{task}/bearbeiten', [$cls, 'edit'])->name('tasks.edit');
        Route::put('/aufgaben/{task}', [$cls, 'update'])->name('tasks.update');
        Route::patch('/aufgaben/{task}/aktion', [$cls, 'quickAction'])->name('tasks.quick-action');

        Route::get('/projects/{project}/tasks/create', [$cls, 'createFromProject'])->name('projects.tasks.create');
        Route::post('/projects/{project}/tasks', [$cls, 'storeFromProject'])->name('projects.tasks.store');
        Route::get('/projects/{project}/tasks/{task}/edit', [$cls, 'editFromProject'])->name('projects.tasks.edit');
        Route::put('/projects/{project}/tasks/{task}', [$cls, 'updateFromProject'])->name('projects.tasks.update');
    });

    // Projekt-Dokumente
    $when($C.'ProjectDocumentController', function($cls){
        Route::get('/projects/{project}/documents/create', [$cls, 'create'])->name('projects.documents.create');
        Route::post('/projects/{project}/documents', [$cls, 'store'])->name('projects.documents.store');
        Route::get('/projects/{project}/documents/{document}/download', [$cls, 'download'])->name('projects.documents.download');
        Route::delete('/projects/{project}/documents/{document}', [$cls, 'destroy'])->name('projects.documents.destroy');
    });

    // Dokumentenzentrale
    $when($C.'DocumentController', function($cls){
        Route::get('/dokumente', [$cls, 'index'])->middleware('tenant.role:Lesen')->name('documents.index');
        Route::get('/dokumente/neu', [$cls, 'create'])->middleware('tenant.role:documents')->name('documents.create');
        Route::post('/dokumente', [$cls, 'store'])->middleware('tenant.role:documents')->name('documents.store');
        Route::get('/dokumente/{document}', [$cls, 'show'])->middleware('tenant.role:Lesen')->name('documents.show');
        Route::get('/dokumente/{document}/download', [$cls, 'download'])->middleware('tenant.role:Lesen')->name('documents.download');
        Route::get('/dokumente/{document}/bearbeiten', [$cls, 'edit'])->middleware('tenant.role:documents')->name('documents.edit');
        Route::put('/dokumente/{document}', [$cls, 'update'])->middleware('tenant.role:documents')->name('documents.update');
        Route::patch('/dokumente/{document}/archivieren', [$cls, 'archive'])->middleware('tenant.role:documents')->name('documents.archive');
        Route::delete('/dokumente/{document}', [$cls, 'destroy'])->middleware('tenant.role:Admin')->name('documents.destroy');
    });

    // Gantt
    $when($C.'ProjectGanttController', function($cls){
        Route::get('/projects/{project}/gantt.json', [$cls, 'json'])->name('projects.gantt.json');
    });

    Route::get('/projects/{project}/gantt', function (\Illuminate\Http\Request $request, \App\Models\Project $project) {
        if (!$request->user() || (string)$request->user()->tenant_id !== (string)$project->tenant_id) {
            abort(404);
        }

        return view('projects.gantt', ['project' => $project]);
    })->name('projects.gantt');

    // Feedback
    $when($C.'FeedbackController', function($cls){
        Route::post('/feedback', [$cls, 'store'])->name('feedback.store');
    });

    // Profil
    $when($C.'ProfileController', function($cls){
        Route::get('/profile', [$cls, 'edit'])->name('profile.edit');
        Route::patch('/profile', [$cls, 'update'])->name('profile.update');
        Route::delete('/profile', [$cls, 'destroy'])->name('profile.destroy');
    });

    // Benutzerverwaltung
    $when($C.'UserController', function($cls){
        Route::middleware('tenant.role:Admin')->group(function () use ($cls) {
            Route::get('/users', [$cls, 'index'])->name('users.index');
            Route::get('/users/create', [$cls, 'create'])->name('users.create');
            Route::get('/users/invite-members', [$cls, 'inviteMembers'])->name('users.invite-members');
            Route::post('/users/invite-members', [$cls, 'storeMemberInvites'])->name('users.invite-members.store');
            Route::get('/users/{user}/edit', [$cls, 'edit'])->name('users.edit');
            Route::post('/users', [$cls, 'store'])->name('users.store');
            Route::put('/users/{user}', [$cls, 'update'])->name('users.update');
            Route::delete('/users/{user}', [$cls, 'destroy'])->name('users.destroy');
        });
    });

    // Mitglieder
    $when($C.'MemberController', function($cls){

        // Limit-Blocker nur für "Neuanlage" + Import (Soft/Hard Limit)
        Route::get('/members/create', [$cls, 'create'])
            ->middleware('tenant.role:Mitarbeiter')
            ->middleware('member.limit')
            ->name('members.create');

        Route::post('/members', [$cls, 'store'])
            ->middleware('tenant.role:Mitarbeiter')
            ->middleware('member.limit')
            ->name('members.store');

        // Restliche Member-Routen ohne Limit (anzeigen/bearbeiten ok)
        Route::get('/members', [$cls, 'index'])->name('members.index');
        Route::get('/members/bic-lookup', [$cls, 'lookupBic'])->middleware('tenant.role:Mitarbeiter')->name('members.bic-lookup');
        Route::get('/members/communication/export', [$cls, 'communicationExport'])->middleware('tenant.role:Mitarbeiter')->name('members.communication.export');
        Route::get('/members/{member}/photo', [$cls, 'photo'])->middleware('tenant.role:Lesen')->name('members.photo');
        Route::get('/members/{member}', [$cls, 'show'])->name('members.show');
        Route::post('/members/{member}/credits', [$cls, 'storeCredit'])->middleware('tenant.role:Admin')->name('members.credits.store');
        Route::post('/members/{member}/communication-log', [$cls, 'storeCommunicationLog'])->middleware('tenant.role:Mitarbeiter')->name('members.communication-log.store');
        Route::post('/members/{member}/restore', [$cls, 'restore'])->middleware('tenant.role:Mitarbeiter')->name('members.restore');
        Route::get('/members/{member}/edit', [$cls, 'edit'])->middleware('tenant.role:Mitarbeiter')->name('members.edit');
        Route::put('/members/{member}', [$cls, 'update'])->middleware('tenant.role:Mitarbeiter')->name('members.update');
        Route::patch('/members/{member}', [$cls, 'update'])->middleware('tenant.role:Mitarbeiter');
        Route::delete('/members/{member}', [$cls, 'destroy'])->middleware('tenant.role:Mitarbeiter')->name('members.destroy');

        Route::post('/members/bulk-action', [$cls, 'bulkAction'])->middleware('tenant.role:Mitarbeiter')->name('members.bulk-action');
        Route::get('/members/{member}/datenauskunft', [$cls, 'exportDatenauskunft'])->middleware('tenant.role:Mitarbeiter')->name('members.datenauskunft');
        Route::get('/members/{member}/pdf', [$cls, 'exportDatenauskunft'])->middleware('tenant.role:Mitarbeiter')->name('members.pdf');
    });

    // Mitgliedschaften
    $when($C.'MembershipController', function($cls){
        Route::middleware('tenant.role:Admin')->group(function () use ($cls) {
            Route::resource('memberships', $cls)->except(['show']);
        });
    });

    // Tags
    $when($C.'TagController', function($cls){
        Route::middleware('tenant.role:Admin')->group(function () use ($cls) {
            Route::resource('tags', $cls)->except(['show']);
        });
    });

    // CSV-Import (mit Limit-Blocker)
    $when($C.'ImportController', function($cls){
        Route::get('/import', [$cls, 'index'])
            ->middleware('tenant.role:Admin')
            ->name('import.index');

        Route::get('/import/vorlage/{type}', [$cls, 'template'])
            ->middleware('tenant.role:Admin')
            ->name('import.template');

        Route::get('/import/mitglieder', [$cls, 'showUploadForm'])
            ->middleware('tenant.role:Admin')
            ->middleware('member.limit')
            ->name('import.mitglieder');

        Route::post('/import/mitglieder/preview', [$cls, 'preview'])
            ->middleware('tenant.role:Admin')
            ->middleware('member.limit')
            ->name('import.mitglieder.preview');

        Route::post('/import/mitglieder/confirm', [$cls, 'confirm'])
            ->middleware('tenant.role:Admin')
            ->middleware('member.limit')
            ->name('import.mitglieder.confirm');

        Route::post('/import/mitglieder/{importRun}/undo', [$cls, 'undo'])
            ->middleware('tenant.role:Admin')
            ->name('import.mitglieder.undo');

        Route::get('/import/{importRun}/bericht', [$cls, 'report'])
            ->middleware('tenant.role:Admin')
            ->name('import.report');

        Route::get('/import/{importRun}/bericht/qualitaet/{issue}', [$cls, 'qualityIssue'])
            ->middleware('tenant.role:Admin')
            ->name('import.quality-issue');

        Route::get('/import/{importRun}/bericht/qualitaet/{issue}/export', [$cls, 'qualityIssueExport'])
            ->middleware('tenant.role:Admin')
            ->name('import.quality-issue.export');

        Route::get('/import/{importRun}/bericht/export', [$cls, 'reportExport'])
            ->middleware('tenant.role:Admin')
            ->name('import.report.export');

        Route::get('/import/{importRun}/bericht/korrekturmappe', [$cls, 'correctionsExport'])
            ->middleware('tenant.role:Admin')
            ->name('import.corrections-export');

        Route::get('/import/kontakte', [$cls, 'showContactUploadForm'])
            ->middleware('tenant.role:Admin')
            ->name('import.kontakte');

        Route::post('/import/kontakte/preview', [$cls, 'previewContacts'])
            ->middleware('tenant.role:Admin')
            ->name('import.kontakte.preview');

        Route::post('/import/kontakte/confirm', [$cls, 'confirmContacts'])
            ->middleware('tenant.role:Admin')
            ->name('import.kontakte.confirm');
    });

    // Vereinsprofil
    $when($C.'TenantController', function($cls){
        Route::get('/verein', [$cls, 'show'])->name('tenant.show');
        Route::get('/verein/logo', [$cls, 'logo'])->name('tenant.logo');
        Route::get('/verein/briefbogen', [$cls, 'letterhead'])->middleware('tenant.role:Lesen')->name('tenant.letterhead');
        Route::get('/verein/bearbeiten', [$cls, 'edit'])->middleware('tenant.role:Admin')->name('tenant.edit');
        Route::patch('/verein/bearbeiten', [$cls, 'update'])->middleware('tenant.role:Admin')->name('tenant.update');
    });

    // Veranstaltungen
    $when($C.'EventController', function($cls){
        Route::get('/events/aushang', [$cls, 'poster'])->middleware('tenant.role:events')->name('events.poster');
        Route::post('/events/aushang/druck', [$cls, 'posterPrint'])->middleware('tenant.role:events')->name('events.poster.print');
        Route::post('/events/aushang/pdf', [$cls, 'posterPdf'])->middleware('tenant.role:events')->name('events.poster.pdf');
        Route::get('/events/anwesenheit/auswertung', [$cls, 'attendanceReport'])->middleware('tenant.role:events')->name('events.attendance.report');
        Route::get('/events', [$cls, 'index'])->middleware('tenant.role:Lesen')->name('events.index');
        Route::get('/events/create', [$cls, 'create'])->middleware('tenant.role:events')->name('events.create');
        Route::post('/events', [$cls, 'store'])->middleware('tenant.role:events')->name('events.store');
        Route::get('/events/{event}/edit', [$cls, 'edit'])->middleware('tenant.role:events')->whereNumber('event')->name('events.edit');
        Route::match(['put', 'patch'], '/events/{event}', [$cls, 'update'])->middleware('tenant.role:events')->whereNumber('event')->name('events.update');
        Route::delete('/events/{event}', [$cls, 'destroy'])->middleware('tenant.role:events')->whereNumber('event')->name('events.destroy');
        Route::get('/events/{event}', [$cls, 'show'])->whereNumber('event')->name('events.show');
        Route::get('/events/{event}/teilnehmer', [$cls, 'participants'])->middleware('tenant.role:events')->whereNumber('event')->name('events.participants.manage');
        Route::get('/events/{event}/teilnehmer/export', [$cls, 'participantsExport'])->middleware('tenant.role:events')->whereNumber('event')->name('events.participants.export');
        Route::get('/events/{event}/teilnehmer/drucken', [$cls, 'participantsPrint'])->middleware('tenant.role:events')->whereNumber('event')->name('events.participants.print');
        Route::get('/events/{event}/teilnehmer/pdf', [$cls, 'participantsPdf'])->middleware('tenant.role:events')->whereNumber('event')->name('events.participants.pdf');
        Route::patch('/events/{event}/buchungen/{booking}', [$cls, 'updateBooking'])->middleware('tenant.role:events')->whereNumber('event')->name('events.bookings.update');
        Route::patch('/events/{event}/buchungen/{booking}/teilnehmer/{participant}', [$cls, 'updateParticipant'])->middleware('tenant.role:events')->whereNumber('event')->name('events.participants.update');
        Route::patch('/events/{event}/teilnehmer/kostenfrei', [$cls, 'markParticipantsFree'])->middleware('tenant.role:events')->whereNumber('event')->name('events.participants.mark-free');
        Route::post('/events/{event}/teilnehmer/nachtragen', [$cls, 'storeManualParticipant'])->middleware('tenant.role:events')->whereNumber('event')->name('events.manual-participants.store');
        Route::get('/events/{event}/dienstplan', [$cls, 'schedule'])->middleware('tenant.role:events')->whereNumber('event')->name('events.schedule.manage');
        Route::get('/events/{event}/dienstplan/druck', [$cls, 'schedulePrint'])->middleware('tenant.role:events')->whereNumber('event')->name('events.schedule.print');
        Route::get('/events/{event}/dienstplan/pdf', [$cls, 'schedulePdf'])->middleware('tenant.role:events')->whereNumber('event')->name('events.schedule.pdf');
        Route::get('/events/{event}/dienstplan/mitglieder-pdf', [$cls, 'scheduleMemberPdf'])->middleware('tenant.role:events')->whereNumber('event')->name('events.schedule.member-pdf');
        Route::get('/events/{event}/dienstplan/export', [$cls, 'scheduleExport'])->middleware('tenant.role:events')->whereNumber('event')->name('events.schedule.export');
        Route::post('/events/{event}/einladungen/synchronisieren', [$cls, 'syncInvitations'])->middleware('tenant.role:events')->whereNumber('event')->name('events.invitations.sync');
        Route::post('/events/{event}/einladungen/mail', [$cls, 'sendInvitationMails'])->middleware('tenant.role:events')->whereNumber('event')->name('events.invitations.mail');
        Route::put('/events/{event}/einladungen', [$cls, 'updateInvitations'])->middleware('tenant.role:events')->whereNumber('event')->name('events.invitations.update');
        Route::put('/events/{event}/anwesenheit', [$cls, 'updateAttendance'])->middleware('tenant.role:events')->whereNumber('event')->name('events.attendance.update');
        Route::post('/events/{event}/shifts', [$cls, 'storeShift'])->middleware('tenant.role:events')->whereNumber('event')->name('events.shifts.store');
        Route::put('/events/{event}/shifts/{shift}', [$cls, 'updateShift'])->middleware('tenant.role:events')->whereNumber('event')->name('events.shifts.update');
        Route::delete('/events/{event}/shifts/{shift}', [$cls, 'destroyShift'])->middleware('tenant.role:events')->whereNumber('event')->name('events.shifts.destroy');
        Route::post('/events/{event}/shifts/{shift}/assignments', [$cls, 'storeShiftAssignment'])->middleware('tenant.role:events')->whereNumber('event')->name('events.shifts.assignments.store');
        Route::delete('/events/{event}/shifts/{shift}/assignments/{assignment}', [$cls, 'destroyShiftAssignment'])->middleware('tenant.role:events')->whereNumber('event')->name('events.shifts.assignments.destroy');
    });

    $when($C.'EventCategoryController', function($cls){
        Route::middleware('tenant.role:Admin')->group(function () use ($cls) {
            Route::get('/events/categories', [$cls, 'index'])->name('event-categories.index');
            Route::post('/events/categories', [$cls, 'store'])->name('event-categories.store');
            Route::put('/events/categories/{eventCategory}', [$cls, 'update'])->name('event-categories.update');
            Route::delete('/events/categories/{eventCategory}', [$cls, 'destroy'])->name('event-categories.destroy');
        });
    });

    // Eigene Mitgliederfelder
    $when($C.'CustomMemberFieldController', function($cls){
        Route::middleware('tenant.role:Admin')->prefix('einstellungen/mitgliederfelder')->name('custom-fields.')->group(function () use ($cls) {
            Route::get('/', [$cls, 'index'])->name('index');
            Route::get('/create', [$cls, 'create'])->name('create');
            Route::post('/', [$cls, 'store'])->name('store');
            Route::get('/{customMemberField}/edit', [$cls, 'edit'])->name('edit');
            Route::put('/{customMemberField}', [$cls, 'update'])->name('update');
            Route::delete('/{customMemberField}', [$cls, 'destroy'])->name('destroy');
        });
    });

    // Rollen
    $when($C.'RoleController', function($cls){
        Route::middleware('superadmin')->group(function () use ($cls) {
            Route::get('/einstellungen/rollen', [$cls, 'edit'])->name('roles.edit');
            Route::post('/einstellungen/rollen', [$cls, 'update'])->name('roles.update');
        });
    });

    // Finanzen – Konten und Buchungen
    $when($C.'AccountController', function($cls){
        Route::middleware('tenant.role:finance')->group(function () use ($cls) {
            Route::post('/accounts/simple-chart', [$cls, 'useSimpleChart'])->name('accounts.simple-chart');
            Route::post('/accounts/import-chart', [$cls, 'importChart'])->name('accounts.import-chart');
            Route::patch('/accounts/bulk-visibility', [$cls, 'bulkVisibility'])->name('accounts.bulk-visibility');
            Route::patch('/accounts/{account}/hide', [$cls, 'hide'])->name('accounts.hide');
            Route::patch('/accounts/{account}/restore', [$cls, 'restore'])->name('accounts.restore');
            Route::resource('accounts', $cls)->except(['show']);
        });
    });

    $when($C.'TransactionController', function($cls){
        Route::middleware('tenant.role:finance')->group(function () use ($cls) {
            Route::post('/transactions/datev-import', [$cls, 'importDatev'])->name('transactions.datev-import');
            Route::resource('transactions', $cls)->except(['show', 'destroy']);
            Route::get('/kassenbuch', [$cls, 'cashbook'])->name('transactions.cashbook');
            Route::get('/kassenbuch/drucken', [$cls, 'cashbookPrint'])->name('transactions.cashbook.print');
            Route::get('/kassenbuch/pdf', [$cls, 'cashbookPdf'])->name('transactions.cashbook.pdf');
            Route::post('/transactions/{transaction}/finalize', [$cls, 'finalize'])->name('transactions.finalize');
            Route::post('/transactions/finalize-selected', [$cls, 'finalizeSelected'])->name('transactions.finalize-selected');
            Route::get('/transactions/summary', [$cls, 'summary'])->name('transactions.summary');
            Route::get('/transactions/{transaction}/eigenbeleg', [$cls, 'ownReceipt'])->name('transactions.own-receipt');
            Route::post('/transactions/{transaction}/eigenbeleg', [$cls, 'storeOwnReceipt'])->name('transactions.own-receipt.store');
            Route::get('/transactions/{transaction}/cancel', [$cls, 'cancel'])->name('transactions.cancel');
            Route::post('/transactions/{transaction}/cancel', [$cls, 'cancelStore'])->name('transactions.cancel.store');
        });
    });

    Route::middleware('tenant.role:finance')->group(function () {
        Route::get('/spenden/einstellungen', [DonationController::class, 'settings'])->name('donations.settings');
        Route::put('/spenden/einstellungen', [DonationController::class, 'updateSettings'])->name('donations.settings.update');
        Route::get('/spenden', [DonationController::class, 'index'])->name('donations.index');
        Route::get('/spenden/neu', [DonationController::class, 'create'])->name('donations.create');
        Route::post('/spenden', [DonationController::class, 'store'])->name('donations.store');
        Route::post('/spenden/sammelbestaetigung', [DonationController::class, 'collectivePdf'])->name('donations.collective-pdf');
        Route::get('/spenden/{donation}', [DonationController::class, 'show'])->name('donations.show');
        Route::get('/spenden/{donation}/pdf', [DonationController::class, 'pdf'])->name('donations.pdf');
        Route::patch('/spenden/{donation}/versendet', [DonationController::class, 'markSent'])->name('donations.mark-sent');
        Route::patch('/spenden/{donation}/stornieren', [DonationController::class, 'cancel'])->name('donations.cancel');
    });

    // Belege
    $when($C.'ReceiptController', function($cls){
        Route::get('/beleg/{path}', [$cls, 'show'])
            ->middleware('tenant.role:finance')
            ->where('path', '.*')
            ->name('receipts.show');
		 });

    // Beitragsrechnungen
    $when($C.'InvoiceController', function($cls){
        Route::middleware('tenant.role:finance')->group(function () use ($cls) {
            Route::resource('invoices', $cls)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
            Route::get('/invoices/{invoice}/pdf', [$cls, 'pdf'])->name('invoices.pdf');
            Route::post('/invoices/{invoice}/send', [$cls, 'sendMail'])->name('invoices.send');
            Route::get('/invoices/{invoice}/reminder', [$cls, 'reminderPreview'])->name('invoices.reminder.preview');
            Route::post('/invoices/{invoice}/reminder', [$cls, 'sendReminder'])->name('invoices.reminder');
            Route::post('/invoices/bulk-cancel', [$cls, 'bulkCancel'])->name('invoices.bulk-cancel');
            Route::patch('/invoices/{invoice}/status', [$cls, 'updateStatus'])->name('invoices.status.update');
            Route::post('/members/{member}/membership-invoice', [$cls, 'storeMembershipInvoiceForMember'])
                ->name('members.membership-invoice.store');
        });
    });

    $when($C.'BudgetPlanController', function($cls){
        Route::middleware('tenant.role:finance')->group(function () use ($cls) {
            Route::get('/haushaltsplan', [$cls, 'index'])->name('budgets.index');
            Route::get('/haushaltsplan/neu', [$cls, 'create'])->name('budgets.create');
            Route::post('/haushaltsplan', [$cls, 'store'])->name('budgets.store');
            Route::get('/haushaltsplan/{budget}', [$cls, 'show'])->name('budgets.show');
            Route::get('/haushaltsplan/{budget}/pdf', [$cls, 'pdf'])->name('budgets.pdf');
            Route::get('/haushaltsplan/{budget}/bearbeiten', [$cls, 'edit'])->name('budgets.edit');
            Route::put('/haushaltsplan/{budget}', [$cls, 'update'])->name('budgets.update');
            Route::post('/haushaltsplan/{budget}/duplizieren', [$cls, 'duplicate'])->name('budgets.duplicate');
        });
    });

    // Nummernkreise
    $when($C.'InvoiceNumberRangeController', function($cls){
        Route::middleware('tenant.role:Admin')->group(function () use ($cls) {
            Route::resource('number-ranges', $cls)->names('number_ranges');
        });
    });

    // Protokolle
    $when($C.'ProtocolController', function($cls){
        Route::get('/protokolle', [$cls, 'index'])->name('protocols.index');
        Route::get('/protokolle/neu', [$cls, 'create'])->middleware('tenant.role:protocols')->name('protocols.create');
        Route::post('/protokolle', [$cls, 'store'])->middleware('tenant.role:protocols')->name('protocols.store');
        Route::get('/protokolle/{protocol}', [$cls, 'show'])->name('protocols.show');
        Route::get('/protokolle/{protocol}/anhaenge/{index}', [$cls, 'attachment'])->middleware('tenant.role:Lesen')->name('protocols.attachments.show');
        Route::get('/protokolle/{protocol}/bearbeiten', [$cls, 'edit'])->middleware('tenant.role:protocols')->name('protocols.edit');
        Route::put('/protokolle/{protocol}', [$cls, 'update'])->middleware('tenant.role:protocols')->name('protocols.update');
        Route::patch('/protokolle/{protocol}/archivieren', [$cls, 'archive'])->middleware('tenant.role:protocols')->name('protocols.archive');
        Route::delete('/protokolle/{protocol}', [$cls, 'destroy'])->middleware('tenant.role:protocols')->name('protocols.destroy');
        Route::get('/protokolle/{protocol}/mail', [$cls, 'mailForm'])->middleware('tenant.role:protocols')->name('protocols.mail.form');
        Route::post('/protokolle/{protocol}/mail', [$cls, 'sendMail'])->middleware('tenant.role:protocols')->name('protocols.mail.send');
    });

    // SMTP-Einstellungen
    Route::get('/settings/email', [EmailSettingsController::class, 'edit'])
        ->middleware('tenant.role:Admin')
        ->name('settings.email.edit');

    Route::put('/settings/email', [EmailSettingsController::class, 'update'])
        ->middleware('tenant.role:Admin')
        ->name('settings.email.update');

    // PDF-Test
    $when($C.'PdfTestController', function($cls){
        Route::get('/pdf-test', [$cls, 'test'])
            ->middleware('tenant.role:Admin')
            ->name('pdf.test');
    });

    // Debug
    Route::get('/envcheck', fn () => dd(config('app.env'), config('app.debug')));

    // --- Kontakte (eigenes Modul, getrennt von Mitgliedern) ---
    $when($C.'ContactController', function($cls){
        Route::get('/contacts', [$cls, 'index'])->name('contacts.index');
        Route::get('/contacts/create', [$cls, 'create'])->middleware('tenant.role:Mitarbeiter')->name('contacts.create');
        Route::post('/contacts', [$cls, 'store'])->middleware('tenant.role:Mitarbeiter')->name('contacts.store');
        Route::get('/contacts/{contact}/edit', [$cls, 'edit'])->middleware('tenant.role:Mitarbeiter')->name('contacts.edit');
        Route::put('/contacts/{contact}', [$cls, 'update'])->middleware('tenant.role:Mitarbeiter')->name('contacts.update');
        Route::delete('/contacts/{contact}', [$cls, 'destroy'])->middleware('tenant.role:Mitarbeiter')->name('contacts.destroy');
        Route::get('/contacts/{contact}', [$cls, 'show'])->name('contacts.show');
    });

    // Oeffentliche Formulare
    Route::get('/formulare', [PublicFormController::class, 'index'])->name('forms.index');
    Route::get('/formulare/neu', [PublicFormController::class, 'create'])->middleware('tenant.role:forms')->name('forms.create');
    Route::post('/formulare', [PublicFormController::class, 'store'])->middleware('tenant.role:forms')->name('forms.store');
    Route::get('/formulare/{form}/bearbeiten', [PublicFormController::class, 'edit'])->middleware('tenant.role:forms')->name('forms.edit');
    Route::put('/formulare/{form}', [PublicFormController::class, 'update'])->middleware('tenant.role:forms')->name('forms.update');
    Route::delete('/formulare/{form}', [PublicFormController::class, 'destroy'])->middleware('tenant.role:forms')->name('forms.destroy');
    Route::get('/formulare/{form}/antworten', [PublicFormController::class, 'submissions'])->name('forms.submissions');
    Route::get('/formulare/{form}/antworten/export', [PublicFormController::class, 'export'])->middleware('tenant.role:forms')->name('forms.export');
    Route::patch('/formulare/{form}/antworten/{submission}/stornieren', [PublicFormController::class, 'cancelSubmission'])->middleware('tenant.role:forms')->name('forms.submissions.cancel');
    Route::delete('/formulare/{form}/antworten/{submission}', [PublicFormController::class, 'destroySubmission'])->middleware('tenant.role:forms')->name('forms.submissions.destroy');
    Route::post('/formulare/{form}/felder', [PublicFormController::class, 'storeField'])->middleware('tenant.role:forms')->name('forms.fields.store');
    Route::put('/formulare/{form}/felder/{field}', [PublicFormController::class, 'updateField'])->middleware('tenant.role:forms')->name('forms.fields.update');
    Route::patch('/formulare/{form}/felder/{field}/move', [PublicFormController::class, 'moveField'])->middleware('tenant.role:forms')->name('forms.fields.move');
    Route::delete('/formulare/{form}/felder/{field}', [PublicFormController::class, 'destroyField'])->middleware('tenant.role:forms')->name('forms.fields.destroy');
});

Route::get('/f/{slug}', [PublicFormController::class, 'publicShow'])->name('forms.public.show');
Route::post('/f/{slug}', [PublicFormController::class, 'publicSubmit'])->name('forms.public.submit');
Route::get('/f/{slug}/embed', [PublicFormController::class, 'publicEmbed'])->name('forms.public.embed');
Route::post('/f/{slug}/embed', [PublicFormController::class, 'publicEmbedSubmit'])->name('forms.public.embed.submit');
Route::get('/mail/tracking/open/{token}', [MailTrackingController::class, 'open'])->name('mail.tracking.open');
Route::get('/mail/tracking/click/{dispatchLog}', [MailTrackingController::class, 'click'])->name('mail.tracking.click');

// Template
Route::middleware(['auth', 'tenant.subscribed'])->group(function () {
    Route::get('/templates/{template}/preview', [TemplateController::class, 'preview'])
        ->middleware('tenant.role:Mitarbeiter')
        ->name('templates.preview');
    Route::get('/templates/protokoll', [TemplateDispatchLogController::class, 'index'])
        ->middleware('tenant.role:Mitarbeiter')
        ->name('templates.dispatch-log');

    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/create', [TemplateController::class, 'create'])->middleware('tenant.role:Mitarbeiter')->name('templates.create');
    Route::post('/templates', [TemplateController::class, 'store'])->middleware('tenant.role:Mitarbeiter')->name('templates.store');
    Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->middleware('tenant.role:Mitarbeiter')->name('templates.edit');
    Route::put('/templates/{template}', [TemplateController::class, 'update'])->middleware('tenant.role:Mitarbeiter')->name('templates.update');
    Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])->middleware('tenant.role:Mitarbeiter')->name('templates.destroy');
});

// Mail und Brief
Route::middleware(['auth', 'tenant.subscribed'])->group(function () {
    Route::get('/mail/send', [MailController::class, 'create'])
        ->middleware('tenant.role:Mitarbeiter')
        ->name('mail.create');

    Route::post('/mail/send', [MailController::class, 'send'])
        ->middleware('tenant.role:Mitarbeiter')
        ->name('mail.send');

    Route::get('/letters/send', [LetterController::class, 'create'])
        ->middleware('tenant.role:Mitarbeiter')
        ->name('letters.create');

    Route::post('/letters/send', [LetterController::class, 'generate'])
        ->middleware('tenant.role:Mitarbeiter')
        ->name('letters.generate');
});

// Buchungsjournal
Route::middleware(['auth', 'tenant.subscribed'])->group(function () {
    Route::get('/sepa', [SepaExportController::class, 'create'])
        ->middleware('tenant.role:finance')
        ->name('sepa.create');

    Route::post('/sepa/export', [SepaExportController::class, 'export'])
        ->middleware('tenant.role:finance')
        ->name('sepa.export');

    Route::get('/sepa/{sepaRun}/download', [SepaExportController::class, 'download'])
        ->middleware('tenant.role:finance')
        ->name('sepa.download');

    Route::get('/transactions/journal', [TransactionController::class, 'journal'])
        ->middleware('tenant.role:finance')
        ->name('transactions.journal');

    Route::patch('/transactions/{transaction}/journal-check', [TransactionController::class, 'updateJournalCheck'])
        ->middleware('tenant.role:finance')
        ->name('transactions.journal-check');

    Route::get('/transactions/journal/pdf', [TransactionController::class, 'journalPdf'])
        ->middleware('tenant.role:finance')
        ->name('transactions.journal.pdf');

    Route::get('/transactions/eur', [TransactionController::class, 'eur'])
        ->middleware('tenant.role:finance')
        ->name('transactions.eur');

    Route::get('/transactions/koerperschaftsteuer', [TransactionController::class, 'corporationTax'])
        ->middleware('tenant.role:finance')
        ->name('transactions.corporation-tax');

    // Payment
    Route::get('/invoices/{invoice}/payment', [PaymentController::class, 'create'])
        ->middleware('tenant.role:finance')
        ->name('payments.create');

    Route::post('/invoices/{invoice}/payment', [PaymentController::class, 'store'])
        ->middleware('tenant.role:finance')
        ->name('payments.store');

    // Mitgliederabrechnung
    Route::post('/invoices/generate-memberships', [InvoiceController::class, 'generateMembershipInvoices'])
        ->middleware('tenant.role:finance')
        ->name('invoices.generateMemberships');
});

// Admin-Dashboard bleibt unabhängig von der Paywall
Route::middleware(['auth', 'superadmin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/konto', [AdminDashboardController::class, 'account'])->name('admin.account');
    Route::patch('/konto', [AdminDashboardController::class, 'updateAccount'])->name('admin.account.update');
    Route::get('/tenants/{tenant}', [AdminDashboardController::class, 'showTenant'])->name('admin.tenants.show');
    Route::patch('/tenants/{tenant}/license', [AdminDashboardController::class, 'updateLicense'])->name('admin.tenants.license');
    Route::patch('/tenants/{tenant}/verification', [AdminDashboardController::class, 'updateVerification'])->name('admin.tenants.verification');
    Route::delete('/tenants/{tenant}', [AdminDashboardController::class, 'destroyTenant'])->name('admin.tenants.destroy');
});

// Authentifizierung (Fortify/Jetstream etc.)
require __DIR__.'/auth.php';
