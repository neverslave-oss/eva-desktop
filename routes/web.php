<?php

use App\Livewire\SetupWizard;
use App\Livewire\EvolutionDashboard;
use App\Livewire\ActivityTab;
use App\Livewire\InsightsTab;
use App\Livewire\Chat;
use App\Livewire\MemoryTab;
use App\Livewire\WorkspaceTab;
use App\Livewire\SqliteViewer;
use App\Livewire\SkillsTab;
use App\Livewire\RoutinesTab;
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
Route::get('/routines', RoutinesTab::class)->name('routines');
Route::get('/replicas', ReplicasTab::class)->name('replicas');
Route::get('/trajectories', TrajectoriesTab::class)->name('trajectories');
Route::get('/memory', MemoryTab::class)->name('memory');
Route::get('/workspace', WorkspaceTab::class)->name('workspace');
Route::get('/sqlite', SqliteViewer::class)->name('sqlite');
Route::get('/system', SystemTab::class)->name('system');
Route::get('/voice', VoiceTab::class)->name('voice');
Route::get('/settings', Settings::class)->name('settings');
