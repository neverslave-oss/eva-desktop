<div class="space-y-6">
    <h2 class="text-2xl font-bold text-white">Evolution Dashboard</h2>
    <p class="text-gray-400">Real-time visualization of the kernel-evolving agent's self-evolution pipeline.</p>

    <!-- Embedded Dashboard -->
    <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <iframe src="http://localhost:8779/evolution/dashboard"
                class="w-full h-[800px]"
                frameborder="0"
                title="Kernel-Evolving Dashboard"
                loading="lazy">
        </iframe>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-gray-900 rounded-xl p-4 border border-gray-800">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Status</div>
            <div class="text-lg font-bold text-emerald-400 mt-1">Running</div>
        </div>
        <div class="bg-gray-900 rounded-xl p-4 border border-gray-800">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Container</div>
            <div class="text-lg font-bold text-white mt-1">kernel-evolving</div>
        </div>
        <div class="bg-gray-900 rounded-xl p-4 border border-gray-800">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Port</div>
            <div class="text-lg font-bold text-white mt-1">8779</div>
        </div>
        <div class="bg-gray-900 rounded-xl p-4 border border-gray-800">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Pairing</div>
            <div class="text-lg font-bold text-indigo-400 mt-1">Active</div>
        </div>
    </div>
</div>
