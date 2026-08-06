<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * KernelEvolvingService
 *
 * Wraps the kernel-evolving agent's installation and lifecycle commands.
 * Mirrors the scripts in kernel-evolving/: install.sh, start.sh, deploy/docker-compose.yml.
 *
 * Two install modes are supported:
 *  - Docker (default): uses deploy/docker-compose.yml to run the agent in a container
 *  - Bare metal: clones the repo, runs install.sh, then start.sh as a background process
 */
class KernelEvolvingService
{
    /** Default repo URL for bare-metal installs */
    public const REPO_URL = 'https://github.com/fabiopacifici-bot/kernel-evolving.git';

    /** Default install directory for bare-metal installs */
    public const DEFAULT_INSTALL_DIR = '~/.kernel-evolving';

    /** API port (matches kernel-evolving config.yaml) */
    public const PORT = 8779;

    /** Health check endpoint */
    public const HEALTH_URL = 'http://localhost:8779/health';

    /** Docker container name */
    public const CONTAINER_NAME = 'kernel-evolving';

    /**
     * Detect whether Docker is installed and the daemon is running.
     */
    public function detectDocker(): array
    {
        $versionResult = Process::run(['docker', '--version']);
        $infoResult = Process::run(['docker', 'info', '--format', '{{.ServerVersion}}']);

        $installed = $versionResult->successful() && str_contains($versionResult->output(), 'Docker');
        $running = $infoResult->successful() && !empty(trim($infoResult->output()));

        return [
            'installed' => $installed,
            'running' => $running,
            'version' => trim($versionResult->output()),
            'server_version' => trim($infoResult->output()),
        ];
    }

    /**
     * Detect whether Docker Compose is available (v2 plugin or standalone).
     */
    public function detectDockerCompose(): array
    {
        $result = Process::run(['docker', 'compose', 'version']);
        $available = $result->successful() && str_contains($result->output(), 'Compose');

        if (!$available) {
            // Try standalone docker-compose
            $result2 = Process::run(['docker-compose', 'version']);
            $available = $result2->successful();
        }

        return [
            'available' => $available,
            'version' => trim($result->output()),
        ];
    }

    /**
     * Detect whether nvidia-smi is available (GPU present for local inference).
     */
    public function detectGpu(): array
    {
        $result = Process::run(['nvidia-smi', '--query-gpu=name,memory.total', '--format=csv,noheader']);
        $available = $result->successful();

        $name = 'Unknown';
        $vramMb = 0;
        if ($available) {
            $line = trim($result->output());
            $parts = str_getcsv($line);
            $name = trim($parts[0] ?? 'Unknown');
            $vramStr = trim($parts[1] ?? '0');
            // Parse "8192 MiB" → 8192
            if (preg_match('/(\d+)\s*MiB/i', $vramStr, $m)) {
                $vramMb = (int) $m[1];
            }
        }

        return [
            'available' => $available,
            'name' => $name,
            'vram_mb' => $vramMb,
            'vram_gb' => round($vramMb / 1024, 1),
        ];
    }

    /**
     * Check if the kernel-evolving API is already running and healthy.
     */
    public function isHealthy(): bool
    {
        $result = Process::run(['curl', '-sf', '-m', '3', self::HEALTH_URL]);
        return $result->successful();
    }

    /**
     * Check if the kernel-evolving container is already running (Docker mode).
     */
    public function isContainerRunning(): bool
    {
        $result = Process::run(['docker', 'inspect', '-f', '{{.State.Running}}', self::CONTAINER_NAME]);
        return $result->successful() && trim($result->output()) === 'true';
    }

    /**
     * Clone the kernel-evolving repo for bare-metal install.
     */
    public function cloneRepo(string $installDir): array
    {
        $expandedDir = $this->expandPath($installDir);

        if (File::exists($expandedDir . '/.git')) {
            return ['success' => true, 'message' => 'Repository already cloned at ' . $expandedDir, 'path' => $expandedDir];
        }

        File::ensureDirectoryExists(dirname($expandedDir));

        $result = Process::run(['git', 'clone', self::REPO_URL, $expandedDir]);

        return [
            'success' => $result->successful(),
            'message' => $result->successful() ? 'Repository cloned to ' . $expandedDir : $result->errorOutput(),
            'path' => $expandedDir,
            'error' => $result->successful() ? null : $result->errorOutput(),
        ];
    }

    /**
     * Run the kernel-evolving install.sh script (bare-metal mode).
     * This creates a venv, installs Python deps, and sets up .env.
     */
    public function runInstallScript(string $installDir): array
    {
        $expandedDir = $this->expandPath($installDir);
        $installScript = $expandedDir . '/install.sh';

        if (!File::exists($installScript)) {
            return ['success' => false, 'message' => 'install.sh not found at ' . $installScript, 'error' => 'Not found'];
        }

        // Run install.sh — it creates venv, installs requirements, sets up .env
        $result = Process::path($expandedDir)->run(['bash', 'install.sh']);

        return [
            'success' => $result->successful(),
            'message' => $result->successful() ? 'Install script completed' : 'Install script failed',
            'output' => $result->output(),
            'error' => $result->errorOutput(),
        ];
    }

    /**
     * Write the .env file with user-provided configuration (bare-metal mode).
     * Mirrors kernel-evolving/.env.example structure.
     */
    public function writeEnvFile(string $installDir, array $config): array
    {
        $expandedDir = $this->expandPath($installDir);
        $envPath = $expandedDir . '/.env';

        $lines = [
            '# Kernel-Evolving — generated by Kernel Desktop Setup Wizard',
            '# Generated: ' . now()->toIso8601String(),
            '',
            '# === Telegram (required for bot) ===',
            'KERNEL_EVO_TELEGRAM_BOT_TOKEN=' . ($config['telegram_bot_token'] ?? 'your_bot_token_here'),
            'KERNEL_EVO_TELEGRAM_CHAT_ID=' . ($config['telegram_chat_id'] ?? 'your_chat_id_here'),
            'KERNEL_USER_NAME=' . ($config['user_name'] ?? 'KernelUser'),
            'KERNEL_USER_HANDLE=' . ($config['user_handle'] ?? 'kerneluser'),
            '',
            '# === AI Providers ===',
            'OPENAI_API_KEY=' . ($config['openai_api_key'] ?? ''),
            'ANTHROPIC_API_KEY=' . ($config['anthropic_api_key'] ?? ''),
            'GITHUB_TOKEN=' . ($config['github_token'] ?? ''),
            'HF_TOKEN=' . ($config['hf_token'] ?? ''),
            '',
            '# === Evolution ===',
            'EVOLUTION_ENABLED=' . ($config['evolution_enabled'] ? 'true' : 'false'),
            '',
            '# === Model cache (optional) ===',
            '# Uncomment to override where Hugging Face models are stored.',
            ($config['models_path'] ?? '') !== ''
                ? 'HF_HOME=' . ($config['models_path'])
                : '# HF_HOME=~/.cache/huggingface',
            '',
            '# === Collective memory service (optional) ===',
            ($config['collective_memory_url'] ?? '') !== ''
                ? 'COLLECTIVE_MEMORY_URL=' . ($config['collective_memory_url'])
                : '# COLLECTIVE_MEMORY_URL=http://<host>:8010',
        ];

        File::put($envPath, implode("\n", $lines) . "\n");

        return [
            'success' => true,
            'message' => '.env written to ' . $envPath,
            'path' => $envPath,
        ];
    }

    /**
     * Public wrapper so Livewire can resolve ~ paths without shell_exec.
     */
    public function expandInstallPath(string $path): string
    {
        return $this->expandPath($path);
    }

    /**
     * Public wrapper for writeDockerEnvFile used by the async start flow.
     */
    public function writeDockerEnvFilePublic(string $envPath, array $config = []): void
    {
        $this->writeDockerEnvFile($envPath, $config);
    }

    /**
     * XP6a: Write collective_memory.url into kernel-evolving's config.yaml.
     * Reads the current config.yaml, sets the url key, writes it back.
     */
    public function setCollectiveMemoryUrl(string $url): array
    {
        $installDir = config('kernel-desktop.evolving.install_dir', '~/.kernel-evolving');
        $configPath = $this->expandPath($installDir) . '/config.yaml';

        if (!File::exists($configPath)) {
            return ['success' => false, 'message' => 'config.yaml not found at ' . $configPath];
        }

        try {
            $content = File::get($configPath);
            // Replace or insert collective_memory.url
            if (preg_match('/^collective_memory:/m', $content)) {
                $content = preg_replace(
                    '/(collective_memory:\s*\n\s*url:\s*)\S+/m',
                    '${1}' . $url,
                    $content
                );
            } else {
                $content .= "\ncollective_memory:\n  url: {$url}\n";
            }
            File::put($configPath, $content);
            return ['success' => true, 'message' => 'collective_memory.url updated in config.yaml'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * XP3: Update provider API keys in kernel-evolving via its /config endpoint.
     * $keys is an associative array of env-var-name => value.
     */
    public function updateProviderKeys(array $keys): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->post(self::BASE_URL . '/config/env', ['keys' => $keys]);
            if ($response->successful()) {
                return ['success' => true, 'message' => 'Provider keys updated'];
            }
            return ['success' => false, 'message' => 'API returned HTTP ' . $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Adjusts model path, VRAM, and provider settings based on user config.
     */
    public function writeConfigYaml(string $installDir, array $config): array
    {
        $expandedDir = $this->expandPath($installDir);
        $configPath = $expandedDir . '/config.yaml';

        // Read the existing config.yaml as a base
        if (!File::exists($configPath)) {
            return ['success' => false, 'message' => 'config.yaml not found', 'error' => 'Not found'];
        }

        $yaml = File::get($configPath);

        // Adjust GPU memory utilization based on VRAM selection
        $vramGb = (int) ($config['vram'] ?? 8);
        $gpuUtil = match ($vramGb) {
            4 => 0.45,
            8 => 0.65,
            12, 16 => 0.75,
            24 => 0.85,
            default => 0.65,
        };
        $yaml = preg_replace('/gpu_memory_utilization:\s*[\d.]+/', "gpu_memory_utilization: {$gpuUtil}", $yaml);

        // Set task_inference provider
        $taskProvider = ($config['default_model'] ?? 'local') === 'cloud' ? 'openai' : 'local';
        $yaml = preg_replace('/task_inference:\s*\w+/', "task_inference: {$taskProvider}", $yaml);

        File::put($configPath, $yaml);

        return [
            'success' => true,
            'message' => 'config.yaml updated',
            'path' => $configPath,
        ];
    }

    /**
     * Start kernel-evolving in bare-metal mode using start.sh.
     * Runs as a background process (nohup).
     */
    public function startBareMetal(string $installDir): array
    {
        $expandedDir = $this->expandPath($installDir);
        $startScript = $expandedDir . '/start.sh';

        if (!File::exists($startScript)) {
            return ['success' => false, 'message' => 'start.sh not found', 'error' => 'Not found'];
        }

        // Kill any existing instance first
        Process::run(['fuser', '-k', self::PORT . '/tcp']);

        // Start via nohup — start.sh handles model server + API
        $logFile = '/tmp/kernel_evolving_startup.log';
        $result = Process::path($expandedDir)->run([
            'bash', '-c',
            "nohup bash start.sh > {$logFile} 2>&1 &",
        ]);

        // Wait for health check (up to 60s — model loading takes time)
        $healthy = $this->waitForHealth(60);

        return [
            'success' => $healthy,
            'message' => $healthy ? 'kernel-evolving started on port ' . self::PORT : 'Health check timed out',
            'log_file' => $logFile,
            'error' => $healthy ? null : 'API did not become healthy within 60s. Check ' . $logFile,
        ];
    }

    /**
     * Build and start kernel-evolving via Docker Compose.
     * Uses the deploy/docker-compose.yml from the repo.
     */
    public function startDocker(?string $repoDir = null, array $config = []): array
    {
        // If no repo dir given, clone to a temp location
        if ($repoDir) {
            $expandedDir = $this->expandPath($repoDir);
        } else {
            // Use the deploy directory from a cloned repo
            $expandedDir = $this->expandPath(self::DEFAULT_INSTALL_DIR);
        }

        $deployDir = $expandedDir . '/deploy';

        if (!File::exists($deployDir . '/docker-compose.yml')) {
            // Clone the repo first
            $cloneResult = $this->cloneRepo($expandedDir);
            if (!$cloneResult['success']) {
                return $cloneResult;
            }
        }

        // Write .env in the deploy directory for docker-compose
        $envPath = $deployDir . '/.env';
        $this->writeDockerEnvFile($envPath, $config);

        // Build and start with docker compose
        $result = Process::path($deployDir)->run([
            'docker', 'compose', 'up', '-d', '--build',
        ]);

        $success = $result->successful();

        // Wait for health check
        $healthy = false;
        if ($success) {
            $healthy = $this->waitForHealth(90); // Docker build + model load takes longer
        }

        return [
            'success' => $healthy,
            'message' => $healthy ? 'kernel-evolving container started on port ' . self::PORT : 'Container started but health check failed',
            'output' => $result->output(),
            'error' => $result->errorOutput(),
            'log_file' => '/tmp/kernel_evolving_docker.log',
        ];
    }

    /**
     * Write the .env file for Docker Compose (in deploy/ directory).
     */
    protected function writeDockerEnvFile(string $path, array $config = []): void
    {
        $lines = [
            '# Kernel-Evolving Docker — generated by Kernel Desktop Setup Wizard',
            '# Generated: ' . now()->toIso8601String(),
            '',
            'KERNEL_EVO_TELEGRAM_BOT_TOKEN=' . ($config['telegram_bot_token'] ?? 'your_bot_token_here'),
            'KERNEL_EVO_TELEGRAM_CHAT_ID=' . ($config['telegram_chat_id'] ?? 'your_chat_id_here'),
            'KERNEL_USER_NAME=' . ($config['user_name'] ?? 'KernelUser'),
            'KERNEL_USER_HANDLE=' . ($config['user_handle'] ?? 'kerneluser'),
            '',
            'OPENAI_API_KEY=' . ($config['openai_api_key'] ?? ''),
            'ANTHROPIC_API_KEY=' . ($config['anthropic_api_key'] ?? ''),
            'GITHUB_TOKEN=' . ($config['github_token'] ?? ''),
            'HF_TOKEN=' . ($config['hf_token'] ?? ''),
            '',
            'EVOLUTION_ENABLED=' . (($config['evolution_enabled'] ?? true) ? 'true' : 'false'),
            '',
            // docker-compose.yml volume: ${MODELS_PATH:-~/.cache/huggingface}:/models
            ($config['models_path'] ?? '') !== ''
                ? 'MODELS_PATH=' . $config['models_path']
                : '# MODELS_PATH=~/.cache/huggingface',
            ($config['collective_memory_url'] ?? '') !== ''
                ? 'COLLECTIVE_MEMORY_URL=' . $config['collective_memory_url']
                : '# COLLECTIVE_MEMORY_URL=',
        ];
        File::put($path, implode("\n", $lines) . "\n");
    }

    /**
     * Stop the kernel-evolving container (Docker mode).
     */
    public function stopDocker(): array
    {
        $result = Process::run(['docker', 'stop', self::CONTAINER_NAME]);
        return [
            'success' => $result->successful(),
            'message' => $result->successful() ? 'Container stopped' : $result->errorOutput(),
        ];
    }

    /**
     * Stop kernel-evolving in bare-metal mode.
     */
    public function stopBareMetal(): array
    {
        // Kill the API process on port 8779
        Process::run(['fuser', '-k', self::PORT . '/tcp']);
        // Kill the model server
        Process::run(['pkill', '-f', 'model_server.py']);

        return [
            'success' => true,
            'message' => 'kernel-evolving stopped',
        ];
    }

    /**
     * Get container logs (Docker mode).
     */
    public function getDockerLogs(int $lines = 50): string
    {
        $result = Process::run(['docker', 'logs', '--tail', (string) $lines, self::CONTAINER_NAME]);
        return $result->output() . $result->errorOutput();
    }

    /**
     * Get bare-metal logs.
     */
    public function getBareMetalLogs(): string
    {
        $logFile = '/tmp/kernel_evolving_api.log';
        if (File::exists($logFile)) {
            return File::get($logFile);
        }
        return 'No log file found at ' . $logFile;
    }

    /**
     * Wait for the API to become healthy.
     */
    public function waitForHealth(int $timeoutSeconds = 60): bool
    {
        $start = time();
        while (time() - $start < $timeoutSeconds) {
            if ($this->isHealthy()) {
                return true;
            }
            sleep(2);
        }
        return false;
    }

    /**
     * Expand ~ in paths.
     */
    protected function expandPath(string $path): string
    {
        if (str_starts_with($path, '~')) {
            return str_replace('~', $_SERVER['HOME'] ?? getenv('HOME'), $path);
        }
        return $path;
    }

    /**
     * Get the system status summary for the dashboard.
     */
    public function getSystemStatus(): array
    {
        $docker = $this->detectDocker();
        $gpu = $this->detectGpu();
        $healthy = $this->isHealthy();
        $containerRunning = $this->isContainerRunning();

        return [
            'api_healthy' => $healthy,
            'container_running' => $containerRunning,
            'docker' => $docker,
            'gpu' => $gpu,
            'port' => self::PORT,
            'health_url' => self::HEALTH_URL,
        ];
    }
}
