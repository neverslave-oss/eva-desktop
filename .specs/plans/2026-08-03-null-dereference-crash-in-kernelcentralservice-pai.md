---
date: 2026-08-03
agent: scout
topic: Null dereference crash in KernelCentralService::pair() and confirm()
severity: high
tags: [scout, agent-ready]
status: resolved
---

# Null dereference crash in KernelCentralService::pair() and confirm()

Root cause: app/Services/KernelCentralService.php lines 71-78 and 124-130 call `$response->json('data')` and immediately index the result without a null guard. If the API returns a 2xx response without a top-level `data` key (maintenance page, non-standard error), `$data` is null and PHP 8 throws a fatal `TypeError: Cannot access offset on null` that is NOT caught by the surrounding `catch (\Exception $e)` block, crashing the pairing flow. Fix: add `if (!is_array($data)) { return ['success' => false, 'error' => 'Unexpected API response format']; }` after each `$data = $response->json('data')` call in both methods.
