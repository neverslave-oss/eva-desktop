<?php

namespace App\Livewire;

use Livewire\Component;

class SkillsTab extends Component
{
    public function render()
    {
        return view('livewire.skills-tab')
            ->layout('layouts.app');
    }
}
