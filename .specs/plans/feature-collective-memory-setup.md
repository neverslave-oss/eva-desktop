---
date: 2026-08-06
agent: copilot
topic: Feature — Collective memory URL in SetupWizard + Settings
tags: [livewire, setup-wizard, collective-memory, xp6a]
status: resolved
---

# Feature: Collective memory URL in SetupWizard + Settings (XP6a)

## Context

The multi-agent collective-memory service is running at `http://192.168.1.113:8010` and is already wired into kernel-evolving (XP6b complete: `src/core/collective_memory_client.py`, `config.yaml collective_memory.url`). However the URL is currently hardcoded. The desktop wizard and settings have no way to configure it — so if the service moves or the user wants to point to a different instance, they must edit `config.yaml` manually.

## Goal

- Add a `collectiveMemoryUrl` field to the SetupWizard (optional, advanced/final step)
- Add a "Test" button that hits `GET {url}/health` server-side and shows `{"status": "ok", "model_loaded": true}`
- On wizard finish, write the URL to kernel-evolving's config (via API or direct file write)
- Expose the same field in Settings for post-install changes

## Files to change

- `app/Livewire/SetupWizard.php` — add field + `testCollectiveMemory()` action
- `app/Services/KernelEvolvingService.php` — add `setCollectiveMemoryUrl(string $url)` that either POSTs to kernel-evolving's config endpoint or writes to `config.yaml` directly
- `resources/views/livewire/setup-wizard.blade.php` — new optional step or field in advanced step
- `app/Livewire/Settings.php` — add `collectiveMemoryUrl` field + save hook
- `resources/views/livewire/settings.blade.php` — add input + test button in settings page

## Implementation plan

### SetupWizard.php / Settings.php

```php
public string $collectiveMemoryUrl = '';
public string $collectiveMemoryTestResult = '';

public function testCollectiveMemory(): void
{
    $url = rtrim(trim($this->collectiveMemoryUrl), '/');
    if (empty($url)) {
        $this->collectiveMemoryTestResult = 'Enter a URL first.';
        return;
    }
    try {
        $response = Http::timeout(5)->get("{$url}/health");
        if ($response->ok()) {
            $data = $response->json();
            $status = $data['status'] ?? 'unknown';
            $model = $data['model_loaded'] ? 'model loaded' : 'no model';
            $this->collectiveMemoryTestResult = "✓ Service reachable — {$status}, {$model}";
        } else {
            $this->collectiveMemoryTestResult = "Service returned HTTP {$response->status()}";
        }
    } catch (\Exception $e) {
        $this->collectiveMemoryTestResult = 'Unreachable: ' . $e->getMessage();
    }
}
```

### KernelEvolvingService.php

```php
public function setCollectiveMemoryUrl(string $url): void
{
    // Option A: POST to kernel-evolving config endpoint (if it exists)
    // Option B: write to config.yaml directly
    $configPath = $this->getKernelEvolvingConfigPath(); // resolve config.yaml path
    $yaml = Yaml::parseFile($configPath);
    $yaml['collective_memory']['url'] = $url;
    file_put_contents($configPath, Yaml::dump($yaml, 4, 2));
}
```

On wizard finish (`finish()` method), call `$this->kernelEvolvingService->setCollectiveMemoryUrl($this->collectiveMemoryUrl)` if the URL is non-empty.

## Acceptance criteria

- [ ] Wizard shows optional field for collective memory URL (pre-filled with default `http://192.168.1.113:8010`)
- [ ] Test button shows success with model-loaded status from live service
- [ ] URL is persisted to `config.yaml` on wizard finish
- [ ] Settings page shows the same field with live value read from config
- [ ] Empty URL (field left blank) does not write to config (leaves existing value)
- [ ] Works for both local LAN address and remote HTTPS URL

## Related

- XP6b: kernel-evolving HTTP client — **COMPLETE** (`src/core/collective_memory_client.py`)
- XP4 (Telegram setup), XP2 (model path), XP3 (API keys) — same wizard files
- Full cross-repo audit: `kernel-evolving/.specs/audits/AUDIT-2026-08-06-crossplatform-onboarding-pairing.md`
