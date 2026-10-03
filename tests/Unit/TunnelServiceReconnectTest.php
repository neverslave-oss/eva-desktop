<?php

use App\Services\KernelCentralService;
use App\Services\TunnelService;
use Illuminate\Support\Facades\Http;
use WebSocket\Client;

beforeEach(function () {
    config(['kernel-desktop.central.url' => 'https://kernel-central.test']);
    config(['kernel-desktop.reverb.host' => '']);
    config(['kernel-desktop.reverb.port' => 8080]);
    config(['kernel-desktop.reverb.app_key' => 'appkey']);
    config(['kernel-desktop.device.token_path' => storage_path('framework/testing/tunnel-reconnect-token.txt')]);

    // Self-contained tests: never let a token file left behind by a
    // previously aborted run satisfy the "no token" path.
    @unlink(config('kernel-desktop.device.token_path'));
});

afterEach(function () {
    @unlink(config('kernel-desktop.device.token_path'));
});

/**
 * Exposes newClient() so tests can inject a mock websocket client
 * without touching the network.
 */
function tunnelWithClient(Client $client): TunnelService
{
    return new class($client) extends TunnelService {
        public function __construct(private Client $wsClient)
        {
            parent::__construct(new KernelCentralService());
        }

        protected function newClient(string $endpoint): Client
        {
            return $this->wsClient;
        }
    };
}

test('connect without a stored token sets error state and returns false', function () {
    $tunnel = new TunnelService(new KernelCentralService());

    $result = $tunnel->connect();

    expect($result)->toBeFalse()
        ->and($tunnel->getState())->toBe(TunnelService::STATE_ERROR)
        ->and($tunnel->getReconnectAttempts())->toBe(0);
});

test('connect establishes the websocket, authenticates and subscribes on success', function () {
    file_put_contents(config('kernel-desktop.device.token_path'), 'desktop-token');
    config(['kernel-desktop.device.id' => 7]);

    $client = Mockery::mock(Client::class);
    // Step 1: pusher:connection_established with a socket id.
    $client->shouldReceive('receive')->once()->andReturn(
        json_encode(['event' => 'pusher:connection_established', 'data' => '{"socket_id":"socket-123"}'])
    );
    // Step 2: subscribe (the auth HTTP call is faked below).
    $client->shouldReceive('send')->once()->with(
        json_encode(['event' => 'pusher:subscribe', 'data' => [
            'channel' => 'private-devices.7',
            'auth' => 'hmac-signature',
        ]])
    );
    // disconnect() runs during teardown via __destruct().
    $client->shouldReceive('close')->once();

    // Broadcast auth returns the HMAC signature for the private channel.
    Http::fake([
        'kernel-central.test/api/broadcasting/auth' => Http::response(['auth' => 'hmac-signature'], 200),
    ]);

    $tunnel = tunnelWithClient($client);

    $result = $tunnel->connect();

    expect($result)->toBeTrue()
        ->and($tunnel->getState())->toBe(TunnelService::STATE_CONNECTED)
        ->and($tunnel->getSocketId())->toBe('socket-123')
        ->and($tunnel->getReconnectAttempts())->toBe(0)
        ->and($tunnel->getLastConnectedAt())->not->toBeNull();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://kernel-central.test/api/broadcasting/auth'
            && $request->hasHeader('Authorization', 'Bearer desktop-token')
            && $request['socket_id'] === 'socket-123'
            && $request['channel_name'] === 'private-devices.7';
    });
});

test('connect increments reconnect attempts and errors when the websocket fails', function () {
    file_put_contents(config('kernel-desktop.device.token_path'), 'desktop-token');

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('receive')->andThrow(
        new \WebSocket\ConnectionException('connection refused')
    );
    // establishConnection() clears the client reference after a failure.
    $client->shouldReceive('close')->zeroOrMoreTimes();

    $tunnel = tunnelWithClient($client);

    $result = $tunnel->connect();

    expect($result)->toBeFalse()
        ->and($tunnel->getState())->toBe(TunnelService::STATE_ERROR)
        ->and($tunnel->getReconnectAttempts())->toBe(1);
});