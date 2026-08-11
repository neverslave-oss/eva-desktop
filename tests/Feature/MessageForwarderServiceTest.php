<?php

use App\Services\MessageForwarderService;
use App\Services\TunnelService;
use Illuminate\Support\Facades\Http;

test('handle posts to /message and then completes relay via kernel-central response endpoint', function () {
    config(['kernel-desktop.central.url' => 'https://kernel-central.test']);

    $tokenPath = storage_path('framework/testing/device-token-forwarder.txt');
    @mkdir(dirname($tokenPath), 0777, true);
    file_put_contents($tokenPath, 'desktop-token');
    config(['kernel-desktop.device.token_path' => $tokenPath]);

    Http::fake([
        'localhost:8779/message' => Http::response(['reply' => 'hello back'], 200),
        'kernel-central.test/api/messages/relay/response' => Http::response(['data' => ['status' => 'responded']], 200),
    ]);

    $tunnel = Mockery::mock(TunnelService::class);

    (new MessageForwarderService())->handle([
        'event' => 'MessageRelayRequested',
        'data' => [
            'relay_id' => 'relay-1',
            'message' => 'hi there',
            'chat_id' => 'chat-42',
        ],
    ], $tunnel);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:8779/message'
            && $request['message'] === 'hi there'
            && $request['chat_id'] === 'chat-42';
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'https://kernel-central.test/api/messages/relay/response'
            && $request->hasHeader('Authorization', 'Bearer desktop-token')
            && $request['relay_id'] === 'relay-1'
            && $request['response'] === 'hello back';
    });

    @unlink($tokenPath);
});

test('handle posts textual error response when kernel-evolving returns an error status', function () {
    config(['kernel-desktop.central.url' => 'https://kernel-central.test']);

    $tokenPath = storage_path('framework/testing/device-token-forwarder.txt');
    @mkdir(dirname($tokenPath), 0777, true);
    file_put_contents($tokenPath, 'desktop-token');
    config(['kernel-desktop.device.token_path' => $tokenPath]);

    Http::fake([
        'localhost:8779/message' => Http::response(['detail' => 'boom'], 500),
        'kernel-central.test/api/messages/relay/response' => Http::response(['data' => ['status' => 'responded']], 200),
    ]);

    $tunnel = Mockery::mock(TunnelService::class);

    (new MessageForwarderService())->handle([
        'event' => 'MessageRelayRequested',
        'data' => [
            'relay_id' => 'relay-2',
            'message' => 'hi there',
        ],
    ], $tunnel);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://kernel-central.test/api/messages/relay/response'
            && $request['relay_id'] === 'relay-2'
            && str_contains((string) $request['response'], 'kernel-evolving returned status 500');
    });

    @unlink($tokenPath);
});
