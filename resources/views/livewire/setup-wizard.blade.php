<div class="max-w-2xl mx-auto py-8"
     wire:poll.3000ms="{{ $pollInstall ? 'pollInstallLog' : ($pollStart ? 'pollStartLog' : ($pollHealth ? 'pollApiHealth' : null)) }}">
    <!-- Progress Bar -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-2">
            @foreach (['Welcome', 'System Check', 'Install', 'Configure', 'Start', 'Pair', 'Done'] as $i => $label)
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
        <div class="text-center text-sm text-gray-500">{{ ['Welcome!', 'System Check', 'Install Agent', 'Configure', 'Start Agent', 'Pair Device', 'All Done!'][$step - 1] }}</div>
    </div>

    @if ($errorMessage)
        <div class="bg-red-900/30 border border-red-800/50 text-red-400 rounded-lg px-4 py-3 text-sm mb-4">
            ⚠️ {{ $errorMessage }}
        </div>
    @endif

    <!-- Step Content -->
    <div class="bg-gray-900 rounded-xl p-8 border border-gray-800">
        @switch($step)
            @case(1)
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-white mb-4">Welcome to Kernel Desktop</h2>
                    <p class="text-gray-400 mb-6">
                        This wizard will install and start the kernel-evolving agent on your machine.
                        The agent runs locally on port 8779 and powers the entire dashboard.
                    </p>
                    <div class="grid grid-cols-3 gap-4 mb-8 text-center">
                        <div class="bg-gray-800 rounded-lg p-4">
                            <div class="text-2xl mb-1">🧬</div>
                            <div class="text-xs text-gray-400">Self-Evolving Agent</div>
                        </div>
                        <div class="bg-gray-800 rounded-lg p-4">
                            <div class="text-2xl mb-1">🐳</div>
                            <div class="text-xs text-gray-400">Docker or Bare Metal</div>
                        </div>
                        <div class="bg-gray-800 rounded-lg p-4">
                            <div class="text-2xl mb-1">📊</div>
                            <div class="text-xs text-gray-400">Live Dashboard</div>
                        </div>
                    </div>

                    @if ($apiAlreadyRunning)
                        <div class="bg-emerald-900/30 border border-emerald-800/50 rounded-lg p-4 mb-6">
                            <p class="text-emerald-400 font-medium">✅ kernel-evolving is already running on port 8779!</p>
                            <p class="text-gray-500 text-sm mt-1">You can skip setup and go straight to the dashboard.</p>
                        </div>
                        <button wire:click="finish"
                                class="px-8 py-3 bg-emerald-600 hover:bg-emerald-500 rounded-lg font-medium transition-colors">
                            Go to Dashboard 🚀
                        </button>
                    @else
                        <button wire:click="nextStep"
                                class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 rounded-lg font-medium transition-colors">
                            Get Started
                        </button>
                    @endif
                </div>
                @break

            @case(2)
                <div>
                    <h2 class="text-xl font-bold text-white mb-4">System Check</h2>
                    <p class="text-gray-400 mb-6">Detecting Docker, GPU, and existing installations.</p>

                    <!-- Docker -->
                    <div class="bg-gray-800 rounded-lg p-4 mb-3">
                        <div class="flex items-center gap-3">
                            @if ($dockerStatus['installed'] ?? false)
                                <span class="w-3 h-3 bg-emerald-500 rounded-full"></span>
                                <span class="text-emerald-400">Docker {{ $dockerStatus['version'] ?? '' }}</span>
                            @else
                                <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                                <span class="text-red-400">Docker not detected</span>
                            @endif
                        </div>
                        @if ($dockerStatus['installed'] ?? false)
                            <p class="text-gray-500 text-sm mt-1">
                                Daemon: {{ ($dockerStatus['running'] ?? false) ? '✅ Running' : '⚠️ Not running — start Docker Desktop' }}
                            </p>
                        @else
                            <p class="text-gray-500 text-sm mt-1">
                                Install from <a href="https://docker.com" class="text-emerald-400 hover:underline">docker.com</a>
                            </p>
                        @endif
                    </div>

                    <!-- Docker Compose -->
                    <div class="bg-gray-800 rounded-lg p-4 mb-3">
                        <div class="flex items-center gap-3">
                            @if ($dockerComposeStatus['available'] ?? false)
                                <span class="w-3 h-3 bg-emerald-500 rounded-full"></span>
                                <span class="text-emerald-400">Docker Compose available</span>
                            @else
                                <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                                <span class="text-yellow-400">Docker Compose not found (needed for Docker mode)</span>
                            @endif
                        </div>
                    </div>

                    <!-- GPU -->
                    <div class="bg-gray-800 rounded-lg p-4 mb-3">
                        <div class="flex items-center gap-3">
                            @if ($gpuStatus['available'] ?? false)
                                <span class="w-3 h-3 bg-emerald-500 rounded-full"></span>
                                <span class="text-emerald-400">GPU: {{ $gpuStatus['name'] ?? 'Unknown' }} ({{ $gpuStatus['vram_gb'] ?? 0 }} GB VRAM)</span>
                            @else
                                <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                                <span class="text-yellow-400">No NVIDIA GPU detected — cloud inference only</span>
                            @endif
                        </div>
                        @if (!($gpuStatus['available'] ?? false))
                            <p class="text-gray-500 text-sm mt-1">
                                Without a GPU, the agent will use cloud providers (OpenAI/Anthropic) for inference.
                            </p>
                        @endif
                    </div>

                    <!-- Install mode selector -->
                    <div class="bg-gray-800 rounded-lg p-4 mb-6">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Installation Mode</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label @class([
                                'border rounded-lg p-3 cursor-pointer text-center text-sm',
                                'border-emerald-600 bg-emerald-600/10 text-emerald-400' => $installMode === 'docker',
                                'border-gray-700 text-gray-400' => $installMode !== 'docker',
                            ])>
                                <input type="radio" wire:model="installMode" value="docker" class="hidden">
                                🐳 Docker<br><span class="text-xs">Recommended — isolated container</span>
                            </label>
                            <label @class([
                                'border rounded-lg p-3 cursor-pointer text-center text-sm',
                                'border-emerald-600 bg-emerald-600/10 text-emerald-400' => $installMode === 'bare-metal',
                                'border-gray-700 text-gray-400' => $installMode !== 'bare-metal',
                            ])>
                                <input type="radio" wire:model="installMode" value="bare-metal" class="hidden">
                                🖥️ Bare Metal<br><span class="text-xs">Direct install — faster, needs Python 3.11+</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button wire:click="detectSystem"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors">
                            ⟳ Recheck
                        </button>
                        <button wire:click="nextStep"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors">
                            Continue →
                        </button>
                    </div>
                </div>
                @break

            @case(3)
                <div>
                    <h2 class="text-xl font-bold text-white mb-4">Install Agent</h2>
                    <p class="text-gray-400 mb-4">
                        @if ($installMode === 'docker')
                            Cloning the kernel-evolving repository to prepare the Docker Compose context.
                        @else
                            Cloning the repository, creating a Python venv, and installing dependencies via <code class="text-emerald-400">install.sh</code>. This may take 3–8 minutes.
                        @endif
                    </p>

                    @if ($installing || $pollInstall)
                        <div class="flex items-center gap-2 text-sm text-emerald-400 mb-3">
                            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            Installing… (output streams below)
                        </div>
                    @endif

                    @if ($installLog)
                        <div id="install-log"
                             class="bg-gray-950 rounded-lg p-4 mb-4 font-mono text-xs text-gray-400 max-h-64 overflow-y-auto whitespace-pre-wrap"
                             x-data
                             x-init="$el.scrollTop = $el.scrollHeight"
                             x-effect="$el.scrollTop = $el.scrollHeight">{{ $installLog }}</div>
                    @endif

                    @if ($installed)
                        <div class="bg-emerald-900/30 border border-emerald-800/50 rounded-lg p-3 mb-4">
                            <p class="text-emerald-400 font-medium text-sm">✅ Installation complete!</p>
                        </div>
                    @endif

                    <div class="flex gap-3">
                        <button wire:click="previousStep" @disabled($installing || $pollInstall)
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors disabled:opacity-40">
                            ← Back
                        </button>
                        @if (!$installed)
                            <button wire:click="installAgent" wire:loading.attr="disabled"
                                    @disabled($pollInstall)
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors disabled:opacity-40">
                                <span wire:loading wire:target="installAgent">Starting…</span>
                                <span wire:loading.remove wire:target="installAgent">
                                    {{ $pollInstall ? 'Installing…' : 'Install Now' }}
                                </span>
                            </button>
                        @else
                            <button wire:click="nextStep"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors">
                                Continue →
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
                        @if ($installMode === 'bare-metal')
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Install Directory</label>
                                <input type="text" wire:model="installDir"
                                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                            </div>

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
                                <label class="block text-sm font-medium text-gray-300 mb-1">Inference Mode</label>
                                <select wire:model="defaultModel"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                                    <option value="local">Local (Nemotron-3B — requires GPU)</option>
                                    <option value="cloud">Cloud (OpenAI — no GPU needed)</option>
                                </select>
                            </div>
                        @else
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Repo Directory (Docker context)</label>
                                <input type="text" wire:model="dockerRepoDir"
                                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                                <p class="text-xs text-gray-600 mt-1">kernel-evolving will be cloned here for the docker-compose context.</p>
                            </div>

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
                                <label class="block text-sm font-medium text-gray-300 mb-1">Inference Mode</label>
                                <select wire:model="defaultModel"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                                    <option value="local">Local (Nemotron-3B — requires GPU)</option>
                                    <option value="cloud">Cloud (OpenAI — no GPU needed)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Model Cache Path (optional)</label>
                                <input type="text" wire:model="modelsPath" placeholder="~/.cache/huggingface"
                                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                                <p class="text-xs text-gray-600 mt-1">Mounted as the HuggingFace model cache inside the container.</p>
                            </div>
                            </div>
                        @endif

                        <div class="border-t border-gray-800 pt-4">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-3">AI Providers (for Tier 2 synthesis)</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">OpenAI API Key</label>
                            <input type="password" wire:model="openaiKey" placeholder="sk-..."
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Anthropic API Key (optional)</label>
                            <input type="password" wire:model="anthropicKey" placeholder="sk-ant-..."
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">GitHub Token (optional)</label>
                            <input type="password" wire:model="githubToken" placeholder="ghp_..."
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">HuggingFace Token (optional)</label>
                            <input type="password" wire:model="hfToken" placeholder="hf_..."
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                        </div>

                        <div class="border-t border-gray-800 pt-4">
                            <p class="text-xs text-gray-500 uppercase tracking-wide mb-3">Telegram (optional — for bot control)</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Telegram Bot Token</label>
                            <input type="password" wire:model="telegramBotToken" placeholder="123456:ABC-DEF..."
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Your Name</label>
                                <input type="text" wire:model="userName" placeholder="Fabio"
                                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Telegram Chat ID</label>
                                <input type="text" wire:model="telegramChatId" placeholder="123456789"
                                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-300">
                            <input type="checkbox" wire:model="evolutionEnabled" class="rounded border-gray-700 bg-gray-800 text-emerald-600">
                            Enable autonomous skill evolution
                        </label>
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
                    <h2 class="text-xl font-bold text-white mb-4">Start Agent</h2>
                    <p class="text-gray-400 mb-4">
                        @if ($installMode === 'docker')
                            Building and starting the kernel-evolving container via <code class="text-emerald-400">docker compose up --build</code>. First build may take several minutes.
                        @else
                            Starting the model server and API via <code class="text-emerald-400">start.sh</code>. Model loading takes ~60–90s.
                        @endif
                    </p>

                    @if ($starting || $pollStart)
                        <div class="flex items-center gap-2 text-sm text-emerald-400 mb-3">
                            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            Starting… watching for health check
                        </div>
                    @endif

                    @if ($startLog)
                        <div id="start-log"
                             class="bg-gray-950 rounded-lg p-4 mb-4 font-mono text-xs text-gray-400 max-h-64 overflow-y-auto whitespace-pre-wrap"
                             x-data
                             x-init="$el.scrollTop = $el.scrollHeight"
                             x-effect="$el.scrollTop = $el.scrollHeight">{{ $startLog }}</div>
                    @endif

                    @if ($started)
                        <div class="bg-emerald-900/30 border border-emerald-800/50 rounded-lg p-4 mb-4">
                            <p class="text-emerald-400 font-medium text-sm">✅ kernel-evolving is running on port {{ \App\Services\KernelEvolvingService::PORT }}!</p>
                        </div>
                    @endif

                    <div class="flex gap-3">
                        <button wire:click="previousStep" @disabled($starting || $pollStart)
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors disabled:opacity-40">
                            ← Back
                        </button>
                        @if (!$started)
                            <button wire:click="startAgent" wire:loading.attr="disabled"
                                    @disabled($pollStart)
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors disabled:opacity-40">
                                <span wire:loading wire:target="startAgent">Starting…</span>
                                <span wire:loading.remove wire:target="startAgent">
                                    {{ $pollStart ? 'Waiting for health…' : 'Start Agent' }}
                                </span>
                            </button>
                        @else
                            <button wire:click="nextStep"
                                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm transition-colors">
                                Continue →
                            </button>
                        @endif
                    </div>
                </div>
                @break

            @case(6)
                <div>
                    <h2 class="text-xl font-bold text-white mb-4">Pair with Kernel-Central</h2>
                    <p class="text-gray-400 mb-6">Connect this device to kernel-central for remote access, message relay, and mobile pairing.</p>

                    @if ($paired)
                        {{-- Paired state --}}
                        <div class="bg-emerald-900/30 border border-emerald-800/50 rounded-lg p-6 mb-6 text-center">
                            <span class="text-4xl">✅</span>
                            <p class="text-emerald-400 mt-2 font-medium text-lg">Device paired successfully!</p>
                            <p class="text-gray-500 text-sm mt-1">This device is registered with kernel-central. Remote access and message relay are active.</p>
                        </div>

                        <div class="bg-gray-800 rounded-lg p-4 mb-6 text-sm text-gray-400 space-y-1">
                            <p><span class="text-gray-300">Central URL:</span> {{ $centralUrl }}</p>
                            <p><span class="text-gray-300">Device name:</span> {{ config('kernel-desktop.device.name', 'kernel-desktop') }}</p>
                        </div>
                    @else
                        {{-- Pairing form --}}
                        <div class="bg-gray-800 rounded-lg p-6 mb-6 space-y-4">
                            <p class="text-sm text-gray-400">
                                To pair this device, you need an API token from kernel-central.
                                Visit <span class="text-emerald-400">{{ rtrim($centralUrl ?: 'https://kernel-central.neverslave.com', '/') }}/settings/tokens</span>
                                to create one, then paste it below.
                            </p>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Kernel-Central URL</label>
                                <input type="url" wire:model="centralUrl" placeholder="https://kernel-central.neverslave.com"
                                       class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                                <p class="text-xs text-gray-600 mt-1">The URL of your kernel-central server.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">API Token</label>
                                <input type="password" wire:model="apiToken" placeholder="kc_..."
                                       class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                                <p class="text-xs text-gray-600 mt-1">Create this in kernel-central Settings → API Tokens.</p>
                            </div>

                            {{-- Pairing status --}}
                            @if ($pairingLabel || $pairing)
                                <div class="flex items-center gap-2 text-sm">
                                    @if ($pairing)
                                        <span class="w-4 h-4 border-2 border-emerald-400 border-t-transparent rounded-full animate-spin"></span>
                                        <span class="text-emerald-400">{{ $pairingLabel }}</span>
                                    @elseif ($paired)
                                        <span class="text-emerald-400">{{ $pairingLabel }}</span>
                                    @endif
                                </div>
                            @endif

                            <div class="flex gap-2">
                                <button wire:click="pairDevice" wire:loading.attr="disabled"
                                        class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="pairDevice">🔗 Pair Device</span>
                                    <span wire:loading wire:target="pairDevice">Pairing…</span>
                                </button>
                            </div>

                            <p class="text-xs text-gray-600">Skip this step if you only want local access. You can pair later from Settings.</p>
                        </div>
                    @endif

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
                        @else
                            <button wire:click="nextStep"
                                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors">
                                Skip →
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
                        <p class="text-gray-400">📊 <span class="text-gray-300">Dashboard:</span> <a href="{{ route('dashboard') }}" class="text-emerald-400 hover:underline">Evolution tab</a></p>
                        <p class="text-gray-400">💬 <span class="text-gray-300">Chat:</span> Open the <span class="text-emerald-400">Agent tab</span> in the sidebar</p>
                        <p class="text-gray-400">🔌 <span class="text-gray-300">API:</span> <code class="text-emerald-400">http://localhost:8779</code></p>
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
