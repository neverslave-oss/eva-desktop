<?php

namespace App\Livewire;

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

        // Add user message
        $this->messages[] = [
            'role' => 'user',
            'content' => $this->message,
            'timestamp' => now()->toIso8601String(),
        ];

        // Placeholder: send to kernel-evolving API and get response
        // $this->messages[] = [
        //     'role' => 'assistant',
        //     'content' => $response,
        //     'timestamp' => now()->toIso8601String(),
        // ];

        $this->message = '';
    }

    public function render()
    {
        return view('livewire.chat')
            ->layout('layouts.app');
    }
}
