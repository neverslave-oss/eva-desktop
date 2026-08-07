<?php

namespace App\Livewire;

use App\Services\KernelCentralService;
use App\Services\KernelEvolvingService;
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

    // XP6a: collective memory service URL
    public string $collectiveMemoryUrl = '';
    public string $collectiveMemoryTestResult = '';

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
    public string $pairApiToken = '';
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

        // XP3: load API keys from DB
        $stored = AppSetting::many(['openai_key', 'anthropic_key', 'github_token', 'hf_token']);
        $this->openaiKey = $stored['openai_key'] ?? '';
        $this->anthropicKey = $stored['anthropic_key'] ?? '';
        $this->githubToken = $stored['github_token'] ?? '';
        $this->hfToken = $stored['hf_token'] ?? '';

        $this->loadTunnelStatus();
    }

    /**
     * Save provider/appearance settings.
     */
    public function save(): void
    {
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

        session()->flash('saved', true);
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

            // Initiate device pairing
            $result = $this->centralService->pair(
                $this->pairDeviceName ?: 'kernel-desktop',
                config('kernel-desktop.device.type', 'desktop'),
                $this->pairApiToken
            );

            if (! $result['success']) {
                $this->pairError = $result['error'] ?? 'Pairing failed';
                $this->pairingInProgress = false;

                return;
            }

            // Confirm pairing with the returned pair_token and pair_secret
            $confirmResult = $this->centralService->confirm(
                $result['pair_token'],
                $result['pair_secret']
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
