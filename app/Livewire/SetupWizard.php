<?php

namespace App\Livewire;

use Livewire\Component;

class SetupWizard extends Component
{
    public int $step = 1;
    public bool $dockerDetected = false;
    public bool $imagePulled = false;
    public bool $containerRunning = false;
    public bool $paired = false;

    // Configuration
    public string $vram = '8';
    public string $defaultModel = 'nemotron-3b';
    public string $openaiKey = '';
    public string $openrouterKey = '';

    public function mount()
    {
        $this->checkDocker();
    }

    public function checkDocker()
    {
        $output = [];
        $returnCode = 0;
        exec('docker --version 2>&1', $output, $returnCode);
        $this->dockerDetected = $returnCode === 0 && str_contains($output[0] ?? '', 'Docker');
    }

    public function nextStep()
    {
        if ($this->step < 7) {
            $this->step++;
        }
    }

    public function previousStep()
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function pullImage()
    {
        // Placeholder: triggers image pull
        $this->imagePulled = true;
    }

    public function startContainer()
    {
        // Placeholder: launches the kernel-evolving container
        $this->containerRunning = true;
    }

    public function pairDevice()
    {
        // Placeholder: pairs with kernel-central
        $this->paired = true;
    }

    public function finish()
    {
        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.setup-wizard')
            ->layout('layouts.app');
    }
}
