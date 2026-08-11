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
