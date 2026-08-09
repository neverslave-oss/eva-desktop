<div>
    <div class="max-w-4xl mx-auto" x-data="{ tab: '{{ request()->get('tab', 'core') }}' }">
        <h2 class="text-2xl font-bold text-white mb-6">Settings</h2>

        @if (session('saved'))
        <div class="bg-emerald-600/20 border border-emerald-700/30 text-emerald-400 rounded-lg px-4 py-3 text-sm mb-4">
            Settings saved successfully.
        </div>
        @endif

        {{-- Tab Bar --}}
        <div class="flex gap-1 mb-6 border-b border-gray-800 overflow-x-auto">
            @foreach ([
            ['key' => 'core', 'label' => 'Core'],
            ['key' => 'providers', 'label' => 'Providers'],
            ['key' => 'models', 'label' => 'Model Storage'],
            ['key' => 'github', 'label' => 'GitHub'],
            ['key' => 'channels', 'label' => 'Channels'],
            ['key' => 'appearance', 'label' => 'Appearance & Updates'],
            ] as $t)
            <button @click="tab = '{{ $t['key'] }}'"
                :class="tab === '{{ $t['key'] }}' ? 'border-b-2 border-emerald-500 text-emerald-400' : 'text-gray-500 hover:text-gray-300'"
                class="px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors">
                {{ $t['label'] }}
            </button>
            @endforeach
        </div>

        {{-- ══ CORE ══════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'core'" class="space-y-6">

            {{-- Eva Agent --}}
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h3 class="text-lg font-semibold text-white mb-1">Eva Agent</h3>
                <p class="text-xs text-gray-500 mb-4">Controls the local Eva agent (kernel-evolving).</p>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-400">Status</span>
                        <span class="text-sm text-gray-500" id="agent-status-label">● Checking…</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-400">Skills / Routines</span>
                        <span class="text-sm text-gray-400" id="agent-skills-label">—</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-400">Active model</span>
                        <span class="text-sm text-gray-400 font-mono text-xs" id="agent-model-label">—</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-400">VRAM free</span>
                        <span class="text-sm text-gray-400" id="agent-vram-label">—</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-400">Port</span>
                        <span class="text-sm text-gray-300 font-mono">8779</span>
                    </div>
                    <hr class="border-gray-800">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-400">Compute Mode</span>
                            <span class="text-xs text-gray-500 font-mono" id="agent-mode-label">—</span>
                        </div>
                        <p class="text-xs text-gray-500 mb-2">Local runs on this machine's GPU/VRAM; Cloud routes inference to a connected provider.</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <button id="agent-mode-local-btn" onclick="setComputeMode('local')"
                                class="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-xs font-medium transition-colors">🏠 Local</button>
                            <button id="agent-mode-cloud-btn" onclick="setComputeMode('cloud')"
                                class="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-xs font-medium transition-colors">☁️ Cloud</button>
                            <select id="agent-cloud-provider" onchange="setComputeMode('cloud')"
                                class="bg-gray-800 border border-gray-700 rounded-lg px-2 py-2 text-gray-200 text-xs">
                                <option value="openai">OpenAI</option>
                                <option value="anthropic">Anthropic</option>
                                <option value="hf">HuggingFace</option>
                                <option value="copilot">GitHub Copilot</option>
                                <option value="openrouter">OpenRouter</option>
                            </select>
                        </div>
                        <div id="agent-mode-msg" class="text-xs text-gray-500 mt-2 min-h-[1.2em]"></div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <button onclick="agentControl('start')" class="px-4 py-2 bg-emerald-800/40 hover:bg-emerald-700/50 border border-emerald-700/40 rounded-lg text-xs font-medium text-emerald-400 transition-colors">▶ Start</button>
                        <button onclick="agentControl('stop')" class="px-4 py-2 bg-red-900/30 hover:bg-red-900/50 border border-red-800/30 rounded-lg text-xs font-medium text-red-400 transition-colors">■ Stop</button>
                        <button onclick="agentControl('restart')" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">↺ Restart</button>
                        <button onclick="agentControl('logs')" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">📋 Logs</button>
                        <button onclick="refreshAgentStatus()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">↺ Status</button>
                    </div>
                    <div id="agent-action-msg" class="text-xs text-gray-500 mt-1 min-h-[1.2em]"></div>
                    <div id="agent-info-output" style="display:none;" class="mt-2 p-3 bg-gray-950 border border-gray-800 rounded-lg font-mono text-xs text-gray-400 max-h-64 overflow-y-auto whitespace-pre-wrap"></div>
                </div>
            </div>

            {{-- Kernel-Central Tunnel --}}
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Kernel-Central Tunnel</h3>
                @if ($paired)
                <div class="space-y-3">
                    <div class="flex items-center justify-between"><span class="text-sm text-gray-400">Pairing Status</span><span class="text-sm text-emerald-400">● Paired</span></div>
                    @if ($deviceId)<div class="flex items-center justify-between"><span class="text-sm text-gray-400">Device ID</span><span class="text-sm text-gray-300 font-mono">#{{ $deviceId }}</span></div>@endif
                    @if ($centralUrl)<div class="flex items-center justify-between"><span class="text-sm text-gray-400">Server</span><span class="text-sm text-gray-300 font-mono text-xs">{{ $centralUrl }}</span></div>@endif
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-400">Tunnel Status</span>
                        <span class="text-sm {{ $tunnelState === 'connected' ? 'text-emerald-400' : ($tunnelState === 'connecting' ? 'text-yellow-400' : 'text-red-400') }}">
                            @switch($tunnelState)
                            @case('connected') ● Connected @break
                            @case('connecting') ◌ Connecting @break
                            @case('reconnecting') ◌ Reconnecting @break
                            @case('error') ✕ Error @break
                            @default ○ Disconnected
                            @endswitch
                        </span>
                    </div>
                    @if ($lastConnectedAt)<div class="flex items-center justify-between"><span class="text-sm text-gray-400">Last Connected</span><span class="text-sm text-gray-300">{{ \Carbon\Carbon::parse($lastConnectedAt)->diffForHumans() }}</span></div>@endif
                    <div class="flex flex-wrap gap-2 mt-3">
                        <button onclick="tunnelControl('start')" class="px-4 py-2 bg-emerald-800/40 hover:bg-emerald-700/50 border border-emerald-700/40 rounded-lg text-xs font-medium text-emerald-400 transition-colors">▶ Start Tunnel</button>
                        <button onclick="tunnelControl('stop')" class="px-4 py-2 bg-red-900/30 hover:bg-red-900/50 border border-red-800/30 rounded-lg text-xs font-medium text-red-400 transition-colors">■ Stop Tunnel</button>
                        <button onclick="tunnelControl('logs')" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">📋 Logs</button>
                    </div>
                    <div id="tunnel-action-msg" class="text-xs text-gray-500 mt-1 min-h-[1.2em]"></div>
                    <div id="tunnel-log-output" style="display:none;" class="mt-2 p-3 bg-gray-950 border border-gray-800 rounded-lg font-mono text-xs text-gray-400 max-h-48 overflow-y-auto whitespace-pre-wrap"></div>
                    <div class="flex gap-2 mt-4">
                        <button wire:click="refreshTunnelStatus" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">Refresh Status</button>
                        <button wire:click="unpair" wire:confirm="Unpair from kernel-central? Mobile relay will stop." class="px-4 py-2 bg-red-900/30 hover:bg-red-900/50 border border-red-800/30 rounded-lg text-xs font-medium text-red-400 transition-colors">Unpair Device</button>
                    </div>
                </div>
                @else
                <div class="space-y-4">
                    <p class="text-sm text-gray-400">Pair this device with kernel-central to enable mobile relay.</p>
                    <div><label class="block text-sm font-medium text-gray-300 mb-1">Kernel-Central URL</label><input type="url" wire:model="pairCentralUrl" placeholder="https://kernel-central.neverslave.com" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono"></div>
                    <div><label class="block text-sm font-medium text-gray-300 mb-1">Device Name</label><input type="text" wire:model="pairDeviceName" placeholder="kernel-desktop" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-300 mb-1">Pair Token</label><input type="text" wire:model="pairToken" placeholder="40-character pair token" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono"></div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Pair Secret</label>
                        <input type="password" wire:model="pairSecret" placeholder="40-character pair secret" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                        <p class="text-xs text-gray-500 mt-1">Generate at kernel-central → <strong class="text-gray-400">Pair a Device</strong> → type Desktop → copy Token & Secret.</p>
                    </div>
                    @if ($pairError)<div class="bg-red-900/20 border border-red-800/30 text-red-400 rounded-lg px-4 py-3 text-sm">{{ $pairError }}</div>@endif
                    <button wire:click="initiatePair" wire:loading.attr="disabled" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 rounded-lg text-sm font-medium transition-colors">
                        <span wire:loading wire:target="initiatePair">Pairing…</span>
                        <span wire:loading.remove wire:target="initiatePair">Pair Device</span>
                    </button>
                </div>
                @endif
            </div>
        </div>

        {{-- ══ PROVIDERS ══════════════════════════════════════════════════ --}}
        <div x-show="tab === 'providers'" class="space-y-6">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h3 class="text-lg font-semibold text-white mb-1">Provider Configuration</h3>
                <p class="text-xs text-gray-500 mb-4">Changes are sent live to kernel-evolving via <code class="bg-gray-800 px-1 py-0.5 rounded">/provider/set</code>.</p>
                <div class="space-y-4">
                    @foreach ([
                    ['prop' => 'providerTaskInference', 'label' => 'Task Inference Provider'],
                    ['prop' => 'providerSynthesis', 'label' => 'Synthesis Provider'],
                    ['prop' => 'providerCritic', 'label' => 'Critic Provider'],
                    ] as $row)
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">{{ $row['label'] }}</label>
                        <select wire:model="{{ $row['prop'] }}" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                            <option value="local">Local (Nemotron)</option>
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic</option>
                            <option value="hf">HuggingFace</option>
                            <option value="copilot">GitHub Copilot</option>
                            <option value="openrouter">OpenRouter</option>
                        </select>
                    </div>
                    @endforeach
                    <hr class="border-gray-700">
                    <p class="text-xs text-gray-500">API keys are stored in kernel-evolving's environment, not this app's database.</p>
                    <div><label class="block text-sm font-medium text-gray-300 mb-1">OpenAI API Key</label><input type="password" wire:model="openaiKey" placeholder="sk-..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono"></div>
                    <div><label class="block text-sm font-medium text-gray-300 mb-1">Anthropic API Key</label><input type="password" wire:model="anthropicKey" placeholder="sk-ant-..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono"></div>
                    <div><label class="block text-sm font-medium text-gray-300 mb-1">HuggingFace Token</label><input type="password" wire:model="hfToken" placeholder="hf_..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono"></div>
                    <div class="flex justify-end pt-2">
                        <button wire:click="save" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm font-medium transition-colors">Save Providers</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ MODEL STORAGE ══════════════════════════════════════════════ --}}
        <div x-show="tab === 'models'" class="space-y-6">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h3 class="text-lg font-semibold text-white mb-1">Model Storage</h3>
                <p class="text-xs text-gray-500 mb-4">HuggingFace hub cache layout (<code class="bg-gray-800 px-1 py-0.5 rounded">models--org--repo</code>).</p>
                <div class="flex gap-2 mb-4">
                    <input type="text" wire:model="modelsRoot" placeholder="/path/to/huggingface/hub" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                    <button wire:click="saveModelsRoot" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors whitespace-nowrap">Save &amp; Scan</button>
                    <button wire:click="openModelsFolder" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors whitespace-nowrap">Open Folder</button>
                </div>
                @if ($modelsScanMsg)<p class="text-xs text-gray-500 mb-2">{{ $modelsScanMsg }}</p>@endif
                <div class="flex items-center gap-1 mb-3 text-xs font-mono flex-wrap">
                    @if ($modelsBrowsePath !== '')<button wire:click="browseModelsUp" class="text-gray-400 hover:text-gray-200 mr-1" title="Up">↑</button>@endif
                    <button wire:click="browseModelsFolder('')" class="text-gray-400 hover:text-gray-200">root</button>
                    @foreach ($modelsBreadcrumbs as $crumb)
                    <span class="text-gray-600">/</span>
                    <button wire:click="browseModelsFolder('{{ $crumb['path'] }}')" class="text-gray-400 hover:text-gray-200">{{ $crumb['label'] }}</button>
                    @endforeach
                </div>
                @if (!empty($localModels))
                <div class="space-y-2 max-h-72 overflow-y-auto">
                    @foreach ($localModels as $m)
                    <button wire:click="browseModelsFolder('{{ $m['rel_path'] }}')" class="w-full flex items-center justify-between px-3 py-2 bg-gray-800/50 hover:bg-gray-800 border border-gray-800 rounded-lg text-sm text-left transition-colors">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="text-[10px] px-1.5 py-0.5 rounded shrink-0 {{ $m['is_model'] ? 'bg-emerald-900/40 text-emerald-400' : 'bg-gray-700/60 text-gray-400' }}">{{ $m['is_model'] ? 'MODEL' : 'FOLDER' }}</span>
                            <span class="text-gray-300 font-mono text-xs truncate" title="{{ $m['raw'] }}">{{ $m['name'] }}</span>
                        </span>
                        <span class="text-gray-500 text-xs shrink-0 ml-3">{{ $m['size_human'] }} · {{ $m['modified'] }}</span>
                    </button>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        {{-- ══ GITHUB ══════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'github'" class="space-y-6">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h3 class="text-lg font-semibold text-white mb-1">GitHub Integration</h3>
                <p class="text-xs text-gray-500 mb-4">Associate GitHub accounts so the agent can create repos, push code, open PRs and manage the full development lifecycle. Expanded in upcoming versions.</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">GitHub Personal Access Token</label>
                        <input type="password" wire:model="githubToken" placeholder="ghp_..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                        <p class="text-xs text-gray-500 mt-1">Requires scopes: <code class="bg-gray-800 px-1 rounded">repo</code>, <code class="bg-gray-800 px-1 rounded">workflow</code>, <code class="bg-gray-800 px-1 rounded">read:user</code>.</p>
                    </div>

                    <div id="github-identity" class="hidden p-3 bg-gray-800/50 border border-gray-700 rounded-lg">
                        <div class="flex items-center gap-3">
                            <img id="github-avatar" src="" alt="" class="w-8 h-8 rounded-full">
                            <div>
                                <div id="github-name" class="text-sm font-medium text-gray-200"></div>
                                <div id="github-login" class="text-xs text-gray-500 font-mono"></div>
                            </div>
                            <span class="ml-auto text-xs text-emerald-400">● Connected</span>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button id="github-verify-btn" onclick="verifyGithubToken()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">Verify Token</button>
                        <button wire:click="save" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-xs font-medium transition-colors">Save</button>
                    </div>
                    <div id="github-msg" class="text-xs text-gray-500 min-h-[1.2em]"></div>

                    {{-- Repository panel - placeholder for upcoming expansion --}}
                    <div class="mt-4 pt-4 border-t border-gray-800">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-medium text-gray-300">Associated Repositories</span>
                            <button id="github-repos-refresh" onclick="loadGithubRepos()" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">↺ Refresh</button>
                        </div>
                        <div id="github-repos-list" class="space-y-1.5 max-h-60 overflow-y-auto text-xs">
                            <div class="text-gray-600 italic">Token not verified yet.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ CHANNELS ════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'channels'" class="space-y-6">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h3 class="text-lg font-semibold text-white mb-1">Communication Channels</h3>
                <p class="text-xs text-gray-500 mb-4">Configure messaging platforms the agent can send and receive messages through.</p>

                <div class="divide-y divide-gray-800">
                    {{-- Telegram --}}
                    <details class="group py-4 first:pt-0">
                        <summary class="flex items-center justify-between cursor-pointer list-none">
                            <div class="flex items-center gap-3">
                                <span class="text-lg">✈️</span>
                                <div>
                                    <div class="text-sm font-medium text-gray-200">Telegram</div>
                                    <div class="text-xs text-gray-500">Receive and send messages via a Telegram bot</div>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-9">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Bot Token</label>
                                <input type="password" wire:model="telegramBotToken" placeholder="123456:ABC-..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Chat ID (your personal chat or group)</label>
                                <input type="text" wire:model="telegramChatId" placeholder="-1001234567890" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="testTelegramConnection" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">Test Connection</button>
                                <button wire:click="save" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-xs font-medium transition-colors">Save</button>
                            </div>
                            @if ($telegramTestResult)
                            <p class="text-xs {{ $telegramTestPassed ? 'text-emerald-400' : 'text-red-400' }}">{{ $telegramTestResult }}</p>
                            @endif
                        </div>
                    </details>

                    {{-- Kernel-Central (messaging channel) --}}
                    <details class="group py-4">
                        <summary class="flex items-center justify-between cursor-pointer list-none">
                            <div class="flex items-center gap-3">
                                <span class="text-lg">🌐</span>
                                <div>
                                    <div class="text-sm font-medium text-gray-200">Kernel-Central</div>
                                    <div class="text-xs text-gray-500">Cloud relay — configure pairing in the Core tab</div>
                                </div>
                            </div>
                            <span class="text-xs {{ $paired ? 'text-emerald-400' : 'text-gray-500' }}">{{ $paired ? '● Paired' : '○ Not paired' }}</span>
                        </summary>
                        <div class="mt-4 pl-9">
                            <p class="text-xs text-gray-500">Kernel-Central pairing is managed in the <button @click="tab='core'" class="text-indigo-400 hover:underline">Core tab</button>. Once paired, the tunnel enables mobile relay and cloud chat.</p>
                        </div>
                    </details>
                </div>
            </div>
        </div>

        {{-- ══ APPEARANCE & UPDATES ════════════════════════════════════════ --}}
        <div x-show="tab === 'appearance'" class="space-y-6">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Appearance</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Theme</label>
                    <select wire:model="theme" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                        <option value="dark">Dark</option>
                        <option value="light">Light</option>
                        <option value="system">System</option>
                    </select>
                </div>
            </div>
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Updates</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Update Channel</label>
                        <select wire:model="updateChannel" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                            <option value="latest">Stable (latest)</option>
                            <option value="beta">Beta</option>
                            <option value="alpha">Alpha</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Check Frequency</label>
                        <select wire:model="updateFrequency" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                            <option value="startup">On startup</option>
                            <option value="daily">Daily</option>
                            <option value="manual">Manual only</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="checkForUpdates" wire:loading.attr="disabled" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors disabled:opacity-50">
                            <span wire:loading wire:target="checkForUpdates">Checking…</span>
                            <span wire:loading.remove wire:target="checkForUpdates">Check Now</span>
                        </button>
                        @if ($updateStatus)<span class="text-sm text-gray-400">{{ $updateStatus }}</span>@endif
                    </div>
                </div>
            </div>
            <div class="flex justify-end">
                <button wire:click="save" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-sm font-medium transition-colors">Save Settings</button>
            </div>
        </div>
    </div>

    <script>
        const KERNEL_BASE = window.KERNEL_API_BASE || 'http://127.0.0.1:8779';
        const CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';

        async function refreshAgentStatus() {
            const statusEl = document.getElementById('agent-status-label');
            const skillsEl = document.getElementById('agent-skills-label');
            const modelEl = document.getElementById('agent-model-label');
            const vramEl = document.getElementById('agent-vram-label');
            if (statusEl) {
                statusEl.textContent = '● Checking…';
                statusEl.className = 'text-sm text-gray-500';
            }
            try {
                const [health, routing] = await Promise.all([
                    fetch(KERNEL_BASE + '/health', {
                        signal: AbortSignal.timeout(4000)
                    }).then(r => r.json()),
                    fetch(KERNEL_BASE + '/provider', {
                        signal: AbortSignal.timeout(4000)
                    }).then(r => r.json()).catch(() => ({})),
                ]);
                if (statusEl) {
                    statusEl.textContent = '● Running';
                    statusEl.className = 'text-sm text-emerald-400';
                }
                if (skillsEl) skillsEl.textContent = (health.skills ?? '—') + ' skills · ' + (health.routines ?? '—') + ' routines';
                const ti = routing.routing?.task_inference || {};
                if (modelEl) modelEl.textContent = [ti.provider, ti.model].filter(Boolean).join(' / ') || health.llm || '—';
                if (vramEl) vramEl.textContent = health.vram_free_mb != null ? health.vram_free_mb + ' MB free' : '—';
                updateModeUi(ti.provider || 'local');
            } catch {
                if (statusEl) {
                    statusEl.textContent = '○ Offline';
                    statusEl.className = 'text-sm text-red-400';
                }
                ['agent-skills-label', 'agent-model-label', 'agent-vram-label'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = '—';
                });
                updateModeUi(null);
            }
        }

        const COMPUTE_CALL_TYPES = ['task_inference', 'synthesis', 'critic', 'planning', 'trajectory_teacher'];

        function updateModeUi(provider) {
            const localBtn = document.getElementById('agent-mode-local-btn');
            const cloudBtn = document.getElementById('agent-mode-cloud-btn');
            const modeLabel = document.getElementById('agent-mode-label');
            if (!localBtn || !cloudBtn) return;
            const isLocal = provider === 'local';
            const isCloud = provider && !isLocal;
            localBtn.className = 'px-4 py-2 rounded-lg text-xs font-medium transition-colors border ' + (isLocal ? 'bg-emerald-800/40 border-emerald-700/40 text-emerald-400' : 'bg-gray-800 border-gray-700 text-gray-300');
            cloudBtn.className = 'px-4 py-2 rounded-lg text-xs font-medium transition-colors border ' + (isCloud ? 'bg-blue-800/40 border-blue-700/40 text-blue-400' : 'bg-gray-800 border-gray-700 text-gray-300');
            if (modeLabel) modeLabel.textContent = provider ? (isLocal ? '🏠 Local' : `☁️ Cloud (${provider})`) : '—';
            const providerSel = document.getElementById('agent-cloud-provider');
            if (isCloud && providerSel) providerSel.value = provider;
        }

        async function setComputeMode(mode) {
            const msg = document.getElementById('agent-mode-msg');
            const providerSel = document.getElementById('agent-cloud-provider');
            const provider = mode === 'local' ? 'local' : (providerSel?.value || 'openai');
            if (msg) msg.textContent = `⏳ Switching…`;
            const body = {
                persist: true
            };
            COMPUTE_CALL_TYPES.forEach(ct => body[ct] = provider);
            try {
                const r = await fetch(KERNEL_BASE + '/provider/set', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(body),
                    signal: AbortSignal.timeout(8000)
                });
                const d = await r.json();
                if (!r.ok || d.error) throw new Error(d.error || `HTTP ${r.status}`);
                if (msg) msg.textContent = `✅ Switched to ${mode === 'local' ? 'Local' : 'Cloud (' + provider + ')'}`;
                updateModeUi(provider);
            } catch (e) {
                if (msg) msg.textContent = `❌ ${e.message}`;
            }
            setTimeout(() => {
                if (msg) msg.textContent = '';
            }, 8000);
        }

        async function agentControl(action) {
            const msg = document.getElementById('agent-action-msg');
            const out = document.getElementById('agent-info-output');
            const labels = {
                start: '▶ Starting…',
                stop: '■ Stopping…',
                restart: '↺ Restarting…',
                logs: '📋 Fetching logs…'
            };
            if (msg) msg.textContent = labels[action] || '…';
            if (action === 'logs') {
                if (out) {
                    out.style.display = '';
                    out.textContent = 'Loading…';
                }
                try {
                    const r = await fetch('/settings/agent/logs', {
                        headers: {
                            'X-CSRF-TOKEN': CSRF
                        }
                    });
                    const d = await r.json();
                    if (out) out.textContent = d.logs || '(no logs)';
                } catch (e) {
                    if (out) out.textContent = 'Error: ' + e.message;
                }
                if (msg) msg.textContent = '';
                return;
            }
            if (out) out.style.display = 'none';
            try {
                const r = await fetch('/settings/agent/' + action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF
                    }
                });
                const d = await r.json();
                if (msg) msg.textContent = d.success ? '✅ ' + (d.message || action + ' done') : '❌ ' + (d.message || 'failed');
            } catch (e) {
                if (msg) msg.textContent = '❌ ' + e.message;
            }
            setTimeout(() => {
                refreshAgentStatus();
                if (msg) setTimeout(() => msg.textContent = '', 6000);
            }, 2000);
        }

        refreshAgentStatus();

        async function tunnelControl(action) {
            const msg = document.getElementById('tunnel-action-msg');
            const out = document.getElementById('tunnel-log-output');
            if (msg) msg.textContent = action === 'logs' ? '📋 Fetching logs…' : action === 'start' ? '▶ Starting…' : '■ Stopping…';
            if (action === 'logs') {
                if (out) {
                    out.style.display = '';
                    out.textContent = 'Loading…';
                }
                try {
                    const r = await fetch('/settings/tunnel/logs', {
                        headers: {
                            'X-CSRF-TOKEN': CSRF
                        }
                    });
                    const d = await r.json();
                    if (out) out.textContent = d.logs || '(no logs)';
                } catch (e) {
                    if (out) out.textContent = 'Error: ' + e.message;
                }
                if (msg) msg.textContent = '';
                return;
            }
            if (out) out.style.display = 'none';
            try {
                const r = await fetch('/settings/tunnel/' + action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF
                    }
                });
                const d = await r.json();
                if (msg) msg.textContent = d.success ? '✅ ' + (d.message || action + ' done') : '❌ ' + (d.message || 'failed');
            } catch (e) {
                if (msg) msg.textContent = '❌ ' + e.message;
            }
            setTimeout(() => {
                if (msg) msg.textContent = '';
            }, 6000);
        }

        // GitHub token verification
        async function verifyGithubToken() {
            const msg = document.getElementById('github-msg');
            const identity = document.getElementById('github-identity');
            const input = document.querySelector('input[wire\\:model="githubToken"]');
            const token = input?.value?.trim();
            if (!token) {
                if (msg) msg.textContent = 'Enter a token first.';
                return;
            }
            if (msg) msg.textContent = '⏳ Verifying…';
            try {
                const r = await fetch('https://api.github.com/user', {
                    headers: {
                        Authorization: 'Bearer ' + token,
                        'X-GitHub-Api-Version': '2022-11-28'
                    }
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const u = await r.json();
                if (msg) msg.textContent = '';
                if (identity) {
                    identity.classList.remove('hidden');
                    document.getElementById('github-avatar').src = u.avatar_url || '';
                    document.getElementById('github-name').textContent = u.name || u.login;
                    document.getElementById('github-login').textContent = '@' + u.login;
                }
                loadGithubRepos(token);
            } catch (e) {
                if (msg) msg.textContent = '❌ ' + e.message;
                if (identity) identity.classList.add('hidden');
            }
        }

        async function loadGithubRepos(token) {
            const list = document.getElementById('github-repos-list');
            if (!list) return;
            const input = document.querySelector('input[wire\\:model="githubToken"]');
            const t = token || input?.value?.trim();
            if (!t) return;
            list.innerHTML = '<div class="text-gray-600 italic">Loading…</div>';
            try {
                const r = await fetch('https://api.github.com/user/repos?sort=pushed&per_page=30', {
                    headers: {
                        Authorization: 'Bearer ' + t,
                        'X-GitHub-Api-Version': '2022-11-28'
                    }
                });
                const repos = await r.json();
                if (!Array.isArray(repos) || !repos.length) {
                    list.innerHTML = '<div class="text-gray-600 italic">No repositories found.</div>';
                    return;
                }
                list.innerHTML = repos.map(repo => `
            <div class="flex items-center justify-between px-3 py-2 bg-gray-800/50 hover:bg-gray-800 border border-gray-800 rounded-lg transition-colors">
                <div class="min-w-0">
                    <div class="text-gray-300 font-mono truncate">${repo.full_name}</div>
                    <div class="text-gray-500 text-[10px]">${repo.description || ''}</div>
                </div>
                <div class="flex items-center gap-2 ml-3 shrink-0">
                    ${repo.private ? '<span class="text-[9px] px-1.5 py-0.5 bg-gray-700 rounded text-gray-400">PRIVATE</span>' : ''}
                    <span class="text-[10px] text-gray-500">⭐ ${repo.stargazers_count}</span>
                    <a href="${repo.html_url}" target="_blank" class="text-indigo-400 hover:underline text-[10px]">Open</a>
                </div>
            </div>`).join('');
            } catch (e) {
                list.innerHTML = `<div class="text-red-400 text-xs">${e.message}</div>`;
            }
        }
    </script>

</div>
