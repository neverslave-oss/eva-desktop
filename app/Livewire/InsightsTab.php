<?php

namespace App\Livewire;

use Livewire\Component;

class InsightsTab extends Component
{
    public function render()
    {
        return view('livewire.insights-tab')
            ->layout('layouts.app');
    }
}
