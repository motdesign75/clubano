<?php

use App\Models\User;
use Laravel\Jetstream\Http\Livewire\LogoutOtherBrowserSessionsForm;
use Livewire\Livewire;

test('other browser sessions can be logged out', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(LogoutOtherBrowserSessionsForm::class)
        ->set('password', 'password')
        ->call('logoutOtherBrowserSessions')
        ->assertSuccessful();
})->skip(fn () => ! class_exists(LogoutOtherBrowserSessionsForm::class), 'Jetstream browser sessions component is not installed.');
