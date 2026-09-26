<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MobileAppController;

Route::prefix('mobile')->name('api.mobile.')->group(function () {
    Route::post('/login', [MobileAppController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [MobileAppController::class, 'logout'])->name('logout');
        Route::get('/me', [MobileAppController::class, 'me'])->name('me');
        Route::post('/me/profile-change', [MobileAppController::class, 'submitProfileChange'])->name('profile-change');
        Route::get('/events', [MobileAppController::class, 'events'])->name('events.index');
        Route::get('/events/{event}', [MobileAppController::class, 'event'])->name('events.show');
        Route::post('/events/{event}/response', [MobileAppController::class, 'respondToEvent'])->name('events.response');
        Route::get('/shifts', [MobileAppController::class, 'shifts'])->name('shifts.index');
        Route::get('/documents', [MobileAppController::class, 'documents'])->name('documents.index');
        Route::get('/contact', [MobileAppController::class, 'contact'])->name('contact');
    });
});
