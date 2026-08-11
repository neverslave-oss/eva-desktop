<?php

namespace App\Console\Commands;

use App\Services\TunnelService;
use Illuminate\Console\Command;

/**
 * TunnelStatusCommand
 *
 * Reports the current WebSocket tunnel status for the Settings UI (KD-004).
 * Reads the tunnel state from the cached status file written by TunnelService.
 *
 * Usage:
 *   php artisan tunnel:status
 */
class TunnelStatusCommand extends Command
{
    protected $signature = 'tunnel:status';

    protected $description = 'Show WebSocket tunnel connection status';

    protected TunnelService $tunnel;

    public function __construct(TunnelService $tunnel)
    {
        parent::__construct();
        $this->tunnel = $tunnel;
    }

    public function handle(): int
    {
        $state = $this->tunnel->getState();
        $stateLabels = [
            TunnelService::STATE_DISCONNECTED => '🔴 Disconnected',
            TunnelService::STATE_CONNECTING => '🟡 Connecting',
            TunnelService::STATE_CONNECTED => '🟢 Connected',
            TunnelService::STATE_RECONNECTING => '🔄 Reconnecting',
            TunnelService::STATE_ERROR => '🔴 Error',
        ];

        $this->line('Tunnel Status: ' . ($stateLabels[$state] ?? $state));
        $this->line('');

        if ($this->tunnel->isConnected()) {
            $this->line('Socket ID: ' . ($this->tunnel->getSocketId() ?? 'N/A'));
            $this->line('Channel: private-devices.' . config('kernel-desktop.device.id', 0));
        }

        $lastConnected = $this->tunnel->getLastConnectedAt();
        if ($lastConnected) {
            $this->line('Last connected: ' . date('Y-m-d H:i:s', $lastConnected));
        }

        return self::SUCCESS;
    }
}
