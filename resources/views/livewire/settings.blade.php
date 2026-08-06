<div class="max-w-3xl mx-auto space-y-6">
    <h2 class="text-2xl font-bold text-white">Settings</h2>

    @if (session('saved'))
        <div class="bg-emerald-600/20 border border-emerald-700/30 text-emerald-400 rounded-lg px-4 py-3 text-sm">
            Settings saved successfully.
        </div>
    @endif

    <!-- Provider Configuration -->
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Provider Configuration</h3>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Task Inference Provider</label>
                <select wire:model="providerTaskInference"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                    <option value="openai">OpenAI</option>
                    <option value="openrouter">OpenRouter</option>
                    <option value="ollama">Ollama (Local)</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Synthesis Provider</label>
                <select wire:model="providerSynthesis"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                    <option value="openai">OpenAI</option>
                    <option value="openrouter">OpenRouter</option>
                    <option value="ollama">Ollama (Local)</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Critic Provider</label>
                <select wire:model="providerCritic"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                    <option value="openai">OpenAI</option>
                    <option value="openrouter">OpenRouter</option>
                    <option value="ollama">Ollama (Local)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Docker Management -->
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Docker Container</h3>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">Status</span>
                <span class="text-sm text-emerald-400">● Running</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">Container</span>
                <span class="text-sm text-gray-300">kernel-evolving</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">Port</span>
                <span class="text-sm text-gray-300">8779</span>
            </div>
            <div class="flex gap-2 mt-4">
                <button class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">
                    Restart
                </button>
                <button class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">
                    View Logs
                </button>
                <button class="px-4 py-2 bg-red-900/30 hover:bg-red-900/50 border border-red-800/30 rounded-lg text-xs font-medium text-red-400 transition-colors">
                    Stop
                </button>
            </div>
        </div>
    </div>

    <!-- Kernel-Central Pairing & Tunnel Status (KD-004) -->
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Kernel-Central Tunnel</h3>

        @if ($paired)
            {{-- Paired state --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">Pairing Status</span>
                    <span class="text-sm text-emerald-400">● Paired</span>
                </div>

                @if ($deviceId)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">Device ID</span>
                    <span class="text-sm text-gray-300 font-mono">#{{ $deviceId }}</span>
                </div>
                @endif

                @if ($centralUrl)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">Server</span>
                    <span class="text-sm text-gray-300 font-mono text-xs">{{ $centralUrl }}</span>
                </div>
                @endif

                {{-- Tunnel connection status --}}
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">Tunnel Status</span>
                    <span class="text-sm {{ $tunnelState === 'connected' ? 'text-emerald-400' : ($tunnelState === 'connecting' ? 'text-yellow-400' : 'text-red-400') }}">
                        @switch($tunnelState)
                            @case('connected')
                                ● Connected
                                @break
                            @case('connecting')
                                ◌ Connecting
                                @break
                            @case('reconnecting')
                                ◌ Reconnecting
                                @break
                            @case('error')
                                ✕ Error
                                @break
                            @default
                                ○ Disconnected
                        @endswitch
                    </span>
                </div>

                @if ($lastConnectedAt)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">Last Connected</span>
                    <span class="text-sm text-gray-300">{{ \Carbon\Carbon::parse($lastConnectedAt)->diffForHumans() }}</span>
                </div>
                @endif

                <p class="text-xs text-gray-500">
                    @if ($tunnelState === 'connected')
                        Mobile relay is active. kernel-mobile-v2 can reach kernel-evolving through kernel-central.
                    @elseif ($tunnelState === 'connecting' || $tunnelState === 'reconnecting')
                        Establishing tunnel connection. Auto-reconnect is active with exponential backoff.
                    @else
                        Tunnel is disconnected. Run <code class="bg-gray-800 px-1 py-0.5 rounded text-xs">php artisan tunnel:start</code>
                        to establish the WebSocket connection for mobile relay.
                    @endif
                </p>

                <div class="flex gap-2 mt-4">
                    <button wire:click="refreshTunnelStatus"
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">
                        Refresh Status
                    </button>
                    <button wire:click="unpair"
                            wire:confirm="Are you sure you want to unpair from kernel-central? Mobile relay will stop working."
                            class="px-4 py-2 bg-red-900/30 hover:bg-red-900/50 border border-red-800/30 rounded-lg text-xs font-medium text-red-400 transition-colors">
                        Unpair Device
                    </button>
                </div>
            </div>
        @else
            {{-- Unpaired state — show pairing form --}}
            <div class="space-y-4">
                <p class="text-sm text-gray-400">Pair this device with kernel-central to enable mobile relay for kernel-mobile-v2.</p>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Kernel-Central URL</label>
                    <input type="url" wire:model="pairCentralUrl"
                           placeholder="https://kernel-central.neverslave.com"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Device Name</label>
                    <input type="text" wire:model="pairDeviceName"
                           placeholder="kernel-desktop"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">API Token</label>
                    <input type="password" wire:model="pairApiToken"
                           placeholder="Paste your kernel-central Sanctum API token"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                    <p class="text-xs text-gray-500 mt-1">
                        Create a token at kernel-central → Settings → API Tokens
                    </p>
                </div>

                @if ($pairError)
                    <div class="bg-red-900/20 border border-red-800/30 text-red-400 rounded-lg px-4 py-3 text-sm">
                        {{ $pairError }}
                    </div>
                @endif

                <button wire:click="initiatePair" wire:loading.attr="disabled"
                        class="px-6 py-3 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 rounded-lg text-sm font-medium transition-colors">
                    @if ($pairingInProgress)
                        Pairing...
                    @else
                        Pair Device
                    @endif
                </button>
            </div>
        @endif
    </div>

    <!-- Appearance -->
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Appearance</h3>
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Theme</label>
            <select wire:model="theme"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                <option value="dark">Dark</option>
                <option value="light">Light</option>
                <option value="system">System</option>
            </select>
        </div>
    </div>

    <!-- Save Button -->
    <div class="flex justify-end">
        <button wire:click="save"
                class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm font-medium transition-colors">
            Save Settings
        </button>
    </div>
</div>
