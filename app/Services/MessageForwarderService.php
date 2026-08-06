<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MessageForwarderService
 *
 * KD-003: Listens for relayed messages from kernel-central via the WebSocket
 * tunnel, forwards them to the local kernel-evolving instance, and sends
 * responses back through the tunnel.
 *
 * Architecture:
 *   Mobile → kernel-central (relay API) → Reverb WS → TunnelService
 *   → MessageForwarder → kernel-evolving:8779/message → response
 *   → TunnelService → Reverb WS → kernel-central → mobile
 */
class MessageForwarderService
{
    /**
     * The kernel-evolving base URL.
     */
    protected string $evolvingUrl;

    /**
     * Request timeout for kernel-evolving API calls (seconds).
     */
    protected int $timeout;

    public function __construct()
    {
        $this->evolvingUrl = rtrim(config('kernel-desktop.evolving.url', 'http://localhost:8779'), '/');
        $this->timeout = (int) config('kernel-desktop.evolving.timeout', 30);
    }

    /**
     * Handle an incoming message from the tunnel.
     *
     * Called by TunnelService's onMessage callback.
     *
     * Expected data format:
     *   [
     *     'event' => 'MessageRelayRequested',     // KC-004 event
     *     'data'  => [
     *       'relay_id' => '01J...',               // ULID of the relay record
     *       'message'  => 'user query text',      // The original message
     *       'type'     => 'chat',                 // message type
     *     ],
     *   ]
     */
    public function handle(array $data, TunnelService $tunnel): void
    {
        $event = $data['event'] ?? '';
        $payload = $data['data'] ?? [];

        if ($event !== 'MessageRelayRequested') {
            Log::debug('Forwarder: ignoring non-relay event', ['event' => $event]);

            return;
        }

        $relayId = $payload['relay_id'] ?? '';
        $message = $payload['message'] ?? '';
        $chatId = $payload['chat_id'] ?? '';

        if (! $relayId || ! $message) {
            Log::warning('Forwarder: invalid relay payload', ['payload' => $payload]);

            return;
        }

        Log::info('Forwarder: processing relay', [
            'relay_id' => $relayId,
            'message_length' => strlen($message),
        ]);

        try {
            // Forward to kernel-evolving
            $response = $this->forwardToKernelEvolving($message, $chatId);

            // Send response back through tunnel
            $this->sendResponse($tunnel, $relayId, $response);

            Log::info('Forwarder: relay completed', ['relay_id' => $relayId]);

        } catch (\Exception $e) {
            Log::error('Forwarder: relay failed', [
                'relay_id' => $relayId,
                'error' => $e->getMessage(),
            ]);

            $this->sendResponse($tunnel, $relayId, [
                'error' => 'Forwarding failed: ' . $e->getMessage(),
                'success' => false,
            ]);
        }
    }

    /**
     * Forward a message to the local kernel-evolving instance.
     *
     * Uses the message endpoint (POST /message) which is kernel-evolving's
     * standard text-only interaction endpoint.
     *
     * @return array{success: bool, response: string, error?: string}
     */
    protected function forwardToKernelEvolving(string $message, string $chatId = ''): array
    {
        $url = $this->evolvingUrl . '/message';

        Log::debug('Forwarder: calling kernel-evolving', ['url' => $url]);

        try {
            $response = Http::timeout($this->timeout)
                ->post($url, [
                    'message' => $message,
                    'chat_id' => $chatId,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                $responseText = $body['reply'] ?? '';

                return [
                    'success' => true,
                    'response' => $responseText,
                ];
            }

            Log::warning('Forwarder: kernel-evolving returned error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => "kernel-evolving returned status {$response->status()}",
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Forwarder: kernel-evolving unreachable', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'kernel-evolving is not running on localhost:8779',
            ];
        }
    }

    /**
     * Send the forwarder response back through the tunnel.
     *
     * The response is sent as a Pusher event message that kernel-central
     * Reverb will route back to the MessageRelayController.
     */
    protected function sendResponse(TunnelService $tunnel, string $relayId, array $response): void
    {
        $eventData = [
            'event' => 'MessageRelayResponse',
            'data' => [
                'relay_id' => $relayId,
                'response' => $response,
                'forwarded_at' => now()->toIso8601String(),
            ],
        ];

        $sent = $tunnel->send($eventData);

        if (! $sent) {
            Log::error('Forwarder: failed to send response through tunnel', [
                'relay_id' => $relayId,
            ]);
        }
    }
}
