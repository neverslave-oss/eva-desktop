<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-white">🧬 Evolution Monitor</h2>
            <p class="text-gray-400 text-sm">Skill acquisition network · proactive goal discovery · live SSE</p>
        </div>
        <div class="live-dot" style="width:8px;height:8px;background:#3fb950;border-radius:50%;animation:pulse 2s infinite;" title="Live"></div>
    </div>
    <style>@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}</style>

    <!-- Controls bar -->
    <div class="dash-controls">
        <div class="dash-state-badge dash-state-stopped" id="state-badge">stopped</div>
        <div class="dash-iter-badge">Iterations: <span id="iter-count">0</span> / <span id="iter-cap">10</span></div>
        <button class="dash-ctrl-btn success" id="btn-start" onclick="ctrlAction('start')">▶ Start</button>
        <button class="dash-ctrl-btn warn" id="btn-pause" onclick="ctrlAction('pause')" disabled>⏸ Pause</button>
        <button class="dash-ctrl-btn success" id="btn-resume" onclick="ctrlAction('resume')" disabled>▶ Resume</button>
        <button class="dash-ctrl-btn danger" id="btn-stop" onclick="ctrlAction('stop')" disabled>⏹ Stop</button>
        <button class="dash-ctrl-btn" id="btn-reset" onclick="ctrlAction('reset')">↺ Reset</button>
        <span style="color:#30363d">│</span>
        <label style="font-size:.75em;color:#8b949e">Cap:</label>
        <input class="dash-cap-input" id="cap-input" type="number" min="1" max="100" value="10" title="Max evolution iterations">
        <span style="color:#30363d">│</span>
        <input class="dash-task-input" id="task-input" placeholder="Enter a task to evolve… e.g. convert PDF to markdown" type="text">
        <button class="dash-ctrl-btn success" onclick="triggerEvolution()">🧬 Evolve</button>
        <label class="dash-ctrl-check" title="When enabled, manual evolve asks for confirmation first.">
            <input id="confirm-evolve" type="checkbox" checked>
            Confirm evolve
        </label>
        <div id="ctrl-msg" style="font-size:.75em;color:#8b949e"></div>
    </div>

    <!-- Graph panel -->
    <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <div id="graph-panel">
            <div class="graph-title">Skill Acquisition Network</div>
            <div class="graph-controls">
                <button class="graph-toggle active" id="g-toggle-skills" onclick="toggleGraphLayer('skills', this)">Skills</button>
                <button class="graph-toggle active" id="g-toggle-routines" onclick="toggleGraphLayer('routines', this)">Routines</button>
                <button class="graph-toggle active" id="g-toggle-replicas" onclick="toggleGraphLayer('replicas', this)">Replicas</button>
                <span class="graph-counts" id="graph-counts">
                    <span>skills: <strong id="gc-skills">0</strong></span>
                    <span>routines: <strong id="gc-routines">0</strong></span>
                    <span>replicas: <strong id="gc-replicas">0</strong></span>
                </span>
            </div>
            <svg id="graph"></svg>
            <div class="dash-legend">
                <div class="leg-item"><div class="leg-dot" style="background:#3fb950"></div>Kernel core</div>
                <div class="leg-item"><div class="leg-dot" style="background:#58a6ff"></div>Acquired (Tier 1)</div>
                <div class="leg-item"><div class="leg-dot" style="background:#bc8cff"></div>Synthesised (Tier 2)</div>
                <div class="leg-item"><div class="leg-dot" style="background:#d29922"></div>Routine</div>
                <div class="leg-item"><div class="leg-dot" style="background:#8b949e"></div>Active replica</div>
                <div class="leg-item"><div class="leg-dot" style="background:#f85149"></div>Gap (unresolved)</div>
            </div>
            <div class="dash-tooltip" id="tooltip"></div>
            <div class="dash-stats" id="stats-bar">
                <div class="dash-stat blue"><div class="num" id="s-total">0</div><div class="lbl">Total Events</div></div>
                <div class="dash-stat green"><div class="num" id="s-resolved">0</div><div class="lbl">Resolved</div></div>
                <div class="dash-stat purple"><div class="num" id="s-synth">0</div><div class="lbl">Synthesised</div></div>
                <div class="dash-stat red"><div class="num" id="s-gaps">0</div><div class="lbl">Open Gaps</div></div>
                <div class="dash-stat orange"><div class="num" id="s-provider">—</div><div class="lbl">Provider(s)</div></div>
            </div>

            <div class="task-outcome-wrap">
                <div class="task-outcome-head">
                    <span>Task Outcomes</span>
                    <div class="task-outcome-summary" id="task-outcome-summary">
                        <span class="task-pill" style="color:#3fb950">Direct: <strong id="to-direct">0</strong></span>
                        <span class="task-pill" style="color:#bc8cff">Escalated+Done: <strong id="to-esc-ok">0</strong></span>
                        <span class="task-pill" style="color:#f85149">Escalated+Open: <strong id="to-esc-open">0</strong></span>
                    </div>
                </div>
                <div id="task-outcome-list" class="activity-scroll"></div>
            </div>
        </div>
    </div>
</div>
