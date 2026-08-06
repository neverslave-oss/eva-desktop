---
date: 2026-08-06
agent: copilot
topic: Feature — Guided Telegram bot setup in SetupWizard
tags: [livewire, setup-wizard, telegram, xp4]
status: open
---

# Feature: Guided Telegram bot setup in SetupWizard (XP4)

## Context

The SetupWizard collects a Telegram bot token in one of its steps but never validates it. Users who mis-type their token will get no error until they try to send a message.

## Goal

Add a "Test Connection" button to the Telegram step of the wizard that validates the bot token against the Telegram Bot API server-side, showing the bot's username on success or a clear error on failure. Gate the "Next" button on a successful test.

## Files to change

- `app/Livewire/SetupWizard.php` — add `testTelegramConnection()` Livewire action + `$telegramTestResult` / `$telegramTestPassed` properties
- `resources/views/livewire/setup-wizard.blade.php` — add BotFather deep-link button, "Test Connection" button, result display, gate Next button on `$telegramTestPassed`

## Implementation plan

### SetupWizard.php

```php
public string $telegramTestResult = '';
public bool $telegramTestPassed = false;

public function testTelegramConnection(): void
{
    $token = trim($this->telegramToken ?? '');
    if (empty($token)) {
        $this->telegramTestResult = 'Please enter a bot token first.';
        $this->telegramTestPassed = false;
        return;
    }
    try {
        $response = Http::timeout(8)->get("https://api.telegram.org/bot{$token}/getMe");
        if ($response->ok() && ($response->json('ok') === true)) {
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
```

Reset `$telegramTestPassed` to `false` whenever `$telegramToken` changes (use `updatedTelegramToken()` hook).

### Blade template

- Add a link button: `<a href="https://t.me/BotFather" target="_blank">Create a bot with BotFather</a>`
- After the token input, add: `<button wire:click="testTelegramConnection">Test Connection</button>`
- Show `$telegramTestResult` below the button (green on pass, red on fail)
- Disable "Next" button when `!$telegramTestPassed`

## Acceptance criteria

- [ ] Entering a valid token and clicking "Test" shows `✓ Connected as @<botname>`
- [ ] Entering an invalid token shows a clear error
- [ ] "Next" button is disabled until test passes
- [ ] Changing the token after a passing test resets the test state
- [ ] BotFather link opens in system browser (not in-app)
- [ ] HTTP request is server-side (no token exposed in JS)

## Security note

The `getMe` call is made server-side (PHP/Laravel) — the bot token is never passed to the frontend JS.

## Related

- XP2 (model storage path), XP3 (API key settings), XP6a (collective memory URL) — same wizard files
- Full cross-repo audit: `kernel-evolving/.specs/audits/AUDIT-2026-08-06-crossplatform-onboarding-pairing.md`
