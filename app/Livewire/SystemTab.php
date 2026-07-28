<?php

namespace App\Livewire;

use Livewire\Component;

class SystemTab extends Component
{
    public function render()
    {
        return view('livewire.system-tab')
            ->layout('layouts.app');
    }
}
