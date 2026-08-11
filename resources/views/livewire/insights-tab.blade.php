<div class="space-y-4">
    <div>
        <h2 class="text-2xl font-bold text-white">💡 Insights</h2>
        <p class="text-gray-400 text-sm">Resolution rates · Ecosystem growth · Verification · Thoughts</p>
    </div>

    <div id="insights-panel" style="padding:20px 24px; overflow-y:auto;">
        <div id="ins-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;"></div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
            <div style="flex:1;min-width:280px;background:#161b22;border:1px solid #30363d;border-radius:8px;padding:14px;">
                <div style="font-size:0.78em;color:#8b949e;margin-bottom:8px;">Resolution Rate by Simulation</div>
                <div style="position:relative;height:180px;"><canvas id="ins-chart-res"></canvas></div>
            </div>
            <div style="flex:1;min-width:280px;background:#161b22;border:1px solid #30363d;border-radius:8px;padding:14px;">
                <div style="font-size:0.78em;color:#8b949e;margin-bottom:8px;">Ecosystem Skill Growth</div>
                <div style="position:relative;height:180px;"><canvas id="ins-chart-eco"></canvas></div>
            </div>
            <div style="flex:0 0 220px;background:#161b22;border:1px solid #30363d;border-radius:8px;padding:14px;">
                <div style="font-size:0.78em;color:#8b949e;margin-bottom:8px;">ADR-006 Verification</div>
                <div style="position:relative;height:180px;"><canvas id="ins-chart-verify"></canvas></div>
            </div>
        </div>
        <div style="background:#161b22;border:1px solid #30363d;border-radius:8px;padding:14px;">
            <div style="font-size:0.78em;color:#8b949e;margin-bottom:10px;display:flex;justify-content:space-between;">
                <span>💭 Thoughts Today</span>
                <span id="ins-thought-count" style="color:#3fb950;"></span>
            </div>
            <div id="ins-thoughts" style="max-height:300px;overflow-y:auto;"></div>
        </div>
    </div>
</div>
