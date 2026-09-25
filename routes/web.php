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

// Setup Wizard — first-run experience. Also guarded by setup.completed so that
// once setup is complete (or after an upgrade) the wizard redirects to chat.
Route::get('/', SetupWizard::class)
    ->middleware('setup.completed')
    ->name('setup-wizard');

// All user-facing app routes are blocked until the setup wizard has been
// completed for the current app version (EnsureSetupCompleted middleware).
// Fresh installs / upgrades are redirected to the setup wizard, so the app
// cannot reach a usable "ready to use" state before setup has finished.
Route::middleware('setup.completed')->group(function () {
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
});

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

// Proxy debug/prompt-logs to the kernel-evolving agent; returns empty gracefully when agent is offline.
Route::get('/debug/prompt-logs', function (\Illuminate\Http\Request $request) {
    try {
        $qs = http_build_query($request->only(['chat_id', 'limit']));
        $resp = \Illuminate\Support\Facades\Http::timeout(5)->get('http://127.0.0.1:8779/debug/prompt-logs?' . $qs);
        return response()->json($resp->json() ?? ['logs' => []]);
    } catch (\Throwable) {
        return response()->json(['logs' => []]);
    }
})->name('debug.prompt-logs');

// Proxy the conversations/sessions API to the kernel-evolving agent; returns an
// empty list gracefully when the agent is offline (mirrors /debug/prompt-logs).
Route::get('/api/sessions', function () {
    try {
        $resp = \Illuminate\Support\Facades\Http::timeout(5)->get('http://127.0.0.1:8779/api/sessions');
        return response()->json($resp->json() ?? ['sessions' => []]);
    } catch (\Throwable) {
        return response()->json(['sessions' => []]);
    }
})->name('api.sessions.list');

Route::post('/api/sessions', function (\Illuminate\Http\Request $request) {
    try {
        $resp = \Illuminate\Support\Facades\Http::timeout(5)->post('http://127.0.0.1:8779/api/sessions', $request->only(['chat_id', 'title']));
        return response()->json($resp->json() ?? ['ok' => false]);
    } catch (\Throwable) {
        return response()->json(['ok' => false, 'error' => 'kernel-evolving unreachable'], 502);
    }
})->name('api.sessions.create');

// Tunnel lifecycle — spawns/kills `php artisan tunnel:start --daemon`
Route::post('/settings/tunnel/start', function () {
    $result = app(\App\Services\TunnelManagerService::class)->start();
    return response()->json($result);
})->name('settings.tunnel-start');

Route::post('/settings/tunnel/stop', function () {
    $result = app(\App\Services\TunnelManagerService::class)->stop();
    return response()->json($result);
})->name('settings.tunnel-stop');

Route::get('/settings/tunnel/logs', function () {
    $logs = app(\App\Services\TunnelManagerService::class)->getLogs(100);
    return response()->json(['logs' => $logs]);
})->name('settings.tunnel-logs');
