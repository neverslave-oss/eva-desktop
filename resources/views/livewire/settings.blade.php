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

    <!-- Kernel-Central Pairing -->
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Kernel-Central Pairing</h3>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">Status</span>
                <span class="text-sm text-indigo-400">● Active</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-400">Device ID</span>
                <span class="text-sm text-gray-300 font-mono">kd-xxxx-xxxx</span>
            </div>
            <p class="text-xs text-gray-500">This device is paired with kernel-central.neverslave.com. Mobile relay is active.</p>
        </div>
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
