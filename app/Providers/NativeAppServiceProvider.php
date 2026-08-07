<?php

namespace App\Providers;

use Native\Desktop\Facades\Window;
use Native\Desktop\Facades\MenuBar;
use Native\Desktop\Facades\Updater;
use Native\Desktop\Events\Windows\WindowClosed;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Native\Desktop\Contracts\ProvidesPhpIni;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        $this->ensureDatabaseReady();
        
        Window::open()
        ->title('EvAgent Desktop')
        ->width(800)
        ->height(600)
        ->resizable(true)
        ->rememberState();

        // Hide to system tray on close instead of quitting
        Event::listen(WindowClosed::class, function () {
            Window::current()->hide();
        });

        MenuBar::create()
        ->icon(public_path('icon.png'))
        ->label('EvAgent Desktop')
        ->tooltip('Self Evolving Agent Desktop')
        ->withContextMenu()
        ->openOnClick();

        // Check for updates on boot — notifies user, does not auto-install
        if (config('nativephp.updater.enabled', true)) {
            Updater::checkForUpdates();
        }
    }


       /**
     * Create the SQLite database file and run migrations if needed.
     * Seeding is NOT done here — the user chooses that in the setup wizard.
     */
    protected function ensureDatabaseReady(): void
    {
        try {
            $dbPath = config('database.connections.nativephp.database')
                ?? config('database.connections.sqlite.database')
                ?? database_path('database.sqlite');

            // Create the database file if it doesn't exist
            if (! file_exists($dbPath)) {
                File::ensureDirectoryExists(dirname($dbPath));
                File::put($dbPath, '');
            }

            // Check if the database has any tables — if not, run migrations only
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

            if (empty($tables)) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            // Log but don't crash — the app should still try to open
            report($e);
        }
    }


    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
