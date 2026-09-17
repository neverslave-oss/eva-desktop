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

test('legacy installs still open the chat route on launch', function () {
    AppSetting::set('install_dir', '~/.kernel-evolving');

    expect(nativeAppProvider()->exposedStartupRouteName())->toBe('chat');
});

test('finishing the setup wizard persists setup completion', function () {
    Livewire::test(SetupWizard::class)
        ->call('finish')
        ->assertRedirect(route('dashboard', absolute: false));

    expect(filter_var(AppSetting::get('setup_completed'), FILTER_VALIDATE_BOOLEAN))->toBeTrue();
});

test('windows nsis config allows guided installation', function () {
    expect(config('nativephp.nsis.one_click'))->toBeFalse()
        ->and(config('nativephp.nsis.allow_to_change_installation_directory'))->toBeTrue();
});