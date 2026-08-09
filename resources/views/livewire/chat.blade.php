<div id="agent-panel" class="flex h-full gap-0" style="padding:0;">

    {{-- ── CHAT COLUMN ──────────────────────────────────────────────── --}}
    <div class="flex flex-col flex-1 min-w-0" style="padding:20px 16px 20px 24px;">
        <div class="activity-card agent-card h-full flex flex-col">
            <div class="agent-chat-shell flex flex-col flex-1">
                <div class="agent-head">
                    <div>
                        <div class="panel-title" style="margin-bottom:4px;">Agent Chat</div>
                        <div class="agent-inspector-summary">Interact with the agent using slash commands or plain messages.</div>
                    </div>
                </div>

                <div class="agent-thread activity-scroll flex-1" id="agent-thread"></div>

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

                <div id="agent-status" style="font-size:0.72em;color:#8b949e;padding-top:4px;"></div>
            </div>
        </div>
    </div>

    {{-- ── MEMORY SIDE PANEL ────────────────────────────────────────── --}}
    <aside id="chat-memory-panel" class="flex flex-col shrink-0 border-l border-gray-800 bg-gray-950/60"
        style="width:280px;padding:20px 16px 20px 16px;overflow:hidden;">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Memory</span>
            <button id="chat-memory-refresh" class="dash-ctrl-btn agent-mini-btn" style="padding:2px 8px;font-size:0.68em;">↺</button>
        </div>

        {{-- Search --}}
        <input id="chat-memory-search" type="text" placeholder="Filter…"
            class="mb-3 w-full bg-gray-800 border border-gray-700 rounded-lg px-2 py-1.5 text-xs text-gray-300 font-mono focus:outline-none focus:border-gray-500">

        {{-- File list --}}
        <div id="chat-memory-list" class="flex-1 overflow-y-auto space-y-0.5 text-xs">
            <div class="text-gray-600 italic">Loading…</div>
        </div>

        {{-- Preview pane --}}
        <div id="chat-memory-preview-wrap" class="hidden mt-3 border-t border-gray-800 pt-3">
            <div class="flex items-center justify-between mb-1">
                <span id="chat-memory-preview-name" class="text-[10px] font-mono text-gray-500 truncate max-w-[200px]"></span>
                <button id="chat-memory-preview-close" class="text-gray-600 hover:text-gray-300 text-xs leading-none">✕</button>
            </div>
            <pre id="chat-memory-preview" class="text-[10px] leading-4 text-gray-400 whitespace-pre-wrap overflow-y-auto max-h-48 font-mono bg-gray-900 rounded p-2"></pre>
        </div>
    </aside>

    {{-- Overlays (inspector, command sheet, provider sheet) --}}
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
                <summary>Tool Calls &amp; Outputs</summary>
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
    <script>
        (function() {
            const KAPI = window.KERNEL_API_BASE || 'http://127.0.0.1:8779';
            let _memFiles = [];

            function renderList(files) {
                const list = document.getElementById('chat-memory-list');
                if (!list) return;
                const term = (document.getElementById('chat-memory-search')?.value || '').toLowerCase();
                const shown = term ? files.filter(f => f.toLowerCase().includes(term)) : files;
                if (!shown.length) {
                    list.innerHTML = '<div class="text-gray-600 italic">No files.</div>';
                    return;
                }
                list.innerHTML = shown.map(f => {
                    const name = f.replace(/^.*[\\/]/, '');
                    return `<button class="memory-file-btn w-full text-left px-2 py-1 rounded hover:bg-gray-800 text-gray-400 hover:text-gray-200 truncate font-mono transition-colors" data-path="${f}" title="${f}">${name}</button>`;
                }).join('');
                list.querySelectorAll('.memory-file-btn').forEach(btn => btn.addEventListener('click', () => loadPreview(btn.dataset.path)));
            }

            async function loadMemoryList() {
                try {
                    const r = await fetch(KAPI + '/memory/files');
                    const d = await r.json();
                    _memFiles = Array.isArray(d.files) ? d.files : [];
                    renderList(_memFiles);
                } catch {
                    document.getElementById('chat-memory-list').innerHTML = '<div class="text-gray-600 italic">Agent offline.</div>';
                }
            }

            async function loadPreview(path) {
                const wrap = document.getElementById('chat-memory-preview-wrap');
                const pre = document.getElementById('chat-memory-preview');
                const name = document.getElementById('chat-memory-preview-name');
                if (!wrap || !pre) return;
                wrap.classList.remove('hidden');
                if (name) name.textContent = path.replace(/^.*[\\/]/, '');
                pre.textContent = 'Loading…';
                try {
                    const r = await fetch(KAPI + '/memory/file?path=' + encodeURIComponent(path));
                    const d = await r.json();
                    pre.textContent = d.content ?? '(empty)';
                } catch {
                    pre.textContent = 'Could not load file.';
                }
            }

            document.getElementById('chat-memory-refresh')?.addEventListener('click', loadMemoryList);
            document.getElementById('chat-memory-search')?.addEventListener('input', () => renderList(_memFiles));
            document.getElementById('chat-memory-preview-close')?.addEventListener('click', () => {
                document.getElementById('chat-memory-preview-wrap')?.classList.add('hidden');
            });

            loadMemoryList();
        })();
    </script>
</div>
