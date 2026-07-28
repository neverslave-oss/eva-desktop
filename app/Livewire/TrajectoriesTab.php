<?php

namespace App\Livewire;

use Livewire\Component;

class TrajectoriesTab extends Component
{
    public function render()
    {
        return view('livewire.trajectories-tab')
            ->layout('layouts.app');
    }
}
