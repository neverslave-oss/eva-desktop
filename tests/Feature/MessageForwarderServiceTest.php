<?php

use App\Services\MessageForwarderService;
use App\Services\TunnelService;
use Illuminate\Support\Facades\Http;

test('handle posts to the real /message endpoint with chat_id and parses reply', function () {
    Http::fake([
        'localhost:8779/message' => Http::response(['reply' => 'hello back'], 200),
    ]);

    $tunnel = Mockery::mock(TunnelService::class);
    $tunnel->shouldReceive('send')
        ->once()
        ->withArgs(function (array $eventData) {
            return $eventData['event'] === 'MessageRelayResponse'
                && $eventData['data']['response']['success'] === true
                && $eventData['data']['response']['response'] === 'hello back';
        })
        ->andReturn(true);

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
});

test('handle reports failure when kernel-evolving returns an error status', function () {
    Http::fake([
        'localhost:8779/message' => Http::response(['detail' => 'boom'], 500),
    ]);

    $tunnel = Mockery::mock(TunnelService::class);
    $tunnel->shouldReceive('send')
        ->once()
        ->withArgs(function (array $eventData) {
            return $eventData['data']['response']['success'] === false;
        })
        ->andReturn(true);

    (new MessageForwarderService())->handle([
        'event' => 'MessageRelayRequested',
        'data' => [
            'relay_id' => 'relay-2',
            'message' => 'hi there',
        ],
    ], $tunnel);
});
