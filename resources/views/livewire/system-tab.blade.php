<div class="space-y-4">
    <div>
        <h2 class="text-2xl font-bold text-white">⚙️ System Status</h2>
        <p class="text-gray-400 text-sm">Backups · Init · Workspace · Ecosystem</p>
    </div>

    <div id="system-panel">
        <div class="dash-stats" style="margin-top: 10px; margin-bottom: 10px;">
            <div class="dash-stat green"><div class="num" id="sys-backups">0</div><div class="lbl">Backups</div></div>
            <div class="dash-stat blue"><div class="num" id="sys-workspace">0 MB</div><div class="lbl">Workspace Size</div></div>
            <div class="dash-stat orange"><div class="num" id="sys-messages">0</div><div class="lbl">Chat Messages</div></div>
            <div class="dash-stat purple"><div class="num" id="sys-promoted">0</div><div class="lbl">Promoted Signals</div></div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 20px; padding: 20px 24px;">
            <div style="border: 1px solid #30363d; border-radius: 8px; padding: 16px; background: #161b22;">
                <div class="panel-title">Backup History</div>
                <div class="backup-list" id="backup-list" style="margin-top: 10px;"></div>
                <button class="dash-ctrl-btn" onclick="createBackup()" style="margin-top: 10px;">💾 Create Backup</button>
                <div id="backup-msg" style="font-size: 0.75em; color: #8b949e; margin-top: 6px;"></div>
            </div>
            <div style="border: 1px solid #30363d; border-radius: 8px; padding: 16px; background: #161b22;">
                <div class="panel-title">Workspace Status</div>
                <div id="workspace-status" style="margin-top: 10px; font-size: 0.75em;"></div>
                <button class="dash-ctrl-btn" onclick="runInit()" style="margin-top:10px;">⚙️ Initialize</button>
            </div>
            <div style="border: 1px solid #30363d; border-radius: 8px; padding: 16px; background: #161b22;">
                <div class="panel-title">Ecosystem &amp; Configuration</div>
                <div id="ecosystem-status" style="margin-top:10px; font-size:0.75em;"></div>
            </div>
        </div>
    </div>
</div>
