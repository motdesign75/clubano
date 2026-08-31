<?php

use Illuminate\Support\Facades\File;

test('security check succeeds without critical release blockers', function () {
    $this->artisan('clubano:security-check')
        ->assertExitCode(0);
});

test('security check blocks public environment leaks', function () {
    $path = public_path('.env');
    $alreadyExisted = File::exists($path);

    if (! $alreadyExisted) {
        File::put($path, 'APP_ENV=production');
    }

    try {
        $this->artisan('clubano:security-check')
            ->expectsOutputToContain('Webroot enthält interne Dateien')
            ->expectsOutputToContain('public/.env')
            ->assertExitCode(1);
    } finally {
        if (! $alreadyExisted) {
            File::delete($path);
        }
    }
});
