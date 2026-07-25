<div class="max-w-2xl mx-auto py-8">
    <!-- Progress Bar -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-2">
            @foreach (['Welcome', 'Docker', 'Pull Image', 'Configure', 'Start', 'Pair', 'Done'] as $i => $label)
                <div class="flex items-center">
                    <div @class([
                        'w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold',
                        'bg-emerald-600 text-white' => $step > $i + 1,
                        'bg-emerald-600 text-white ring-2 ring-emerald-400' => $step === $i + 1,
                        'bg-gray-800 text-gray-500' => $step < $i + 1,
                    ])>
                        {{ $i + 1 }}
                    </div>
                    @if ($i < 6)
                        <div @class([
                            'h-1 w-12 sm:w-16 mx-1 rounded',
                            'bg-emerald-600' => $step > $i + 1,
                            'bg-gray-800' => $step <= $i + 1,
                        ])></div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="text-center text-sm text-gray-500">{{ ['Welcome!', 'Docker Check', 'Pull Image', 'Configure', 'Start Container', 'Pair Device', 'All Done!'][$step - 1] }}</div>
    </div>

    <!-- Step Content -->
    <div class="bg-gray-900 rounded-xl p-8 border border-gray-800">
        @switch($step)
            @case(1)
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-white mb-4">Welcome to Kernel Desktop</h2>
                    <p class="text-gray-400 mb-6">
                        This wizard will set up the kernel-evolving agent on your machine.
                        You'll need Docker and a few minutes to get started.
                    </p>
                    <div class="grid grid-cols-3 gap-4 mb-8 text-center">
                        <div class="bg-gray-800 rounded-lg p-4">
                            <div class="text-2xl mb-1">🐳</div>
                            <div class="text-xs text-gray-400">Docker</div>
                        </div>
                        <div class="bg-gray-800 rounded-lg p-4">
                            <div class="text-2xl mb-1">🧠</div>
                            <div class="text-xs text-gray-400">AI Agent</div>
                        </div>
                        <div class="bg-gray-800 rounded-lg p-4">
                            <div class="text-2xl mb-1">🔗</div>
                            <div class="text-xs text-gray-400">Cloud Pairing</div>
                        </div>
                    </div>
                    <button wire:click="nextStep"
                            class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 rounded-lg font-medium transition-colors">
                        Get Started
                    </button>
                </div>
                @break

            @case(2)
                <div>
                    <h2 class="text-xl font-bold text-white mb-4">Docker Detection</h2>
                    <p class="text-gray-400 mb-6">Checking if Docker is installed and running on your system.</p>

                    <div class="bg-gray-800 rounded-lg p-4 mb-6">
                        <div class="flex items-center gap-3">
                            @if ($dockerDetected)
                                <span class="w-3 h-3 bg-emerald-500 rounded-full"></span>
                                <span class="text-emerald-400">Docker is installed and running</span>
                            @else
                                <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                                <span class="text-red-400">Docker not detected</span>
                            @endif
                        </div>
                        <p class="text-gray-500 text-sm mt-2">
                            @unless ($dockerDetected)
                                Please install Docker Desktop from <a href="https://docker.com" class="text-emerald-400 hover:underline">docker.com</a>
                                and make sure the daemon is running, then click "Recheck".
                            @else
                                All good! Docker is ready to use.
                            @endunless
                        </p>
                    </div>

                    <div class="flex gap-3">
                        <button wire:click="checkDocker"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors">
                            ⟳ Recheck
                        </button>
                        @if ($dockerDetected)
                            <button wire:click="nextStep"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors">
                                Continue →
                            </button>
                        @endif
                    </div>
                </div>
                @break

            @case(3)
                <div>
                    <h2 class="text-xl font-bold text-white mb-4">Pull Kernel-Evolving Image</h2>
                    <p class="text-gray-400 mb-6">Downloading the kernel-evolving Docker image. This may take a few minutes.</p>

                    <div class="bg-gray-800 rounded-lg p-4 mb-6">
                        <div class="flex items-center gap-3 mb-2">
                            <svg class="w-5 h-5 text-emerald-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <span class="text-sm text-gray-300">Pulling fabiopacifici/kernel-evolving:latest...</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="bg-emerald-600 h-2 rounded-full" style="width: {{ $imagePulled ? '100' : '45' }}%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">{{ $imagePulled ? 'Image pulled successfully!' : 'Downloading layers...' }}</p>
                    </div>

                    <div class="flex gap-3">
                        <button wire:click="previousStep"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors">
                            ← Back
                        </button>
                        @if ($imagePulled)
                            <button wire:click="nextStep"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors">
                                Continue →
                            </button>
                        @else
                            <button wire:click="pullImage"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors">
                                Pull Image
                            </button>
                        @endif
                    </div>
                </div>
                @break

            @case(4)
                <div>
                    <h2 class="text-xl font-bold text-white mb-4">Configuration</h2>
                    <p class="text-gray-400 mb-6">Configure your kernel-evolving instance.</p>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">VRAM Allocation (GB)</label>
                            <select wire:model="vram"
                                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                                <option value="4">4 GB</option>
                                <option value="8">8 GB</option>
                                <option value="12">12 GB</option>
                                <option value="16">16 GB</option>
                                <option value="24">24 GB</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Default Model</label>
                            <select wire:model="defaultModel"
                                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                                <option value="nemotron-3b">Nemotron-3B (Balanced)</option>
                                <option value="gemma-4-e2b">Gemma 4 E2B (Fast)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">OpenAI API Key (optional)</label>
                            <input type="password" wire:model="openaiKey" placeholder="sk-..."
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">OpenRouter API Key (optional)</label>
                            <input type="password" wire:model="openrouterKey" placeholder="sk-or-..."
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button wire:click="previousStep"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors">
                            ← Back
                        </button>
                        <button wire:click="nextStep"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors">
                            Continue →
                        </button>
                    </div>
                </div>
                @break

            @case(5)
                <div>
                    <h2 class="text-xl font-bold text-white mb-4">Start Container</h2>
                    <p class="text-gray-400 mb-6">Launching the kernel-evolving container...</p>

                    <div class="bg-gray-800 rounded-lg p-4 mb-6 font-mono text-sm">
                        <p class="text-gray-500">$ docker run -d --name kernel-evolving -p 8779:8779 ...</p>
                        @if ($containerRunning)
                            <p class="text-emerald-400 mt-2">✓ Container started on port 8779</p>
                            <p class="text-gray-500 text-xs mt-1">Health check: OK</p>
                        @else
                            <p class="text-yellow-400 mt-2">⏳ Starting...</p>
                        @endif
                    </div>

                    <div class="flex gap-3">
                        <button wire:click="previousStep"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors">
                            ← Back
                        </button>
                        @if ($containerRunning)
                            <button wire:click="nextStep"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors">
                                Continue →
                            </button>
                        @else
                            <button wire:click="startContainer"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors">
                                Start Container
                            </button>
                        @endif
                    </div>
                </div>
                @break

            @case(6)
                <div>
                    <h2 class="text-xl font-bold text-white mb-4">Pair with Kernel-Central</h2>
                    <p class="text-gray-400 mb-6">Connect your local instance to kernel-central for remote access via mobile.</p>

                    <div class="bg-gray-800 rounded-lg p-4 mb-6">
                        @if ($paired)
                            <div class="text-center">
                                <span class="text-4xl">✅</span>
                                <p class="text-emerald-400 mt-2 font-medium">Device paired successfully!</p>
                                <p class="text-gray-500 text-sm mt-1">Your mobile app can now reach this instance.</p>
                            </div>
                        @else
                            <div class="space-y-4">
                                <p class="text-sm text-gray-400">Click "Pair Now" to generate a pairing token and link this device to your kernel-central account.</p>
                                <button wire:click="pairDevice"
                                        class="w-full px-4 py-3 bg-indigo-600 hover:bg-indigo-500 rounded-lg text-sm font-medium transition-colors">
                                    🔗 Pair Now
                                </button>
                            </div>
                        @endif
                    </div>

                    <div class="flex gap-3">
                        <button wire:click="previousStep"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors">
                            ← Back
                        </button>
                        @if ($paired)
                            <button wire:click="nextStep"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors">
                                Continue →
                            </button>
                        @endif
                    </div>
                </div>
                @break

            @case(7)
                <div class="text-center">
                    <span class="text-6xl mb-4 block">🎉</span>
                    <h2 class="text-2xl font-bold text-white mb-4">All Set!</h2>
                    <p class="text-gray-400 mb-6">
                        Kernel-evolving is running and ready to use.
                        Your dashboard is waiting for you.
                    </p>

                    <div class="bg-gray-800 rounded-lg p-4 mb-6 text-left text-sm space-y-2">
                        <p class="text-gray-400">📊 <span class="text-gray-300">Dashboard:</span> <a href="http://localhost:8779/evolution/dashboard" class="text-emerald-400 hover:underline" target="_blank">http://localhost:8779/evolution/dashboard</a></p>
                        <p class="text-gray-400">💬 <span class="text-gray-300">Chat:</span> Open the <span class="text-emerald-400">Chat tab</span> in the sidebar</p>
                        <p class="text-gray-400">📱 <span class="text-gray-300">Mobile:</span> Use kernel-mobile on your phone</p>
                    </div>

                    <button wire:click="finish"
                            class="px-8 py-3 bg-emerald-600 hover:bg-emerald-500 rounded-lg font-medium transition-colors">
                        Launch Dashboard 🚀
                    </button>
                </div>
                @break
        @endswitch
    </div>
</div>
