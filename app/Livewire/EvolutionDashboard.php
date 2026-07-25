<?php

namespace App\Livewire;

use Livewire\Component;

class EvolutionDashboard extends Component
{
    public function render()
    {
        return view('livewire.evolution-dashboard')
            ->layout('layouts.app');
    }
}
