---
date: 2026-08-06
agent: copilot
topic: MessageForwarderService (and the sendMessage plan) target a nonexistent /chat/message endpoint
severity: high
tags: [copilot, cross-repo-audit, kernel-evolving]
status: open
---

# MessageForwarderService calls a nonexistent /chat/message endpoint on kernel-evolving

Root cause: `app/Services/MessageForwarderService.php::forwardToKernelEvolving()` (line ~103) posts
to `{$this->evolvingUrl}/chat/message`. kernel-evolving's actual API (`src/api.py`) only exposes
`POST /message` and `POST /message/stream` — there is no `/chat/*` prefix anywhere in its route
table. Any message relayed through kernel-desktop's WebSocket tunnel (mobile → kernel-central →
Reverb → TunnelService → MessageForwarderService → kernel-evolving) will 404 against the real
server, and the resulting `!$response->successful()` branch will surface a generic
"kernel-evolving returned status 404" error instead of a real reply.

This is not an isolated typo — the same wrong endpoint is assumed in this repo's own prior audit
plan [2026-08-02-chat-sendmessage-is-a-stub-no-api-call-to-kernel-e.md](2026-08-02-chat-sendmessage-is-a-stub-no-api-call-to-kernel-e.md),
which recommends wiring `Chat.php::sendMessage()` to also call `POST /chat/message`. **Confirmed:**
that plan was implemented literally — `app/Livewire/Chat.php:37` posts to the same wrong
`/chat/message` path, so this bug exists in two independent call sites, not one.

Confirmed against kernel-evolving's live route table (`src/api.py`):
- `POST /message` — body `{"message": str, "chat_id": str = ""}`, returns `{"reply": str}`
- `POST /message/stream` — same body, SSE streaming variant
- No `/chat/message` route exists at any point in kernel-evolving's history checked today.

Affected files:
- `app/Services/MessageForwarderService.php:103,110` — `$url = $this->evolvingUrl . '/chat/message';`
- `app/Livewire/Chat.php:37` — `->post($evolvingUrl . '/chat/message', [...])`, same bug, second site.

Suggested fix:
1. Change `MessageForwarderService::forwardToKernelEvolving()` to POST to `/message` (not
   `/chat/message`), with body `{"message": $message, "chat_id": <the relay's originating chat
   id, if available>}` instead of the current `{"message": $message, "source": "kernel-mobile-v2"}`
   (kernel-evolving's `MessageIn` schema doesn't have a `source` field — harmless extra key, but
   `chat_id` should be passed through for conversation isolation rather than omitted).
2. Update the response parsing: kernel-evolving's `/message` always returns `{"reply": str}` —
   the current fallback chain `$body['response'] ?? $body['message'] ?? $body['text'] ?? ''` will
   never match `reply` and will silently return an empty string on a successful 200 response.
3. Apply the same fix to `app/Livewire/Chat.php:37`.
4. Add a feature/integration test that asserts the exact URL and body shape posted to
   kernel-evolving (per the existing "zero coverage of services" finding in
   [2026-08-03-entire-test-suite-is-scaffold-placeholders-zero-co.md](2026-08-03-entire-test-suite-is-scaffold-placeholders-zero-co.md))
   so this class of endpoint drift is caught automatically going forward.

Found while cross-checking kernel-evolving's API surface against this repo's HTTP client code,
at the user's request, after a separate kernel-evolving inference-pipeline fix session
(2026-08-06) — no kernel-evolving changes affect this bug either way; it predates that session.
