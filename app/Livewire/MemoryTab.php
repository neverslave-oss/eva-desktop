<?php

namespace App\Livewire;

use Livewire\Component;

class MemoryTab extends Component
{
    public function render()
    {
        return view('livewire.memory-tab')
            ->layout('layouts.app');
    }
}
