<?php

namespace App\Livewire\Concerns;

use App\Services\KernelEvolvingService;
use Illuminate\Support\Facades\Log;

/**
 * Manages the installed-agent lifecycle UI state/methods for the Setup wizard
 * (install + start + polling). Extracted from SetupWizard.php (issue #19).
 */
trait ManagesAgentLifecycle
{
    public bool $installing = false;
    public bool $installed = false;
    public bool $starting = false;
    public bool $started = false;
    public string $installLog = '';
    public string $startLog = '';
    public string $errorMessage = '';
    public string $installLogFile = '/tmp/kernel_install.log';
    public string $startLogFile = '/tmp/kernel_start.log';
    public bool $pollInstall = false;
    public bool $pollStart = false;
    public bool $pollHealth = false;


    public function installAgent()
    {
        $this->installing = true;
        $this->errorMessage = '';
        $this->installLog = '';
        $this->pollInstall = false;

        try {
            if ($this->installMode === 'bare-metal') {
                $cloneResult = $this->service->cloneRepo($this->installDir);
                $this->installLog .= $cloneResult['message'] . "\n";
                if (!$cloneResult['success']) {
                    $this->errorMessage = $cloneResult['error'] ?? $cloneResult['message'];
                    $this->installing = false;
                    return;
                }

                $this->service->writeEnvFile($this->installDir, [
                    'telegram_bot_token'   => $this->telegramBotToken,
                    'telegram_chat_id'     => $this->telegramChatId,
                    'user_name'            => $this->userName,
                    'user_handle'          => $this->userHandle,
                    'openai_api_key'       => $this->openaiKey,
                    'anthropic_api_key'    => $this->anthropicKey,
                    'github_token'         => $this->githubToken,
                    'hf_token'             => $this->hfToken,
                    'evolution_enabled'    => $this->evolutionEnabled,
                    'models_path'          => $this->modelsPath,
                    'workspace_path'       => $this->workspacePath,
                    'collective_memory_url'=> $this->collectiveMemoryUrl,
                ]);
                $this->service->writeConfigYaml($this->installDir, [
                    'vram'          => $this->vram,
                    'default_model' => $this->defaultModel,
                ]);

                // Fire install.sh in background — write to log file, poll for completion
                $expandedDir = $this->service->expandInstallPath($this->installDir);
                $log = $this->installLogFile;
                $marker = '###KERNEL_INSTALL_DONE###';
                $cmd = "bash -c 'cd " . escapeshellarg($expandedDir) . " && bash install.sh >> " . escapeshellarg($log) . " 2>&1 && echo " . escapeshellarg($marker) . " >> " . escapeshellarg($log) . "' &";
                file_put_contents($log, "🚀 Starting installation…\n");
                shell_exec($cmd);
                $this->pollInstall = true;
                $this->installLog = file_get_contents($log) ?: '';

            } else {
                // Docker mode: clone is the only synchronous step
                $cloneResult = $this->service->cloneRepo($this->dockerRepoDir);
                $this->installLog .= $cloneResult['message'] . "\n";
                if (!$cloneResult['success']) {
                    $this->errorMessage = $cloneResult['error'] ?? $cloneResult['message'];
                    $this->installing = false;
                    return;
                }
                $this->installed = true;
                $this->installing = false;
            }
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
            Log::error('Setup wizard install failed', ['error' => $e->getMessage()]);
            $this->installing = false;
        }
    }

    /** Poll the install log file every 2s while install.sh is running. */
    public function pollInstallLog(): void
    {
        if (!$this->pollInstall) {
            return;
        }
        $content = @file_get_contents($this->installLogFile) ?: '';
        $this->installLog = $content;

        if (str_contains($content, '###KERNEL_INSTALL_DONE###')) {
            $this->pollInstall = false;
            $this->installing = false;
            $this->installed = true;
            $this->installLog = str_replace('###KERNEL_INSTALL_DONE###', '✅ Installation complete!', $content);
        } elseif (str_contains(strtolower($content), 'error') && str_contains(strtolower($content), 'traceback')) {
            $this->pollInstall = false;
            $this->installing = false;
            $this->errorMessage = 'Install script reported an error. See log above.';
        }
    }

    /**
     * Step 5: Start the agent (Docker or bare-metal).
     * Fires the start command in the background and polls for health.
     */
    public function startAgent()
    {
        $this->starting = true;
        $this->errorMessage = '';
        $this->startLog = '';
        $this->startPollCount = 0;
        $log = $this->startLogFile;
        file_put_contents($log, "🚀 Starting kernel-evolving…\n");

        try {
            if ($this->installMode === 'docker') {
                // Write docker env first (synchronous — fast), then docker compose up in bg
                $expandedDir = $this->service->expandInstallPath($this->dockerRepoDir);
                $deployDir = $expandedDir . '/deploy';
                $this->service->writeDockerEnvFilePublic($deployDir . '/.env', [
                    'telegram_bot_token'   => $this->telegramBotToken,
                    'telegram_chat_id'     => $this->telegramChatId,
                    'user_name'            => $this->userName,
                    'user_handle'          => $this->userHandle,
                    'openai_api_key'       => $this->openaiKey,
                    'anthropic_api_key'    => $this->anthropicKey,
                    'github_token'         => $this->githubToken,
                    'hf_token'             => $this->hfToken,
                    'evolution_enabled'    => $this->evolutionEnabled,
                    'models_path'          => $this->modelsPath,
                    'workspace_path'       => $this->workspacePath,
                    'collective_memory_url'=> $this->collectiveMemoryUrl,
                ]);
                $cmd = "bash -c 'cd " . escapeshellarg($deployDir) . " && docker compose up -d --build >> " . escapeshellarg($log) . " 2>&1' &";
                shell_exec($cmd);
            } else {
                $expandedDir = $this->service->expandInstallPath($this->installDir);
                $cmd = "bash -c 'cd " . escapeshellarg($expandedDir) . " && bash start.sh >> " . escapeshellarg($log) . " 2>&1' &";
                shell_exec($cmd);
            }
            $this->pollStart = true;
            $this->startLog = file_get_contents($log) ?: '';
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
            Log::error('Setup wizard start failed', ['error' => $e->getMessage()]);
            $this->starting = false;
        }
    }

    /** Poll for start log output + health check every 3s. */
    public function pollStartLog(): void
    {
        if (!$this->pollStart) {
            return;
        }
        $this->startPollCount++;
        $content = @file_get_contents($this->startLogFile) ?: '';
        $this->startLog = $content;

        if ($this->service->isHealthy()) {
            $this->pollStart = false;
            $this->starting = false;
            $this->started = true;
            $this->pollHealth = false;
            $this->startLog .= "\n✅ API healthy on port " . KernelEvolvingService::PORT . "\n";
            return;
        }

        // After ~30s (10 polls × 3s) with no health response, hint at model download
        if ($this->startPollCount >= 10 && !$this->started) {
            $elapsed = $this->startPollCount * 3;
            $this->startLog .= "\n⏳ {$elapsed}s elapsed — model weights may still be downloading (~2GB on first run). This can take several minutes depending on your connection.\n";
        }
    }

    /**
     * Poll the health endpoint — called via wire:poll when pollHealth is true.
     */
    public function pollApiHealth()
    {
        if (!$this->pollHealth) {
            return;
        }

        if ($this->service->isHealthy()) {
            $this->started = true;
            $this->pollHealth = false;
            $this->startLog .= "✅ API healthy on port " . KernelEvolvingService::PORT . "\n";
        }
    }

    /**
     * Step 6: Pair this desktop device with kernel-central.
     *
     * Flow:
     *   1. Ping kernel-central health endpoint
     *   2. POST /api/devices/pair — create device, get pair_token + pair_secret
     *   3. POST /api/devices/confirm — claim pairing, get Sanctum token
     *   4. Store token locally
     */
}
