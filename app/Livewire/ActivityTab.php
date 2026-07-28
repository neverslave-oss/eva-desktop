<?php

namespace App\Livewire;

use Livewire\Component;

class ActivityTab extends Component
{
    public function render()
    {
        return view('livewire.activity-tab')
            ->layout('layouts.app');
    }
}
