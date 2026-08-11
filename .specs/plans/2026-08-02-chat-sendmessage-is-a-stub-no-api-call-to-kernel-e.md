---
date: 2026-08-02
agent: scout
topic: Chat.sendMessage() is a stub — no API call to kernel-evolving
severity: high
tags: [scout, agent-ready]
status: resolved
---

# Chat.sendMessage() is a stub — no API call to kernel-evolving

Root cause: The assistant response block in Chat.php lines 25-31 is entirely commented out with a placeholder comment. When a user sends a message, the user turn is appended to $messages and $message is cleared, but no HTTP request is ever made to kernel-evolving and no assistant reply is ever added. The UI will silently show only the user bubble and nothing else, making the chat feature completely non-functional.

Affected file: app/Livewire/Chat.php:12-33.

Suggested fix: Inject KernelEvolvingService (or use the existing forwardToKernelEvolving pattern from MessageForwarderService) and call POST /chat/message inside sendMessage(). Uncomment and populate the assistant response block:

  $result = $this->evolvingService->forwardMessage($this->message);
  $this->messages[] = [
    'role' => 'assistant',
    'content' => $result['response'] ?? $result['error'] ?? 'No response',
    'timestamp' => now()->toIso8601String(),
  ];

Also add error handling (try/catch + a 'error' role message) so connection failures are surfaced in the UI.
