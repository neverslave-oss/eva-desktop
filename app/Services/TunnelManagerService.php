<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

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

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // proc_open with DETACH on Windows — no nohup/bash needed
                $cmd = "\"{$php}\" \"{$artisan}\" tunnel:start";
                $desc = [
                    0 => ['pipe', 'r'],
                    1 => ['file', $log, 'a'],
                    2 => ['file', $log, 'a'],
                ];
                $proc = proc_open($cmd, $desc, $pipes, base_path(), null, ['create_new_process_group' => true]);

                if (! is_resource($proc)) {
                    throw new \RuntimeException('proc_open failed on Windows');
                }

                $status = proc_get_status($proc);
                $childPid = $status['pid'];
                proc_close($proc);

                file_put_contents($pid, (string) $childPid);
            } else {
                $out = [];
                exec("nohup \"{$php}\" \"{$artisan}\" tunnel:start > \"{$log}\" 2>&1 & echo \$!", $out);
                $childPid = (int) trim($out[0] ?? '0');

                if ($childPid <= 0) {
                    throw new \RuntimeException('Could not obtain PID from nohup');
                }

                file_put_contents($pid, (string) $childPid);
            }
        } catch (\Throwable $e) {
            Log::error('Tunnel start failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Failed to start tunnel: ' . $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Tunnel started.'];
    }

    public function stop(): array
    {
        $pid = $this->getRunningPid();

        if (! $pid) {
            return ['success' => true, 'message' => 'Tunnel was not running.'];
        }

        if (PHP_OS_FAMILY === 'Windows') {
            exec("taskkill /F /PID {$pid}");
        } else {
            exec("kill {$pid}");
        }

        if (file_exists($this->pidFile)) {
            @unlink($this->pidFile);
        }

        return ['success' => true, 'message' => 'Tunnel stopped.'];
    }

    public function isRunning(): bool
    {
        return $this->getRunningPid() !== null;
    }

    public function getLogs(int $lines = 100): string
    {
        if (! file_exists($this->logFile)) {
            return '(no tunnel log yet)';
        }

        $content = file_get_contents($this->logFile);
        $all = explode("\n", $content);
        return implode("\n", array_slice($all, -$lines));
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

        // Check if the process is alive
        if (PHP_OS_FAMILY === 'Windows') {
            exec("tasklist /FI \"PID eq {$pid}\" /NH", $out);
            $alive = collect($out)->contains(fn($line) => str_contains($line, (string) $pid));
        } else {
            exec("kill -0 {$pid} 2>/dev/null", $out, $code);
            $alive = $code === 0;
        }

        if (! $alive) {
            @unlink($this->pidFile);
            return null;
        }

        return $pid;
    }
}
