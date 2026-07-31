<?php

use App\Console\Commands\TunnelCommand;
use App\Console\Commands\TunnelStatusCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| WebSocket Tunnel Commands
|--------------------------------------------------------------------------
|
| The tunnel connects to kernel-central's Reverb server and maintains
| a persistent WebSocket connection for mobile relay (KD-002/003/004).
|
| Commands:
|   php artisan tunnel:start           # Start tunnel (foreground)
|   php artisan tunnel:start --daemon  # Start tunnel (background)
|   php artisan tunnel:status          # Show connection status
|
*/

// Register tunnel commands
Artisan::command('tunnel:start', function () {
    $this->call(TunnelCommand::class);
})->purpose('Start WebSocket tunnel to kernel-central for mobile relay');

Artisan::command('tunnel:status', function () {
    $this->call(TunnelStatusCommand::class);
})->purpose('Show WebSocket tunnel connection status');

// Schedule: restart tunnel if not running (every minute)
Schedule::command('tunnel:start --daemon')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/tunnel-scheduler.log'));
