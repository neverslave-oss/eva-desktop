<div id="memory-panel" style="padding:20px 24px;">
    <!-- Top bar: stats -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <div style="font-size:0.7em;color:#8b949e;text-transform:uppercase;letter-spacing:.1em;">Memory &amp; Workspace</div>
        <div style="display:flex;gap:8px;align-items:center;">
            <button class="dash-ctrl-btn" id="mem-refresh-btn" onclick="loadMemoryStats();loadWorkspaceTree('/');">↺ Refresh</button>
        </div>
    </div>
    <!-- Stats bar -->
    <div id="mem-stats-bar" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <div class="dash-stat blue" style="min-width:90px;"><div class="num" id="mem-stat-files">—</div><div class="lbl">Files</div></div>
        <div class="dash-stat purple" style="min-width:90px;"><div class="num" id="mem-stat-size">—</div><div class="lbl">Total Size</div></div>
        <div class="dash-stat green" style="min-width:90px;"><div class="num" id="mem-stat-sessions">—</div><div class="lbl">Chat Sessions</div></div>
        <div class="dash-stat orange" style="min-width:90px;"><div class="num" id="mem-stat-dbsize">—</div><div class="lbl">DB Size</div></div>
    </div>
    <!-- Context menu -->
    <div id="mem-ctx-menu" style="display:none;position:fixed;background:#161b22;border:1px solid #30363d;border-radius:6px;z-index:1000;padding:4px 0;min-width:130px;box-shadow:0 4px 12px rgba(0,0,0,.5);">
        <div style="padding:6px 12px;cursor:pointer;font-size:0.78em;font-family:monospace;color:#c9d1d9;" onclick="openMemoryFile()">📂 Open</div>
        <div style="padding:6px 12px;cursor:pointer;font-size:0.78em;font-family:monospace;color:#f85149;" onclick="deleteCurrentMemoryFile()">🗑 Delete</div>
    </div>
    <div id="mem-layout">
        <!-- Left: file tree -->
        <div id="mem-tree-panel">
            <div id="mem-tree-header">
                <button id="mem-collapse-btn" title="Toggle tree" onclick="_memToggleTree()">«</button>
                <span>FILES</span>
                <input id="mem-search" placeholder="filter..." oninput="filterMemoryTree(this.value)" class="dash-mono-input" style="width:80px;">
                <button class="dash-ctrl-btn" style="padding:3px 8px;font-size:0.7em;" onclick="promptNewMemoryFile()">+ New</button>
            </div>
            <div id="mem-file-tree" style="padding:6px 0;"></div>
        </div>
        <!-- Right: editor -->
        <div id="mem-editor-panel">
            <div id="mem-editor-header" style="display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid #21262d;background:#0f141a;min-height:42px;flex-wrap:wrap;">
                <span id="mem-editor-filename" style="font-size:0.82em;color:#79c0ff;font-family:monospace;">No file selected</span>
                <span id="mem-editor-meta" style="font-size:0.7em;color:#8b949e;margin-left:auto;"></span>
                <button class="dash-ctrl-btn" id="mem-edit-btn" style="display:none;" onclick="toggleMemoryEdit()">Edit</button>
                <button class="dash-ctrl-btn success" id="mem-save-btn" style="display:none;" onclick="saveCurrentMemoryFile()">Save</button>
                <button class="dash-ctrl-btn" id="mem-cancel-btn" style="display:none;" onclick="cancelMemoryEdit()">Cancel</button>
                <button class="dash-ctrl-btn danger" id="mem-delete-btn" style="display:none;" onclick="deleteCurrentMemoryFile()">🗑 Delete</button>
            </div>
            <div id="mem-editor-view" style="flex:1;overflow:auto;padding:14px;">
                <div style="color:#555;font-size:0.8em;font-family:monospace;">Select a file from the tree to view its contents.</div>
            </div>
            <textarea id="mem-editor-textarea" style="display:none;flex:1;resize:none;background:#0d1117;color:#c9d1d9;border:none;padding:14px;font-family:monospace;font-size:0.82em;line-height:1.5;outline:none;width:100%;"></textarea>
            <!-- SQLite viewer panel (hidden by default) -->
            <div id="mem-sqlite-view" style="display:none;flex:1;flex-direction:column;overflow:hidden;">
                <div id="mem-sqlite-header" style="display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid #21262d;background:#0f141a;min-height:42px;flex-wrap:wrap;">
                    <span id="mem-sqlite-filename" style="font-size:0.82em;color:#79c0ff;font-family:monospace;">No file selected</span>
                    <span id="mem-sqlite-meta" style="font-size:0.7em;color:#8b949e;margin-left:auto;"></span>
                    <button class="dash-ctrl-btn" id="mem-sqlite-refresh-btn" onclick="sqliteRefresh()">↺ Refresh</button>
                    <button class="dash-ctrl-btn danger" id="mem-sqlite-clear-btn" onclick="sqliteClearAllTables()">🗑 Clear all tables</button>
                    <button class="dash-ctrl-btn" id="mem-sqlite-close-btn" onclick="sqliteClose()">Close</button>
                </div>
                <div id="mem-sqlite-tables-bar" style="display:flex;align-items:center;gap:8px;padding:6px 14px;border-bottom:1px solid #21262d;background:#0d1117;flex-wrap:wrap;">
                    <span style="font-size:0.7em;color:#8b949e;text-transform:uppercase;letter-spacing:.08em;">Tables:</span>
                    <div id="mem-sqlite-tables-list" style="display:flex;gap:6px;flex-wrap:wrap;"></div>
                </div>
                <div id="mem-sqlite-table-data" style="flex:1;overflow:auto;padding:10px 14px;">
                    <div style="color:#555;font-size:0.8em;font-family:monospace;">Select a table to view its data.</div>
                </div>
                <div id="mem-sqlite-pagination" style="display:none;padding:8px 14px;border-top:1px solid #21262d;background:#0d1117;align-items:center;gap:10px;flex-wrap:wrap;">
                    <button class="dash-ctrl-btn" id="sqlite-prev-btn" onclick="sqlitePrevPage()">◀ Prev</button>
                    <span id="sqlite-page-info" style="font-size:0.72em;color:#8b949e;"></span>
                    <button class="dash-ctrl-btn" id="sqlite-next-btn" onclick="sqliteNextPage()">Next ▶</button>
                    <span style="flex:1;"></span>
                    <span id="sqlite-total-rows" style="font-size:0.72em;color:#8b949e;"></span>
                </div>
            </div>
        </div>
    </div>
</div>
