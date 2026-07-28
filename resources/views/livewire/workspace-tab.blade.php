<div id="memory-panel" style="padding:20px 24px;">
    <!-- Top bar: stats -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <div style="font-size:0.7em;color:#8b949e;text-transform:uppercase;letter-spacing:.1em;">Workspace Browser</div>
        <div style="display:flex;gap:8px;align-items:center;">
            <button class="dash-ctrl-btn" id="mem-refresh-btn" onclick="loadWorkspaceTree('/');">↺ Refresh</button>
        </div>
    </div>
    <div id="mem-layout">
        <!-- Left: file tree -->
        <div id="mem-tree-panel">
            <div id="mem-tree-header">
                <button id="mem-collapse-btn" title="Toggle tree" onclick="_memToggleTree()">«</button>
                <span>FILES</span>
                <input id="mem-search" placeholder="filter..." oninput="filterMemoryTree(this.value)" class="dash-mono-input" style="width:80px;">
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
            </div>
            <div id="mem-editor-view" style="flex:1;overflow:auto;padding:14px;">
                <div style="color:#555;font-size:0.8em;font-family:monospace;">Select a file from the tree to view its contents.</div>
            </div>
            <textarea id="mem-editor-textarea" style="display:none;flex:1;resize:none;background:#0d1117;color:#c9d1d9;border:none;padding:14px;font-family:monospace;font-size:0.82em;line-height:1.5;outline:none;width:100%;"></textarea>
        </div>
    </div>
</div>
