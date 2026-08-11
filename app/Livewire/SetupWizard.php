<?php

namespace App\Livewire;

use App\Services\KernelCentralService;
use App\Services\KernelEvolvingService;
use App\Models\AppSetting;
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

    // XP2: model cache location
    public string $modelsPath = '';

    // Docker workspace mount — host path for /app/workspace (chat history, evolution DB, trajectories)
    public string $workspacePath = '';

    // XP6a: collective memory service
    public string $collectiveMemoryUrl = '';
    public string $collectiveMemoryTestResult = '';

    // XP4: Telegram connection test
    public string $telegramTestResult = '';
    public bool $telegramTestPassed = false;

    // Progress tracking
    public bool $installing = false;
    public bool $installed = false;
    public bool $starting = false;
    public bool $started = false;
    public bool $paired = false;
    public string $installLog = '';
    public string $startLog = '';
    public string $errorMessage = '';

    // Streaming log file paths for async operations
    public string $installLogFile = '/tmp/kernel_install.log';
    public string $startLogFile = '/tmp/kernel_start.log';
    public bool $pollInstall = false;
    public bool $pollStart = false;
    public int $startPollCount = 0; // tracks elapsed polls for model download hint

    // Polling
    public bool $pollHealth = false;

    // Kernel-central pairing
    public string $centralUrl = '';
    public string $apiToken = '';
    public string $pairingLabel = '';
    public bool $pairing = false;
    public bool $pairingError = false;

    protected KernelEvolvingService $service;
    protected KernelCentralService $central;

    public function boot(KernelEvolvingService $service, KernelCentralService $central)
    {
        $this->service = $service;
        $this->central = $central;
    }

    public function mount()
    {
        $this->centralUrl = AppSetting::get('central_url', config('kernel-desktop.central.url', ''));
        $this->modelsPath = AppSetting::get('models_path', config('kernel-desktop.evolving.models_path', ''));
        $this->workspacePath = AppSetting::get('workspace_path', '~/.kernel-evolving/workspace');
        $this->collectiveMemoryUrl = AppSetting::get('collective_memory_url', config('kernel-desktop.evolving.collective_memory_url', ''));

        $stored = AppSetting::many(['telegram_bot_token', 'telegram_chat_id', 'user_name', 'user_handle', 'openai_key', 'anthropic_key', 'github_token', 'hf_token']);
        
        $this->telegramBotToken = $stored['telegram_bot_token'] ?? '';
        $this->telegramChatId   = $stored['telegram_chat_id'] ?? '';
        $this->userName         = $stored['user_name'] ?? '';
        $this->userHandle       = $stored['user_handle'] ?? '';
        $this->openaiKey        = $stored['openai_key'] ?? '';
        $this->anthropicKey     = $stored['anthropic_key'] ?? '';
        $this->githubToken      = $stored['github_token'] ?? '';
        $this->hfToken          = $stored['hf_token'] ?? '';

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

    // XP4: reset test state when token changes
    public function updatedTelegramBotToken(): void
    {
        $this->telegramTestPassed = false;
        $this->telegramTestResult = '';
    }

    // XP4: validate bot token against Telegram API (server-side, token never sent to JS)
    public function testTelegramConnection(): void
    {
        $token = trim($this->telegramBotToken);
        if (empty($token)) {
            $this->telegramTestResult = 'Enter a bot token first.';
            $this->telegramTestPassed = false;
            return;
        }
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(8)
                ->get("https://api.telegram.org/bot{$token}/getMe");
            if ($response->ok() && $response->json('ok') === true) {
                $username = $response->json('result.username', 'unknown');
                $this->telegramTestResult = "\u2713 Connected as @{$username}";
                $this->telegramTestPassed = true;
            } else {
                $this->telegramTestResult = 'Invalid token — Telegram rejected it.';
                $this->telegramTestPassed = false;
            }
        } catch (\Exception $e) {
            $this->telegramTestResult = 'Connection failed: ' . $e->getMessage();
            $this->telegramTestPassed = false;
        }
    }

    // XP6a: test collective memory service reachability
    public function testCollectiveMemory(): void
    {
        $url = rtrim(trim($this->collectiveMemoryUrl), '/');
        if (empty($url)) {
            $this->collectiveMemoryTestResult = 'Enter a URL first.';
            return;
        }
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get("{$url}/health");
            if ($response->ok()) {
                $data = $response->json();
                $status = $data['status'] ?? 'unknown';
                $model = ($data['model_loaded'] ?? false) ? 'model loaded' : 'no model';
                $this->collectiveMemoryTestResult = "\u2713 Reachable — {$status}, {$model}";
            } else {
                $this->collectiveMemoryTestResult = "Service returned HTTP {$response->status()}";
            }
        } catch (\Exception $e) {
            $this->collectiveMemoryTestResult = 'Unreachable: ' . $e->getMessage();
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
     * Runs synchronous pre-flight steps (clone + write config) inline,
     * then fires install.sh in the background and returns immediately.
     */
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
    public function pairDevice()
    {
        $this->pairing = true;
        $this->pairingError = false;
        $this->errorMessage = '';
        $this->pairingLabel = 'Connecting to kernel-central…';

        try {
            // Step 1: Validate inputs
            $url = trim($this->centralUrl);
            $token = trim($this->apiToken);

            if (empty($url)) {
                throw new \RuntimeException('Please enter the kernel-central URL.');
            }
            if (empty($token)) {
                throw new \RuntimeException('Please enter an API token from kernel-central Settings → API Tokens.');
            }

            // Update config runtime so KernelCentralService uses the user's URL
            config(['kernel-desktop.central.url' => rtrim($url, '/')]);

            // Step 2: Check connectivity
            $this->pairingLabel = 'Checking connectivity…';
            if (! $this->central->ping()) {
                throw new \RuntimeException('Could not reach kernel-central at ' . $url . '. Make sure the URL is correct and the server is running.');
            }

            // Step 3: Initiate pairing
            $this->pairingLabel = 'Initiating device pairing…';
            $deviceName = config('kernel-desktop.device.name', 'kernel-desktop');
            $deviceType = config('kernel-desktop.device.type', 'desktop');

            $pairResult = $this->central->pair($deviceName, $deviceType, $token);

            if (! $pairResult['success']) {
                $apiUrl = rtrim($url, '/') . '/api/tokens';
                throw new \RuntimeException(
                    'Pairing failed: ' . ($pairResult['error'] ?? 'Unknown error') .
                    '. Verify your API token is valid by visiting ' . $apiUrl
                );
            }

            // Step 4: Confirm pairing
            $this->pairingLabel = 'Confirming device pairing…';
            $confirmResult = $this->central->confirm(
                $pairResult['pair_token'],
                $pairResult['pair_secret']
            );

            if (! $confirmResult['success']) {
                throw new \RuntimeException(
                    'Pairing confirmation failed: ' . ($confirmResult['error'] ?? 'Unknown error')
                );
            }

            // Step 5: Store the Sanctum token locally
            $this->central->storeToken($confirmResult['token']);

            $this->paired = true;
            $this->pairingLabel = '✅ Device paired successfully!';
        } catch (\RuntimeException $e) {
            $this->pairingError = true;
            $this->errorMessage = $e->getMessage();
            $this->pairingLabel = '';
            Log::error('Device pairing failed', ['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->pairingError = true;
            $this->errorMessage = 'An unexpected error occurred: ' . $e->getMessage();
            $this->pairingLabel = '';
            Log::error('Device pairing exception', ['error' => $e->getMessage()]);
        } finally {
            $this->pairing = false;
        }
    }

    /**
     * Step 7: Finish — redirect to dashboard.
     */
    public function finish()
    {
        AppSetting::set('central_url', $this->centralUrl);
        AppSetting::set('models_path', $this->modelsPath);
        AppSetting::set('workspace_path', $this->workspacePath);
        AppSetting::set('install_mode', $this->installMode === 'docker' ? 'docker' : 'baremetal');
        AppSetting::set('install_dir', $this->installMode === 'docker' ? $this->dockerRepoDir : $this->installDir);
        AppSetting::set('collective_memory_url', $this->collectiveMemoryUrl);
        AppSetting::set('telegram_bot_token', $this->telegramBotToken);
        AppSetting::set('telegram_chat_id', $this->telegramChatId);
        AppSetting::set('user_name', $this->userName);
        AppSetting::set('user_handle', $this->userHandle);
        AppSetting::set('openai_key', $this->openaiKey);
        AppSetting::set('anthropic_key', $this->anthropicKey);
        AppSetting::set('github_token', $this->githubToken);
        AppSetting::set('hf_token', $this->hfToken);

        // XP6a: persist collective memory URL to kernel-evolving config if set
        if (!empty($this->collectiveMemoryUrl)) {
            $this->service->setCollectiveMemoryUrl($this->collectiveMemoryUrl);
        }
        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.setup-wizard')
            ->layout('layouts.app');
    }
}
