<?php

use App\Livewire\SetupWizard;
use App\Livewire\EvolutionDashboard;
use App\Livewire\Chat;
use App\Livewire\MemoryTab;
use App\Livewire\WorkspaceTab;
use App\Livewire\SqliteViewer;
use App\Livewire\SkillsTab;
use App\Livewire\RoutinesTab;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kernel Desktop Routes
|--------------------------------------------------------------------------
|
| The desktop app wraps the kernel-evolving dashboard and provides
| a setup wizard, chat interface, and configuration settings.
|
*/

// Setup Wizard — first-run experience
Route::get('/', SetupWizard::class)
    ->name('setup-wizard');

// Dashboard Tabs
Route::get('/dashboard', EvolutionDashboard::class)
    ->name('dashboard');

Route::get('/chat', Chat::class)
    ->name('chat');

Route::get('/memory', MemoryTab::class)
    ->name('memory');

Route::get('/workspace', WorkspaceTab::class)
    ->name('workspace');

Route::get('/sqlite', SqliteViewer::class)
    ->name('sqlite');

Route::get('/skills', SkillsTab::class)
    ->name('skills');

Route::get('/routines', RoutinesTab::class)
    ->name('routines');

Route::get('/settings', Settings::class)
    ->name('settings');
