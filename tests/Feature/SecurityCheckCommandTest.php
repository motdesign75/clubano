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

test('security check blocks destructive pending migrations', function () {
    $path = database_path('migrations/9999_12_31_235959_drop_member_notes_for_security_check.php');

    File::put($path, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });
    }
};
PHP);

    try {
        $this->artisan('clubano:security-check')
            ->expectsOutputToContain('Riskante ausstehende Migrationen')
            ->expectsOutputToContain('9999_12_31_235959_drop_member_notes_for_security_check.php')
            ->assertExitCode(1);
    } finally {
        File::delete($path);
    }
});
