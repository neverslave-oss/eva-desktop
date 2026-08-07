<?php

use App\Livewire\SetupWizard;
use App\Livewire\EvolutionDashboard;
use App\Livewire\ActivityTab;
use App\Livewire\InsightsTab;
use App\Livewire\Chat;
use App\Livewire\MemoryTab;
use App\Livewire\SkillsTab;
use App\Livewire\ReplicasTab;
use App\Livewire\TrajectoriesTab;
use App\Livewire\SystemTab;
use App\Livewire\VoiceTab;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kernel Desktop Routes
|--------------------------------------------------------------------------
|
| The desktop app wraps the kernel-evolving dashboard and provides
| a setup wizard, chat interface, and configuration settings.
| All tabs mirror the evolution_dashboard.html from kernel-evolving.
|
*/

// Setup Wizard — first-run experience
Route::get('/', SetupWizard::class)
    ->name('setup-wizard');

// Dashboard Tabs (mirrors kernel-evolving evolution_dashboard.html)
Route::get('/dashboard', EvolutionDashboard::class)->name('dashboard');
Route::get('/activity', ActivityTab::class)->name('activity');
Route::get('/insights', InsightsTab::class)->name('insights');
Route::get('/chat', Chat::class)->name('chat');
Route::get('/skills', SkillsTab::class)->name('skills');
Route::get('/replicas', ReplicasTab::class)->name('replicas');
Route::get('/trajectories', TrajectoriesTab::class)->name('trajectories');
Route::get('/memory', MemoryTab::class)->name('memory');
Route::get('/system', SystemTab::class)->name('system');
Route::get('/voice', VoiceTab::class)->name('voice');
Route::get('/settings', Settings::class)->name('settings');

// Kernel-evolving agent lifecycle — POST from Settings page
Route::post('/settings/agent/start', function () {
    $svc = app(\App\Services\KernelEvolvingService::class);
    $installDir = \App\Models\AppSetting::get('install_dir', $svc::DEFAULT_INSTALL_DIR);
    $mode = \App\Models\AppSetting::get('install_mode', 'baremetal');
    $result = $mode === 'docker' ? $svc->startDocker($installDir) : $svc->startBareMetal($installDir);
    return response()->json($result);
})->name('settings.agent-start');

Route::post('/settings/agent/stop', function () {
    $svc = app(\App\Services\KernelEvolvingService::class);
    $mode = \App\Models\AppSetting::get('install_mode', 'baremetal');
    $result = $mode === 'docker' ? $svc->stopDocker() : $svc->stopBareMetal();
    return response()->json($result);
})->name('settings.agent-stop');

Route::post('/settings/agent/restart', function () {
    $svc = app(\App\Services\KernelEvolvingService::class);
    $installDir = \App\Models\AppSetting::get('install_dir', $svc::DEFAULT_INSTALL_DIR);
    $mode = \App\Models\AppSetting::get('install_mode', 'baremetal');
    $mode === 'docker' ? $svc->stopDocker() : $svc->stopBareMetal();
    sleep(1);
    $result = $mode === 'docker' ? $svc->startDocker($installDir) : $svc->startBareMetal($installDir);
    return response()->json($result);
})->name('settings.agent-restart');

Route::get('/settings/agent/logs', function () {
    $svc = app(\App\Services\KernelEvolvingService::class);
    $mode = \App\Models\AppSetting::get('install_mode', 'baremetal');
    $logs = $mode === 'docker' ? $svc->getDockerLogs(80) : $svc->getBareMetalLogs();
    return response()->json(['logs' => $logs]);
})->name('settings.agent-logs');

// Native OS notification when the frontend heartbeat detects the agent is unreachable.
// Debounced client-side; silently no-ops outside the NativePHP runtime (e.g. `artisan serve`).
Route::post('/agent/notify-offline', function () {
    try {
        \Native\Desktop\Facades\Notification::new()
            ->title('Eva Agent Offline')
            ->message('kernel-evolving is not responding on port 8779.')
            ->addAction('Start')
            ->addAction('Restart')
            ->event('kernel-agent-offline')
            ->show();
    } catch (\Throwable $e) {
        // Not running inside the NativePHP/Electron shell — nothing to notify.
    }
    return response()->json(['ok' => true]);
})->name('agent.notify-offline');
