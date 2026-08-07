<div id="agent-panel" style="padding:20px 24px;">
    <div class="agent-layout">
        <div class="activity-card agent-card">
            <div class="agent-chat-shell">
                <div class="agent-head">
                    <div>
                        <div class="panel-title" style="margin-bottom:4px;">Agent Chat</div>
                        <div class="agent-inspector-summary">Interact with the agent here using the same slash commands Telegram supports.</div>
                    </div>
                </div>

                <div class="agent-thread activity-scroll" id="agent-thread"></div>

                <div class="agent-controls">
                    <div class="agent-controls-top">
                        <div class="agent-head-actions">
                            <div class="agent-session-pill">Session <strong id="agent-session-id">—</strong></div>
                            <button id="agent-command-menu-toggle" class="dash-ctrl-btn" aria-expanded="false">Commands</button>
                            <button id="agent-provider-menu-toggle" class="dash-ctrl-btn" aria-expanded="false">Routing</button>
                            <button id="agent-inspector-toggle" class="dash-ctrl-btn" aria-expanded="false">Inspector</button>
                        </div>
                        <button class="dash-ctrl-btn agent-mini-btn" id="agent-reset-btn">New</button>
                        <button class="dash-ctrl-btn agent-mini-btn" id="agent-refresh-btn">Inspect</button>
                    </div>
                    <div class="agent-controls-main">
                        <input id="agent-input" class="agent-input" placeholder="Type a message or /command. Press Enter to send." autocomplete="off">
                        <button class="dash-ctrl-btn success" id="agent-send-btn">Send ↵</button>
                    </div>
                </div>

                <div id="agent-status" style="font-size:0.72em;color:#8b949e;"></div>
            </div>
        </div>

        <div class="agent-inspector" id="agent-inspector">
            <div class="agent-inspector-card">
                <div class="agent-inspector-topline">
                    <div class="panel-title" style="margin-bottom:0;">Inspector</div>
                    <select id="agent-prompt-log-select" class="dash-mono-input agent-log-select"></select>
                    <button id="agent-inspector-close" class="dash-ctrl-btn agent-mini-btn">Close</button>
                </div>
                <div class="agent-inspector-summary" id="agent-inspector-summary">Select a prompt log to inspect the exact prompt and history passed to the model.</div>
            </div>

            <div class="agent-inspector-card">
                <details open>
                    <summary>System Prompt</summary>
                    <pre id="agent-system-prompt">No prompt log loaded yet.</pre>
                </details>
                <details open>
                    <summary>Conversation History</summary>
                    <div id="agent-history" class="agent-history-list"></div>
                </details>
                <details open>
                    <summary>Tool Calls & Outputs</summary>
                    <div id="agent-trace" class="agent-trace-list"></div>
                </details>
                <details>
                    <summary>Recent Trajectories</summary>
                    <div id="agent-trajectories" class="agent-trajectory-list"></div>
                </details>
            </div>
        </div>
        <div class="agent-inspector-backdrop" id="agent-inspector-backdrop"></div>
        <div class="agent-command-sheet-backdrop" id="agent-command-sheet-backdrop"></div>
        <div class="agent-provider-sheet-backdrop" id="agent-provider-sheet-backdrop"></div>
        <div class="agent-command-sheet" id="agent-command-sheet" aria-hidden="true">
            <div class="agent-command-sheet-topline">
                <div class="panel-title" style="margin-bottom:0;">Commands</div>
                <button id="agent-command-sheet-close" class="dash-ctrl-btn agent-mini-btn">Close</button>
            </div>
            <div class="agent-command-grid" id="agent-command-grid">
                <button class="agent-command-chip" data-command="/help">/help</button>
                <button class="agent-command-chip" data-command="/skills">/skills</button>
                <button class="agent-command-chip" data-command="/routines">/routines</button>
                <button class="agent-command-chip" data-command="/init">/init</button>
                <button class="agent-command-chip" data-command="/system">/system</button>
                <button class="agent-command-chip" data-command="/models">/models</button>
                <button class="agent-command-chip" data-command="/thoughts">/thoughts</button>
                <button class="agent-command-chip" data-command="/verbose">/verbose</button>
                <button class="agent-command-chip" data-command="/replica">/replica</button>
                <button class="agent-command-chip" data-command="/workspaces">/workspaces</button>
                <button class="agent-command-chip" data-command="/new">/new</button>
                <button class="agent-command-chip" data-command="/evolve">/evolve</button>
                <button class="agent-command-chip" data-command="/version">/version</button>
                <button class="agent-command-chip" data-command="/fresh">/fresh</button>
            </div>
        </div>
        <div class="agent-provider-sheet" id="agent-provider-sheet" aria-hidden="true">
            <div class="agent-provider-sheet-topline">
                <div class="panel-title" style="margin-bottom:0;">Provider &amp; Model Routing</div>
                <button id="agent-provider-sheet-close" class="dash-ctrl-btn agent-mini-btn">Close</button>
            </div>
            <div class="agent-provider-row">
                <select id="agent-provider-calltype" class="dash-mono-input" title="Call type"></select>
                <select id="agent-provider-select" class="dash-mono-input" title="Provider"></select>
                <input id="agent-model-input" class="dash-mono-input" placeholder="Model override (optional)">
                <label style="display:flex;align-items:center;gap:6px;font-size:0.72em;color:#8b949e;padding:0 6px;">
                    <input type="checkbox" id="agent-provider-persist"> Persist
                </label>
                <div style="display:flex;gap:8px;">
                    <button class="dash-ctrl-btn" id="agent-provider-refresh" style="flex:1;">Refresh</button>
                    <button class="dash-ctrl-btn success" id="agent-provider-apply" style="flex:1;">Apply</button>
                </div>
            </div>
            <div class="agent-provider-meta" id="agent-provider-meta">Provider/model routing loading…</div>
        </div>
    </div>
</div>
