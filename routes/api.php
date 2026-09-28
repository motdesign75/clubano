<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MobileAppController;
use App\Http\Controllers\Api\TrinkwertIdentityController;

Route::prefix('integrations/trinkwert')->name('api.integrations.trinkwert.')->group(function () {
    Route::post('/resolve-member', [TrinkwertIdentityController::class, 'resolveMember'])
        ->middleware('throttle:120,1')
        ->name('resolve-member');
});

Route::prefix('mobile')->name('api.mobile.')->group(function () {
    Route::post('/login', [MobileAppController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [MobileAppController::class, 'logout'])->name('logout');
        Route::get('/me', [MobileAppController::class, 'me'])->name('me');
        Route::post('/me/profile-change', [MobileAppController::class, 'submitProfileChange'])->name('profile-change');
        Route::post('/push-token', [MobileAppController::class, 'registerPushToken'])->name('push-token');
        Route::get('/notifications', [MobileAppController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/read-all', [MobileAppController::class, 'markNotificationsRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [MobileAppController::class, 'markNotificationRead'])->name('notifications.read');
        Route::get('/member-card', [MobileAppController::class, 'memberCard'])->name('member-card');
        Route::get('/events', [MobileAppController::class, 'events'])->name('events.index');
        Route::get('/events/{event}', [MobileAppController::class, 'event'])->name('events.show');
        Route::post('/events/{event}/response', [MobileAppController::class, 'respondToEvent'])->name('events.response');
        Route::get('/shifts', [MobileAppController::class, 'shifts'])->name('shifts.index');
        Route::get('/documents', [MobileAppController::class, 'documents'])->name('documents.index');
        Route::get('/news', [MobileAppController::class, 'news'])->name('news.index');
        Route::get('/contact', [MobileAppController::class, 'contact'])->name('contact');
    });
});
