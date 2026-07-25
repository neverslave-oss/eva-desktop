<?php

namespace App\Livewire;

use Livewire\Component;

class RoutinesTab extends Component
{
    public function render()
    {
        return view('livewire.routines-tab')
            ->layout('layouts.app');
    }
}
