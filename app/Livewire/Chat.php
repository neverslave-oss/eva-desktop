<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Chat extends Component
{
    public string $message = '';
    public array $messages = [];

    public function sendMessage()
    {
        if (empty(trim($this->message))) {
            return;
        }

        $text = $this->message;

        // Add user message
        $this->messages[] = [
            'role' => 'user',
            'content' => $text,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->message = '';

        // Send to kernel-evolving API
        try {
            $evolvingUrl = rtrim(config('kernel-desktop.evolving.url', 'http://localhost:8779'), '/');
            $timeout = (int) config('kernel-desktop.evolving.timeout', 30);

            $response = Http::timeout($timeout)
                ->post($evolvingUrl . '/chat/message', [
                    'message' => $text,
                    'source' => 'kernel-desktop-v1',
                ]);

            if ($response->successful()) {
                $body = $response->json();
                $responseText = $body['response'] ?? $body['message'] ?? $body['text'] ?? 'No response';
            } else {
                $responseText = 'Error: kernel-evolving returned status ' . $response->status();
                Log::warning('Chat: kernel-evolving error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $responseText = '⚠️ kernel-evolving is not running (localhost:8779). Start the agent first.';
            Log::error('Chat: kernel-evolving unreachable', ['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            $responseText = '⚠️ An error occurred: ' . $e->getMessage();
            Log::error('Chat: unexpected error', ['error' => $e->getMessage()]);
        }

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $responseText,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function render()
    {
        return view('livewire.chat')
            ->layout('layouts.app');
    }
}
