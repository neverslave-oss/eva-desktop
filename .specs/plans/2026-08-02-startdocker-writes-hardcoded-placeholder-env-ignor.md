---
date: 2026-08-02
agent: scout
topic: startDocker() writes hardcoded placeholder .env, ignoring all SetupWizard user config
severity: high
tags: [scout, agent-ready]
status: open
---

# startDocker() writes hardcoded placeholder .env, ignoring all SetupWizard user config

Root cause: In KernelEvolvingService::startDocker() (line 309-311), if the deploy/.env file does not exist it calls $this->writeDockerEnvFile($envPath) with no arguments. writeDockerEnvFile() (lines 339-355) hard-codes 'your_bot_token_here', empty API keys, and 'KernelUser' placeholders. None of the actual values collected by SetupWizard (telegramBotToken, openaiKey, anthropicKey, userName, userHandle, etc.) are forwarded into the Docker path.

By contrast, the bare-metal path at SetupWizard.php:131-148 correctly calls writeEnvFile() with all user config. The Docker path silently ships with unusable placeholder credentials, so Telegram bot, OpenAI, and other integrations will fail at runtime.

Affected files: app/Services/KernelEvolvingService.php:288-355, app/Livewire/SetupWizard.php:163-173.

Suggested fix: Change startDocker() signature to accept an optional config array and pass it through:

  public function startDocker(?string $repoDir = null, array $config = []): array

Replace the writeDockerEnvFile() call with writeEnvFile($deployDir, $config). In SetupWizard::startAgent() (line 195), pass the same config array that bare-metal mode uses.
