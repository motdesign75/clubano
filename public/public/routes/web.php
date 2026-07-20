<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CustomMemberFieldController;
use App\Http\Controllers\ReceiptController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Dashboard mit Event-Übersicht
Route::get('/dashboard', [EventController::class, 'dashboardEvents'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Geschützte Routen
Route::middleware('auth')->group(function () {

    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Mitglieder
    Route::resource('members', MemberController::class);
    Route::get('/members/{member}/datenauskunft', [MemberController::class, 'exportDatenauskunft'])->name('members.datenauskunft');
    Route::get('/members/{member}/pdf', [MemberController::class, 'exportDatenauskunft'])->name('members.pdf');

    // Vereinsprofil
    Route::get('/verein', [TenantController::class, 'show'])->name('tenant.show');
    Route::get('/verein/bearbeiten', [TenantController::class, 'edit'])->name('tenant.edit');
    Route::patch('/verein/bearbeiten', [TenantController::class, 'update'])->name('tenant.update');

    // Mitgliedschaften
    Route::resource('memberships', MembershipController::class)->except(['show']);

    // CSV-Import
    Route::get('/import/mitglieder', [ImportController::class, 'showUploadForm'])->name('import.mitglieder');
    Route::post('/import/mitglieder/preview', [ImportController::class, 'preview'])->name('import.mitglieder.preview');
    Route::post('/import/mitglieder/confirm', [ImportController::class, 'confirm'])->name('import.mitglieder.confirm');

    // Veranstaltungen
    Route::resource('events', EventController::class)->except(['show']);

    // Rollen
    Route::get('/einstellungen/rollen', [RoleController::class, 'edit'])->name('roles.edit');
    Route::post('/einstellungen/rollen', [RoleController::class, 'update'])->name('roles.update');

    // Eigene Mitgliederfelder (Custom Fields)
    Route::prefix('einstellungen/mitgliederfelder')->name('custom-fields.')->group(function () {
        Route::get('/', [CustomMemberFieldController::class, 'index'])->name('index');
        Route::get('/create', [CustomMemberFieldController::class, 'create'])->name('create');
        Route::post('/', [CustomMemberFieldController::class, 'store'])->name('store');
        Route::get('/{customMemberField}/edit', [CustomMemberFieldController::class, 'edit'])->name('edit');
        Route::put('/{customMemberField}', [CustomMemberFieldController::class, 'update'])->name('update');
        Route::delete('/{customMemberField}', [CustomMemberFieldController::class, 'destroy'])->name('destroy');
    });

    // Finanzen – Konten & Buchungen
    Route::resource('accounts', AccountController::class)->except(['show']);
    Route::resource('transactions', TransactionController::class)->except(['show', 'edit', 'update']);

    Route::get('/transactions/summary', [TransactionController::class, 'summary'])->name('transactions.summary');

    // Stornieren
    Route::get('/transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('transactions.cancel');
    Route::post('/transactions/{transaction}/cancel', [TransactionController::class, 'cancelStore'])->name('transactions.cancel.store');

    // 📎 Belege anzeigen über Controller
    Route::get('/beleg/{filename}', [ReceiptController::class, 'show'])->name('receipts.show');

    // Debug
    Route::get('/envcheck', function () {
        dd(config('app.env'), config('app.debug'));
    });
});

require __DIR__.'/auth.php';
