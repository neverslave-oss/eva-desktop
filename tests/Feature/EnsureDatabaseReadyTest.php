<?php

use App\Providers\NativeAppServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * NOTE: This file intentionally does NOT use RefreshDatabase. ensureDatabaseReady()
 * runs `php artisan migrate` against the default connection, which conflicts with
 * the DB transaction that RefreshDatabase wraps each test in. Running it against
 * a dedicated fresh SQLite file here avoids that conflict.
 */

function nativeAppProviderWithDb(): NativeAppServiceProvider
{
    return new class extends NativeAppServiceProvider {
        public function exposedEnsureDatabaseReady(): void
        {
            $this->ensureDatabaseReady();
        }
    };
}

test('ensureDatabaseReady creates the app_settings table on a fresh database', function () {
    // Reproduces the crash from the installed-app log:
    //   SetupWizard::mount() → AppSetting::get('central_url')
    //   SQLSTATE[HY000]: General error: 1 no such table: app_settings
    // ensureDatabaseReady() must create `app_settings` before the wizard mounts.
    $freshDb = tempnam(sys_get_temp_dir(), 'app_settings_test_') . '.sqlite';
    File::ensureDirectoryExists(dirname($freshDb));
    File::put($freshDb, '');

    // Point the default sqlite connection at the fresh, unmigrated database.
    $originalDb = config('database.connections.sqlite.database');
    $originalDefault = config('database.default');
    config()->set('database.connections.sqlite.database', $freshDb);
    config()->set('database.default', 'sqlite');
    DB::purge('sqlite');

    try {
        nativeAppProviderWithDb()->exposedEnsureDatabaseReady();

        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table'"))->pluck('name');
        expect($tables)->toContain('app_settings');
    } finally {
        config()->set('database.connections.sqlite.database', $originalDb);
        config()->set('database.default', $originalDefault);
        DB::purge('sqlite');
        File::delete($freshDb);
    }
});
