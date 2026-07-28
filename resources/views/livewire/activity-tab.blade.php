<div class="space-y-4">
    <div>
        <h2 class="text-2xl font-bold text-white">📊 Activity</h2>
        <p class="text-gray-400 text-sm">Timeline · Event log · Open gaps</p>
    </div>

    <div id="activity-panel">
        <div class="activity-layout">
            <div class="activity-card activity-timeline-card">
                <div id="timeline-panel" class="activity-scroll">
                    <div class="timeline-head">
                        <div class="panel-title" style="margin-bottom:0;">Timeline</div>
                        <div class="timeline-dir">Newest &larr; Oldest</div>
                    </div>
                    <svg id="timeline-svg" height="70"></svg>
                </div>
            </div>

            <div class="activity-card activity-log-card">
                <div id="log-panel" class="activity-scroll">
                    <div class="panel-title">Event Log</div>
                    <div id="log-entries"></div>
                </div>
            </div>

            <div class="activity-card activity-gap-card">
                <div id="gaps-panel" class="activity-scroll">
                    <div class="panel-title" style="color:#f85149">Open Gaps</div>
                    <div id="gap-entries"></div>
                </div>
            </div>
        </div>
    </div>
</div>
