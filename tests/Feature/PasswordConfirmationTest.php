<?php

use App\Models\User;
use Laravel\Jetstream\Features;

test('confirm password screen can be rendered', function () {
    $user = class_exists(Features::class) && Features::hasTeamFeatures()
                    ? User::factory()->withPersonalTeam()->create()
                    : User::factory()->create();

    $response = $this->actingAs($user)->get('/user/confirm-password');

    $response->assertStatus(200);
})->skip(fn () => ! class_exists(Features::class), 'Jetstream password confirmation routes are not installed.');

test('password can be confirmed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/user/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
})->skip(fn () => ! class_exists(Features::class), 'Jetstream password confirmation routes are not installed.');

test('password is not confirmed with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/user/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
})->skip(fn () => ! class_exists(Features::class), 'Jetstream password confirmation routes are not installed.');
