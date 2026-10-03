---
date: 2026-08-03
agent: scout
topic: Entire test suite is scaffold placeholders — zero coverage of services
severity: normal
tags: [scout, agent-ready]
status: resolved
---

# Entire test suite is scaffold placeholders — zero coverage of services

Root cause: tests/Feature/ExampleTest.php asserts GET / returns 200; tests/Unit/ExampleTest.php asserts true === true. There are no tests for TunnelService (reconnect state machine), KernelCentralService (pairing/heartbeat), MessageForwarderService (relay logic), or the Chat/SetupWizard Livewire components. All external HTTP calls can be mocked with Laravel's Http::fake(). Suggested fix: add at minimum a Feature test for the pairing flow using Http::fake() to mock kernel-central responses, and a Unit test for TunnelService::wsEndpoint() covering port 80, 443, and custom port cases.

## ✅ Resolution (2026-10-03)

Coverage added + merged via **PR #27** (squash `f4e0469`, branch `test/real-service-coverage`):
- `tests/Unit/KernelCentralServiceTest.php` (6 tests): ping ok/degraded/unreachable, heartbeat Bearer header + device_id body, no-token short-circuit
- `tests/Unit/TunnelServiceReconnectTest.php` (3 tests): connect/reconnect state machine via mock WS
- `tests/Unit/TunnelServiceTest.php` + `KernelCentralServiceTest` suite green: `php8.4` → 16 passed, 35 assertions
- Issue #15 CLOSED (2026-10-03) — audit complete
