---
date: 2026-08-03
agent: scout
topic: Dead code makes wsEndpoint() emit ':80'/':443' in WebSocket URLs
severity: high
tags: [scout, agent-ready]
status: resolved
---

# Dead code makes wsEndpoint() emit ':80'/':443' in WebSocket URLs

Root cause: app/Services/TunnelService.php line 128 has a ternary where the standard-port suppression branch (`$reverbPort === 80 || $reverbPort === 443`) is in the falsy arm of `$reverbPort ? ...`. Because PHP evaluates `80` as truthy, the check is unreachable — port 80 is always appended, producing `wss://host:80/app/key` which many WebSocket clients reject. Fix: replace line 128 with `$port = ($reverbPort && $reverbPort !== 80 && $reverbPort !== 443) ? ":{$reverbPort}" : '';`

## ✅ Resolution (2026-10-03)

Fixed + merged via **PR #26** (squash `e0e6c6c`, branch `fix/wsendpoint-standard-port-suppression`):
- dead ternary removed in the explicit-Reverb-host branch of `wsEndpoint()`; `:80`/`:443` no longer appended to WebSocket URLs
- 3 new unit tests (443/80/falsy explicit-host + derived-host regression), gate `php8.4 artisan test tests/Unit` → 11 passed, 14 assertions
- Issue #13 CLOSED (2026-10-03) — audit complete
