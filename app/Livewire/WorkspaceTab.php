<?php

namespace App\Livewire;

use Livewire\Component;

class WorkspaceTab extends Component
{
    public function render()
    {
        return view('livewire.workspace-tab')
            ->layout('layouts.app');
    }
}
