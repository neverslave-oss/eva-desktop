<?php

use App\Livewire\SetupWizard;
use App\Models\AppSetting;
use App\Providers\NativeAppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function nativeAppProvider(): NativeAppServiceProvider
{
    return new class extends NativeAppServiceProvider {
        public function exposedStartupRouteName(): string
        {
            return $this->startupRouteName();
        }
    };
}

test('fresh installs open the setup wizard on launch', function () {
    expect(nativeAppProvider()->exposedStartupRouteName())->toBe('setup-wizard');
});

test('legacy installs that completed setup still open the chat route', function () {
    // Legacy installs set setup_completed=true but predate the versioned marker.
    AppSetting::set('setup_completed', true);

    expect(nativeAppProvider()->exposedStartupRouteName())->toBe('chat');
});

test('leftover legacy keys without the completion marker open the wizard', function () {
    // A machine with stale data from a previous app identity / interrupted
    // install has install_mode/install_dir/workspace_path but never finished
    // the wizard. It must NOT skip straight to the dashboard.
    AppSetting::set('install_dir', '~/.kernel-evolving');
    AppSetting::set('install_mode', 'baremetal');
    AppSetting::set('workspace_path', '~/.kernel-evolving');

    expect(nativeAppProvider()->exposedStartupRouteName())->toBe('setup-wizard');
});

test('setup completed for the current version opens the chat route', function () {
    AppSetting::set('setup_completed', true);
    AppSetting::set('setup_completed_version', config('nativephp.version'));

    expect(nativeAppProvider()->exposedStartupRouteName())->toBe('chat');
});

test('setup completed for a different version re-runs the wizard', function () {
    AppSetting::set('setup_completed', true);
    AppSetting::set('setup_completed_version', '0.0.0-old');

    expect(nativeAppProvider()->exposedStartupRouteName())->toBe('setup-wizard');
});

test('finishing the setup wizard persists setup completion', function () {
    Livewire::test(SetupWizard::class)
        ->call('finish')
        ->assertRedirect(route('dashboard', absolute: false));

    expect(filter_var(AppSetting::get('setup_completed'), FILTER_VALIDATE_BOOLEAN))->toBeTrue()
        ->and(AppSetting::get('setup_completed_version'))->toBe(config('nativephp.version'));
});

test('windows nsis config allows guided installation', function () {
    expect(config('nativephp.nsis.one_click'))->toBeFalse()
        ->and(config('nativephp.nsis.allow_to_change_installation_directory'))->toBeTrue();
});

test('app routes redirect to the setup wizard before setup is complete', function () {
    // Fresh install — setup not complete. The chat page must not be reachable.
    $this->get(route('chat', absolute: false))
        ->assertRedirect(route('setup-wizard', absolute: false));
});

test('app routes are reachable once setup is complete', function () {
    AppSetting::set('setup_completed', true);
    AppSetting::set('setup_completed_version', config('nativephp.version'));

    $this->get(route('chat', absolute: false))
        ->assertOk();
});

test('setup wizard redirects to chat once setup is complete', function () {
    AppSetting::set('setup_completed', true);
    AppSetting::set('setup_completed_version', config('nativephp.version'));

    $this->get(route('setup-wizard', absolute: false))
        ->assertRedirect(route('chat', absolute: false));
});