<?php

namespace App\Livewire\Concerns;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages local model storage + model management UI state/methods for the
 * Settings screen (models-root browser, hub search, pull + assign).
 *
 * Extracted from Settings.php (issue #19 — split oversized Livewire components).
 */
trait ManagesLocalModels
{
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
}
