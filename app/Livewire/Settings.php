<?php

namespace App\Livewire;

use Livewire\Component;

class Settings extends Component
{
    public string $providerTaskInference = 'openai';
    public string $providerSynthesis = 'openai';
    public string $providerCritic = 'openai';
    public string $theme = 'dark';
    public bool $paired = false;

    public function mount()
    {
        // Load settings from config/database
    }

    public function save()
    {
        // Placeholder: persist settings
        session()->flash('saved', true);
    }

    public function render()
    {
        return view('livewire.settings')
            ->layout('layouts.app');
    }
}
