<?php

namespace App\Livewire;

use Livewire\Component;

class SqliteViewer extends Component
{
    public function render()
    {
        return view('livewire.sqlite-viewer')
            ->layout('layouts.app');
    }
}
