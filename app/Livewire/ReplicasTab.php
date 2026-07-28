<?php

namespace App\Livewire;

use Livewire\Component;

class ReplicasTab extends Component
{
    public function render()
    {
        return view('livewire.replicas-tab')
            ->layout('layouts.app');
    }
}
