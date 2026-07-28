<?php

namespace App\Livewire;

use Livewire\Component;

class VoiceTab extends Component
{
    public function render()
    {
        return view('livewire.voice-tab')
            ->layout('layouts.app');
    }
}
