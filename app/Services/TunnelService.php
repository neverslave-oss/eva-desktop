<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WebSocket\Client;
use WebSocket\ConnectionException;

/**
 * TunnelService
 *
 * Manages a persistent WebSocket tunnel to kernel-central's Reverb server,
 * using the Pusher protocol. The desktop device subscribes to its private
 * channel (private-devices.{deviceId}) to receive relayed messages from
 * kernel-mobile-v2, and forwards responses back through the same tunnel.
 *
 * Lifecycle:
 *   1. Connect to Reverb WS endpoint
 *   2. Auth to private-devices.{deviceId} via broadcasting auth API
 *   3. Subscribe to channel
 *   4. Listen for relayed messages (event: "MessageRelayRequested")
 *   5. Forward messages to local kernel-evolving (KD-003)
 *   6. Auto-reconnect on disconnect (KD-004)
 */
class TunnelService
{
    /**
     * WebSocket connection states
     */
    public const STATE_DISCONNECTED = 'disconnected';
    public const STATE_CONNECTING = 'connecting';
    public const STATE_CONNECTED = 'connected';
    public const STATE_RECONNECTING = 'reconnecting';
    public const STATE_ERROR = 'error';

    /**
     * Current connection state.
     */
    protected string $state = self::STATE_DISCONNECTED;

    /**
     * The WebSocket client instance.
     */
    protected ?Client $client = null;

    /**
     * The socket ID received from Reverb upon connection.
     */
    protected ?string $socketId = null;

    /**
     * The device ID from local config.
     */
    protected ?int $deviceId = null;

    /**
     * The device Sanctum token for auth.
     */
    protected ?string $deviceToken = null;

    /**
     * KernelCentralService for auth and token retrieval.
     */
    protected KernelCentralService $centralService;

    /**
     * Last successful connection time for health tracking.
     */
    protected ?int $lastConnectedAt = null;

    /**
     * Number of consecutive reconnection attempts.
     */
    protected int $reconnectAttempts = 0;

    /**
     * Maximum reconnect delay (exponential backoff cap).
     */
    protected int $maxReconnectDelay = 60; // seconds

    /**
     * Callback for incoming messages (set by caller for KD-003).
     *
     * @var callable|null
     */
    protected $onMessage = null;

    /**
     * Callback for state changes.
     *
     * @var callable|null
     */
    protected $onStateChange = null;

    public function __construct(KernelCentralService $centralService)
    {
        $this->centralService = $centralService;
    }

    // -----------------------------------------------------------------------
    //  Configuration
    // -----------------------------------------------------------------------

    /**
     * Get the Reverb WS endpoint URL.
     */
    protected function wsEndpoint(): string
    {
        $centralUrl = config('kernel-desktop.central.url', 'https://kernel-central.neverslave.dev');
        $reverbHost = config('kernel-desktop.reverb.host', '');
        $reverbPort = config('kernel-desktop.reverb.port', 8080);
        $appKey = config('kernel-desktop.reverb.app_key', '');

        // Use explicit Reverb host if configured (for dev environments),
        // otherwise derive from kernel-central URL.
        if ($reverbHost) {
            $scheme = config('kernel-desktop.reverb.scheme', 'wss');
            $port = $reverbPort ? ":{$reverbPort}" : '';

            return "{$scheme}://{$reverbHost}{$port}/app/{$appKey}";
        }

        // Derive from central URL: replace https:// with wss://
        $parsed = parse_url($centralUrl);
        $scheme = ($parsed['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws';
        $host = $parsed['host'] ?? 'kernel-central.neverslave.dev';
        $port = $reverbPort ? ":{$reverbPort}" : ($reverbPort === 80 || $reverbPort === 443 ? '' : ':8080');

        return "{$scheme}://{$host}{$port}/app/{$appKey}";
    }

    /**
     * Get the broadcasting auth URL.
     */
    protected function authUrl(): string
    {
        $base = config('kernel-desktop.central.url', 'https://kernel-central.neverslave.dev');

        return rtrim($base, '/') . '/api/broadcasting/auth';
    }

    /**
     * Get the private channel name for this device.
     */
    protected function channelName(): string
    {
        $id = $this->deviceId ?? 0;

        return "private-devices.{$id}";
    }

    // -----------------------------------------------------------------------
    //  State management
    // -----------------------------------------------------------------------

    public function getState(): string
    {
        return $this->state;
    }

    public function getSocketId(): ?string
    {
        return $this->socketId;
    }

    public function getLastConnectedAt(): ?int
    {
        return $this->lastConnectedAt;
    }

    public function getReconnectAttempts(): int
    {
        return $this->reconnectAttempts;
    }

    public function isConnected(): bool
    {
        return $this->state === self::STATE_CONNECTED && $this->client !== null;
    }

    /**
     * Register a callback for incoming messages.
     * The callback receives (array $data, TunnelService $tunnel).
     */
    public function onMessage(callable $callback): void
    {
        $this->onMessage = $callback;
    }

    /**
     * Register a callback for state changes.
     * The callback receives (string $newState, ?string $oldState).
     */
    public function onStateChange(callable $callback): void
    {
        $this->onStateChange = $callback;
    }

    /**
     * Update state and fire callback.
     */
    protected function setState(string $newState): void
    {
        $old = $this->state;
        $this->state = $newState;
        Log::debug('Tunnel state changed', ['from' => $old, 'to' => $newState]);

        if ($this->onStateChange) {
            call_user_func($this->onStateChange, $newState, $old);
        }
    }

    // -----------------------------------------------------------------------
    //  Connection lifecycle
    // -----------------------------------------------------------------------

    /**
     * Connect the tunnel to kernel-central Reverb.
     *
     * Loads the device identity from local storage, establishes the WS
     * connection, authenticates to the private channel, and subscribes.
     *
     * @return bool True if connection established.
     */
    public function connect(): bool
    {
        // Load device identity
        $this->deviceId = (int) config('kernel-desktop.device.id', 0);
        $this->deviceToken = $this->centralService->getStoredToken();

        if (! $this->deviceToken) {
            Log::warning('Tunnel cannot connect: no device token');
            $this->setState(self::STATE_ERROR);

            return false;
        }

        return $this->establishConnection();
    }

    /**
     * Establish the actual WS connection and subscribe.
     */
    protected function establishConnection(): bool
    {
        $endpoint = $this->wsEndpoint();
        $this->setState(self::STATE_CONNECTING);

        try {
            Log::info('Tunnel connecting', ['endpoint' => $endpoint]);

            $this->client = new Client($endpoint, [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'KernelDesktopTunnel/1.0',
                ],
            ]);

            // Step 1: Wait for pusher:connection_established
            $response = $this->client->receive();
            $data = json_decode($response->getContent(), true);

            if (! $data || ($data['event'] ?? '') !== 'pusher:connection_established') {
                Log::error('Tunnel: unexpected initial response', ['response' => $data]);
                $this->setState(self::STATE_ERROR);

                return false;
            }

            $connectionData = json_decode($data['data'] ?? '{}', true);
            $this->socketId = $connectionData['socket_id'] ?? null;

            if (! $this->socketId) {
                Log::error('Tunnel: no socket_id in connection response');
                $this->setState(self::STATE_ERROR);

                return false;
            }

            Log::info('Tunnel connected to Reverb', ['socket_id' => $this->socketId]);

            // Step 2: Auth to private channel
            if (! $this->authenticateChannel()) {
                $this->disconnect();

                return false;
            }

            // Step 3: Subscribe to private channel
            if (! $this->subscribeChannel()) {
                $this->disconnect();

                return false;
            }

            $this->setState(self::STATE_CONNECTED);
            $this->lastConnectedAt = time();
            $this->reconnectAttempts = 0;

            Log::info('Tunnel established', [
                'device_id' => $this->deviceId,
                'channel' => $this->channelName(),
                'socket_id' => $this->socketId,
            ]);

            return true;

        } catch (ConnectionException $e) {
            Log::error('Tunnel connection failed', ['error' => $e->getMessage()]);
            $this->setState(self::STATE_ERROR);
            $this->client = null;

            return false;
        } catch (\Exception $e) {
            Log::error('Tunnel unexpected error', ['error' => $e->getMessage()]);
            $this->setState(self::STATE_ERROR);
            $this->client = null;

            return false;
        }
    }

    /**
     * Authenticate to the private channel via broadcasting auth API.
     *
     * POST /api/broadcasting/auth with socket_id and channel_name,
     * using the Sanctum token as Bearer auth.
     */
    protected function authenticateChannel(): bool
    {
        $channel = $this->channelName();

        try {
            $response = Http::timeout(10)
                ->withToken($this->deviceToken)
                ->asForm()
                ->post($this->authUrl(), [
                    'socket_id' => $this->socketId,
                    'channel_name' => $channel,
                ]);

            if (! $response->successful()) {
                Log::error('Tunnel: broadcast auth failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $authData = $response->json();
            Log::info('Tunnel: channel auth successful', ['channel' => $channel]);

            // The auth response contains 'auth' key with the HMAC signature
            $this->lastAuthResponse = $authData['auth'] ?? '';

            return true;

        } catch (\Exception $e) {
            Log::error('Tunnel: broadcast auth exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Subscribe to the private channel with the auth signature.
     */
    protected function subscribeChannel(): bool
    {
        if (! $this->lastAuthResponse) {
            Log::error('Tunnel: no auth signature available for subscribe');

            return false;
        }

        try {
            $subscribeMsg = json_encode([
                'event' => 'pusher:subscribe',
                'data' => [
                    'channel' => $this->channelName(),
                    'auth' => $this->lastAuthResponse,
                ],
            ]);

            $this->client->send($subscribeMsg);
            Log::info('Tunnel: subscribed to channel', ['channel' => $this->channelName()]);

            return true;

        } catch (\Exception $e) {
            Log::error('Tunnel: subscribe failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    // Store the last auth response for subscribe
    protected string $lastAuthResponse = '';

    // -----------------------------------------------------------------------
    //  Message loop
    // -----------------------------------------------------------------------

    /**
     * Run the message loop, processing incoming WS messages.
     *
     * Blocks until the connection is closed or an unrecoverable error occurs.
     * For each incoming message, fires the onMessage callback.
     *
     * @param int $timeoutSeconds Max time to listen (0 = indefinite)
     */
    public function listen(int $timeoutSeconds = 0): void
    {
        if (! $this->client) {
            return;
        }

        $startTime = time();

        while (true) {
            // Check timeout
            if ($timeoutSeconds > 0 && (time() - $startTime) >= $timeoutSeconds) {
                break;
            }

            try {
                $message = $this->client->receive();
                $data = json_decode($message->getContent(), true);

                if (! $data) {
                    continue;
                }

                $event = $data['event'] ?? '';
                $payload = json_decode($data['data'] ?? '{}', true);

                // Handle Pusher protocol events
                switch ($event) {
                    case 'pusher:ping':
                        $this->client->send(json_encode(['event' => 'pusher:pong']));
                        break;

                    case 'pusher:pong':
                        // Server responded to our ping — connection is healthy
                        break;

                    case 'pusher:error':
                        Log::warning('Tunnel: pusher error', ['data' => $payload]);
                        break;

                    default:
                        // Application events (relay messages, etc.)
                        $this->handleAppEvent($event, $payload);
                        break;
                }

            } catch (ConnectionException $e) {
                Log::warning('Tunnel: connection lost during listen', [
                    'error' => $e->getMessage(),
                ]);
                $this->setState(self::STATE_DISCONNECTED);
                break;

            } catch (\Exception $e) {
                Log::error('Tunnel: listen error', ['error' => $e->getMessage()]);
                $this->setState(self::STATE_ERROR);
                break;
            }
        }
    }

    /**
     * Handle an application event from the tunnel.
     */
    protected function handleAppEvent(string $event, array $payload): void
    {
        Log::debug('Tunnel: app event received', ['event' => $event]);

        if ($this->onMessage) {
            call_user_func($this->onMessage, [
                'event' => $event,
                'data' => $payload,
            ], $this);
        }
    }

    // -----------------------------------------------------------------------
    //  Send data through tunnel
    // -----------------------------------------------------------------------

    /**
     * Send a message back through the tunnel to kernel-central.
     *
     * Used by KD-003 to send forwarder responses back.
     */
    public function send(array $data): bool
    {
        if (! $this->isConnected() || ! $this->client) {
            Log::warning('Tunnel: cannot send, not connected');

            return false;
        }

        try {
            $this->client->send(json_encode($data));

            return true;
        } catch (\Exception $e) {
            Log::error('Tunnel: send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    // -----------------------------------------------------------------------
    //  Disconnect & cleanup
    // -----------------------------------------------------------------------

    /**
     * Disconnect the tunnel cleanly.
     */
    public function disconnect(): void
    {
        $this->setState(self::STATE_DISCONNECTED);

        if ($this->client) {
            try {
                $this->client->close();
            } catch (\Exception $e) {
                // Ignore close errors
            }
            $this->client = null;
        }

        $this->socketId = null;
        Log::info('Tunnel disconnected');
    }

    /**
     * Disconnect (used by destructor or cleanup).
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
