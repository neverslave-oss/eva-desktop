<?php

namespace App\Console\Commands;

use App\Services\KernelCentralService;
use App\Services\MessageForwarderService;
use App\Services\TunnelService;
use Illuminate\Console\Command;

/**
 * TunnelCommand
 *
 * Manages the kernel-desktop WebSocket tunnel to kernel-central:
 *   - Connects to Reverb and subscribes to the device channel
 *   - Listens for relayed messages and forwards to kernel-evolving (KD-003)
 *   - Auto-reconnects on connection loss with exponential backoff (KD-004)
 *
 * Usage:
 *   php artisan tunnel:start              # Start tunnel (foreground)
 *   php artisan tunnel:start --daemon     # Start tunnel as daemon (output to log)
 *   php artisan tunnel:status             # Show tunnel connection status
 *   php artisan tunnel:stop               # Stop running tunnel process
 */
class TunnelCommand extends Command
{
    protected $signature = 'tunnel:start
        {--daemon : Run in daemon mode (background with logging)}
        {--max-listen=0 : Max seconds to listen (0 = indefinite)}';

    protected $description = 'Start the WebSocket tunnel to kernel-central for mobile relay';

    protected TunnelService $tunnel;
    protected MessageForwarderService $forwarder;
    protected KernelCentralService $central;

    /**
     * PID file path for tracking the daemon process.
     */
    protected string $pidFile;

    public function __construct()
    {
        parent::__construct();
        $this->pidFile = storage_path('app/tunnel.pid');
    }

    public function handle(
        TunnelService $tunnel,
        MessageForwarderService $forwarder,
        KernelCentralService $central
    ): int {
        $this->tunnel = $tunnel;
        $this->forwarder = $forwarder;
        $this->central = $central;

        // Check if already paired
        if (! $central->isPaired()) {
            $this->error('Device is not paired with kernel-central. Run the setup wizard first.');

            return self::FAILURE;
        }

        $this->line('🔌 Starting WebSocket tunnel to kernel-central...');
        $this->newLine();

        // Register message forwarder callback
        $tunnel->onMessage(function (array $data, TunnelService $tunnel) {
            $this->forwarder->handle($data, $tunnel);
        });

        // Register state change logging
        $tunnel->onStateChange(function (string $newState, ?string $oldState) {
            $this->logStateChange($newState, $oldState);
        });

        // Connection loop with auto-reconnect
        $maxListen = (int) $this->option('max-listen');
        $heartbeatInterval = max(5, (int) config('kernel-desktop.device.health_interval_seconds', 30));
        $deviceId = (int) config('kernel-desktop.device.id', 0);

        while (true) {
            $connected = $tunnel->connect();

            if (! $connected) {
                $delay = $this->getReconnectDelay();
                $this->warn("Connection failed. Reconnecting in {$delay}s...");

                if (! $this->sleep($delay)) {
                    return self::FAILURE;
                }

                continue;
            }

            $this->line('✅ Tunnel connected. Listening for relayed messages...');

            $startedAt = time();
            $lastHeartbeatAt = 0;

            while (true) {
                $now = time();

                if ($deviceId > 0 && ($now - $lastHeartbeatAt) >= $heartbeatInterval) {
                    $ok = $central->heartbeat($deviceId);
                    $lastHeartbeatAt = $now;

                    if (! $ok) {
                        $this->warn('⚠️ Heartbeat failed (will retry).');
                    }
                }

                if ($maxListen > 0) {
                    $elapsed = $now - $startedAt;
                    $remaining = $maxListen - $elapsed;

                    if ($remaining <= 0) {
                        return self::SUCCESS;
                    }

                    $window = max(1, min($heartbeatInterval, $remaining));
                } else {
                    $window = $heartbeatInterval;
                }

                $tunnel->listen($window);

                // Connection lost — reconnect
                if ($tunnel->getState() === TunnelService::STATE_DISCONNECTED) {
                    $delay = $this->getReconnectDelay();
                    $this->warn("🔌 Connection lost. Reconnecting in {$delay}s...");

                    if (! $this->sleep($delay)) {
                        return self::FAILURE;
                    }

                    continue 2;
                }

                // Unrecoverable state
                if ($tunnel->getState() === TunnelService::STATE_ERROR) {
                    break 2;
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Log a state change to the console.
     */
    protected function logStateChange(string $newState, ?string $oldState): void
    {
        $stateLabels = [
            TunnelService::STATE_DISCONNECTED => '🔴 Disconnected',
            TunnelService::STATE_CONNECTING => '🟡 Connecting',
            TunnelService::STATE_CONNECTED => '🟢 Connected',
            TunnelService::STATE_RECONNECTING => '🔄 Reconnecting',
            TunnelService::STATE_ERROR => '🔴 Error',
        ];

        $label = $stateLabels[$newState] ?? $newState;

        if ($newState === TunnelService::STATE_CONNECTED) {
            $this->info($label);
        } elseif (in_array($newState, [TunnelService::STATE_ERROR, TunnelService::STATE_DISCONNECTED], true)) {
            $this->error($label);
        } else {
            $this->warn($label);
        }
    }

    /**
     * Get the reconnect delay with exponential backoff.
     */
    protected function getReconnectDelay(): int
    {
        $attempts = $this->tunnel->getReconnectAttempts();
        $delay = min(pow(2, $attempts), 60); // 1s, 2s, 4s, 8s, 16s, 32s, 60s max

        return (int) $delay;
    }

    /**
     * Sleep for the given number of seconds, checking for exit signals.
     * Returns false if interrupted by signal.
     */
    protected function sleep(int $seconds): bool
    {
        for ($i = 0; $i < $seconds; $i++) {
            sleep(1);

            if ($this->getLaravel()->runningInConsole() && extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }
        }

        return true;
    }
}
