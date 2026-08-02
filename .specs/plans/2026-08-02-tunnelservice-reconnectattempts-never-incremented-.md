---
date: 2026-08-02
agent: scout
topic: TunnelService::$reconnectAttempts never incremented — exponential backoff always 1 second
severity: high
tags: [scout, agent-ready]
status: open
---

# TunnelService::$reconnectAttempts never incremented — exponential backoff always 1 second

Root cause: TunnelService declares $reconnectAttempts (line 75) and resets it to 0 on successful connection (line 299), but nowhere in TunnelService or TunnelCommand is it ever incremented. TunnelCommand::getReconnectDelay() (line 143-149) calls $this->tunnel->getReconnectAttempts() which always returns 0, so pow(2, 0) = 1. The delay is always 1 second regardless of how many consecutive failures occur.

This means that on a sustained outage the tunnel hammers kernel-central Reverb with a reconnect attempt every ~1 second indefinitely, instead of backing off to the intended 60-second maximum.

Affected files: app/Services/TunnelService.php:75,299 and app/Console/Commands/TunnelCommand.php:143-149.

Suggested fix: In TunnelService::connect() (or establishConnection()), increment $reconnectAttempts on failure:

  // In the failure path of establishConnection():
  $this->reconnectAttempts++;

And ensure it is only reset to 0 on STATE_CONNECTED (already done at line 299). This makes the backoff sequence 1s, 2s, 4s, 8s, 16s, 32s, 60s as intended.
