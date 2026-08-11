<div style="padding:20px 24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <div style="font-size:0.7em;color:#8b949e;text-transform:uppercase;letter-spacing:.1em;">SQLite Viewer</div>
        <div style="display:flex;gap:8px;align-items:center;">
            <button class="dash-ctrl-btn" onclick="sqliteRefresh()">↺ Refresh</button>
        </div>
    </div>
    <div id="mem-sqlite-view" style="display:flex;flex-direction:column;overflow:hidden;border:1px solid #21262d;border-radius:8px;background:#0d1117;min-height:500px;">
        <div id="mem-sqlite-header" style="display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid #21262d;background:#0f141a;min-height:42px;flex-wrap:wrap;">
            <span id="mem-sqlite-filename" style="font-size:0.82em;color:#79c0ff;font-family:monospace;">Select a database</span>
            <span id="mem-sqlite-meta" style="font-size:0.7em;color:#8b949e;margin-left:auto;"></span>
            <button class="dash-ctrl-btn" id="mem-sqlite-refresh-btn" onclick="sqliteRefresh()">↺ Refresh</button>
            <button class="dash-ctrl-btn danger" id="mem-sqlite-clear-btn" onclick="sqliteClearAllTables()">🗑 Clear all tables</button>
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
