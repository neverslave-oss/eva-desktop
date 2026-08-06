<?php

use App\Livewire\Chat;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('sendMessage posts to the real /message endpoint and shows the reply', function () {
    Http::fake([
        'localhost:8779/message' => Http::response(['reply' => 'hi from kernel-evolving'], 200),
    ]);

    $component = Livewire::test(Chat::class)
        ->set('message', 'hello')
        ->call('sendMessage')
        ->assertSet('message', '');

    expect($component->get('messages'))->toHaveCount(2)
        ->and($component->get('messages')[1]['content'])->toBe('hi from kernel-evolving')
        ->and($component->get('messages')[1]['role'])->toBe('assistant');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:8779/message'
            && $request['message'] === 'hello'
            && ! empty($request['chat_id']);
    });
});

test('sendMessage surfaces an error message on a failed response', function () {
    Http::fake([
        'localhost:8779/message' => Http::response(['detail' => 'boom'], 500),
    ]);

    $component = Livewire::test(Chat::class)
        ->set('message', 'hello')
        ->call('sendMessage');

    expect($component->get('messages')[1]['content'])
        ->toBe('Error: kernel-evolving returned status 500');
});

