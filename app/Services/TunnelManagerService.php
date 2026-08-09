<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class TunnelManagerService
{
    protected string $pidFile;
    protected string $logFile;

    public function __construct()
    {
        $this->pidFile = storage_path('app/tunnel.pid');
        $this->logFile = storage_path('logs/tunnel.log');
    }

    public function start(): array
    {
        if ($this->isRunning()) {
            return ['success' => true, 'message' => 'Tunnel is already running.'];
        }

        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $log = $this->logFile;
        $pid = $this->pidFile;

        $result = Process::run([
            'bash',
            '-c',
            "nohup {$php} {$artisan} tunnel:start --daemon > {$log} 2>&1 & echo \$! > {$pid}",
        ]);

        if ($result->failed()) {
            Log::error('Tunnel start failed', ['output' => $result->output()]);
            return ['success' => false, 'message' => 'Failed to start tunnel process.'];
        }

        // Brief wait for it to initialise
        sleep(2);

        return ['success' => true, 'message' => 'Tunnel started.'];
    }

    public function stop(): array
    {
        $pid = $this->getRunningPid();

        if (! $pid) {
            return ['success' => true, 'message' => 'Tunnel was not running.'];
        }

        Process::run(['kill', (string) $pid]);

        if (file_exists($this->pidFile)) {
            @unlink($this->pidFile);
        }

        return ['success' => true, 'message' => 'Tunnel stopped.'];
    }

    public function isRunning(): bool
    {
        $pid = $this->getRunningPid();
        return $pid !== null;
    }

    public function getLogs(int $lines = 100): string
    {
        if (! file_exists($this->logFile)) {
            return '(no tunnel log yet)';
        }

        $result = Process::run(['tail', '-n', (string) $lines, $this->logFile]);
        return $result->output() ?: '(empty log)';
    }

    protected function getRunningPid(): ?int
    {
        if (! file_exists($this->pidFile)) {
            return null;
        }

        $pid = (int) trim(file_get_contents($this->pidFile));
        if ($pid <= 0) {
            return null;
        }

        // Check if the process is actually alive
        $check = Process::run(['kill', '-0', (string) $pid]);
        if ($check->failed()) {
            @unlink($this->pidFile);
            return null;
        }

        return $pid;
    }
}
