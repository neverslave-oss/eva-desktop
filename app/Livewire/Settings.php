<?php

namespace App\Livewire;

use App\Services\KernelCentralService;
use App\Services\KernelEvolvingService;
use App\Services\TunnelManagerService;
use App\Services\TunnelService;
use App\Models\AppSetting;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class Settings extends Component
{
    public string $providerTaskInference = 'openai';
    public string $providerSynthesis = 'openai';
    public string $providerCritic = 'openai';
    public string $theme = 'dark';

    // XP3: API keys (pushed to kernel-evolving on save, never stored in this app)
    public string $openaiKey = '';
    public string $anthropicKey = '';
    public string $githubToken = '';
    public string $hfToken = '';

    // Channels: Telegram
    public string $telegramBotToken = '';
    public string $telegramChatId = '';
    public string $telegramTestResult = '';
    public bool $telegramTestPassed = false;

    // XP6a: collective memory service URL
    public string $collectiveMemoryUrl = '';
    public string $collectiveMemoryTestResult = '';

    // Local model storage (HuggingFace hub-cache layout: models--org--repo)
    public string $modelsRoot = '';
    public string $modelsBrowsePath = '';
    public array $localModels = [];
    public array $modelsBreadcrumbs = [];
    public string $modelsScanMsg = '';

    // Auto-updater
    public string $updateChannel = 'latest';
    public string $updateFrequency = 'startup';
    public bool $checkingForUpdates = false;
    public string $updateStatus = '';

    // Pairing state
    public bool $paired = false;
    public ?int $deviceId = null;
    public ?string $tunnelState = 'disconnected';
    public ?string $lastConnectedAt = null;
    public ?string $centralUrl = '';

    // Pair form
    public string $pairCentralUrl = '';
    public string $pairToken = '';
    public string $pairSecret = '';
    public string $pairDeviceName = 'kernel-desktop';
    public bool $pairingInProgress = false;
    public ?string $pairError = null;

    protected KernelCentralService $centralService;
    protected KernelEvolvingService $evolvingService;
    protected ?TunnelService $tunnelService = null;

    public function boot(KernelCentralService $centralService, KernelEvolvingService $evolvingService)
    {
        $this->centralService = $centralService;
        $this->evolvingService = $evolvingService;
    }

    public function mount(): void
    {
        $this->paired = $this->centralService->isPaired();
        $this->deviceId = (int) config('kernel-desktop.device.id', 0);
        $this->centralUrl = config('kernel-desktop.central.url', '');
        $this->pairCentralUrl = $this->centralUrl;

        // XP6a: load collective memory URL from DB (set during wizard), fall back to env config
        $this->collectiveMemoryUrl = AppSetting::get('collective_memory_url', config('kernel-desktop.evolving.collective_memory_url', ''));

        // Load current provider routing from kernel-evolving
        try {
            $routing = \Illuminate\Support\Facades\Http::timeout(3)->get('http://127.0.0.1:8779/provider')->json();
            $r = $routing['routing'] ?? [];
            if (!empty($r['task_inference']['provider'])) $this->providerTaskInference = $r['task_inference']['provider'];
            if (!empty($r['synthesis']['provider']))      $this->providerSynthesis = $r['synthesis']['provider'];
            if (!empty($r['critic']['provider']))         $this->providerCritic = $r['critic']['provider'];
        } catch (\Exception $e) {
            Log::debug('Settings: could not load provider routing: ' . $e->getMessage());
        }

        // XP3: load API keys from DB
        $stored = AppSetting::many(['openai_key', 'anthropic_key', 'github_token', 'hf_token', 'telegram_bot_token', 'telegram_chat_id']);
        $this->openaiKey        = $stored['openai_key'] ?? '';
        $this->anthropicKey     = $stored['anthropic_key'] ?? '';
        $this->githubToken      = $stored['github_token'] ?? '';
        $this->hfToken          = $stored['hf_token'] ?? '';
        $this->telegramBotToken = $stored['telegram_bot_token'] ?? '';
        $this->telegramChatId   = $stored['telegram_chat_id'] ?? '';

        $this->modelsRoot = AppSetting::get('models_root', $this->guessModelsRoot());
        $this->scanModels();

        $this->loadTunnelStatus();
    }

    /**
     * Best-effort guess at the local HuggingFace hub cache directory.
     */
    protected function guessModelsRoot(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
        foreach ([getenv('HF_HOME'), $home ? $home . '/.cache/huggingface/hub' : null] as $candidate) {
            if ($candidate && is_dir($candidate)) {
                return $candidate;
            }
        }
        return $home ? $home . '/.cache/huggingface/hub' : '';
    }

    protected function expandHome(string $path): string
    {
        if (str_starts_with($path, '~')) {
            $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
            return $home . substr($path, 1);
        }
        return $path;
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }

    protected function dirSize(string $dir): int
    {
        $size = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) $size += $file->getSize();
            }
        } catch (\Exception $e) {
            // Unreadable subdirectory — skip.
        }
        return $size;
    }

    /**
     * Save the configured models directory and rescan it.
     */
    public function saveModelsRoot(): void
    {
        AppSetting::set('models_root', $this->modelsRoot);
        $this->modelsBrowsePath = '';
        $this->scanModels();
    }

    /**
     * Navigate into a subdirectory (relative to modelsRoot) and rescan.
     */
    public function browseModelsFolder(string $relPath): void
    {
        $this->modelsBrowsePath = trim($relPath, '/');
        $this->scanModels();
    }

    /**
     * Navigate up one directory level.
     */
    public function browseModelsUp(): void
    {
        $parts = array_filter(explode('/', $this->modelsBrowsePath));
        array_pop($parts);
        $this->modelsBrowsePath = implode('/', $parts);
        $this->scanModels();
    }

    /**
     * Scan the configured directory (plus any browsed sub-path) for locally
     * downloaded models. Recognises the HuggingFace hub cache layout
     * (models--org--repo) and falls back to listing plain subdirectories.
     */
    public function scanModels(): void
    {
        $this->modelsScanMsg = '';
        $this->localModels = [];

        $base = $this->expandHome($this->modelsRoot);
        $root = $this->modelsBrowsePath !== ''
            ? rtrim($base, '/') . '/' . $this->modelsBrowsePath
            : $base;

        if (! $root || ! is_dir($root)) {
            $this->modelsScanMsg = 'Directory not found.';
            $this->modelsBreadcrumbs = [];
            return;
        }

        // Build breadcrumb trail: [['label' => ..., 'path' => ...], ...]
        $this->modelsBreadcrumbs = [];
        $accum = [];
        foreach (array_filter(explode('/', $this->modelsBrowsePath)) as $segment) {
            $accum[] = $segment;
            $this->modelsBreadcrumbs[] = ['label' => $segment, 'path' => implode('/', $accum)];
        }

        $entries = @scandir($root) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $full = $root . DIRECTORY_SEPARATOR . $entry;
            if (! is_dir($full)) continue;

            $isModel = str_starts_with($entry, 'models--');
            $size = $this->dirSize($full);
            $label = $isModel ? str_replace('--', '/', substr($entry, 8)) : $entry;
            $relPath = $this->modelsBrowsePath !== '' ? $this->modelsBrowsePath . '/' . $entry : $entry;

            $this->localModels[] = [
                'name' => $label,
                'raw' => $entry,
                'rel_path' => $relPath,
                'is_model' => $isModel,
                'size_human' => $this->humanSize($size),
                'size' => $size,
                'modified' => file_exists($full) ? date('Y-m-d H:i', filemtime($full)) : '',
            ];
        }

        usort($this->localModels, fn($a, $b) => $b['size'] <=> $a['size']);

        if (empty($this->localModels)) {
            $this->modelsScanMsg = 'No folders found here.';
        }
    }

    /**
     * Reveal the models directory in the OS file manager (native runtime only).
     */
    public function openModelsFolder(): void
    {
        try {
            $base = $this->expandHome($this->modelsRoot);
            $target = $this->modelsBrowsePath !== '' ? rtrim($base, '/') . '/' . $this->modelsBrowsePath : $base;
            \Native\Desktop\Facades\Shell::showInFolder($target);
        } catch (\Throwable $e) {
            // Not running inside the NativePHP/Electron shell — nothing to open.
        }
    }

    /**
     * Save provider/appearance settings.
     */
    public function save(): void
    {
        // Push provider routing to kernel-evolving via /provider/set
        $providerPayload = [
            'task_inference' => $this->providerTaskInference,
            'synthesis'      => $this->providerSynthesis,
            'critic'         => $this->providerCritic,
            'persist'        => true,
        ];
        try {
            \Illuminate\Support\Facades\Http::timeout(5)
                ->post('http://127.0.0.1:8779/provider/set', $providerPayload);
        } catch (\Exception $e) {
            Log::warning('Settings: /provider/set failed: ' . $e->getMessage());
        }

        // XP3: push non-empty API keys to kernel-evolving and persist locally
        $keys = array_filter([
            'OPENAI_API_KEY'    => $this->openaiKey,
            'ANTHROPIC_API_KEY' => $this->anthropicKey,
            'GITHUB_TOKEN'      => $this->githubToken,
            'HF_TOKEN'          => $this->hfToken,
        ]);
        if (!empty($keys)) {
            $this->evolvingService->updateProviderKeys($keys);
            // Mirror to app_settings so fields survive a page reload
            AppSetting::set('openai_key', $this->openaiKey);
            AppSetting::set('anthropic_key', $this->anthropicKey);
            AppSetting::set('github_token', $this->githubToken);
            AppSetting::set('hf_token', $this->hfToken);
        }

        // XP6a: persist collective memory URL if set
        if (!empty($this->collectiveMemoryUrl)) {
            AppSetting::set('collective_memory_url', $this->collectiveMemoryUrl);
            $this->evolvingService->setCollectiveMemoryUrl($this->collectiveMemoryUrl);
        }

        // Persist update preferences
        AppSetting::set('update_channel', $this->updateChannel);
        AppSetting::set('update_frequency', $this->updateFrequency);

        // Channels: persist Telegram config
        AppSetting::set('telegram_bot_token', $this->telegramBotToken);
        AppSetting::set('telegram_chat_id', $this->telegramChatId);

        session()->flash('saved', true);
    }

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
                $this->telegramTestResult = "✓ Connected as @{$username}";
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

    public function checkForUpdates(): void
    {
        $this->checkingForUpdates = true;
        $this->updateStatus = '';
        try {
            \Native\Desktop\Facades\Updater::checkForUpdates();
            $this->updateStatus = 'Checking… you will be notified if an update is available.';
        } catch (\Exception $e) {
            $this->updateStatus = 'Update check failed: ' . $e->getMessage();
        } finally {
            $this->checkingForUpdates = false;
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
                $this->collectiveMemoryTestResult = 'Service returned HTTP ' . $response->status();
            }
        } catch (\Exception $e) {
            $this->collectiveMemoryTestResult = 'Unreachable: ' . $e->getMessage();
        }
    }

    /**
     * Initiate pairing with kernel-central.
     */
    public function initiatePair(): void
    {
        $this->pairError = null;
        $this->pairingInProgress = true;

        try {
            // Update config URL if changed
            if ($this->pairCentralUrl !== $this->centralUrl) {
                $this->updateCentralUrl($this->pairCentralUrl);
            }

            // Test connectivity first
            if (! $this->centralService->ping()) {
                $this->pairError = 'Cannot reach kernel-central at ' . $this->pairCentralUrl;
                $this->pairingInProgress = false;

                return;
            }

            // Confirm pairing using pair_token + pair_secret from kernel-central → Pair a Device
            $confirmResult = $this->centralService->confirm(
                $this->pairToken,
                $this->pairSecret
            );

            if (! $confirmResult['success']) {
                $this->pairError = $confirmResult['error'] ?? 'Confirmation failed';
                $this->pairingInProgress = false;

                return;
            }

            // Store the device token and ID
            $this->centralService->storeToken($confirmResult['token']);
            $this->deviceId = $confirmResult['device_id'];

            // Persist device ID to config
            $this->persistDeviceId($this->deviceId);
            $this->paired = true;

            Log::info('Device paired with kernel-central', ['device_id' => $this->deviceId]);

            // Auto-start tunnel now that pairing is complete
            app(TunnelManagerService::class)->start();
        } catch (\Exception $e) {
            $this->pairError = $e->getMessage();
            Log::error('Pairing error', ['error' => $e->getMessage()]);
        }

        $this->pairingInProgress = false;
    }

    /**
     * Unpair from kernel-central.
     */
    public function unpair(): void
    {
        // Clear stored token
        $this->centralService->clearToken();
        $this->paired = false;
        $this->deviceId = null;
        $this->tunnelState = 'disconnected';

        Log::info('Device unpaired from kernel-central');
    }

    /**
     * Poll current tunnel status for the UI.
     */
    public function refreshTunnelStatus(): void
    {
        $this->loadTunnelStatus();
    }

    /**
     * Load the tunnel status from the cached status file.
     */
    protected function loadTunnelStatus(): void
    {
        $statusPath = storage_path('app/tunnel-status.json');

        if (file_exists($statusPath)) {
            try {
                $status = json_decode(file_get_contents($statusPath), true);

                if ($status && isset($status['state'])) {
                    $this->tunnelState = $status['state'];
                    $this->lastConnectedAt = $status['connected_at'] ?? null;
                }
            } catch (\Exception $e) {
                // Ignore corrupt status file
            }
        }
    }

    /**
     * Update the kernel-central URL config.
     */
    protected function updateCentralUrl(string $url): void
    {
        // Write to a config override file
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $content = file_get_contents($envPath);
            $pattern = '/^KERNEL_CENTRAL_URL=.*/m';
            $replacement = 'KERNEL_CENTRAL_URL=' . $url;

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            } else {
                $content .= "\nKERNEL_CENTRAL_URL={$url}\n";
            }

            file_put_contents($envPath, $content);
        }
    }

    /**
     * Persist the device ID to a local config file.
     */
    protected function persistDeviceId(int $deviceId): void
    {
        $configPath = config('kernel-desktop.device.token_path', storage_path('app/device-token.txt'));
        $idPath = dirname($configPath) . '/device-id.txt';
        file_put_contents($idPath, (string) $deviceId);

        // Also set runtime config
        config(['kernel-desktop.device.id' => $deviceId]);
    }

    public function render()
    {
        return view('livewire.settings')
            ->layout('layouts.app');
    }
}
