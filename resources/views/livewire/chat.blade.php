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

        /* ── Right sidebar: tabbed viewers (MOBILE-FIRST) ──
           Small screens: full-height overlay (slide-in) with backdrop.
           >=xl: persistent, resizable column beside the chat. */
        .agent-viewers {
            --aw-w: 540px;                 /* desktop drag-resizable; JS overrides */
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: min(94vw, var(--aw-w));
            max-width: 100vw;
            z-index: 72;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #223346;
            background: #0d151f;
            transform: translateX(106%);
            transition: transform .22s ease, box-shadow .22s ease;
            box-shadow: none;
        }
        .agent-viewers.open {
            transform: translateX(0);
            box-shadow: -10px 0 28px rgba(0, 0, 0, .38);
        }
        .agent-viewers-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(3, 7, 13, .55);
            z-index: 71;
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease;
        }
        .agent-viewers-backdrop.open {
            opacity: 1;
            pointer-events: auto;
        }
        /* Desktop (xl+): persistent, resizable column inside the flex shell */
        @media (min-width: 1280px) {
            .agent-viewers {
                position: relative;
                top: auto;
                right: auto;
                bottom: auto;
                height: auto;
                width: var(--aw-w);
                max-width: 100vw;
                flex-shrink: 0;
                min-height: 0;
                transform: none;
                z-index: auto;
                box-shadow: none;
                transition: none;
            }
            .agent-viewers-backdrop {
                display: none;
            }
        }
        /* Drag grip to resize on desktop */
        .agent-viewers-grip {
            position: absolute;
            top: 0;
            bottom: 0;
            left: -5px;
            width: 10px;
            cursor: col-resize;
            z-index: 3;
            display: none;
        }
        @media (min-width: 1280px) {
            .agent-viewers-grip {
                display: block;
            }
            .agent-viewers-grip:hover,
            .agent-viewers-grip.dragging {
                background: rgba(42, 138, 212, .35);
            }
        }
        .agent-viewers-toggle-btn {
            display: inline-flex;
        }
        @media (min-width: 1280px) {
            .agent-viewers-toggle-btn {
                display: none;
            }
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

        /* ── Agent readable-text font bump (+2px) ── */
        .agent-bubble { font-size: 15px; }
        .agent-input  { font-size: 16px; }

        /* ── Sessions (💬) sidebar tab ── */
        .agent-session-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 10px;
            overflow-y: auto;
            min-height: 0;
            flex: 1;
        }
        .agent-session-empty {
            color: #8eaac0;
            font-size: 14px;
            line-height: 1.5;
            padding: 12px;
        }
        .agent-session-row {
            display: flex;
            flex-direction: column;
            gap: 2px;
            text-align: left;
            padding: 9px 11px;
            border-radius: 9px;
            border: 1px solid #223346;
            background: #101b29;
            color: #c9d7e4;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }
        .agent-session-row:hover { border-color: #2a8ad4; background: #122031; }
        .agent-session-row.active {
            border-color: #2a8ad4;
            background: linear-gradient(90deg, #1a2f46, #122031);
            box-shadow: inset 0 0 0 1px #2a8ad466;
        }
        .agent-session-row-title {
            font-size: 15px;
            font-weight: 600;
            color: #e4eef7;
        }
        .agent-session-row-chat {
            font-size: 12px;
            color: #6e9cbf;
            font-family: monospace;
            word-break: break-all;
        }

        /* ── Terminal (⌨️) sidebar tab ── */
        .agent-terminal-tabs {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px;
            border-bottom: 1px solid #223346;
            background: rgba(18, 30, 43, .96);
            flex-shrink: 0;
            overflow-x: auto;
        }
        .agent-terminal-new {
            flex-shrink: 0;
            border: 1px solid #2a8ad4;
            background: transparent;
            color: #2a8ad4;
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 13px;
            cursor: pointer;
        }
        .agent-terminal-new:hover { background: rgba(42, 138, 212, .15); }
        .agent-terminal-tab {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #223346;
            background: #101b29;
            color: #8eaac0;
            border-radius: 6px;
            padding: 5px 8px;
            font-size: 13px;
            font-family: monospace;
            cursor: pointer;
        }
        .agent-terminal-tab.active { border-color: #2a8ad4; color: #e4eef7; background: #14283c; }
        .agent-terminal-close {
            color: #8eaac0;
            line-height: 1;
            font-size: 14px;
            padding: 0 2px;
        }
        .agent-terminal-close:hover { color: #f85149; }
        .agent-terminal-wrap {
            flex: 1;
            min-height: 0;
            position: relative;
            background: #0b1117;
        }
        .agent-terminal-view {
            position: absolute;
            inset: 0;
            display: none;
            padding: 6px;
            overflow: hidden;
        }
        .agent-terminal-view.active { display: block; }
        .agent-terminal-view .xterm { height: 100%; }

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
                <button class="dash-ctrl-btn agent-mini-btn agent-viewers-toggle-btn" id="agent-viewers-toggle" title="Viewers">➤</button>
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
        <div class="agent-viewers-backdrop" id="agent-viewers-backdrop"></div>
        <aside id="agent-viewers" class="agent-viewers" aria-label="Agent viewers">
            <div class="agent-viewers-grip" id="agent-viewers-grip" title="Drag to resize"></div>
            <div class="agent-viewers-tabs" role="tablist">
                <button class="agent-viewers-tab active" data-tab="files" role="tab" aria-selected="true">📁 Files</button>
                <button class="agent-viewers-tab" data-tab="terminal" role="tab" aria-selected="false">⌨️ Term</button>
                <button class="agent-viewers-tab" data-tab="sessions" role="tab" aria-selected="false">💬 Sessions</button>
                <button class="agent-viewers-tab" data-tab="mental" role="tab" aria-selected="false">🧠 Map</button>
                <button class="agent-viewers-tab" data-tab="browser" role="tab" aria-selected="false">🌐 Browser</button>
                <button class="agent-viewers-tab" data-tab="computer" role="tab" aria-selected="false">🖥️ Use</button>
            </div>

            <section class="agent-viewers-panel active" data-panel="files" role="tabpanel">
                @include('livewire.workspace-tab')
            </section>

            <section class="agent-viewers-panel" data-panel="terminal" role="tabpanel">
                <div class="agent-terminal-tabs" id="agent-terminal-tabs">
                    <button class="agent-terminal-new" id="agent-terminal-new" title="Open a new terminal">＋ New</button>
                </div>
                <div class="agent-terminal-wrap" id="agent-terminal-wrap"></div>
            </section>

            <section class="agent-viewers-panel" data-panel="sessions" role="tabpanel">
                <div class="agent-viewers-bar">
                    <span style="font-size:.74em;color:#8eaac0;">💬 Sessions</span>
                    <button class="dash-ctrl-btn agent-mini-btn" id="agent-session-new-btn" title="Create a new session">New Session</button>
                </div>
                <div class="agent-session-list" id="agent-session-list"></div>
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

    {{-- Vendored xterm.js for the Terminal viewer tab (see public/vendor/xterm/) --}}
    <link rel="stylesheet" href="{{ asset('vendor/xterm/xterm.css') }}">
    <script src="{{ asset('vendor/xterm/xterm.js') }}"></script>
    <script src="{{ asset('vendor/xterm/addon-fit.js') }}"></script>

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
                    if (tab.dataset.tab === 'terminal' && typeof initTerminalTab === 'function') initTerminalTab();
                    if (tab.dataset.tab === 'sessions' && typeof loadAgentSessions === 'function') {
                        loadAgentSessions(document.getElementById('agent-session-list'));
                    }
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

                // ── Mobile-first toggle + backdrop ──
                const viewersToggle = document.getElementById('agent-viewers-toggle');
                const viewersBackdrop = document.getElementById('agent-viewers-backdrop');
                function isViewersOpen() { return viewers.classList.contains('open'); }
                function setViewersOpen(open) {
                    viewers.classList.toggle('open', open);
                    if (viewersBackdrop) viewersBackdrop.classList.toggle('open', open);
                }
                if (viewersToggle) {
                    viewersToggle.addEventListener('click', () => setViewersOpen(!isViewersOpen()));
                }
                if (viewersBackdrop) {
                    viewersBackdrop.addEventListener('click', () => setViewersOpen(false));
                }
                // Memory toolbar button also opens the viewer (mobile or desktop)
                if (memToggle) {
                    setViewersOpen(window.innerWidth < 1280 ? true : isViewersOpen());
                    memToggle.addEventListener('click', () => {
                        const filesTab = document.querySelector('.agent-viewers-tab[data-tab="files"]');
                        if (filesTab) selectTab(filesTab);
                        if (window.innerWidth < 1280) setViewersOpen(true);
                    });
                }

                // ── Drag-to-resize on desktop (xl+), persisted ──
                const grip = document.getElementById('agent-viewers-grip');
                const storedW = localStorage.getItem('agent-viewers-width');
                if (storedW) viewers.style.setProperty('--aw-w', storedW + 'px');
                if (grip) {
                    let dragging = false;
                    grip.addEventListener('pointerdown', (e) => {
                        if (window.innerWidth < 1280) return;
                        dragging = true;
                        grip.classList.add('dragging');
                        e.preventDefault();
                    });
                    window.addEventListener('pointermove', (e) => {
                        if (!dragging) return;
                        const rect = viewers.parentElement.getBoundingClientRect();
                        const w = Math.min(rect.width - 120, Math.max(300, rect.right - e.clientX));
                        viewers.style.setProperty('--aw-w', w + 'px');
                    });
                    window.addEventListener('pointerup', () => {
                        if (!dragging) return;
                        dragging = false;
                        grip.classList.remove('dragging');
                        localStorage.setItem('agent-viewers-width', parseInt(viewers.style.getPropertyValue('--aw-w')) || 540);
                    });
                }
            }

            // ── Terminal tab: real xterm instances over WS→PTY bridge ──
            let _agentTerminalInit = false;
            const _agentTerminals = {}; // id -> { term, fit, ws, viewEl, tabEl }
            let _agentTerminalSeq = 0;

            function initTerminalTab() {
                if (typeof Terminal === 'undefined' || typeof FitAddon === 'undefined') {
                    const wrap = document.getElementById('agent-terminal-wrap');
                    if (wrap) {
                        wrap.innerHTML = '<div style="color:#f85149;padding:12px;font-size:.8em;">xterm.js not loaded. Rebuild frontend assets.</div>';
                    }
                    return;
                }
                if (_agentTerminalInit) return;
                _agentTerminalInit = true;
                document.getElementById('agent-terminal-new')?.addEventListener('click', () => addAgentTerminal());
                // Restore the number of terminals that were open (persisted).
                let count = 1;
                try { count = Math.min(parseInt(localStorage.getItem('agent-terminal-count') || '1') || 1, 4); } catch (_) {}
                for (let i = 0; i < count; i++) addAgentTerminal();
            }

            function addAgentTerminal() {
                const wrap = document.getElementById('agent-terminal-wrap');
                const tabsEl = document.getElementById('agent-terminal-tabs');
                if (!wrap || !tabsEl || typeof Terminal === 'undefined') return;

                _agentTerminalSeq++;
                const id = _agentTerminalSeq;
                const path = `/term/${id}`;

                const tab = document.createElement('button');
                tab.className = 'agent-terminal-tab active';
                tab.textContent = `⌘ ${id}`;
                tab.title = `Terminal ${id}`;

                const close = document.createElement('span');
                close.className = 'agent-terminal-close';
                close.textContent = '×';
                close.addEventListener('click', (e) => { e.stopPropagation(); closeAgentTerminal(id); });
                tab.appendChild(close);

                const newBtn = document.getElementById('agent-terminal-new');
                if (newBtn) tabsEl.insertBefore(tab, newBtn); else tabsEl.appendChild(tab);

                const view = document.createElement('div');
                view.className = 'agent-terminal-view active';
                wrap.appendChild(view);

                const term = new Terminal({
                    cursorBlink: true,
                    fontFamily: 'Menlo, Consolas, "DejaVu Sans Mono", monospace',
                    fontSize: 13,
                    theme: {
                        background: '#0b1117', foreground: '#d8e4ef',
                        cursor: '#2a8ad4', selectionBackground: '#1d2c3d',
                        black: '#0b1117', red: '#f85149', green: '#3fb950', yellow: '#d29922',
                        blue: '#2a8ad4', magenta: '#bc8cff', cyan: '#39c5cf', white: '#d8e4ef',
                    },
                });
                const fit = new FitAddon.FitAddon();
                term.loadAddon(fit);
                term.open(view);
                try { fit.fit(); } catch (_) {}

                const ws = new WebSocket(`ws://127.0.0.1:6090${path}`);
                const sendResize = () => {
                    if (ws.readyState === 1) ws.send(JSON.stringify({ type: 'resize', cols: term.cols, rows: term.rows }));
                };
                ws.onopen = () => { try { fit.fit(); sendResize(); } catch (_) {} };
                ws.onmessage = (ev) => {
                    try {
                        const msg = JSON.parse(ev.data);
                        if (msg.type === 'output') term.write(msg.data);
                        else if (msg.type === 'exit') term.write('\r\n\x1b[31m[terminal exited]\x1b[0m\r\n');
                    } catch (_) { term.write(ev.data || ''); }
                };
                ws.onerror = () => {
                    term.write('\r\n\x1b[31m[ws error — is the terminal-bridge running on :6090?]\x1b[0m\r\n');
                };
                ws.onclose = () => { /* keep UI; PTY closed */ };

                term.onData((data) => {
                    if (ws.readyState === 1) ws.send(JSON.stringify({ type: 'input', data }));
                });
                term.onResize(({ cols, rows }) => {
                    if (ws.readyState === 1) ws.send(JSON.stringify({ type: 'resize', cols, rows }));
                });
                window.addEventListener('resize', () => { try { fit.fit(); sendResize(); } catch (_) {} });

                tab.addEventListener('click', () => {
                    Object.values(_agentTerminals).forEach(t => { t.tabEl.classList.remove('active'); t.viewEl.classList.remove('active'); });
                    tab.classList.add('active');
                    view.classList.add('active');
                    setTimeout(() => { try { fit.fit(); sendResize(); } catch (_) {} }, 10);
                });

                _agentTerminals[id] = { id, term, fit, ws, viewEl: view, tabEl: tab };
                persistTerminalCount();
            }

            function closeAgentTerminal(id) {
                const t = _agentTerminals[id];
                if (!t) return;
                try { t.ws.close(); } catch (_) {}
                t.tabEl.remove();
                t.viewEl.remove();
                delete _agentTerminals[id];
                persistTerminalCount();
                // If none left (and xterm available), reopen one so the tab isn't empty.
                const remaining = Object.values(_agentTerminals);
                if (!remaining.length && typeof Terminal !== 'undefined') addAgentTerminal();
            }

            function persistTerminalCount() {
                const n = Object.keys(_agentTerminals).length || 1;
                try { localStorage.setItem('agent-terminal-count', String(n)); } catch (_) {}
            }

            // ── Sessions tab: New Session button + hookups to dashboard.js ──
            document.getElementById('agent-session-new-btn')?.addEventListener('click', () => {
                if (typeof createAgentSession === 'function') createAgentSession();
            });
        })();
    </script>
</div>
