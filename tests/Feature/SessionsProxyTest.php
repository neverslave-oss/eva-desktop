<?php

use Illuminate\Support\Facades\Http;

test('GET /api/sessions proxies the session list from kernel-evolving', function () {
    Http::fake([
        '127.0.0.1:8779/api/sessions' => Http::response([
            'sessions' => [
                ['id' => 's1', 'chat_id' => 'agent-abc', 'title' => 'Hello', 'updated_at' => '2026-01-01T00:00:00+00:00'],
            ],
        ], 200),
    ]);

    $resp = $this->get('/api/sessions');

    $resp->assertOk();
    $resp->assertJsonPath('sessions.0.chat_id', 'agent-abc');
    Http::assertSent(function ($request) {
        return $request->url() === 'http://127.0.0.1:8779/api/sessions';
    });
});

test('GET /api/sessions returns an empty list gracefully when the agent is offline', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('unreachable');
    });

    $this->get('/api/sessions')
        ->assertOk()
        ->assertJson(['sessions' => []]);
});

test('POST /api/sessions proxies a create request to kernel-evolving', function () {
    Http::fake([
        '127.0.0.1:8779/api/sessions' => Http::response([
            'ok' => true,
            'chat_id' => 'agent-new123',
            'title' => 'New session',
        ], 200),
    ]);

    $resp = $this->postJson('/api/sessions', ['chat_id' => '', 'title' => 'New session']);

    $resp->assertOk();
    $resp->assertJsonPath('chat_id', 'agent-new123');
    Http::assertSent(function ($request) {
        return $request->url() === 'http://127.0.0.1:8779/api/sessions'
            && $request['title'] === 'New session';
    });
});

test('POST /api/sessions degrades to 502 when the agent is unreachable', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('unreachable');
    });

    $this->postJson('/api/sessions', ['chat_id' => '', 'title' => 'x'])
        ->assertStatus(502)
        ->assertJson(['ok' => false]);
});
