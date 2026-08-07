<div class="max-w-3xl mx-auto space-y-6">
    <h2 class="text-2xl font-bold text-white">Settings</h2>

    @if (session('saved'))
        <div class="bg-emerald-600/20 border border-emerald-700/30 text-emerald-400 rounded-lg px-4 py-3 text-sm">
            Settings saved successfully.
        </div>
    @endif

    <!-- Provider Configuration -->
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-1">Provider Configuration</h3>
        <p class="text-xs text-gray-500 mb-4">Changes are sent live to kernel-evolving via <code class="bg-gray-800 px-1 py-0.5 rounded">/provider/set</code>. Set API keys below to unlock cloud providers.</p>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Task Inference Provider</label>
                <select wire:model="providerTaskInference"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                    <option value="local">Local (Nemotron)</option>
                    <option value="openai">OpenAI</option>
                    <option value="anthropic">Anthropic</option>
                    <option value="hf">HuggingFace</option>
                    <option value="copilot">GitHub Copilot</option>
                    <option value="openrouter">OpenRouter</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Synthesis Provider</label>
                <select wire:model="providerSynthesis"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                    <option value="local">Local (Nemotron)</option>
                    <option value="openai">OpenAI</option>
                    <option value="anthropic">Anthropic</option>
                    <option value="hf">HuggingFace</option>
                    <option value="copilot">GitHub Copilot</option>
                    <option value="openrouter">OpenRouter</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Critic Provider</label>
                <select wire:model="providerCritic"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                    <option value="local">Local (Nemotron)</option>
                    <option value="openai">OpenAI</option>
                    <option value="anthropic">Anthropic</option>
                    <option value="hf">HuggingFace</option>
                    <option value="copilot">GitHub Copilot</option>
                    <option value="openrouter">OpenRouter</option>
                </select>
            </div>

            <hr class="border-gray-700">
            <p class="text-xs text-gray-500">API keys are stored in kernel-evolving's environment and never in this app's database.</p>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">OpenAI API Key</label>
                <input type="password" wire:model="openaiKey"
                       placeholder="sk-..."
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Anthropic API Key</label>
                <input type="password" wire:model="anthropicKey"
                       placeholder="sk-ant-..."
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">GitHub Token (Copilot)</label>
                <input type="password" wire:model="githubToken"
                       placeholder="ghp_..."
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">HuggingFace Token</label>
                <input type="password" wire:model="hfToken"
                       placeholder="hf_..."
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
            </div>
        </div>
    </div>

    <!-- Model Storage -->
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-1">Model Storage</h3>
        <p class="text-xs text-gray-500 mb-4">Where kernel-evolving looks for downloaded local models (HuggingFace hub cache layout: <code class="bg-gray-800 px-1 py-0.5 rounded">models--org--repo</code>).</p>

        <div class="flex gap-2 mb-4">
            <input type="text" wire:model="modelsRoot"
                   placeholder="/path/to/huggingface/hub"
                   class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm font-mono">
            <button wire:click="saveModelsRoot"
                    class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors whitespace-nowrap">
                Save &amp; Scan
            </button>
            <button wire:click="openModelsFolder"
                    class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors whitespace-nowrap">
                Open Folder
            </button>
        </div>

        @if ($modelsScanMsg)
            <p class="text-xs text-gray-500 mb-2">{{ $modelsScanMsg }}</p>
        @endif

        <!-- Breadcrumb navigation -->
        <div class="flex items-center gap-1 mb-3 text-xs font-mono flex-wrap">
            @if ($modelsBrowsePath !== '')
                <button wire:click="browseModelsUp" class="text-gray-400 hover:text-gray-200 mr-1" title="Up one level">↑</button>
            @endif
            <button wire:click="browseModelsFolder('')" class="text-gray-400 hover:text-gray-200">root</button>
            @foreach ($modelsBreadcrumbs as $crumb)
                <span class="text-gray-600">/</span>
                <button wire:click="browseModelsFolder('{{ $crumb['path'] }}')" class="text-gray-400 hover:text-gray-200">{{ $crumb['label'] }}</button>
            @endforeach
        </div>

        @if (!empty($localModels))
            <div class="space-y-2 max-h-72 overflow-y-auto">
                @foreach ($localModels as $m)
                    <button wire:click="browseModelsFolder('{{ $m['rel_path'] }}')"
                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-800/50 hover:bg-gray-800 border border-gray-800 rounded-lg text-sm text-left transition-colors">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="text-[10px] px-1.5 py-0.5 rounded shrink-0 {{ $m['is_model'] ? 'bg-emerald-900/40 text-emerald-400' : 'bg-gray-700/60 text-gray-400' }}">
                                {{ $m['is_model'] ? 'MODEL' : 'FOLDER' }}
                            </span>
                            <span class="text-gray-300 font-mono text-xs truncate" title="{{ $m['raw'] }}">{{ $m['name'] }}</span>
                        </span>
                        <span class="text-gray-500 text-xs shrink-0 ml-3">{{ $m['size_human'] }} · {{ $m['modified'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Eva Agent -->
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-1">Eva Agent</h3>
        <p class="text-xs text-gray-500 mb-4">Controls the local Eva agent (kernel-evolving). Works whether started via <code class="bg-gray-800 px-1 py-0.5 rounded">start.sh</code> (bare metal) or Docker. Start/Stop run server-side shell commands; status is checked live in the browser.</p>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">Agent Status</span>
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

            <!-- Compute mode: run locally (GPU/VRAM) or offload to a cloud provider -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-400">Compute Mode</span>
                    <span class="text-xs text-gray-500 font-mono" id="agent-mode-label">—</span>
                </div>
                <p class="text-xs text-gray-500 mb-2">Choose Local to run models on this machine's GPU/VRAM, or Cloud to free up system resources by routing inference to a connected provider. Applies to task inference, synthesis, critic, planning and trajectory teaching.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <button id="agent-mode-local-btn" onclick="setComputeMode('local')"
                            class="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-xs font-medium transition-colors">
                        🏠 Local
                    </button>
                    <button id="agent-mode-cloud-btn" onclick="setComputeMode('cloud')"
                            class="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-xs font-medium transition-colors">
                        ☁️ Cloud
                    </button>
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
                <button onclick="agentControl('start')"
                        class="px-4 py-2 bg-emerald-800/40 hover:bg-emerald-700/50 border border-emerald-700/40 rounded-lg text-xs font-medium text-emerald-400 transition-colors">
                    ▶ Start
                </button>
                <button onclick="agentControl('stop')"
                        class="px-4 py-2 bg-red-900/30 hover:bg-red-900/50 border border-red-800/30 rounded-lg text-xs font-medium text-red-400 transition-colors">
                    ■ Stop
                </button>
                <button onclick="agentControl('restart')"
                        class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">
                    ↺ Restart
                </button>
                <button onclick="agentControl('logs')"
                        class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">
                    📋 Logs
                </button>
                <button onclick="refreshAgentStatus()"
                        class="px-4 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs font-medium transition-colors">
                    ↺ Status
                </button>
            </div>
            <div id="agent-action-msg" class="text-xs text-gray-500 mt-1 min-h-[1.2em]"></div>
            <div id="agent-info-output" style="display:none;"
                 class="mt-2 p-3 bg-gray-950 border border-gray-800 rounded-lg font-mono text-xs text-gray-400 max-h-64 overflow-y-auto whitespace-pre-wrap"></div>
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

    <!-- Updates -->
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Updates</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Update Channel</label>
                <select wire:model="updateChannel"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                    <option value="latest">Stable (latest)</option>
                    <option value="beta">Beta</option>
                    <option value="alpha">Alpha</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Check Frequency</label>
                <select wire:model="updateFrequency"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-gray-200 text-sm">
                    <option value="startup">On startup</option>
                    <option value="daily">Daily</option>
                    <option value="manual">Manual only</option>
                </select>
            </div>
            <div class="flex items-center gap-3">
                <button wire:click="checkForUpdates" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm transition-colors disabled:opacity-50">
                    <span wire:loading wire:target="checkForUpdates">Checking…</span>
                    <span wire:loading.remove wire:target="checkForUpdates">Check Now</span>
                </button>
                @if ($updateStatus)
                    <span class="text-sm text-gray-400">{{ $updateStatus }}</span>
                @endif
            </div>
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

<script>
// Status checks run browser-side (VS Code port-forwarding makes 8779 reachable).
// Start/Stop/Restart/Logs POST to Laravel routes that run shell commands server-side.
// In NativePHP production on Windows, PHP also reaches WSL's 8779 via Windows-WSL bridging.

const KERNEL_BASE = window.KERNEL_API_BASE || 'http://127.0.0.1:8779';
const CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';

async function refreshAgentStatus() {
    const statusEl = document.getElementById('agent-status-label');
    const skillsEl = document.getElementById('agent-skills-label');
    const modelEl  = document.getElementById('agent-model-label');
    const vramEl   = document.getElementById('agent-vram-label');
    if (statusEl) { statusEl.textContent = '● Checking…'; statusEl.className = 'text-sm text-gray-500'; }
    try {
        const [health, routing] = await Promise.all([
            fetch(KERNEL_BASE + '/health', { signal: AbortSignal.timeout(4000) }).then(r => r.json()),
            fetch(KERNEL_BASE + '/provider', { signal: AbortSignal.timeout(4000) }).then(r => r.json()).catch(() => ({})),
        ]);
        if (statusEl) { statusEl.textContent = '● Running'; statusEl.className = 'text-sm text-emerald-400'; }
        if (skillsEl) skillsEl.textContent = (health.skills ?? '—') + ' skills · ' + (health.routines ?? '—') + ' routines';
        const ti = routing.routing?.task_inference || {};
        if (modelEl)  {
            modelEl.textContent = [ti.provider, ti.model].filter(Boolean).join(' / ') || health.llm || '—';
        }
        if (vramEl)   vramEl.textContent = health.vram_free_mb != null ? health.vram_free_mb + ' MB free' : '—';
        updateModeUi(ti.provider || 'local');
    } catch {
        if (statusEl) { statusEl.textContent = '○ Offline'; statusEl.className = 'text-sm text-red-400'; }
        if (skillsEl) skillsEl.textContent = '—';
        if (modelEl)  modelEl.textContent  = '—';
        if (vramEl)   vramEl.textContent   = '—';
        updateModeUi(null);
    }
}

// ── Compute mode: Local (this machine's GPU/VRAM) vs Cloud (connected provider) ──
const COMPUTE_CALL_TYPES = ['task_inference', 'synthesis', 'critic', 'planning', 'trajectory_teacher'];

function updateModeUi(provider) {
    const localBtn  = document.getElementById('agent-mode-local-btn');
    const cloudBtn   = document.getElementById('agent-mode-cloud-btn');
    const providerSel = document.getElementById('agent-cloud-provider');
    const modeLabel  = document.getElementById('agent-mode-label');
    if (!localBtn || !cloudBtn) return;

    const isLocal = provider === 'local';
    const isCloud = provider && !isLocal;

    localBtn.className = 'px-4 py-2 rounded-lg text-xs font-medium transition-colors border ' +
        (isLocal ? 'bg-emerald-800/40 border-emerald-700/40 text-emerald-400' : 'bg-gray-800 border-gray-700 text-gray-300');
    cloudBtn.className = 'px-4 py-2 rounded-lg text-xs font-medium transition-colors border ' +
        (isCloud ? 'bg-blue-800/40 border-blue-700/40 text-blue-400' : 'bg-gray-800 border-gray-700 text-gray-300');

    if (modeLabel) modeLabel.textContent = provider ? (isLocal ? '🏠 Local' : `☁️ Cloud (${provider})`) : '—';
    if (isCloud && providerSel) providerSel.value = provider;
}

async function setComputeMode(mode) {
    const msg = document.getElementById('agent-mode-msg');
    const providerSel = document.getElementById('agent-cloud-provider');
    const provider = mode === 'local' ? 'local' : (providerSel?.value || 'openai');

    if (msg) msg.textContent = `⏳ Switching to ${mode === 'local' ? 'Local' : 'Cloud (' + provider + ')'}…`;

    const body = { persist: true };
    COMPUTE_CALL_TYPES.forEach(ct => body[ct] = provider);

    try {
        const r = await fetch(KERNEL_BASE + '/provider/set', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            signal: AbortSignal.timeout(8000),
        });
        const d = await r.json();
        if (!r.ok || d.error) throw new Error(d.error || `HTTP ${r.status}`);
        const actions = Array.isArray(d.vram_actions) && d.vram_actions.length ? ` · ${d.vram_actions.join(' | ')}` : '';
        if (msg) msg.textContent = `✅ Switched to ${mode === 'local' ? 'Local' : 'Cloud (' + provider + ')'}${actions}`;
        updateModeUi(provider);
    } catch (e) {
        if (msg) msg.textContent = `❌ ${e.message}`;
    }
    setTimeout(() => { if (msg) msg.textContent = ''; }, 8000);
}

async function agentControl(action) {
    const msg    = document.getElementById('agent-action-msg');
    const out    = document.getElementById('agent-info-output');
    const labels = { start: '▶ Starting…', stop: '■ Stopping…', restart: '↺ Restarting…', logs: '📋 Fetching logs…' };
    if (msg) msg.textContent = labels[action] || '…';
    if (out && action !== 'logs') out.style.display = 'none';

    if (action === 'logs') {
        out.style.display = '';
        out.textContent = 'Loading…';
        try {
            const r = await fetch('/settings/agent/logs', { headers: { 'X-CSRF-TOKEN': CSRF } });
            const d = await r.json();
            out.textContent = d.logs || '(no logs)';
        } catch (e) { out.textContent = 'Error: ' + e.message; }
        if (msg) msg.textContent = '';
        return;
    }

    // Start/Stop/Restart are long-running — show spinner, poll health after
    try {
        const r = await fetch('/settings/agent/' + action, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
        const d = await r.json();
        if (msg) msg.textContent = d.success ? '✅ ' + (d.message || action + ' done') : '❌ ' + (d.message || d.error || 'failed');
    } catch (e) {
        if (msg) msg.textContent = '❌ ' + e.message;
    }
    setTimeout(() => { refreshAgentStatus(); if (msg) setTimeout(() => msg.textContent = '', 6000); }, 2000);
}

refreshAgentStatus();
</script>
