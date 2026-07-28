<div class="space-y-4">
    <div>
        <h2 class="text-2xl font-bold text-white">🤖 Replicas</h2>
        <p class="text-gray-400 text-sm">Spawn named replicas · Run async pipelines</p>
    </div>

    <div id="replicas-panel" style="padding:20px 24px;">
        <div style="background:#161b22;border:1px solid #30363d;border-radius:8px;padding:14px;margin-bottom:16px;">
            <div style="font-size:0.78em;color:#8b949e;margin-bottom:10px;text-transform:uppercase;letter-spacing:.08em;">
                Spawn Named Replica</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <input id="rep-name" class="dash-mono-input" placeholder="name (e.g. analyst)" style="flex:1;min-width:120px;">
                <select id="rep-role" class="dash-mono-input">
                    <option value="custom">custom</option>
                    <option value="writer">writer</option>
                    <option value="critic">critic</option>
                    <option value="analyst">analyst</option>
                    <option value="planner">planner</option>
                </select>
                <input id="rep-brief" class="dash-mono-input" placeholder="Custom system prompt…" style="flex:2;min-width:200px;">
                <button class="dash-ctrl-btn success" onclick="spawnReplica()">+ Spawn</button>
            </div>
            <div id="rep-spawn-msg" style="font-size:0.72em;color:#8b949e;margin-top:6px;"></div>
        </div>
        <div style="font-size:0.78em;color:#8b949e;margin-bottom:8px;display:flex;justify-content:space-between;">
            <span style="text-transform:uppercase;letter-spacing:.08em;">Active Replicas</span>
            <span id="rep-count" style="color:#58a6ff;"></span>
        </div>
        <div id="rep-list" style="display:flex;flex-direction:column;gap:6px;"></div>
        <div style="background:#161b22;border:1px solid #30363d;border-radius:8px;padding:14px;margin-top:16px;">
            <div style="font-size:0.78em;color:#8b949e;margin-bottom:10px;text-transform:uppercase;letter-spacing:.08em;">
                Run Async Pipeline</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <input id="pipe-task" class="dash-mono-input" placeholder="Task for writer stage…" style="flex:2;min-width:200px;">
                <button class="dash-ctrl-btn success" onclick="launchPipeline()">▶ writer→critic</button>
            </div>
            <div id="pipe-status" style="font-size:0.72em;color:#8b949e;margin-top:6px;"></div>
        </div>
    </div>
</div>
