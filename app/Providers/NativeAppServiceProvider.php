<?php

namespace App\Providers;

use Native\Desktop\Facades\Window;
use Native\Desktop\Facades\MenuBar;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Events\Windows\WindowClosed;
use Native\Desktop\Events\Menu\MenuItemClicked;
use Native\Desktop\Events\Notifications\NotificationActionClicked;
use App\Services\KernelEvolvingService;
use App\Models\AppSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Native\Desktop\Contracts\ProvidesPhpIni;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    protected const MAIN_WINDOW_ID = 'main';

    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        $this->ensureDatabaseReady();

        Window::open(self::MAIN_WINDOW_ID)
            ->title('EvAgent Desktop')
            ->route($this->startupRouteName())
            ->width(1280)
            ->height(800)
            ->position(80, 80)
            ->resizable(true);

        // Hide to system tray on close instead of quitting
        Event::listen(WindowClosed::class, function () {
            Window::current()->hide();
        });

        MenuBar::create()
            ->icon(public_path('icon.png'))
            ->label('EvAgent Desktop')
            ->tooltip('Self Evolving Agent Desktop')
            ->withContextMenu(Menu::make(
                Menu::label('Show App')->event('tray-app-show'),
                Menu::link(route('dashboard'), 'Open Dashboard'),
                Menu::link(route('settings'), 'Settings'),
                Menu::separator(),
                Menu::label('Start Agent')->event('tray-agent-start'),
                Menu::label('Stop Agent')->event('tray-agent-stop'),
                Menu::separator(),
                Menu::quit('Quit'),
            ));

        // Tray context menu actions (Start/Stop Agent)
        Event::listen(MenuItemClicked::class, function (MenuItemClicked $event) {
            if (($event->item['event'] ?? null) === 'tray-app-show') {
                // Recover a hidden/off-screen window by forcing it back into view.
                Window::show(self::MAIN_WINDOW_ID);
                Window::position(80, 80, false, self::MAIN_WINDOW_ID);

                return;
            }

            $this->handleAgentLifecycleEvent($event->item['event'] ?? null);
        });

        // Native notification action buttons ("Agent Offline" -> Start/Restart)
        Event::listen(NotificationActionClicked::class, function (NotificationActionClicked $event) {
            if ($event->event !== 'kernel-agent-offline') return;
            $this->handleAgentLifecycleEvent($event->index === 0 ? 'tray-agent-start' : 'tray-agent-restart');
        });

        // Check for updates on boot — notifies user, does not auto-install
        if (config('nativephp.updater.enabled', true) && class_exists(\Native\Desktop\Facades\Updater::class)) {
            \Native\Desktop\Facades\Updater::checkForUpdates();
        }
    }

    protected function startupRouteName(): string
    {
        return $this->hasCompletedSetup() ? 'chat' : 'setup-wizard';
    }

    protected function hasCompletedSetup(): bool
    {
        // Version-aware setup completion check (see AppSetting::isSetupComplete()).
        return AppSetting::isSetupComplete();
    }

    /**
     * Start/stop/restart kernel-evolving from tray menu clicks or notification actions.
     */
    protected function handleAgentLifecycleEvent(?string $event): void
    {
        if (! in_array($event, ['tray-agent-start', 'tray-agent-stop', 'tray-agent-restart'], true)) {
            return;
        }

        $svc = app(KernelEvolvingService::class);
        $installDir = AppSetting::get('install_dir', KernelEvolvingService::DEFAULT_INSTALL_DIR);
        $mode = AppSetting::get('install_mode', 'baremetal');

        match ($event) {
            'tray-agent-start' => $mode === 'docker' ? $svc->startDocker() : $svc->startBareMetal($installDir),
            'tray-agent-stop' => $mode === 'docker' ? $svc->stopDocker() : $svc->stopBareMetal(),
            'tray-agent-restart' => (function () use ($svc, $mode, $installDir) {
                $mode === 'docker' ? $svc->stopDocker() : $svc->stopBareMetal();
                sleep(1);
                $mode === 'docker' ? $svc->startDocker() : $svc->startBareMetal($installDir);
            })(),
        };
    }


    /**
     * Create the SQLite database file and run migrations if needed.
     * Seeding is NOT done here — the user chooses that in the setup wizard.
     */
    protected function ensureDatabaseReady(): void
    {
        $dbPath = config('database.connections.nativephp.database')
            ?? config('database.connections.sqlite.database')
            ?? database_path('database.sqlite');

        // Create the database file if it doesn't exist
        if (! file_exists($dbPath)) {
            File::ensureDirectoryExists(dirname($dbPath));
            File::put($dbPath, '');
        }

        // Run migrations idempotently. Laravel tracks already-applied
        // migrations in the `migrations` table, so running `migrate` on every
        // boot is safe and guarantees every table (including `app_settings`)
        // exists before the setup wizard mounts. Previously we only migrated
        // when the DB had zero tables, which let a partially-migrated DB skip
        // `app_settings` and crash the wizard with "no such table:
        // app_settings" (SetupWizard::mount() → AppSetting::get()).
        Artisan::call('migrate', ['--force' => true]);
    }


    /**
     * Return an array of php.ini directives to be set.
     *
     * The NativePHP Electron runtime builds each PHP process env without
     * TMP/TEMP, so on Windows sys_get_temp_dir() can fall back to C:\WINDOWS,
     * which the app cannot write. Any Symfony Process::run() then fails with
     * "A temporary file could not be opened ... sf_proc_*.out.lock" and the
     * request 500s (observed on the setup wizard, which calls detectDocker()).
     * Pin sys_temp_dir to a writable per-app dir so spawned processes work.
     */
    public function phpIni(): array
    {
        // The Electron runtime sets NATIVEPHP_USER_DATA_PATH to the writable
        // per-app user-data dir (e.g. %APPDATA%\eva-agent). Use it for a
        // writable temp dir so Symfony Process can create its lock files.
        $userData = rtrim(str_replace('\\', '/', env('NATIVEPHP_USER_DATA_PATH') ?: storage_path('framework')), '/');
        $tmpDir = $userData.'/tmp';

        // Ensure the temp dir exists (PHP creates it lazily, but create now to
        // be safe and to fail loudly in development if it is not writable).
        if (! is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        return [
            'sys_temp_dir' => $tmpDir,
        ];
    }
}
