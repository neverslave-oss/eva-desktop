<?php

namespace App\Livewire;

use App\Services\KernelCentralService;
use App\Services\KernelEvolvingService;
use App\Services\TunnelManagerService;
use App\Services\TunnelService;
use App\Models\AppSetting;
use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PacificDev\AiProviders\Services\ProviderCatalogService;

class Settings extends Component
{
    public string $providerTaskInference = 'openai';
    public string $providerSynthesis = 'openai';
    public string $providerCritic = 'openai';
    public string $providerPlanning = 'openai';
    public string $providerTrajectoryTeacher = 'openai';
    public string $providerTaskInferenceModel = '';
    public string $providerSynthesisModel = '';
    public string $providerCriticModel = '';
    public string $providerPlanningModel = '';
    public string $providerTrajectoryTeacherModel = '';
    // HF Router inference provider (maps to kernel-evolving providers.hf_provider / HF_ROUTER_PROVIDER).
    public string $hfProvider = 'deepinfra';
    public array $providerModelCatalog = [];
    public string $providerModelsStatus = '';
    public string $theme = 'dark';

    // XP3: API keys (pushed to kernel-evolving on save, never stored in this app)
    public string $openaiKey = '';
    public string $tmpOpenAiKey = '';
    public string $anthropicKey = '';
    public string $githubToken = '';
    public string $githubCopilotToken = '';
    public string $hfToken = '';
    public string $openRouterKey = '';

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

    // XP7: Local model management via kernel-evolving /models, /pull, /models/assign
    public array $kernelLocalModels = [];
    public array $kernelCuratedModels = [];
    public string $hubSearchQuery = '';
    public array $hubSearchResults = [];
    public string $modelsMsg = '';
    public string $activePullJob = '';
    public string $modelsPullStatus = '';

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
            $routing = Http::timeout(3)->get('http://127.0.0.1:8779/provider')->json();
            $r = $routing['routing'] ?? [];
            if (!empty($r['task_inference']['provider'])) $this->providerTaskInference = $r['task_inference']['provider'];
            if (!empty($r['synthesis']['provider']))      $this->providerSynthesis = $r['synthesis']['provider'];
            if (!empty($r['critic']['provider']))         $this->providerCritic = $r['critic']['provider'];
            if (!empty($r['planning']['provider']))       $this->providerPlanning = $r['planning']['provider'];
            if (!empty($r['trajectory_teacher']['provider'])) $this->providerTrajectoryTeacher = $r['trajectory_teacher']['provider'];

            if (!empty($r['task_inference']['model'])) $this->providerTaskInferenceModel = $r['task_inference']['model'];
            if (!empty($r['synthesis']['model']))      $this->providerSynthesisModel = $r['synthesis']['model'];
            if (!empty($r['critic']['model']))         $this->providerCriticModel = $r['critic']['model'];
            if (!empty($r['planning']['model']))       $this->providerPlanningModel = $r['planning']['model'];
            if (!empty($r['trajectory_teacher']['model'])) $this->providerTrajectoryTeacherModel = $r['trajectory_teacher']['model'];
        } catch (\Exception $e) {
            Log::debug('Settings: could not load provider routing: ' . $e->getMessage());
        }

        // Load HF Router provider from kernel-evolving config (providers.hf_provider)
        $this->hfProvider = AppSetting::get('hf_provider', 'deepinfra');

        // XP3: load API keys from DB
        $stored = AppSetting::many([
            'openai_key',
            'tmp_openai_key',
            'anthropic_key',
            'github_token',
            'github_copilot_token',
            'hf_token',
            'openrouter_key',
            'telegram_bot_token',
            'telegram_chat_id',
        ]);
        $this->openaiKey        = $stored['openai_key'] ?? '';
        if ($this->openaiKey === '') {
            $this->openaiKey = (string) (config('ai-providers.providers.openai.api_key')
                ?? config('ai-providers.openai.api_key')
                ?? env('OPENAI_API_KEY', ''));
        }

        $this->tmpOpenAiKey     = $stored['tmp_openai_key'] ?? '';
        if ($this->tmpOpenAiKey === '') {
            $this->tmpOpenAiKey = (string) env('TMP_OPEN_AI_API_KEY', '');
        }

        $this->anthropicKey     = $stored['anthropic_key'] ?? '';
        if ($this->anthropicKey === '') {
            $this->anthropicKey = (string) (config('ai-providers.providers.anthropic.api_key')
                ?? env('ANTHROPIC_API_KEY', ''));
        }

        $this->githubToken      = $stored['github_token'] ?? '';
        if ($this->githubToken === '') {
            $this->githubToken = (string) (config('ai-providers.providers.copilot.api_key')
                ?? env('GITHUB_TOKEN', ''));
        }

        $this->githubCopilotToken = $stored['github_copilot_token'] ?? '';
        if ($this->githubCopilotToken === '') {
            $this->githubCopilotToken = (string) env('GITHUB_COPILOT_TOKEN', '');
        }

        $this->hfToken          = $stored['hf_token'] ?? '';
        if ($this->hfToken === '') {
            $this->hfToken = (string) (config('ai-providers.providers.hf.api_key')
                ?? env('HF_TOKEN', ''));
        }

        $this->openRouterKey    = $stored['openrouter_key'] ?? '';
        if ($this->openRouterKey === '') {
            $this->openRouterKey = (string) (config('ai-providers.providers.openrouter.api_key')
                ?? env('OPENROUTER_API_KEY', ''));
        }
        $this->telegramBotToken = $stored['telegram_bot_token'] ?? '';
        $this->telegramChatId   = $stored['telegram_chat_id'] ?? '';

        $this->modelsRoot = AppSetting::get('models_root', $this->guessModelsRoot());
        $this->scanModels();
        $this->loadProviderModelCatalogs();

        $this->loadTunnelStatus();
    }

    public function updatedProviderTaskInference(string $provider): void
    {
        $this->providerTaskInferenceModel = $this->resolveModelSelection($provider, $this->providerTaskInferenceModel);
    }

    public function updatedProviderSynthesis(string $provider): void
    {
        $this->providerSynthesisModel = $this->resolveModelSelection($provider, $this->providerSynthesisModel);
    }

    public function updatedProviderCritic(string $provider): void
    {
        $this->providerCriticModel = $this->resolveModelSelection($provider, $this->providerCriticModel);
    }

    public function updatedProviderPlanning(string $provider): void
    {
        $this->providerPlanningModel = $this->resolveModelSelection($provider, $this->providerPlanningModel);
    }

    public function updatedProviderTrajectoryTeacher(string $provider): void
    {
        $this->providerTrajectoryTeacherModel = $this->resolveModelSelection($provider, $this->providerTrajectoryTeacherModel);
    }

    protected function resolveModelSelection(string $provider, string $current): string
    {
        $models = $this->getModelsForProvider($provider);
        if (empty($models)) {
            return $current;
        }

        return in_array($current, $models, true) ? $current : $models[0];
    }

    protected function loadProviderModelCatalogs(): void
    {
        $providers = $this->supportedProviders();

        foreach ($providers as $provider) {
            $this->providerModelCatalog[$provider] = $this->getModelsForProvider($provider);
        }

        $this->providerTaskInferenceModel = $this->resolveModelSelection($this->providerTaskInference, $this->providerTaskInferenceModel);
        $this->providerSynthesisModel = $this->resolveModelSelection($this->providerSynthesis, $this->providerSynthesisModel);
        $this->providerCriticModel = $this->resolveModelSelection($this->providerCritic, $this->providerCriticModel);
        $this->providerPlanningModel = $this->resolveModelSelection($this->providerPlanning, $this->providerPlanningModel);
        $this->providerTrajectoryTeacherModel = $this->resolveModelSelection($this->providerTrajectoryTeacher, $this->providerTrajectoryTeacherModel);
    }

    protected function supportedProviders(): array
    {
        return ['local', 'openai', 'anthropic', 'hf', 'copilot', 'openrouter', 'google', 'ollama'];
    }

    public function getModelsForProvider(string $provider): array
    {
        $provider = strtolower(trim($provider));

        if (isset($this->providerModelCatalog[$provider]) && is_array($this->providerModelCatalog[$provider])) {
            return $this->providerModelCatalog[$provider];
        }

        $models = [];

        // Prefer kernel-evolving catalog when available.
        try {
            $resp = Http::timeout(5)->get('http://127.0.0.1:8779/provider/models');
            if ($resp->ok()) {
                $catalog = $resp->json('models', []);
                $candidate = $catalog[$provider] ?? [];
                if (is_array($candidate)) {
                    foreach ($candidate as $model) {
                        if (is_string($model) && trim($model) !== '') {
                            $models[] = trim($model);
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Fallback to local package catalog below.
        }

        if (empty($models)) {
            try {
                if ($provider === 'local') {
                    config()->set('ai-providers.providers.local.models_root', $this->expandHome($this->modelsRoot));
                }

                /** @var ProviderCatalogService $catalog */
                $catalog = app('ai-providers.catalog');
                $models = $catalog->getProviderModels($provider);
            } catch (\Throwable $e) {
                Log::debug('Settings: provider catalog unavailable: ' . $e->getMessage());
            }
        }

        $this->providerModelCatalog[$provider] = array_values(array_unique($models));

        return $this->providerModelCatalog[$provider];
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

        unset($this->providerModelCatalog['local']);
        $this->providerModelCatalog['local'] = $this->getModelsForProvider('local');
        $this->providerTaskInferenceModel = $this->resolveModelSelection($this->providerTaskInference, $this->providerTaskInferenceModel);
        $this->providerSynthesisModel = $this->resolveModelSelection($this->providerSynthesis, $this->providerSynthesisModel);
        $this->providerCriticModel = $this->resolveModelSelection($this->providerCritic, $this->providerCriticModel);
        $this->providerPlanningModel = $this->resolveModelSelection($this->providerPlanning, $this->providerPlanningModel);
        $this->providerTrajectoryTeacherModel = $this->resolveModelSelection($this->providerTrajectoryTeacher, $this->providerTrajectoryTeacherModel);
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
     * XP7: Refresh locally downloaded models + curated catalog from kernel-evolving.
     */
    public function refreshKernelModels(): void
    {
        $this->modelsMsg = '';
        try {
            $resp = Http::timeout(5)->get('http://127.0.0.1:8779/models');
            $data = $resp->json() ?? [];
            $this->kernelLocalModels = $data['models'] ?? [];
            $this->kernelCuratedModels = $data['curated'] ?? [];
        } catch (\Exception $e) {
            $this->modelsMsg = 'Could not reach kernel-evolving on :8779 — ' . $e->getMessage();
        }
    }

    /**
     * XP7: Search HuggingFace Hub for models to pull.
     */
    public function searchHub(): void
    {
        $this->modelsMsg = '';
        $q = trim($this->hubSearchQuery);
        try {
            $resp = Http::timeout(10)->get('http://127.0.0.1:8779/hub/search', ['q' => $q, 'limit' => 20]);
            $data = $resp->json() ?? [];
            if (isset($data['error'])) {
                $this->modelsMsg = 'Hub search error: ' . $data['error'];
                $this->hubSearchResults = [];
                return;
            }
            $this->hubSearchResults = $data['models'] ?? [];
        } catch (\Exception $e) {
            $this->modelsMsg = 'Hub search failed — ' . $e->getMessage();
            $this->hubSearchResults = [];
        }
    }

    /**
     * XP7: Pull a model from HuggingFace Hub in the background.
     */
    public function pullModel(string $repoId): void
    {
        $this->modelsMsg = '';
        $this->activePullJob = '';
        $this->modelsPullStatus = "Pulling $repoId…";
        try {
            $resp = Http::timeout(5)->post('http://127.0.0.1:8779/pull', ['model' => $repoId]);
            $data = $resp->json() ?? [];
            if (isset($data['error'])) {
                $this->modelsMsg = 'Pull error: ' . $data['error'];
                $this->modelsPullStatus = '';
                return;
            }
            $this->activePullJob = $data['job_id'] ?? '';
            $this->pollPullJob();
        } catch (\Exception $e) {
            $this->modelsMsg = 'Pull request failed — ' . $e->getMessage();
            $this->modelsPullStatus = '';
        }
    }

    /**
     * XP7: Poll the active pull job for progress.
     */
    public function pollPullJob(): void
    {
        if ($this->activePullJob === '') {
            return;
        }
        try {
            $resp = Http::timeout(5)->get('http://127.0.0.1:8779/jobs/' . $this->activePullJob);
            $job = $resp->json() ?? [];
            $status = $job['status'] ?? 'unknown';
            $this->modelsPullStatus = "Pull " . ($job['model'] ?? '') . " → {$status}";
            if (in_array($status, ['succeeded', 'failed'], true)) {
                $this->modelsPullStatus .= $status === 'succeeded' ? ' ✅' : ' ❌ ' . ($job['error'] ?? '');
                $this->activePullJob = '';
                $this->refreshKernelModels();
            }
        } catch (\Exception $e) {
            $this->modelsPullStatus = 'Job poll failed — ' . $e->getMessage();
        }
    }

    /**
     * XP7: Assign a pulled local model to a named model_slots entry.
     */
    public function assignModel(string $repoId, string $slot): void
    {
        $this->modelsMsg = '';
        try {
            $resp = Http::timeout(5)->post('http://127.0.0.1:8779/models/assign', [
                'slot' => $slot,
                'repo_id' => $repoId,
            ]);
            $data = $resp->json() ?? [];
            if (isset($data['error'])) {
                $this->modelsMsg = 'Assign error: ' . $data['error'];
                return;
            }
            $this->modelsMsg = "Assigned $repoId → slot '$slot' ✅";
            $this->refreshKernelModels();
        } catch (\Exception $e) {
            $this->modelsMsg = 'Assign request failed — ' . $e->getMessage();
        }
    }

    /**
     * Save provider/appearance settings.
     */
    public function save(): void
    {
        // Push provider routing to kernel-evolving via /provider/set
        $modelOverrides = array_filter([
            'task_inference' => trim($this->providerTaskInferenceModel),
            'synthesis' => trim($this->providerSynthesisModel),
            'critic' => trim($this->providerCriticModel),
            'planning' => trim($this->providerPlanningModel),
            'trajectory_teacher' => trim($this->providerTrajectoryTeacherModel),
        ]);

        $providerPayload = [
            'task_inference' => $this->providerTaskInference,
            'synthesis'      => $this->providerSynthesis,
            'critic'         => $this->providerCritic,
            'planning'       => $this->providerPlanning,
            'trajectory_teacher' => $this->providerTrajectoryTeacher,
            'persist'        => true,
        ];
        // HF Router provider (providers.hf_provider) — only meaningful when hf is in use.
        $hfProvider = trim($this->hfProvider);
        if ($hfProvider !== '') {
            $providerPayload['hf_provider'] = $hfProvider;
        }
        if (!empty($modelOverrides)) {
            $providerPayload['model_override'] = $modelOverrides;
        }
        try {
            Http::timeout(5)
                ->post('http://127.0.0.1:8779/provider/set', $providerPayload);
        } catch (\Exception $e) {
            Log::warning('Settings: /provider/set failed: ' . $e->getMessage());
        }

        // XP3: push non-empty API keys to kernel-evolving and persist locally
        $keys = array_filter([
            'OPENAI_API_KEY'    => $this->openaiKey,
            'TMP_OPEN_AI_API_KEY' => $this->tmpOpenAiKey,
            'ANTHROPIC_API_KEY' => $this->anthropicKey,
            'GITHUB_TOKEN'      => $this->githubToken,
            'GITHUB_COPILOT_TOKEN' => $this->githubCopilotToken,
            'HF_TOKEN'          => $this->hfToken,
            'OPENROUTER_API_KEY'=> $this->openRouterKey,
        ]);
        if (!empty($keys)) {
            $this->evolvingService->updateProviderKeys($keys);
        }

        // Mirror to app_settings so fields survive a page reload.
        AppSetting::set('openai_key', $this->openaiKey);
        AppSetting::set('tmp_openai_key', $this->tmpOpenAiKey);
        AppSetting::set('anthropic_key', $this->anthropicKey);
        AppSetting::set('github_token', $this->githubToken);
        AppSetting::set('github_copilot_token', $this->githubCopilotToken);
        AppSetting::set('hf_token', $this->hfToken);
        AppSetting::set('openrouter_key', $this->openRouterKey);
        AppSetting::set('hf_provider', $hfProvider);

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
