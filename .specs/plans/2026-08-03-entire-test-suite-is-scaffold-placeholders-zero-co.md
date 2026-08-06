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
