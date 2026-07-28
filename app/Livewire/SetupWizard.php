<?php

namespace App\Livewire;

use App\Services\KernelEvolvingService;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class SetupWizard extends Component
{
    public int $step = 1;
    public int $totalSteps = 7;

    // System detection
    public array $dockerStatus = [];
    public array $dockerComposeStatus = [];
    public array $gpuStatus = [];
    public bool $apiAlreadyRunning = false;

    // Install mode
    public string $installMode = 'docker'; // 'docker' or 'bare-metal'

    // Bare-metal config
    public string $installDir = '~/.kernel-evolving';

    // Docker config
    public string $dockerRepoDir = '~/.kernel-evolving';

    // User configuration
    public string $vram = '8';
    public string $defaultModel = 'local'; // 'local' (Nemotron-3B) or 'cloud' (OpenAI)
    public string $openaiKey = '';
    public string $anthropicKey = '';
    public string $githubToken = '';
    public string $hfToken = '';
    public string $telegramBotToken = '';
    public string $telegramChatId = '';
    public string $userName = '';
    public string $userHandle = '';
    public bool $evolutionEnabled = true;

    // Progress tracking
    public bool $installing = false;
    public bool $installed = false;
    public bool $starting = false;
    public bool $started = false;
    public bool $paired = false;
    public string $installLog = '';
    public string $startLog = '';
    public string $errorMessage = '';

    // Polling
    public bool $pollHealth = false;

    protected KernelEvolvingService $service;

    public function boot(KernelEvolvingService $service)
    {
        $this->service = $service;
    }

    public function mount()
    {
        $this->detectSystem();
    }

    /**
     * Detect Docker, Docker Compose, GPU, and whether the API is already running.
     */
    public function detectSystem()
    {
        $this->dockerStatus = $this->service->detectDocker();
        $this->dockerComposeStatus = $this->service->detectDockerCompose();
        $this->gpuStatus = $this->service->detectGpu();
        $this->apiAlreadyRunning = $this->service->isHealthy();

        // If API is already running, skip ahead
        if ($this->apiAlreadyRunning && $this->step <= 2) {
            $this->step = 7;
            $this->started = true;
        }
    }

    public function nextStep()
    {
        if ($this->step < $this->totalSteps) {
            $this->step++;
        }
    }

    public function previousStep()
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    /**
     * Step 3: Clone the repo (bare-metal) or prepare Docker context.
     */
    public function installAgent()
    {
        $this->installing = true;
        $this->errorMessage = '';
        $this->installLog = '';

        try {
            if ($this->installMode === 'bare-metal') {
                // Clone the repo
                $cloneResult = $this->service->cloneRepo($this->installDir);
                $this->installLog .= $cloneResult['message'] . "\n";

                if (!$cloneResult['success']) {
                    $this->errorMessage = $cloneResult['error'] ?? $cloneResult['message'];
                    $this->installing = false;
                    return;
                }

                // Write .env
                $envResult = $this->service->writeEnvFile($this->installDir, [
                    'telegram_bot_token' => $this->telegramBotToken,
                    'telegram_chat_id' => $this->telegramChatId,
                    'user_name' => $this->userName,
                    'user_handle' => $this->userHandle,
                    'openai_api_key' => $this->openaiKey,
                    'anthropic_api_key' => $this->anthropicKey,
                    'github_token' => $this->githubToken,
                    'hf_token' => $this->hfToken,
                    'evolution_enabled' => $this->evolutionEnabled,
                ]);
                $this->installLog .= $envResult['message'] . "\n";

                // Write config.yaml
                $configResult = $this->service->writeConfigYaml($this->installDir, [
                    'vram' => $this->vram,
                    'default_model' => $this->defaultModel,
                ]);
                $this->installLog .= $configResult['message'] . "\n";

                // Run install.sh (creates venv, installs Python deps)
                $installResult = $this->service->runInstallScript($this->installDir);
                $this->installLog .= $installResult['message'] . "\n";
                if (isset($installResult['output'])) {
                    $this->installLog .= $installResult['output'] . "\n";
                }

                if (!$installResult['success']) {
                    $this->errorMessage = $installResult['error'] ?? $installResult['message'];
                    $this->installing = false;
                    return;
                }
            } else {
                // Docker mode — clone repo for the docker-compose context
                $cloneResult = $this->service->cloneRepo($this->dockerRepoDir);
                $this->installLog .= $cloneResult['message'] . "\n";

                if (!$cloneResult['success']) {
                    $this->errorMessage = $cloneResult['error'] ?? $cloneResult['message'];
                    $this->installing = false;
                    return;
                }
            }

            $this->installed = true;
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
            Log::error('Setup wizard install failed', ['error' => $e->getMessage()]);
        } finally {
            $this->installing = false;
        }
    }

    /**
     * Step 5: Start the agent (Docker or bare-metal).
     */
    public function startAgent()
    {
        $this->starting = true;
        $this->errorMessage = '';
        $this->startLog = '';

        try {
            if ($this->installMode === 'docker') {
                $result = $this->service->startDocker($this->dockerRepoDir);
            } else {
                $result = $this->service->startBareMetal($this->installDir);
            }

            $this->startLog .= $result['message'] . "\n";
            if (isset($result['output'])) {
                $this->startLog .= $result['output'] . "\n";
            }
            if (isset($result['error'])) {
                $this->startLog .= $result['error'] . "\n";
            }

            if ($result['success']) {
                $this->started = true;
                $this->pollHealth = true;
            } else {
                $this->errorMessage = $result['error'] ?? $result['message'];
            }
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
            Log::error('Setup wizard start failed', ['error' => $e->getMessage()]);
        } finally {
            $this->starting = false;
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
     * Step 6: Pair with kernel-central (placeholder — actual pairing via OAuth).
     */
    public function pairDevice()
    {
        // TODO: implement kernel-central OAuth flow
        $this->paired = true;
    }

    /**
     * Step 7: Finish — redirect to dashboard.
     */
    public function finish()
    {
        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.setup-wizard')
            ->layout('layouts.app');
    }
}
