<div id="agent-panel" style="display:flex;height:100%;width:100%;overflow:hidden;">
    <style>
        .agent-telegram-shell {
            display: flex;
            width: 100%;
            height: 100%;
            min-height: 0;
            background: #0f1720;
            color: #d8e4ef;
        }

        .agent-telegram-main {
            flex: 1;
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: radial-gradient(circle at 0 0, rgba(85, 155, 195, .18), transparent 44%), #0e1a27;
        }

        .agent-telegram-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 14px;
            border-bottom: 1px solid #223346;
            background: rgba(18, 30, 43, .96);
            backdrop-filter: blur(4px);
        }

        .agent-telegram-mainhead {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .agent-telegram-badge {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .06em;
            background: #2a8ad4;
            color: #fff;
            flex-shrink: 0;
        }

        .agent-telegram-mainhead .agent-session-pill {
            background: #102536;
            border-color: #2d4a65;
            color: #9eb9cf;
        }
        @media (max-width: 600px) {
            .agent-telegram-topbar {
                padding: 8px 10px;
                gap: 6px;
            }
            .agent-telegram-mainhead {
                gap: 6px;
            }
            .agent-session-pill {
                display: none;
            }
            .panel-title {
                font-size: .82em;
            }
            .agent-inspector-summary {
                display: none;
            }
            .agent-thread {
                padding: 10px 8px 16px;
            }
            .agent-composer {
                padding: 8px 8px 9px;
                gap: 4px;
            }
            .agent-composer-btn {
                width: 34px;
                height: 34px;
            }
            #agent-send-btn {
                width: 34px;
                height: 34px;
            }
            .agent-input {
                padding: 8px 10px;
                font-size: .83em;
            }
        }

        .agent-telegram-toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 6px;
        }

        .agent-telegram-thread-wrap {
            flex: 1;
            min-height: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background:
                linear-gradient(135deg, rgba(35, 58, 77, .12) 25%, transparent 25%) -16px 0/32px 32px,
                linear-gradient(225deg, rgba(35, 58, 77, .12) 25%, transparent 25%) -16px 0/32px 32px,
                linear-gradient(315deg, rgba(35, 58, 77, .12) 25%, transparent 25%) 0 0/32px 32px,
                linear-gradient(45deg, rgba(35, 58, 77, .12) 25%, transparent 25%) 0 0/32px 32px,
                #0f1b29;
        }

        .agent-thread {
            padding: 18px 20px 26px;
        }

        .agent-controls {
            position: relative;
            z-index: 3;
        }

        .agent-action-sheet {
            position: absolute;
            bottom: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #122031;
            border: 1px solid #2a4260;
            border-radius: 14px;
            padding: 10px;
            z-index: 20;
            display: none;
            flex-wrap: wrap;
            gap: 6px;
        }

        .agent-action-sheet.open {
            display: flex;
        }

        .agent-composer {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            padding: 10px 12px 11px;
            border-top: 1px solid #223548;
            background: rgba(19, 33, 47, .95);
            backdrop-filter: blur(6px);
        }

        .agent-composer-btn {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 999px;
            border: none;
            background: transparent;
            color: #6e9cbf;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: color .15s, background .15s;
        }

        .agent-composer-btn:hover {
            color: #a8cadf;
            background: rgba(255,255,255,.07);
        }

        .agent-input-wrap {
            flex: 1;
            min-width: 0;
            position: relative;
        }

        .agent-input {
            width: 100%;
            border: 1px solid #2f4b67;
            background: #142434;
            color: #e4eef7;
            border-radius: 22px;
            min-height: 42px;
            max-height: 180px;
            padding: 10px 14px;
            resize: none;
            font-size: .88em;
            line-height: 1.45;
            display: block;
        }

        .agent-input::placeholder {
            color: #88a9c4;
        }

        #agent-send-btn {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 999px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .agent-status {
            padding: 3px 16px 0;
            font-size: .67em;
            color: #8eaac0;
        }

        /* ── Telegram footer composer ── */

        /* ── Right sidebar: tabbed viewers (Files / Mental Map / Browser / Computer use) ── */
        .agent-viewers {
            width: 420px;
            max-width: 46vw;
            flex-shrink: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #223346;
            background: #0d151f;
        }
        .agent-viewers-tabs {
            display: flex;
            align-items: stretch;
            border-bottom: 1px solid #223346;
            background: rgba(18, 30, 43, .96);
            flex-shrink: 0;
        }
        .agent-viewers-tab {
            flex: 1;
            padding: 10px 4px;
            background: transparent;
            color: #8eaac0;
            border: none;
            border-bottom: 2px solid transparent;
            font-size: .72em;
            line-height: 1.1;
            cursor: pointer;
            white-space: nowrap;
            text-align: center;
            transition: color .15s, background .15s, border-color .15s;
        }
        .agent-viewers-tab:hover {
            color: #c9d7e4;
        }
        .agent-viewers-tab.active {
            color: #fff;
            border-bottom-color: #2a8ad4;
            background: rgba(255, 255, 255, .03);
        }
        .agent-viewers-panel {
            display: none;
            flex: 1;
            min-height: 0;
            overflow: auto;
        }
        .agent-viewers-panel.active {
            display: flex;
            flex-direction: column;
        }
        .agent-viewers-panel iframe {
            flex: 1;
            width: 100%;
            min-height: 0;
            border: 0;
            background: #0b1117;
        }
        /* Files tab: fit the workspace viewer into the sidebar */
        #agent-viewers #memory-panel {
            padding: 12px 14px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        #agent-viewers #mem-layout {
            height: 100%;
            min-height: 0;
        }
        #agent-viewers #mem-tree-panel {
            flex-shrink: 0;
            max-height: 46%;
            overflow: auto;
        }
        #agent-viewers #mem-editor-panel {
            flex: 1;
            min-height: 0;
        }
        .agent-viewers-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-bottom: 1px solid #223346;
            background: rgba(18, 30, 43, .96);
            flex-shrink: 0;
        }
        .agent-viewers-bar input {
            flex: 1;
            min-width: 0;
            background: #142434;
            color: #e4eef7;
            border: 1px solid #2f4b67;
            border-radius: 6px;
            padding: 5px 10px;
            font-size: .76em;
            font-family: monospace;
        }

    </style>

    <div id="agent-telegram-shell" class="agent-telegram-shell">
        {{-- ── main chat area (full width, no extra sidebar) ── --}}
        <div class="agent-telegram-main">

            {{-- topbar: avatar + name + session pill --}}
            <div class="agent-telegram-topbar">
                <div class="agent-telegram-mainhead">
                    <span class="agent-telegram-badge">AG</span>
                    <div>
                        <div class="panel-title" style="margin-bottom:2px;">Kernel Agent</div>
                        <div class="agent-inspector-summary">Kernel evolving agent</div>
                    </div>
                    <div class="agent-session-pill">Session <strong id="agent-session-id">—</strong></div>
                </div>
            </div>

            {{-- scrollable thread --}}
            <div class="agent-telegram-thread-wrap">
                <div class="agent-thread activity-scroll flex-1" id="agent-thread"></div>

                {{-- sticky footer: action-sheet + composer row --}}
                <div class="agent-controls">
                    {{-- toolbar sheet, pops up above composer when menu btn clicked --}}
                    <div class="agent-action-sheet" id="agent-action-sheet" role="menu">
                        <button id="agent-command-menu-toggle" class="dash-ctrl-btn agent-mini-btn" aria-expanded="false">Commands</button>
                        <button id="agent-provider-menu-toggle" class="dash-ctrl-btn agent-mini-btn" aria-expanded="false">Routing</button>
                        <button id="agent-memory-toggle" class="dash-ctrl-btn agent-mini-btn" aria-expanded="false">Memory</button>
                        <button id="agent-inspector-toggle" class="dash-ctrl-btn agent-mini-btn" aria-expanded="false">Inspector</button>
                        <button class="dash-ctrl-btn agent-mini-btn" id="agent-fresh-btn">Fresh</button>
                        <button class="dash-ctrl-btn agent-mini-btn" id="agent-reset-btn">New</button>
                        <button class="dash-ctrl-btn agent-mini-btn" id="agent-refresh-btn">Inspect</button>
                    </div>

                    {{-- [menu][attach][ textarea ][emoji][send] --}}
                    <div class="agent-composer">
                        <button class="agent-composer-btn" id="agent-action-menu-btn" title="Actions" aria-expanded="false" aria-controls="agent-action-sheet">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
                            </svg>
                        </button>
                        <button class="agent-composer-btn" title="Attach file">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
                            </svg>
                        </button>
                        <div class="agent-input-wrap">
                            <textarea id="agent-input" class="agent-input" rows="1" placeholder="Write a message..." autocomplete="off"></textarea>
                        </div>
                        <button class="agent-composer-btn" title="Emoji">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M8 13s1.5 2 4 2 4-2 4-2"/>
                                <line x1="9" y1="9" x2="9.01" y2="9"/>
                                <line x1="15" y1="9" x2="15.01" y2="9"/>
                            </svg>
                        </button>
                        <button class="dash-ctrl-btn success" id="agent-send-btn" title="Send">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                    <div id="agent-status" class="agent-status"></div>
                </div>
            </div>

        </div>{{-- /.agent-telegram-main --}}

        {{-- ── RIGHT SIDEBAR: tabbed viewers ─────────────────────────────── --}}
        <aside id="agent-viewers" class="agent-viewers" aria-label="Agent viewers">
            <div class="agent-viewers-tabs" role="tablist">
                <button class="agent-viewers-tab active" data-tab="files" role="tab" aria-selected="true">📁 Files</button>
                <button class="agent-viewers-tab" data-tab="mental" role="tab" aria-selected="false">🧠 Map</button>
                <button class="agent-viewers-tab" data-tab="browser" role="tab" aria-selected="false">🌐 Browser</button>
                <button class="agent-viewers-tab" data-tab="computer" role="tab" aria-selected="false">🖥️ Use</button>
            </div>

            <section class="agent-viewers-panel active" data-panel="files" role="tabpanel">
                @include('livewire.workspace-tab')
            </section>

            <section class="agent-viewers-panel" data-panel="mental" role="tabpanel">
                <iframe id="agent-mentalmap-frame" loading="lazy" src="http://localhost:3131/"
                        title="Mental Map" sandbox="allow-scripts allow-same-origin allow-popups allow-forms"></iframe>
            </section>

            <section class="agent-viewers-panel" data-panel="browser" role="tabpanel">
                <div class="agent-viewers-bar">
                    <span style="font-size:.72em;color:#8eaac0;">🌐 Browser — live desktop view</span>
                    <span style="font-size:.66em;color:#3fb950;margin-left:auto;">● live</span>
                </div>
                <iframe id="browser-frame" src="http://127.0.0.1:6080/vnc.html?host=127.0.0.1&port=6080&autoconnect=1&reconnect=1&resize=scale"
                        title="Browser noVNC viewer" allow="clipboard-read; clipboard-write" style="flex:1;width:100%;min-height:0;border:0;background:#0b1117;"></iframe>
            </section>

            <section class="agent-viewers-panel" data-panel="computer" role="tabpanel">
                <div class="agent-viewers-bar">
                    <span style="font-size:.72em;color:#8eaac0;">🖥️ Computer Use — live desktop view</span>
                    <span style="font-size:.66em;color:#3fb950;margin-left:auto;">● live</span>
                </div>
                <iframe id="computer-frame" src="http://127.0.0.1:6080/vnc.html?host=127.0.0.1&port=6080&autoconnect=1&reconnect=1&resize=scale"
                        title="Computer-use noVNC viewer" allow="clipboard-read; clipboard-write" style="flex:1;width:100%;min-height:0;border:0;background:#0b1117;"></iframe>
            </section>
        </aside>
    </div>{{-- /#agent-telegram-shell --}}

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
            // action-sheet toggle
            const actionMenuBtn = document.getElementById('agent-action-menu-btn');
            const actionSheet   = document.getElementById('agent-action-sheet');
            if (actionMenuBtn && actionSheet) {
                actionMenuBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const open = actionSheet.classList.toggle('open');
                    actionMenuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                document.addEventListener('click', () => {
                    actionSheet.classList.remove('open');
                    actionMenuBtn.setAttribute('aria-expanded', 'false');
                });
                actionSheet.addEventListener('click', (e) => e.stopPropagation());
            }

            // ── Right sidebar tab switching ──
            const viewers = document.getElementById('agent-viewers');
            if (viewers) {
                const tabs = Array.from(viewers.querySelectorAll('.agent-viewers-tab'));
                const panels = Array.from(viewers.querySelectorAll('.agent-viewers-panel'));
                function selectTab(tab) {
                    tabs.forEach(t => {
                        const active = t === tab;
                        t.classList.toggle('active', active);
                        t.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    panels.forEach(p => p.classList.toggle('active', p.dataset.panel === tab.dataset.tab));
                    if (tab.dataset.tab === 'files' && typeof loadWorkspaceTree === 'function') loadWorkspaceTree('/');
                }
                tabs.forEach(tab => tab.addEventListener('click', () => selectTab(tab)));

                // Memory toolbar button now opens the sidebar Files tab
                const memToggle = document.getElementById('agent-memory-toggle');
                if (memToggle) {
                    memToggle.addEventListener('click', () => {
                        const filesTab = document.querySelector('.agent-viewers-tab[data-tab="files"]');
                        if (filesTab) selectTab(filesTab);
                    });
                }

                // Browser/Computer tabs are live noVNC iframes (shared desktop);
                // no JS wiring needed — autoconnect handles it.
            }
        })();
    </script>
</div>
