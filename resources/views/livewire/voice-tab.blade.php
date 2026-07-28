<div class="space-y-4">
    <div>
        <h2 class="text-2xl font-bold text-white">🎤 Voice</h2>
        <p class="text-gray-400 text-sm">Voice conversation with the kernel-evolving agent</p>
    </div>

    <div id="voice-panel" style="padding:20px 24px; max-width:720px; margin:0 auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <span style="font-size:0.7em;color:#8b949e;text-transform:uppercase;letter-spacing:.1em">Voice conversation</span>
            <div style="display:flex;gap:8px;align-items:center;">
                <button class="dash-ctrl-btn" id="v-self-test">Voice self-test</button>
                <button class="dash-ctrl-btn" id="v-new-conv">New conversation</button>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
            <span style="font-size:0.75em;color:#8b949e;">Brain:</span>
            <select id="v-server-select" class="dash-mono-input">
                <option value="http://localhost:8779">Kernel-Evo (local :8779)</option>
                <option value="http://localhost:8005">AI-Server (:8005)</option>
                <option value="http://localhost:11434">Ollama (:11434)</option>
            </select>
            <span id="v-server-status" style="font-size:0.72em;color:#8b949e;">—</span>
        </div>
        <div id="v-selftest-results" style="display:none;margin-bottom:12px;padding:10px 12px;background:#11161e;border:1px solid #30363d;border-radius:8px;font-size:0.72em;color:#8b949e;"></div>
        <div class="voice-helper">Hold SPACE to talk. Status will show each stage: listening, transcribing, thinking, and voice cloning.</div>
        <div id="v-thread" style="height:52vh;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding:10px;background:#0d1117;border:1px solid #21262d;border-radius:8px;margin-bottom:12px;"></div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:8px;">
            <div id="v-wave" class="voice-wave-wrap idle">
                <div class="voice-wave-meta">
                    <span>Voice signal</span>
                    <span id="v-wave-state" class="voice-wave-state">idle</span>
                </div>
                <div id="v-wave-bars" class="voice-wave-bars"></div>
            </div>
            <button id="v-mic-btn" title="Click to start/stop. Hold SPACE for push-to-talk."
                style="width:100px;height:100px;border-radius:50%;background:linear-gradient(180deg,#1a6ed8,#0b4fa8);border:none;color:white;font-size:34px;cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,0.6);transition:background .15s;">🎤</button>
            <button class="dash-ctrl-btn" id="v-retry-tts" disabled>Generate voice for last reply</button>
            <span id="v-status" style="font-size:0.78em;color:#8b949e;">Checking voice readiness…</span>
        </div>
    </div>
</div>
