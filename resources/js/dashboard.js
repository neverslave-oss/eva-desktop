/* ═══════════════════════════════════════════════════════════════
   Kernel Desktop — Evolution Dashboard JavaScript
   Source: kernel-evolving/src/views/evolution_dashboard.html
   KERNEL_API_BASE defaults to 127.0.0.1
   ═══════════════════════════════════════════════════════════════ */

window.KERNEL_API_BASE = window.KERNEL_API_BASE || 'http://127.0.0.1:8779';

function kapi(path) {
    if (!path) return window.KERNEL_API_BASE;
    if (path.startsWith('http')) return path;
    return window.KERNEL_API_BASE + (path.startsWith('/') ? path : '/' + path);
}

// Suppress evolution/activity-only render errors on other pages
function safeById(id) {
    const e = document.getElementById(id);
    return e || null;
}

// ── Connectivity heartbeat — replaces per-request console spam with a single
// periodic check, an in-app banner, and a debounced native OS notification.
window.__kernelAgentOnline = null;
let _agentOfflineNotifiedAt = 0;
const _csrfToken = () => document.querySelector('meta[name=csrf-token]')?.content || '';

function _agentOfflineBannerEl() {
    let el = document.getElementById('agent-offline-banner');
    if (!el) {
        el = document.createElement('div');
        el.id = 'agent-offline-banner';
        el.style.cssText = 'position:fixed;bottom:0;right:1rem;z-index:9999;background:#3a1a1a;color:#f85149;padding:8px 16px;font-size:0.8em;font-family:monospace;display:none;align-items:center;gap:12px;justify-content:center;border-bottom:1px solid #f85149;';
        el.innerHTML = '<span>⚠ Eva Agent offline</span>' +
            '<button onclick="kernelAgentAction(\'start\')" style="padding:2px 10px;border:1px solid #f85149;border-radius:4px;background:transparent;color:#f85149;cursor:pointer;">Start</button>' +
            '<button onclick="kernelAgentAction(\'restart\')" style="padding:2px 10px;border:1px solid #f85149;border-radius:4px;background:transparent;color:#f85149;cursor:pointer;">Restart</button>';
        document.body.prepend(el);
    }
    return el;
}

async function kernelHeartbeat() {
    try {
        await fetch(kapi('/health'), { signal: AbortSignal.timeout(3500) });
        if (window.__kernelAgentOnline === false) _agentOfflineBannerEl().style.display = 'none';
        window.__kernelAgentOnline = true;
    } catch (_) {
        window.__kernelAgentOnline = false;
        _agentOfflineBannerEl().style.display = 'flex';
        const now = Date.now();
        if (now - _agentOfflineNotifiedAt > 180000) {
            _agentOfflineNotifiedAt = now;
            fetch('/agent/notify-offline', { method: 'POST', headers: { 'X-CSRF-TOKEN': _csrfToken() } }).catch(() => {});
        }
    }
}

async function kernelAgentAction(action) {
    try {
        await fetch('/settings/agent/' + action, { method: 'POST', headers: { 'X-CSRF-TOKEN': _csrfToken() } });
    } catch (_) { }
    setTimeout(kernelHeartbeat, 3000);
}

setInterval(kernelHeartbeat, 15000);
kernelHeartbeat();

// Data injected server-side (with fallback defaults for dev/Live Preview)
const EVENTS = (typeof __EVENTS__ !== 'undefined') ? __EVENTS__ : [];
const GAPS = (typeof __GAPS__ !== 'undefined') ? __GAPS__ : [];
const STATS = (typeof __STATS__ !== 'undefined') ? __STATS__ : {};
const AGENT_SESSION_STORAGE_KEY = 'kernel.evolutionDashboard.agentSessionId';
const AGENT_SELECTED_PROMPT_KEY = 'kernel.evolutionDashboard.agentSelectedPromptId';

let _agentInitDone = false;
let _agentBusy = false;
let _agentLogs = [];
let _agentCurrentPrompt = null;
let _agentCurrentTrace = [];
let _agentCurrentTrajectories = [];
let _agentSessionCleared = false;
let _agentLiveThreadTimer = null;
let _agentLiveThreadSig = '';
let _agentProviderSnapshot = null;
let _tabBarOpen = false;

function setTabBarOpen(open) {
    _tabBarOpen = Boolean(open);
    const tabBar = document.getElementById('tab-bar');
    const btn = document.getElementById('tab-bar-toggle');
    const indicator = document.getElementById('tab-bar-toggle-indicator');
    if (tabBar) tabBar.classList.toggle('open', _tabBarOpen);
    if (btn) btn.setAttribute('aria-expanded', _tabBarOpen ? 'true' : 'false');
    if (indicator) indicator.textContent = _tabBarOpen ? '▲' : '▼';
}

function toggleTabBar() {
    setTabBarOpen(!_tabBarOpen);
}

function stableSessionId(prefix) {
    const rand = Math.random().toString(36).slice(2, 10);
    return `${prefix}-${rand}`;
}

function agentSessionId() {
    try {
        let value = localStorage.getItem(AGENT_SESSION_STORAGE_KEY);
        if (!value) {
            value = stableSessionId('agent');
            localStorage.setItem(AGENT_SESSION_STORAGE_KEY, value);
        }
        return value;
    } catch (_) {
        if (!window.__agentSessionId) window.__agentSessionId = stableSessionId('agent');
        return window.__agentSessionId;
    }
}

function setAgentSessionId(value) {
    try {
        localStorage.setItem(AGENT_SESSION_STORAGE_KEY, value);
    } catch (_) {
        window.__agentSessionId = value;
    }
}

function setAgentSelectedPromptId(value) {
    try {
        if (value) localStorage.setItem(AGENT_SELECTED_PROMPT_KEY, value);
        else localStorage.removeItem(AGENT_SELECTED_PROMPT_KEY);
    } catch (_) { }
}

function getAgentSelectedPromptId() {
    try {
        return localStorage.getItem(AGENT_SELECTED_PROMPT_KEY) || '';
    } catch (_) {
        return '';
    }
}

function agentSetStatus(text) {
    const el = document.getElementById('agent-status');
    if (el) el.textContent = text;
}

function setAgentProviderMeta(text, isError = false) {
    const el = document.getElementById('agent-provider-meta');
    if (!el) return;
    el.textContent = text || '';
    el.style.color = isError ? '#f85149' : '#8b949e';
}

function getSelectedAgentCallType() {
    const select = document.getElementById('agent-provider-calltype');
    return select?.value || 'task_inference';
}

function applyAgentCallTypeSelection(callType) {
    const snapshot = _agentProviderSnapshot || {};
    const route = (snapshot.routing || {})[callType] || {};
    const providerSelect = document.getElementById('agent-provider-select');
    const modelInput = document.getElementById('agent-model-input');
    const overrides = snapshot.model_overrides || {};
    const catalog = snapshot.model_catalog || {};

    if (providerSelect) {
        const providers = snapshot.providers || ['local', 'openai', 'anthropic', 'hf', 'copilot'];
        providerSelect.innerHTML = providers.map(p => {
            const availability = snapshot.available?.[p];
            const suffix = availability?.ready ? '' : ' (unavailable)';
            return `<option value="${agentEscapeText(p)}">${agentEscapeText(p + suffix)}</option>`;
        }).join('');
        providerSelect.value = route.provider || providers[0] || 'local';
    }

    if (modelInput) {
        const override = overrides[callType] || '';
        const fallbackModel = route.model || catalog[route.provider] || '';
        modelInput.value = override || fallbackModel;
    }

    const provider = route.provider || 'unknown';
    const model = route.model || (catalog[provider] || 'n/a');
    setAgentProviderMeta(`Current ${callType}: ${provider} / ${model}`);
}

async function loadAgentProviderControls() {
    try {
        const [routingResp, availabilityResp] = await Promise.all([
            fetchJsonWithTimeout(kapi('/provider'), 7000),
            fetchJsonWithTimeout(kapi('/provider/available'), 7000),
        ]);
        _agentProviderSnapshot = {
            ...routingResp,
            available: availabilityResp || {},
        };

        const callTypeSelect = document.getElementById('agent-provider-calltype');
        if (callTypeSelect) {
            const callTypes = routingResp.call_types || ['task_inference', 'synthesis', 'critic', 'planning', 'trajectory_teacher'];
            const previous = callTypeSelect.value;
            callTypeSelect.innerHTML = callTypes.map(ct => `<option value="${agentEscapeText(ct)}">${agentEscapeText(ct)}</option>`).join('');
            callTypeSelect.value = callTypes.includes(previous) ? previous : 'task_inference';
        }
        applyAgentCallTypeSelection(getSelectedAgentCallType());
    } catch (e) {
        setAgentProviderMeta(`Provider controls unavailable: ${e.message}`, true);
    }
}

async function applyAgentProviderControls() {
    const callType = getSelectedAgentCallType();
    const provider = document.getElementById('agent-provider-select')?.value || '';
    const model = (document.getElementById('agent-model-input')?.value || '').trim();
    const persist = Boolean(document.getElementById('agent-provider-persist')?.checked);

    if (!callType || !provider) {
        setAgentProviderMeta('Select call type and provider first.', true);
        return;
    }

    const body = { [callType]: provider, persist };
    if (model) {
        body.model_override = { [callType]: model };
    }

    setAgentProviderMeta('Applying provider/model settings…');
    try {
        const res = await fetch(kapi('/provider/set'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok || data.error) {
            throw new Error(data.error || `HTTP ${res.status}`);
        }
        const actions = Array.isArray(data.vram_actions) && data.vram_actions.length
            ? ` · ${data.vram_actions.join(' | ')}`
            : '';
        setAgentProviderMeta(`Updated ${callType} -> ${provider}${model ? ` / ${model}` : ''}${actions}`);
        await loadAgentProviderControls();
    } catch (e) {
        setAgentProviderMeta(`Provider update failed: ${e.message}`, true);
    }
}

function agentEscapeText(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function agentAppendBubble(role, text, extraClass = '') {
    const thread = document.getElementById('agent-thread');
    if (!thread) return null;
    const wrap = document.createElement('div');
    wrap.className = `agent-bubble ${role}${extraClass ? ` ${extraClass}` : ''}`;
    wrap.dataset.role = role;
    wrap.textContent = text || '';
    const meta = document.createElement('div');
    meta.className = 'agent-bubble-meta';
    meta.textContent = `${role === 'user' ? 'You' : 'Agent'} · ${new Date().toLocaleTimeString()}`;
    wrap.appendChild(meta);
    thread.appendChild(wrap);
    thread.scrollTop = thread.scrollHeight;
    return wrap;
}

function agentUpdateBubble(node, text) {
    if (!node) return;
    const meta = node.querySelector('.agent-bubble-meta');
    node.textContent = text || '';
    if (meta) node.appendChild(meta);
    const thread = document.getElementById('agent-thread');
    if (thread) thread.scrollTop = thread.scrollHeight;
}

function agentClearThread() {
    const thread = document.getElementById('agent-thread');
    if (thread) thread.innerHTML = '';
}

function agentSourceLabel(sessionId) {
    const sid = String(sessionId || '');
    if (!sid) return 'unknown';
    if (/^\d+$/.test(sid)) return `telegram:${sid}`;
    if (sid.startsWith('agent-')) return `dashboard:${sid}`;
    if (sid.startsWith('api-')) return `api:${sid}`;
    if (sid.startsWith('web-')) return `web:${sid}`;
    return sid;
}

function renderAgentThreadFromHistory(rows) {
    const thread = document.getElementById('agent-thread');
    if (!thread) return;
    const history = Array.isArray(rows) ? rows : [];
    thread.innerHTML = '';
    if (!history.length) {
        agentAppendBubble('assistant', 'No persisted conversation yet.');
        return;
    }

    history.forEach(turn => {
        const roleRaw = String(turn?.role || '').toLowerCase();
        const role = roleRaw === 'user' ? 'user' : 'assistant';
        const bubble = document.createElement('div');
        bubble.className = `agent-bubble ${role}`;
        bubble.dataset.role = role;
        bubble.textContent = String(turn?.content || '');

        const meta = document.createElement('div');
        meta.className = 'agent-bubble-meta';
        const who = role === 'user' ? 'User' : 'Agent';
        const src = agentSourceLabel(turn?.session_id);
        const when = turn?.created_at ? new Date(turn.created_at).toLocaleTimeString() : 'unknown time';
        meta.textContent = `${who} · ${src} · ${when}`;
        bubble.appendChild(meta);

        thread.appendChild(bubble);
    });

    thread.scrollTop = thread.scrollHeight;
}

async function syncAgentLiveThread() {
    if (_agentBusy) return;
    try {
        const data = await fetchJsonWithTimeout(kapi('/debug/chat-history?include_all=true&limit=120'), 7000);
        const rows = Array.isArray(data.history) ? data.history : [];
        const sig = rows.map(r => `${r.created_at || ''}|${r.session_id || ''}|${r.role || ''}|${String(r.content || '').length}`).join('||');
        if (sig === _agentLiveThreadSig) return;
        _agentLiveThreadSig = sig;
        renderAgentThreadFromHistory(rows);
    } catch (_) {
        // Keep existing thread render on transient API failures.
    }
}

function startAgentLiveThread() {
    if (_agentLiveThreadTimer) return;
    syncAgentLiveThread();
    _agentLiveThreadTimer = setInterval(syncAgentLiveThread, 2000);
}

function stopAgentLiveThread() {
    if (_agentLiveThreadTimer) {
        clearInterval(_agentLiveThreadTimer);
        _agentLiveThreadTimer = null;
    }
}

function setAgentInspectorOpen(open) {
    const inspector = document.getElementById('agent-inspector');
    const backdrop = document.getElementById('agent-inspector-backdrop');
    const toggleBtn = document.getElementById('agent-inspector-toggle');
    const isOpen = Boolean(open);
    if (inspector) inspector.classList.toggle('open', isOpen);
    if (backdrop) backdrop.classList.toggle('open', isOpen);
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function setAgentCommandMenuOpen(open) {
    const sheet = document.getElementById('agent-command-sheet');
    const backdrop = document.getElementById('agent-command-sheet-backdrop');
    const toggleBtn = document.getElementById('agent-command-menu-toggle');
    const isOpen = Boolean(open);
    if (sheet) {
        sheet.classList.toggle('open', isOpen);
        sheet.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    }
    if (backdrop) backdrop.classList.toggle('open', isOpen);
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function setAgentProviderSheetOpen(open) {
    const sheet = document.getElementById('agent-provider-sheet');
    const backdrop = document.getElementById('agent-provider-sheet-backdrop');
    const toggleBtn = document.getElementById('agent-provider-menu-toggle');
    const isOpen = Boolean(open);
    if (sheet) {
        sheet.classList.toggle('open', isOpen);
        sheet.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    }
    if (backdrop) backdrop.classList.toggle('open', isOpen);
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function agentBuildModelTurns(payload) {
    const turns = [];
    const base = Array.isArray(payload?.model_messages)
        ? payload.model_messages
        : (Array.isArray(payload?.history) ? payload.history : []);

    base.forEach((turn) => {
        if (!turn || typeof turn !== 'object') return;
        const role = String(turn.role || '').trim().toLowerCase();
        if (!role || !['user', 'assistant', 'tool', 'system'].includes(role)) return;
        turns.push({ role, content: String(turn.content ?? '') });
    });

    const userMessage = String(payload?.user_message || '');
    if (userMessage) {
        const last = turns[turns.length - 1];
        if (!(last && last.role === 'user' && last.content === userMessage)) {
            turns.push({ role: 'user', content: userMessage, _source: 'user_message' });
        }
    }

    return turns;
}

function renderAgentHistory(history, meta = {}) {
    const el = document.getElementById('agent-history');
    if (!el) return;
    if (!Array.isArray(history) || !history.length) {
        const where = agentEscapeText(meta.source || 'this view');
        const sid = agentEscapeText(meta.chatId || agentSessionId());
        const persistedTurns = Number(meta.persistedTurns || 0);
        el.innerHTML = `<div style="color:#f85149;font-size:0.72em;line-height:1.45;">No conversation turns were passed to the model in ${where}.<br>This usually means context persistence failed or the session is brand-new.<br>Session: <strong>${sid}</strong> · Persisted turns: <strong>${persistedTurns}</strong></div>`;
        return;
    }
    el.innerHTML = history.map((turn, idx) => {
        const role = agentEscapeText(turn.role || `turn ${idx + 1}`);
        const content = agentEscapeText(turn.content || '');
        const sourceBadge = turn._source === 'user_message'
            ? '<span style="margin-left:6px;font-size:0.74em;color:#58a6ff;">current input</span>'
            : '';
        return `<div class="agent-history-turn">
      <div class="agent-history-role"><span>${role}${sourceBadge}</span><span>#${idx + 1}</span></div>
      <div class="agent-history-content">${content}</div>
    </div>`;
    }).join('');
}

function renderAgentTrace(steps) {
    const el = document.getElementById('agent-trace');
    if (!el) return;
    const trace = Array.isArray(steps) ? steps : [];
    if (!trace.length) {
        el.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No tool calls captured yet.</div>';
        return;
    }
    el.innerHTML = trace.map((step, idx) => {
        const argsText = typeof step.args === 'string' ? step.args : JSON.stringify(step.args || {}, null, 2);
        const resultText = typeof step.result === 'string' ? step.result : JSON.stringify(step.result ?? '', null, 2);
        return `<div class="agent-trace-step">
      <div class="agent-trace-head"><span>${agentEscapeText(step.tool || 'tool')}</span><span>Step ${idx + 1}</span></div>
      <div class="agent-trace-body"><strong>Arguments</strong>\n${agentEscapeText(argsText)}</div>
      <div class="agent-trace-result"><strong>Output</strong>\n${agentEscapeText(resultText)}</div>
    </div>`;
    }).join('');
}

function renderAgentTrajectories(items) {
    const el = document.getElementById('agent-trajectories');
    if (!el) return;
    const rows = Array.isArray(items) ? items : [];
    if (!rows.length) {
        el.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No recent trajectories found.</div>';
        return;
    }
    el.innerHTML = rows.map(row => {
        const toolCount = Array.isArray(row.tool_calls) ? row.tool_calls.length : 0;
        const artifactsCount = Array.isArray(row.artifacts) ? row.artifacts.length : 0;
        const score = row.critic_score == null ? 'n/a' : Number(row.critic_score).toFixed(2);
        const toolCalls = Array.isArray(row.tool_calls) ? row.tool_calls.map((call, idx) => {
            const argsText = typeof call.args === 'string' ? call.args : JSON.stringify(call.args || {}, null, 2);
            return `<div style="margin-top:8px;padding-top:8px;border-top:1px solid #21262d;">
        <div style="color:#58a6ff;font-weight:700;">${agentEscapeText(call.tool || call.name || `tool ${idx + 1}`)}</div>
        <div style="color:#8b949e;white-space:pre-wrap;">${agentEscapeText(argsText)}</div>
        <div style="margin-top:4px;color:#c9d1d9;white-space:pre-wrap;">${agentEscapeText(call.result || '')}</div>
      </div>`;
        }).join('') : '';
        return `<details class="agent-trajectory-item">
      <summary class="agent-trajectory-head"><span>${agentEscapeText(row.task || 'task')}</span><span>${toolCount} tools · ${artifactsCount} artifacts · score ${score}</span></summary>
      <div class="agent-trajectory-body">
        <div style="margin-top:8px;color:#8b949e;">Provider: ${agentEscapeText(row.provider || '')}</div>
        <div style="margin-top:8px;color:#c9d1d9;white-space:pre-wrap;">${agentEscapeText(row.final_reply || '')}</div>
        ${toolCalls}
      </div>
    </details>`;
    }).join('');
}

function renderAgentPromptSelector(logs) {
    const select = document.getElementById('agent-prompt-log-select');
    if (!select) return;
    const entries = Array.isArray(logs) ? logs : [];
    const selected = getAgentSelectedPromptId();
    select.innerHTML = entries.map(log => {
        const label = `${log.ts ? String(log.ts).slice(0, 19).replace('T', ' ') : 'unknown'} · ${log.user_message || '(no user message)'}`;
        return `<option value="${agentEscapeText(log.id)}">#${log.id} · ${agentEscapeText(label)}</option>`;
    }).join('');
    if (!entries.length) {
        select.innerHTML = '<option value="">No prompt logs found</option>';
        return;
    }
    const preferred = entries.find(log => String(log.id) === String(selected)) || entries[0];
    select.value = String(preferred.id);
    setAgentSelectedPromptId(String(preferred.id));
}

async function loadAgentPromptLogs() {
    const sessionId = agentSessionId();
    const summary = document.getElementById('agent-inspector-summary');
    if (_agentSessionCleared) {
        _agentLogs = [];
        renderAgentPromptSelector([]);
        if (summary) summary.textContent = `Session ${sessionId} was cleared. Showing live prompt preview.`;
        await loadAgentPromptPreview();
        return;
    }
    try {
        const data = await fetchJsonWithTimeout(`/debug/prompt-logs?chat_id=${encodeURIComponent(sessionId)}&limit=20`, 7000);
        _agentLogs = data.logs || [];
        renderAgentPromptSelector(_agentLogs);
        if (_agentLogs.length) {
            await loadAgentPromptLog(_agentLogs[0].id);
        } else {
            await loadAgentPromptPreview();
        }
    } catch (e) {
        if (summary) summary.textContent = `Failed to load prompt logs: ${e.message}`;
    }
}

async function loadAgentPromptPreview() {
    const sessionId = agentSessionId();
    const summary = document.getElementById('agent-inspector-summary');
    try {
        const data = await fetchJsonWithTimeout(`/debug/current-prompt?chat_id=${encodeURIComponent(sessionId)}`, 7000);
        _agentCurrentPrompt = data;
        document.getElementById('agent-system-prompt').textContent = data.prompt || 'No prompt available.';
        const modelTurns = agentBuildModelTurns(data);
        renderAgentHistory(modelTurns, {
            source: data.source || 'live-preview',
            chatId: data.chat_id || sessionId,
            persistedTurns: data.persisted_turns || modelTurns.length,
        });
        if (summary) summary.textContent = `Live prompt preview for ${sessionId} · ${data.prompt_len || 0} chars · ${modelTurns.length} model turns`;
    } catch (e) {
        if (summary) summary.textContent = `No prompt logs found for session ${sessionId} and live preview failed: ${e.message}`;
        document.getElementById('agent-system-prompt').textContent = 'No prompt log loaded yet.';
        renderAgentHistory([], { source: 'live-preview', chatId: sessionId, persistedTurns: 0 });
    }
}

async function loadAgentPromptLog(entryId) {
    if (!entryId) return;
    setAgentSelectedPromptId(String(entryId));
    try {
        const data = await fetchJsonWithTimeout(`/debug/prompt-log/${encodeURIComponent(entryId)}`, 7000);
        _agentCurrentPrompt = data;
        document.getElementById('agent-system-prompt').textContent = data.prompt || 'No prompt available.';
        const modelTurns = agentBuildModelTurns(data);
        renderAgentHistory(modelTurns, {
            source: data.source || 'prompt-log',
            chatId: data.chat_id || agentSessionId(),
            persistedTurns: modelTurns.length,
        });
        const summary = document.getElementById('agent-inspector-summary');
        if (summary) {
            summary.textContent = `#${data.id} · ${data.ts || 'unknown'} · ${data.provider || 'unknown provider'} · ${data.model || 'unknown model'} · ${data.prompt_len || 0} chars · ${modelTurns.length} model turns`;
        }
    } catch (e) {
        document.getElementById('agent-system-prompt').textContent = `Failed to load prompt log ${entryId}: ${e.message}`;
    }
}

async function loadAgentTrajectories() {
    try {
        const data = await fetchJsonWithTimeout(kapi('/debug/trajectories?limit=8'), 7000);
        _agentCurrentTrajectories = data.trajectories || [];
        renderAgentTrajectories(_agentCurrentTrajectories);
    } catch (e) {
        const el = document.getElementById('agent-trajectories');
        if (el) el.innerHTML = `<div style="color:#f85149;font-size:0.72em;">Failed to load trajectories: ${agentEscapeText(e.message)}</div>`;
    }
}

async function refreshAgentInspector() {
    await Promise.allSettled([loadAgentPromptLogs(), loadAgentTrajectories()]);
}

function resetAgentUi() {
    _agentSessionCleared = true;
    agentClearThread();
    _agentCurrentTrace = [];
    renderAgentTrace([]);
    renderAgentHistory([]);
    const summary = document.getElementById('agent-inspector-summary');
    if (summary) summary.textContent = 'The current session was cleared. Showing live prompt preview.';
    loadAgentPromptPreview();
}

function updateAgentSessionBadge(sessionId) {
    const sessionEl = document.getElementById('agent-session-id');
    if (sessionEl) sessionEl.textContent = sessionId || '—';
}

function autosizeAgentInput() {
    const input = document.getElementById('agent-input');
    if (!input) return;
    input.style.height = 'auto';
    input.style.height = `${Math.min(input.scrollHeight, 180)}px`;
}

async function postJsonWithTimeout(url, body = {}, timeoutMs = 7000) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            cache: 'no-store',
            signal: ctrl.signal,
            body: JSON.stringify(body || {}),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.error) {
            throw new Error(data.error || `${url} ${res.status}`);
        }
        return data;
    } finally {
        clearTimeout(timer);
    }
}

async function callAgentChatEndpoint(path, payload) {
    const url = kapi(path);
    try {
        return await postJsonWithTimeout(url, payload, 7000);
    } catch (postError) {
        const params = new URLSearchParams();
        Object.entries(payload || {}).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') params.set(key, String(value));
        });
        const fallbackUrl = params.size ? `${url}?${params.toString()}` : url;
        try {
            return await fetchJsonWithTimeout(fallbackUrl, 7000);
        } catch (_) {
            throw postError;
        }
    }
}

async function agentFreshSession() {
    if (_agentBusy) return;
    _agentBusy = true;
    const sessionId = agentSessionId();
    agentSetStatus(`Cleaning session ${sessionId}…`);
    try {
        await callAgentChatEndpoint('/chat/fresh', { chat_id: sessionId });
        resetAgentUi();
        agentAppendBubble('assistant', 'Conversation cleaned. This session is now fresh.');
        agentSetStatus(`Session ${sessionId} cleaned`);
        await refreshAgentInspector();
    } catch (e) {
        agentSetStatus(`Fresh failed: ${e.message}`);
    } finally {
        _agentBusy = false;
    }
}

async function agentSend(text) {
    const payload = (text || document.getElementById('agent-input')?.value || '').trim();
    if (!payload || _agentBusy) return;

    if (payload.toLowerCase() === '/new') {
        await agentNewSession();
        return;
    }
    if (payload.toLowerCase() === '/fresh') {
        await agentFreshSession();
        return;
    }

    _agentBusy = true;
    const input = document.getElementById('agent-input');
    if (input) {
        input.value = '';
        input.style.height = '44px';
    }
    agentAppendBubble('user', payload);
    const assistantNode = agentAppendBubble('assistant', '…');
    _agentCurrentTrace = [];
    renderAgentTrace([]);
    agentSetStatus(`Sending to session ${agentSessionId()}…`);

    try {
        const response = await fetch(kapi('/message/stream'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: payload, chat_id: agentSessionId() }),
        });

        if (!response.ok || !response.body) {
            const text = await response.text();
            throw new Error(`HTTP ${response.status}: ${text.slice(0, 200)}`);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let replyText = '';
        let replyButtons = [];

        const flushLine = async (line) => {
            if (!line) return;
            let event = null;
            try {
                event = JSON.parse(line);
            } catch (_) {
                return;
            }
            if (event.event === 'chunk') {
                replyText += event.text || '';
                agentUpdateBubble(assistantNode, replyText || '…');
            } else if (event.event === 'step') {
                _agentCurrentTrace.push({ tool: event.tool, args: event.args || {}, result: event.result || '' });
                renderAgentTrace(_agentCurrentTrace);
                agentSetStatus(`Step ${event.step}: ${event.tool}`);
            } else if (event.event === 'done') {
                if (event.reply) replyText = event.reply;
                if (Array.isArray(event.buttons)) replyButtons = event.buttons;
            } else if (event.event === 'error') {
                throw new Error(event.error || 'Unknown stream error');
            }
        };

        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            let idx = buffer.indexOf('\n');
            while (idx >= 0) {
                const line = buffer.slice(0, idx).trim();
                buffer = buffer.slice(idx + 1);
                await flushLine(line);
                idx = buffer.indexOf('\n');
            }
        }

        const tail = buffer.trim();
        if (tail) await flushLine(tail);

        agentUpdateBubble(assistantNode, replyText || '(no reply)');
        if (Array.isArray(replyButtons) && replyButtons.length) {
            const buttonsText = replyButtons
                .map(row => Array.isArray(row) ? row.map(btn => btn.text || btn.label || btn.callback_data || '').filter(Boolean).join(' · ') : '')
                .filter(Boolean)
                .join(' | ');
            if (buttonsText) {
                const btn = document.createElement('div');
                btn.className = 'agent-bubble-meta';
                btn.textContent = `Buttons: ${buttonsText}`;
                assistantNode.appendChild(btn);
            }
        }

        _agentSessionCleared = false;
        agentSetStatus('Response received. Loading prompt snapshot…');
        await refreshAgentInspector();
        agentSetStatus('Ready');
    } catch (e) {
        agentUpdateBubble(assistantNode, `Error: ${e.message}`);
        assistantNode.classList.add('error');
        agentSetStatus(`Error: ${e.message}`);
        await refreshAgentInspector();
    } finally {
        _agentBusy = false;
        syncAgentLiveThread();
    }
}

async function agentNewSession() {
    if (_agentBusy) return;
    _agentBusy = true;

    const previousSessionId = agentSessionId();
    agentSetStatus(`Creating new session from ${previousSessionId}…`);
    try {
        const data = await callAgentChatEndpoint('/chat/new', { chat_id: previousSessionId });
        const newSessionId = String(data?.chat_id || stableSessionId('agent'));
        setAgentSessionId(newSessionId);
        setAgentSelectedPromptId('');
        updateAgentSessionBadge(newSessionId);
        resetAgentUi();
        agentAppendBubble('assistant', `New session ready: ${newSessionId}`);
        agentSetStatus(`Ready · session ${newSessionId}`);
        await refreshAgentInspector();
    } catch (e) {
        agentSetStatus(`New session failed: ${e.message}`);
    } finally {
        _agentBusy = false;
    }
}

function initAgentTab() {
    if (_agentInitDone) return;
    _agentInitDone = true;

    const sessionId = agentSessionId();
    const sessionEl = document.getElementById('agent-session-id');
    updateAgentSessionBadge(sessionId);

    const input = document.getElementById('agent-input');
    const sendBtn = document.getElementById('agent-send-btn');
    const freshBtn = document.getElementById('agent-fresh-btn');
    const resetBtn = document.getElementById('agent-reset-btn');
    const refreshBtn = document.getElementById('agent-refresh-btn');
    const providerApplyBtn = document.getElementById('agent-provider-apply');
    const providerRefreshBtn = document.getElementById('agent-provider-refresh');
    const callTypeSelect = document.getElementById('agent-provider-calltype');
    const select = document.getElementById('agent-prompt-log-select');
    const commandMenuToggleBtn = document.getElementById('agent-command-menu-toggle');
    const commandMenuCloseBtn = document.getElementById('agent-command-sheet-close');
    const commandMenuBackdrop = document.getElementById('agent-command-sheet-backdrop');
    const providerMenuToggleBtn = document.getElementById('agent-provider-menu-toggle');
    const providerSheetCloseBtn = document.getElementById('agent-provider-sheet-close');
    const providerSheetBackdrop = document.getElementById('agent-provider-sheet-backdrop');
    const inspectorToggleBtn = document.getElementById('agent-inspector-toggle');
    const inspectorCloseBtn = document.getElementById('agent-inspector-close');
    const inspectorBackdrop = document.getElementById('agent-inspector-backdrop');

    document.getElementById('agent-command-grid')?.addEventListener('click', (ev) => {
        const target = ev.target.closest('[data-command]');
        if (!target) return;
        const command = target.getAttribute('data-command') || '';
        if (input) {
            input.value = command;
            input.focus();
        }
        setAgentCommandMenuOpen(false);
        agentSend(command);
    });

    commandMenuToggleBtn?.addEventListener('click', () => {
        const sheet = document.getElementById('agent-command-sheet');
        const isOpen = sheet?.classList.contains('open');
        setAgentCommandMenuOpen(!isOpen);
    });
    commandMenuCloseBtn?.addEventListener('click', () => setAgentCommandMenuOpen(false));
    commandMenuBackdrop?.addEventListener('click', () => setAgentCommandMenuOpen(false));
    providerMenuToggleBtn?.addEventListener('click', () => {
        const sheet = document.getElementById('agent-provider-sheet');
        const isOpen = sheet?.classList.contains('open');
        setAgentProviderSheetOpen(!isOpen);
    });
    providerSheetCloseBtn?.addEventListener('click', () => setAgentProviderSheetOpen(false));
    providerSheetBackdrop?.addEventListener('click', () => setAgentProviderSheetOpen(false));
    sendBtn?.addEventListener('click', () => agentSend());
    freshBtn?.addEventListener('click', () => agentFreshSession());
    resetBtn?.addEventListener('click', () => agentNewSession());
    refreshBtn?.addEventListener('click', () => refreshAgentInspector());
    providerApplyBtn?.addEventListener('click', () => applyAgentProviderControls());
    providerRefreshBtn?.addEventListener('click', () => loadAgentProviderControls());
    callTypeSelect?.addEventListener('change', (ev) => applyAgentCallTypeSelection(ev.target.value));
    inspectorToggleBtn?.addEventListener('click', () => {
        const inspector = document.getElementById('agent-inspector');
        const isOpen = inspector?.classList.contains('open');
        setAgentInspectorOpen(!isOpen);
    });
    inspectorCloseBtn?.addEventListener('click', () => setAgentInspectorOpen(false));
    inspectorBackdrop?.addEventListener('click', () => setAgentInspectorOpen(false));
    input?.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter' && !ev.shiftKey) {
            ev.preventDefault();
            agentSend();
        }
    });
    input?.addEventListener('input', () => autosizeAgentInput());
    autosizeAgentInput();
    window.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') setAgentCommandMenuOpen(false);
        if (ev.key === 'Escape') setAgentProviderSheetOpen(false);
        if (ev.key === 'Escape') setAgentInspectorOpen(false);
    });
    select?.addEventListener('change', (ev) => {
        const value = ev.target.value;
        if (value) loadAgentPromptLog(value);
    });

    setAgentInspectorOpen(false);
    refreshAgentInspector();
    loadAgentProviderControls();
    startAgentLiveThread();
    agentSetStatus(`Ready · session ${sessionId}`);
}

// ── Stat cards
function updateStats(stats, gapsArr) {
    if (!stats) return;
    const setText = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    setText('s-total', stats.total_events ?? stats.total ?? 0);
    setText('s-resolved', stats.resolved ?? 0);
    setText('s-synth', stats.synthesised ?? 0);
    const gaps = gapsArr ?? [];
    setText('s-gaps', stats.unresolved_gaps ?? gaps.length);
    const prov = (stats.providers_used || stats.providers || []).filter(Boolean);
    setText('s-provider', prov.length ? prov.join(', ') : '—');
}
if (document.getElementById('s-total')) updateStats(STATS, GAPS);

function fmtTs(ts) {
    if (!ts) return { date: '—', time: '—' };
    const d = new Date(ts);
    if (Number.isNaN(d.getTime())) return { date: String(ts).slice(0, 10), time: String(ts).slice(11, 19) };
    return {
        date: d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }),
        time: d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
    };
}

function eventTsMs(e) {
    const raw = e?.timestamp || e?.ts;
    const ms = raw ? new Date(raw).getTime() : 0;
    return Number.isFinite(ms) ? ms : 0;
}

function sortEventsNewestFirst(events) {
    return [...(events || [])].sort((a, b) => {
        const dt = eventTsMs(b) - eventTsMs(a);
        if (dt !== 0) return dt;
        return (Number(b?.id) || 0) - (Number(a?.id) || 0);
    });
}

function classifyOutcome(e) {
    if (e.event_type && e.event_type !== 'evolution') return 'runtime';
    if (e.escalated && e.found) return 'escalated_done';
    if (e.escalated && !e.found) return 'escalated_open';
    if (!e.escalated && e.found) return 'direct_done';
    return 'open_gap';
}

function renderTaskOutcomes(events) {
    const evoEvents = sortEventsNewestFirst(events).filter(e => !e.event_type || e.event_type === 'evolution');
    const direct = evoEvents.filter(e => classifyOutcome(e) === 'direct_done').length;
    const escOk = evoEvents.filter(e => classifyOutcome(e) === 'escalated_done').length;
    const escOpen = evoEvents.filter(e => classifyOutcome(e) === 'escalated_open').length;

    document.getElementById('to-direct').textContent = direct;
    document.getElementById('to-esc-ok').textContent = escOk;
    document.getElementById('to-esc-open').textContent = escOpen;

    const list = document.getElementById('task-outcome-list');
    if (!list) return;

    const recent = evoEvents.slice(0, 18);
    if (!recent.length) {
        list.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No evolution outcomes yet.</div>';
        return;
    }

    list.innerHTML = recent.map(e => {
        const cls = classifyOutcome(e);
        const st = cls === 'direct_done'
            ? ['Direct done', '#3fb950']
            : cls === 'escalated_done'
                ? ['Escalated done', '#bc8cff']
                : cls === 'escalated_open'
                    ? ['Escalated open', '#f85149']
                    : ['Open gap', '#f85149'];
        const ts = fmtTs(e.timestamp || e.ts);
        const task = (e.task || '').substring(0, 120) || '(no task text)';
        return `<div class="task-outcome-item">
      <div class="task-outcome-ts">${ts.date}<br>${ts.time}</div>
      <div class="task-outcome-task">${task}</div>
      <div class="task-outcome-status" style="color:${st[1]};border-color:${st[1]}66;">${st[0]}</div>
    </div>`;
    }).join('');
}
if (document.getElementById('task-outcome-list')) renderTaskOutcomes(EVENTS);

// ── Event log
function renderLog(events) {
    const el = document.getElementById('log-entries');
    if (!el) return;
    el.innerHTML = '';
    sortEventsNewestFirst(events).slice(0, 100).forEach(e => {
        const isRoutineExec = e.event_type === 'routine_execution';
        const icon = isRoutineExec ? '🔁' : (e.found ? '✅' : (e.escalated ? '⬆️' : '❌'));
        const badge = isRoutineExec
            ? '<span class="log-badge badge-orange">routine run</span>'
            : (e.found
                ? (e.escalated
                    ? '<span class="log-badge badge-purple">T2 synth</span>'
                    : '<span class="log-badge badge-green">T1 acquire</span>')
                : (e.escalated
                    ? '<span class="log-badge badge-orange">escalated</span>'
                    : '<span class="log-badge badge-red">gap</span>'));
        const prov = e.provider_used ? `<span class="log-badge badge-blue">${e.provider_used}</span>` : '';
        const installed = (e.installed || []).join(', ');
        const text = isRoutineExec
            ? (e.entity_name ? `Routine: ${e.entity_name}` : (e.task || ''))
            : (e.task || '');
        const ts = fmtTs(e.timestamp || e.ts);
        const row = document.createElement('div');
        row.className = 'log-entry';
        row.innerHTML = `
      <span class="log-icon">${icon}</span>
      <span class="log-time"><span class="d">${ts.date}</span><span class="t">${ts.time}</span></span>
      <span class="log-text">${text.substring(0, 70)}${badge}${prov}${installed ? `<br><span style="color:#58a6ff">+${installed}</span>` : ''}</span>`;
        el.appendChild(row);
    });
}
if (document.getElementById('log-entries')) renderLog(EVENTS);

// ── Gap list
function renderGaps(gaps) {
    const el = document.getElementById('gap-entries');
    if (!el) return;
    if (!gaps.length) {
        el.innerHTML = '<div class="gap-item" style="color:#3fb950">No open gaps ✔</div>';
        return;
    }
    el.innerHTML = '';
    gaps.forEach(g => {
        const d = document.createElement('div');
        d.className = 'gap-item';
        d.innerHTML = `<span>⚠️</span>${(g.task || '').substring(0, 70)}`;
        el.appendChild(d);
    });
}
if (document.getElementById('gap-entries')) renderGaps(GAPS);

// ── D3 Force graph
let _graphBusy = false;
let _graphFilter = { skills: true, routines: true, replicas: true };
let _graphCtx = { skillNames: new Set(), routineNames: new Set(), activeReplicas: [] };
let _graphRebuildTimer = null;

function toggleGraphLayer(layer, btn) {
    _graphFilter[layer] = !_graphFilter[layer];
    btn.classList.toggle('active', _graphFilter[layer]);
    scheduleGraphRebuild();
}

function scheduleGraphRebuild() {
    clearTimeout(_graphRebuildTimer);
    if (!document.getElementById('graph-panel')) return;
    _graphRebuildTimer = setTimeout(() => { buildGraph(); }, 120);
}

async function refreshGraphContext() {
    if (window.__kernelAgentOnline === false) return;
    const [skillsRes, routinesRes, replicasRes] = await Promise.allSettled([
        fetch(kapi('/skills')).then(r => r.json()),
        fetch(kapi('/routines')).then(r => r.json()),
        fetch(kapi('/replica/active')).then(r => r.json()),
    ]);

    if (skillsRes.status === 'fulfilled') {
        const skills = skillsRes.value.skills || skillsRes.value || [];
        _graphCtx.skillNames = new Set(skills.map(s => s.name).filter(Boolean));
    }

    if (routinesRes.status === 'fulfilled') {
        const routines = routinesRes.value.routines || routinesRes.value || [];
        _graphCtx.routineNames = new Set(routines.map(r => r.name).filter(Boolean));
    }

    if (replicasRes.status === 'fulfilled') {
        _graphCtx.activeReplicas = replicasRes.value.replicas || replicasRes.value || [];
    }
}

async function buildGraph() {
    if (_graphBusy) return;
    const panel = document.getElementById('graph-panel');
    if (!panel) return;
    _graphBusy = true;
    try {
        await refreshGraphContext();

        const W = panel.clientWidth || window.innerWidth, H = 430;
        const svg = d3.select('#graph').attr('viewBox', `0 0 ${W} ${H}`);
        svg.selectAll('*').remove();
        const tip = document.getElementById('tooltip');

        const nodeMap = {};
        const links = [];
        nodeMap['kernel'] = { id: 'kernel', label: 'Kernel', type: 'core', r: 18 };

        // Seed the graph with the currently installed ecosystem so a fresh restart
        // still shows known skills/routines even before new evolution events arrive.
        if (_graphFilter.skills) {
            Array.from(_graphCtx.skillNames || []).forEach((skillName) => {
                if (!skillName) return;
                if (_graphCtx.routineNames.has(skillName)) return;
                if (!nodeMap[skillName]) {
                    nodeMap[skillName] = {
                        id: skillName,
                        label: skillName,
                        type: 'acquired',
                        provider: 'ecosystem',
                        task: 'Loaded from skill registry',
                        r: 11,
                    };
                }
                links.push({ source: 'kernel', target: skillName, value: 0.62, link_type: 'catalog_skill' });
            });
        }

        if (_graphFilter.routines) {
            Array.from(_graphCtx.routineNames || []).forEach((routineName) => {
                if (!routineName) return;
                const rid = `routine:${routineName}`;
                if (!nodeMap[rid]) {
                    nodeMap[rid] = {
                        id: rid,
                        label: routineName,
                        type: 'routine',
                        provider: 'ecosystem',
                        task: 'Loaded from routine registry',
                        executions: 0,
                        r: 11,
                    };
                }
                links.push({ source: 'kernel', target: rid, value: 0.62, link_type: 'catalog_routine' });
            });
        }

        EVENTS.forEach(e => {
            if (e.event_type === 'routine_execution') {
                if (!_graphFilter.routines) return;
                const routineName = e.entity_name || (e.task || '').replace(/^Routine (executed|failed):\s*/i, '').trim();
                if (!routineName) return;
                const rid = `routine:${routineName}`;
                if (!nodeMap[rid]) {
                    nodeMap[rid] = {
                        id: rid,
                        label: routineName,
                        type: 'routine',
                        confidence: e.confidence,
                        provider: e.provider_used,
                        task: e.task,
                        timestamp: e.timestamp,
                        executions: 0,
                        r: 11,
                    };
                }
                nodeMap[rid].executions = (nodeMap[rid].executions || 0) + 1;
                nodeMap[rid].r = Math.min(18, 11 + Math.floor((nodeMap[rid].executions || 1) / 2));
                links.push({ source: 'kernel', target: rid, value: 0.75, link_type: 'routine_exec' });
                return;
            }

            (e.installed || []).forEach(skill => {
                const isRoutine = _graphCtx.routineNames.has(skill);
                const targetId = isRoutine ? `routine:${skill}` : skill;
                const nodeType = isRoutine
                    ? 'routine'
                    : ((e.escalated && e.found) ? 'synthesised' : 'acquired');

                if ((nodeType === 'routine' && !_graphFilter.routines) ||
                    (nodeType !== 'routine' && !_graphFilter.skills)) {
                    return;
                }

                if (!nodeMap[targetId]) {
                    nodeMap[targetId] = {
                        id: targetId, label: skill,
                        type: nodeType,
                        confidence: e.confidence, provider: e.provider_used,
                        task: e.task, timestamp: e.timestamp, r: 12
                    };
                }
                links.push({ source: 'kernel', target: targetId, value: 1, link_type: (nodeType === 'routine' ? 'routine_acquire' : 'acquire') });
            });
            if (!e.found) {
                const gid = 'gap:' + (e.task || '').substring(0, 20);
                if (!nodeMap[gid]) {
                    nodeMap[gid] = { id: gid, label: (e.task || '').substring(0, 20) + '…', type: 'gap', r: 8 };
                    links.push({ source: 'kernel', target: gid, value: 0.4, link_type: 'gap' });
                }
            }
        });

        if (_graphFilter.replicas) {
            (_graphCtx.activeReplicas || []).forEach(r => {
                const rid = `replica:${r.name}`;
                if (!nodeMap[rid]) {
                    nodeMap[rid] = {
                        id: rid,
                        label: `@${r.name}`,
                        type: 'replica',
                        task: r.task || '',
                        r: 10,
                    };
                    links.push({ source: 'kernel', target: rid, value: 0.8, link_type: 'replica_live' });
                }
            });
        }

        const nodes = Object.values(nodeMap);
        const color = {
            core: '#3fb950',
            acquired: '#58a6ff',
            synthesised: '#bc8cff',
            routine: '#d29922',
            replica: '#8b949e',
            gap: '#f85149'
        };

        if (nodes.length <= 1) {
            svg.append('text').attr('x', W / 2).attr('y', H / 2)
                .attr('text-anchor', 'middle').attr('fill', '#30363d').attr('font-size', 13)
                .text('No evolution events yet — waiting for Kernel to encounter unknown tasks');
            return;
        }

        const sim = d3.forceSimulation(nodes)
            .force('link', d3.forceLink(links).id(d => d.id).distance(100).strength(0.6))
            .force('charge', d3.forceManyBody().strength(-220))
            .force('center', d3.forceCenter(W / 2, H / 2))
            .force('collide', d3.forceCollide(d => d.r + 12));

        const g = svg.append('g');
        svg.call(d3.zoom().scaleExtent([0.3, 4]).on('zoom', e => g.attr('transform', e.transform)));

        svg.append('defs').append('marker')
            .attr('id', 'arr').attr('viewBox', '0 -4 8 8').attr('refX', 22)
            .attr('markerWidth', 6).attr('markerHeight', 6).attr('orient', 'auto')
            .append('path').attr('d', 'M0,-4L8,0L0,4').attr('fill', '#30363d');

        const link = g.append('g').selectAll('line').data(links).join('line')
            .attr('stroke', d => {
                if (d.link_type === 'acquire') return '#58a6ff66';
                if (d.link_type === 'catalog_skill') return '#58a6ff44';
                if (d.link_type === 'catalog_routine') return '#d2992244';
                if (d.link_type === 'routine_acquire') return '#d2992266';
                if (d.link_type === 'routine_exec') return '#d29922';
                if (d.link_type === 'replica_live') return '#8b949e';
                if (d.link_type === 'gap') return '#f85149aa';
                return '#30363d';
            })
            .attr('stroke-width', d => {
                if (d.link_type === 'catalog_skill' || d.link_type === 'catalog_routine') return 0.9;
                if (d.link_type === 'routine_exec') return 2.1;
                if (d.link_type === 'replica_live') return 1.2;
                return d.value > 0.7 ? 1.5 : 0.7;
            })
            .attr('stroke-dasharray', d => {
                if (d.link_type === 'catalog_skill' || d.link_type === 'catalog_routine') return '1,4';
                if (d.link_type === 'routine_exec') return '2,2';
                if (d.link_type === 'replica_live') return '5,3';
                return d.value < 0.7 ? '4,3' : null;
            })
            .attr('marker-end', 'url(#arr)');

        const counts = {
            skills: nodes.filter(n => n.type === 'acquired' || n.type === 'synthesised').length,
            routines: nodes.filter(n => n.type === 'routine').length,
            replicas: nodes.filter(n => n.type === 'replica').length,
        };
        const setText = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = String(val);
        };
        setText('gc-skills', counts.skills);
        setText('gc-routines', counts.routines);
        setText('gc-replicas', counts.replicas);

        const node = g.append('g').selectAll('g').data(nodes).join('g')
            .attr('cursor', 'pointer')
            .call(d3.drag()
                .on('start', (e, d) => { if (!e.active) sim.alphaTarget(0.3).restart(); d.fx = d.x; d.fy = d.y; })
                .on('drag', (e, d) => { d.fx = e.x; d.fy = e.y; })
                .on('end', (e, d) => { if (!e.active) sim.alphaTarget(0); d.fx = null; d.fy = null; }))
            .on('mouseover', (event, d) => {
                let h = `<strong>${d.label}</strong><br>`;
                if (d.type !== 'core') h += `Type: ${d.type}<br>`;
                if (d.confidence) h += `Confidence: ${d.confidence}<br>`;
                if (d.provider) h += `Provider: ${d.provider}<br>`;
                if (d.task) h += `Task: ${d.task.substring(0, 60)}<br>`;
                if (d.timestamp) h += `<span style="color:#8b949e">${d.timestamp.substring(0, 19)}</span>`;
                tip.innerHTML = h; tip.style.opacity = 1;
            })
            .on('mousemove', event => {
                const r = panel.getBoundingClientRect();
                tip.style.left = (event.clientX - r.left + 14) + 'px';
                tip.style.top = (event.clientY - r.top + 14) + 'px';
            })
            .on('mouseleave', () => tip.style.opacity = 0);

        const defs = svg.select('defs');
        const filt = defs.append('filter').attr('id', 'glow');
        filt.append('feGaussianBlur').attr('stdDeviation', 3).attr('result', 'blur');
        const merge = filt.append('feMerge');
        merge.append('feMergeNode').attr('in', 'blur');
        merge.append('feMergeNode').attr('in', 'SourceGraphic');

        node.append('circle')
            .attr('r', d => d.r)
            .attr('fill', d => color[d.type] || '#58a6ff')
            .attr('fill-opacity', 0.85)
            .attr('stroke', d => color[d.type] || '#58a6ff')
            .attr('stroke-width', 1.5).attr('stroke-opacity', 0.4)
            .filter(d => d.type === 'core')
            .attr('filter', 'url(#glow)');

        node.filter(d => d.type === 'core').append('circle')
            .attr('r', 26).attr('fill', 'none')
            .attr('stroke', '#3fb950').attr('stroke-width', 1).attr('stroke-opacity', 0.25);

        node.append('text')
            .attr('dy', d => d.r + 12).attr('text-anchor', 'middle')
            .attr('font-size', 9).attr('fill', '#8b949e')
            .text(d => d.label.substring(0, 18));

        sim.on('tick', () => {
            link.attr('x1', d => d.source.x).attr('y1', d => d.source.y)
                .attr('x2', d => d.target.x).attr('y2', d => d.target.y);
            node.attr('transform', d => `translate(${d.x},${d.y})`);
        });
    } finally {
        _graphBusy = false;
    }
}

scheduleGraphRebuild();

// ── Live SSE updates — only connect when the Evolution tab is mounted
const es = document.getElementById('graph-panel') ? new EventSource(kapi('/evolution/stream')) : null;
let totalEvents = EVENTS.length;

function updateGaps(gaps) {
    if (!Array.isArray(gaps)) return;
    const sGaps = document.getElementById('s-gaps');
    if (sGaps) sGaps.textContent = gaps.length;
    const el = document.getElementById('gap-entries');
    if (!el) return;
    if (!gaps.length) { el.innerHTML = '<div class="gap-item" style="color:#3fb950">No open gaps ✓</div>'; return; }
    el.innerHTML = gaps.map(g =>
        `<div class="gap-item"><span>⚠</span>${String(g.gap || g.task || g).substring(0, 120)}</div>`
    ).join('');
}

function rebuildTimeline(events) {
    const ordered = sortEventsNewestFirst(events);
    const svgEl = document.getElementById('timeline-svg');
    if (!svgEl) return;
    while (svgEl.firstChild) svgEl.removeChild(svgEl.firstChild);
    const panel = document.getElementById('timeline-panel');
    const H = 90;
    const visW = (panel.clientWidth || window.innerWidth) - 48;
    const W = Math.max(visW, ordered.length * 12, 800);
    svgEl.setAttribute('width', W); svgEl.setAttribute('height', H);
    const svg = d3.select('#timeline-svg');
    const times = ordered.map(e => new Date(e.ts || e.timestamp || 0));
    if (!times.length) return;
    const extent = d3.extent(times);
    const xScale = d3.scaleTime().domain([extent[1], extent[0]]).range([40, W - 20]);
    const col = { acquired: '#58a6ff', synthesised: '#bc8cff', gap: '#f85149', routine: '#d29922' };
    const yLane = { acquired: 10, synthesised: 27, gap: 44, routine: 61 };

    [['T1', 10, '#58a6ff'], ['T2', 27, '#bc8cff'], ['gap', 44, '#f85149'], ['routine', 61, '#d29922']].forEach(([l, y, c]) => {
        svg.append('text').attr('x', 2).attr('y', y + 4).attr('font-size', 8).attr('fill', c).attr('font-family', 'monospace').text(l);
    });

    ordered.forEach((e) => {
        const type = e.event_type === 'routine_execution'
            ? 'routine'
            : (!e.found ? 'gap' : (e.escalated ? 'synthesised' : 'acquired'));
        const t = new Date(e.ts || e.timestamp || 0);
        svg.append('circle')
            .attr('cx', xScale(t)).attr('cy', yLane[type] || 27)
            .attr('r', 4).attr('fill', col[type] || '#58a6ff').attr('opacity', 0.85);
    });

    const tickCount = Math.max(3, Math.min(10, Math.floor(W / 120)));
    svg.append('g')
        .attr('transform', `translate(0,${H - 22})`)
        .call(
            d3.axisBottom(xScale)
                .ticks(tickCount)
                .tickSize(3)
                .tickFormat(d => {
                    const dt = new Date(d);
                    const span = d3.extent(times)[1] - d3.extent(times)[0];
                    return span > 86400000
                        ? dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) + ' ' + dt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
                        : dt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                })
        )
        .call(g => {
            g.select('.domain').attr('stroke', '#30363d');
            g.selectAll('text').attr('fill', '#8b949e').attr('font-size', 8);
            g.selectAll('.tick line').attr('stroke', '#30363d');
        });
}

function updateEvolutionStatsFromEvents() {
    const evoOnly = EVENTS.filter(e => !e.event_type || e.event_type === 'evolution');
    document.getElementById('s-total').textContent = evoOnly.length;
    document.getElementById('s-resolved').textContent = evoOnly.filter(e => e.found).length;
    document.getElementById('s-synth').textContent = evoOnly.filter(e => e.escalated && e.found).length;
}

function renderActivityViews() {
    renderLog(EVENTS);
    renderTaskOutcomes(EVENTS);
    rebuildTimeline(EVENTS);
    updateEvolutionStatsFromEvents();
}

let _uiFrameScheduled = false;
function scheduleActivityRender() {
    if (_uiFrameScheduled) return;
    _uiFrameScheduled = true;
    requestAnimationFrame(() => {
        _uiFrameScheduled = false;
        renderActivityViews();
    });
}

async function fetchJsonWithTimeout(url, timeoutMs = 6000) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
        const res = await fetch(url, { signal: ctrl.signal, cache: 'no-store' });
        if (!res.ok) throw new Error(`${url} ${res.status}`);
        return await res.json();
    } finally {
        clearTimeout(timer);
    }
}

// Full refresh
let _fullRefreshInFlight = false;
async function fullRefresh() {
    if (_fullRefreshInFlight || window.__kernelAgentOnline === false) return;
    _fullRefreshInFlight = true;
    try {
        const [evo, st] = await Promise.all([
            fetchJsonWithTimeout(kapi('/evolution'), 7000),
            fetchJsonWithTimeout(kapi('/evolution/state'), 7000),
        ]);
        const knownIds = new Set(EVENTS.map(e => e.id));
        let added = false;
        (evo.history || []).forEach(e => { if (!knownIds.has(e.id)) { EVENTS.unshift(e); added = true; } });
        if (added) scheduleActivityRender();
        else renderTaskOutcomes(EVENTS);
        totalEvents = evo.stats?.total_events ?? EVENTS.length;
        updateStats(evo.stats);
        updateGaps(evo.gaps);
        updateStateUI(st);
        scheduleGraphRebuild();
    } catch (_) {
        // Transient backend/network blips can happen during restarts; keep UI stable.
    } finally {
        _fullRefreshInFlight = false;
    }
}

if (es) {
    es.onmessage = ev => {
        try {
            const event = JSON.parse(ev.data);
            if (!EVENTS.find(e => e.id === event.id)) { EVENTS.unshift(event); totalEvents++; }
            scheduleActivityRender();
            scheduleGraphRebuild();
        } catch (_) { }
    };
    es.onerror = () => { /* SSE auto-reconnects */ };
}

// Guard: JS loads on every page in the desktop app; only poll when Evolution tab is mounted
if (document.getElementById('graph-panel')) {
    setInterval(fullRefresh, 10000);
    fullRefresh();
}

let _evoRequestInFlight = false;

function setEvolutionControlsBusy(busy) {
    _evoRequestInFlight = busy;
    const btns = ['btn-start', 'btn-pause', 'btn-resume', 'btn-stop'];
    btns.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (busy) el.classList.add('busy'); else el.classList.remove('busy');
        el.disabled = busy ? true : el.disabled;
    });
}

document.getElementById('task-input')?.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') {
        ev.preventDefault();
        triggerEvolution();
    }
});

// ── Evolution state controls
function updateStateUI(s) {
    const badge = document.getElementById('state-badge');
    if (!badge) return;
    badge.textContent = s.state;
    badge.className = 'state-badge state-' + s.state;
    const setText = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    const setVal = (id, v) => { const e = document.getElementById(id); if (e) e.value = v; };
    const setDis = (id, v) => { const e = document.getElementById(id); if (e) e.disabled = v; };
    setText('iter-count', s.iterations);
    setText('iter-cap', s.cap);
    setVal('cap-input', s.cap);
    const running = s.state === 'running';
    const paused = s.state === 'paused';
    const stopped = s.state === 'stopped';
    setDis('btn-start', _evoRequestInFlight || running);
    setDis('btn-pause', _evoRequestInFlight || !running);
    setDis('btn-resume', _evoRequestInFlight || !paused);
    setDis('btn-stop', _evoRequestInFlight || stopped);
    const dot = document.querySelector('.live-dot');
    if (dot) dot.style.background = running ? '#3fb950' : (paused ? '#d29922' : '#f85149');
}

async function ctrlAction(action) {
    if (_evoRequestInFlight) return;
    const cap = parseInt(document.getElementById('cap-input').value) || 10;
    const msg = document.getElementById('ctrl-msg');
    msg.textContent = '⏳ ' + action + '...';
    setEvolutionControlsBusy(true);
    try {
        const r = await fetch(kapi('/evolution/control'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, cap: (action === 'start' || action === 'reset') ? cap : null })
        });
        if (!r.ok) {
            const txt = await r.text();
            throw new Error(`HTTP ${r.status}: ${txt.slice(0, 160)}`);
        }
        const s = await r.json();
        updateStateUI(s);
        msg.textContent = '✅ ' + action + ' — ' + s.iterations + '/' + s.cap + ' iterations';
        setTimeout(() => msg.textContent = '', 4000);
    } catch (e) {
        msg.textContent = '❌ ' + e.message;
    } finally {
        setEvolutionControlsBusy(false);
        try {
            const st = await fetch(kapi('/evolution/state')).then(r => r.json());
            updateStateUI(st);
        } catch (_) { }
    }
}

async function triggerEvolution() {
    if (_evoRequestInFlight) return;
    const task = document.getElementById('task-input').value.trim();
    if (!task) { alert('Enter a task first'); return; }
    const confirmEnabled = document.getElementById('confirm-evolve')?.checked;
    if (confirmEnabled && !window.confirm(`Trigger evolution for task:\n\n${task}`)) return;

    const cap = parseInt(document.getElementById('cap-input').value) || 10;
    const msg = document.getElementById('ctrl-msg');
    msg.textContent = '🧬 Evolving…';
    setEvolutionControlsBusy(true);
    try {
        const r = await fetch(kapi('/evolution/trigger'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task, cap })
        });
        if (!r.ok) {
            const txt = await r.text();
            throw new Error(`HTTP ${r.status}: ${txt.slice(0, 160)}`);
        }
        const d = await r.json();
        if (d.error) { msg.textContent = '❌ ' + d.error; return; }
        const res = d.result || {};
        if (d.state) updateStateUI(d.state);
        if (res.found)
            msg.textContent = '✅ Acquired: ' + (res.installed || []).join(', ') + ' [' + res.confidence + ']';
        else if (d.triggered === false)
            msg.textContent = '⏸ ' + (d.reason || 'Paused or cap reached');
        else
            msg.textContent = '⚠️ Gap: ' + (res.gap || 'not resolved').substring(0, 60);
        setTimeout(() => msg.textContent = '', 6000);
        document.getElementById('task-input').value = '';
    } catch (e) {
        msg.textContent = '❌ ' + e.message;
    } finally {
        setEvolutionControlsBusy(false);
        fullRefresh();
    }
}

// ── Tab switching
const DASH_TABS = ['evolution', 'activity', 'insights', 'skills', 'replicas', 'trajectories', 'agent', 'memory', 'system', 'voice'];
const ACTIVE_TAB_STORAGE_KEY = 'kernel.evolutionDashboard.activeTab';

function switchTab(name, event) {
    if (!document.getElementById('ctrl-bar')) return; // desktop: sidebar navigation handles tabs, not this legacy switcher
    if (!DASH_TABS.includes(name)) name = 'evolution';
    if (name !== 'agent') stopAgentLiveThread();
    if (name !== 'agent') setAgentInspectorOpen(false);
    if (name !== 'agent') setAgentCommandMenuOpen(false);
    if (name !== 'agent') setAgentProviderSheetOpen(false);
    DASH_TABS.forEach(p => {
        const el = document.getElementById(p + '-panel');
        if (el) el.style.display = 'none';
    });
    document.getElementById('ctrl-bar').style.display = name === 'evolution' ? 'flex' : 'none';
    const panel = document.getElementById(name + '-panel');
    if (panel) panel.style.display = '';
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const activeBtn = (event && event.target)
        ? event.target
        : document.querySelector(`.tab-btn[data-tab="${name}"]`);
    if (activeBtn) activeBtn.classList.add('active');
    try { localStorage.setItem(ACTIVE_TAB_STORAGE_KEY, name); } catch (_) { }
    if (name === 'insights') loadInsights();
    if (name === 'skills') { loadSkills(); loadRoutines(); }
    if (name === 'replicas') loadReplicas();
    if (name === 'trajectories') loadTrajectories();
    if (name === 'agent') setTimeout(() => { setAgentInspectorOpen(false); initAgentTab(); startAgentLiveThread(); }, 0);
    if (name === 'system') loadSystemData();
    if (name === 'memory') { loadMemoryStats(); loadWorkspaceTree('/'); }
    if (name === 'voice') setTimeout(() => initVoiceTab(), 0);
    document.body.classList.toggle('agent-mobile-focus', name === 'agent' && window.innerWidth <= 860);
    if (window.innerWidth <= 860) setTabBarOpen(false);
}

(function restoreLastActiveTab() {
    let saved = 'evolution';
    try {
        const v = localStorage.getItem(ACTIVE_TAB_STORAGE_KEY);
        if (v && DASH_TABS.includes(v)) saved = v;
    } catch (_) { }
    switchTab(saved);
})();

(function initTabBarState() {
    if (!document.getElementById('tab-bar')) return;
    setTabBarOpen(window.innerWidth > 860);
    window.addEventListener('resize', () => {
        if (window.innerWidth > 860) setTabBarOpen(true);
        else setTabBarOpen(false);
        const activeTab = document.querySelector('.tab-btn.active')?.getAttribute('data-tab') || 'evolution';
        document.body.classList.toggle('agent-mobile-focus', activeTab === 'agent' && window.innerWidth <= 860);
    });
})();

// ── Insights tab
let _insChartRes = null, _insChartEco = null, _insChartVerify = null;

function categoryColor(cat) {
    const m = { retrospective: '#58a6ff', gap_reflection: '#f85149', self_improvement: '#3fb950', curiosity: '#bc8cff' };
    return m[cat] || '#8b949e';
}

function escHtml(s) {
    return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function loadInsights() {
    if (!document.getElementById('ins-stats')) return;
    try {
        const [evo, todayResp, healthResp] = await Promise.all([
            fetch(kapi('/evolution')).then(r => r.json()),
            fetch(kapi('/thoughts/today')).then(r => r.json()),
            fetch(kapi('/health')).then(r => r.json()),
        ]);

        // Stats cards
        const stats = evo.stats || {};
        const cards = [
            { label: 'Total Events', value: stats.total_events ?? 0, color: '#58a6ff' },
            { label: 'Resolved', value: stats.resolved ?? 0, color: '#3fb950' },
            { label: 'Synthesised', value: stats.synthesised ?? 0, color: '#bc8cff' },
            { label: 'Open Gaps', value: stats.unresolved_gaps ?? (evo.gaps || []).length, color: '#f85149' },
            { label: 'Skills', value: healthResp.skills ?? 0, color: '#d29922' },
            { label: 'Routines', value: healthResp.routines ?? 0, color: '#8b949e' },
        ];
        document.getElementById('ins-stats').innerHTML = cards.map(c =>
            `<div style="background:#161b22;border:1px solid #30363d;border-radius:8px;padding:10px 16px;min-width:100px;text-align:center;">
        <div style="font-size:1.4em;font-weight:700;color:${c.color}">${c.value}</div>
        <div style="font-size:0.65em;color:#8b949e;margin-top:2px;text-transform:uppercase;letter-spacing:.05em">${c.label}</div>
      </div>`).join('');

        // Charts
        const simLabels = (evo.sim_stats || []).map(s => s.label);
        const t1Data = (evo.sim_stats || []).map(s => s.tier1 || 0);
        const ctxRes = document.getElementById('ins-chart-res');
        if (ctxRes) {
            if (_insChartRes) _insChartRes.destroy();
            _insChartRes = new Chart(ctxRes, {
                type: 'bar',
                data: { labels: simLabels, datasets: [{ label: 'Tier 1 resolved', data: t1Data, backgroundColor: '#3fb950' }] },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#c9d1d9', font: { size: 9 } } } },
                    scales: {
                        x: { ticks: { color: '#8b949e', font: { size: 8 } }, grid: { color: '#21262d' } },
                        y: { ticks: { color: '#8b949e', font: { size: 8 } }, grid: { color: '#21262d' } }
                    }
                }
            });
        }
        const skillLabels = (evo.skill_counts || []).map(s => s.label);
        const skillData = (evo.skill_counts || []).map(s => s.count || 0);
        const ctxEco = document.getElementById('ins-chart-eco');
        if (ctxEco) {
            if (_insChartEco) _insChartEco.destroy();
            _insChartEco = new Chart(ctxEco, {
                type: 'line',
                data: {
                    labels: skillLabels, datasets: [{
                        label: 'Skills', data: skillData,
                        borderColor: '#3fb950', backgroundColor: 'rgba(63,185,80,0.1)', fill: true, tension: 0.3,
                        pointBackgroundColor: '#3fb950'
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#c9d1d9', font: { size: 9 } } } },
                    scales: {
                        x: { ticks: { color: '#8b949e', font: { size: 8 } }, grid: { color: '#21262d' } },
                        y: { ticks: { color: '#8b949e', font: { size: 8 } }, grid: { color: '#21262d' } }
                    }
                }
            });
        }
        // ADR-006 verification doughnut from events
        const evts = evo.history || EVENTS;
        const yesCount = evts.filter(e => e.found === 1 && e.verification_result === 'YES').length;
        const noCount = evts.filter(e => e.found === 0).length;
        const ctxV = document.getElementById('ins-chart-verify');
        if (ctxV) {
            if (_insChartVerify) _insChartVerify.destroy();
            _insChartVerify = new Chart(ctxV, {
                type: 'doughnut',
                data: {
                    labels: ['Verified YES', 'Not acquired'],
                    datasets: [{ data: [yesCount || 1, noCount || 1], backgroundColor: ['#3fb950', '#f85149'], borderWidth: 0 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#c9d1d9', font: { size: 9 } }, position: 'bottom' } }
                }
            });
        }

        // Thoughts feed
        const content = todayResp.content || '';
        const entries = todayResp.entries || 0;
        const ideasCount = Number(todayResp.ideas_count || 0);
        const includedDates = Array.isArray(todayResp.included_dates) ? todayResp.included_dates : [];
        const dateWindow = includedDates.length ? includedDates.join(', ') : 'today+yesterday';
        const thoughtCountEl = document.getElementById('ins-thought-count');
        if (thoughtCountEl) thoughtCountEl.textContent = `${entries} entries · ${ideasCount} ideas · ${dateWindow}`;
        // Parse basic thought lines from content
        const thoughtLines = content.split('\n').filter(l => l.trim() && !l.startsWith('#')).slice(0, 30);
        const thoughtsEl = document.getElementById('ins-thoughts');
        if (thoughtsEl) thoughtsEl.innerHTML = thoughtLines.map(line => {
            const cat = line.includes('gap') ? 'gap_reflection' : line.includes('retro') ? 'retrospective' : 'curiosity';
            return `<div style="padding:6px 0;border-bottom:1px solid #21262d;font-size:0.72em;">
        <span style="color:${categoryColor(cat)};">[${cat}]</span>
        <span style="color:#c9d1d9;margin-left:6px;">${escHtml(line.slice(0, 120))}</span>
      </div>`;
        }).join('') || '<div style="color:#8b949e;font-size:0.72em;">No thoughts in the last 2 days.</div>';

    } catch (e) {
        console.error('loadInsights error', e);
    }
}

// ── Skills & Routines tab
let _allSkills = [];
async function loadSkills() {
    if (!document.getElementById('skill-list')) return;
    try {
        const data = await fetch(kapi('/skills')).then(r => r.json());
        _allSkills = data.skills || data || [];
        const cnt = document.getElementById('skill-count');
        if (cnt) cnt.textContent = _allSkills.length + ' skills';
        renderSkillList(_allSkills);
    } catch (e) {
        const el = document.getElementById('skill-list');
        if (el) el.innerHTML = '<div style="color:#f85149;font-size:0.72em;">Failed to load skills</div>';
    }
}
function renderSkillList(skills) {
    const el = document.getElementById('skill-list');
    if (!el) return;
    el.innerHTML = skills.map(s =>
        `<div style="display:flex;align-items:center;gap:8px;padding:5px 8px;background:#161b22;border:1px solid #30363d;border-radius:6px;font-size:0.72em;">
      <span style="color:#58a6ff;font-weight:700;">${escHtml(s.name || '')}</span>
      <span style="color:#8b949e;flex:1;">${escHtml(s.description || '')}</span>
      <span style="color:#30363d;">${(s.commands || []).join(' ')}</span>
    </div>`).join('');
}
function filterSkills() {
    const searchEl = document.getElementById('skill-search');
    if (!searchEl) return;
    const q = searchEl.value.toLowerCase();
    const filtered = _allSkills.filter(s =>
        (s.name || '').toLowerCase().includes(q) || (s.description || '').toLowerCase().includes(q));
    const cnt = document.getElementById('skill-count');
    if (cnt) cnt.textContent = filtered.length + ' / ' + _allSkills.length;
    renderSkillList(filtered);
}
async function loadRoutines() {
    const routineEl = document.getElementById('routine-list');
    if (!routineEl) return;
    try {
        const data = await fetch(kapi('/routines')).then(r => r.json());
        const routines = data.routines || data || [];
        routineEl.innerHTML = routines.map(r =>
            `<div style="display:flex;align-items:center;gap:8px;padding:5px 8px;background:#161b22;border:1px solid #30363d;border-radius:6px;font-size:0.72em;">
        <span style="color:#3fb950;font-weight:700;">${escHtml(r.name || '')}</span>
        <span style="color:#8b949e;flex:1;">${escHtml(r.description || r.trigger || '')}</span>
      </div>`).join('') || '<div style="color:#8b949e;font-size:0.72em;">No routines found.</div>';
    } catch (e) {
        routineEl.innerHTML = '<div style="color:#f85149;font-size:0.72em;">Failed to load routines</div>';
    }
}

// ── Replicas tab
let _repInterval = null;
async function loadReplicas() {
    const repList = document.getElementById('rep-list');
    if (!repList) return;
    try {
        const data = await fetch(kapi('/replica/active')).then(r => r.json());
        const replicas = data.replicas || data || [];
        const repCount = document.getElementById('rep-count');
        if (repCount) repCount.textContent = replicas.length + ' active';
        repList.innerHTML = replicas.map(r =>
            `<div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#161b22;border:1px solid #30363d;border-radius:6px;font-size:0.72em;">
        <span style="color:#bc8cff;font-weight:700;">${escHtml(r.name || '')}</span>
        <span style="color:#8b949e;">${escHtml(r.role || '')}</span>
        <span style="color:${r.done ? '#3fb950' : '#d29922'};">${r.done ? '✓ done' : '⟳ running'}</span>
        <button onclick="stopReplica('${escHtml(r.name || '')}') " style="margin-left:auto;padding:3px 8px;border:1px solid #f85149;border-radius:4px;background:transparent;color:#f85149;cursor:pointer;font-size:0.9em;font-family:monospace;">✕ Stop</button>
      </div>`).join('') || '<div style="color:#8b949e;font-size:0.72em;">No active replicas.</div>';
    } catch (e) {
        repList.innerHTML = '<div style="color:#f85149;font-size:0.72em;">Failed to load replicas</div>';
    }
}
async function spawnReplica() {
    const name = document.getElementById('rep-name').value.trim();
    const role = document.getElementById('rep-role').value;
    const brief = document.getElementById('rep-brief').value.trim();
    const msg = document.getElementById('rep-spawn-msg');
    if (!name) { msg.textContent = '❌ Name required'; return; }
    msg.textContent = '⏳ Spawning…';
    try {
        const r = await fetch(kapi('/replica/named'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, role, custom_prompt: brief })
        });
        const d = await r.json();
        msg.textContent = d.error ? '❌ ' + d.error : '✅ Spawned: ' + name;
        loadReplicas();
    } catch (e) { msg.textContent = '❌ ' + e.message; }
}
async function stopReplica(name) {
    try {
        await fetch(kapi('/replica/') + encodeURIComponent(name), { method: 'DELETE' });
        loadReplicas();
    } catch (e) { }
}
let _pipeJobId = null;
async function launchPipeline() {
    const task = document.getElementById('pipe-task').value.trim();
    const msg = document.getElementById('pipe-status');
    if (!task) { msg.textContent = '❌ Enter a task'; return; }
    msg.textContent = '⏳ Launching pipeline…';
    try {
        const r = await fetch(kapi('/replica/pipeline/async'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ stages: ['writer', 'critic'], task })
        });
        const d = await r.json();
        _pipeJobId = d.job_id || d.id || null;
        msg.textContent = _pipeJobId ? `⟳ Job ${_pipeJobId} running…` : '✅ Launched';
        if (_pipeJobId) {
            const poll = setInterval(async () => {
                try {
                    const sr = await fetch(kapi('/replica/pipeline/') + _pipeJobId).then(x => x.json());
                    if (sr.done || sr.status === 'done') {
                        msg.textContent = '✅ Pipeline done: ' + (sr.summary || JSON.stringify(sr).slice(0, 80));
                        clearInterval(poll);
                    } else {
                        msg.textContent = `⟳ Job ${_pipeJobId}: ${sr.status || 'running'}`;
                    }
                } catch (_) { clearInterval(poll); }
            }, 5000);
        }
    } catch (e) { msg.textContent = '❌ ' + e.message; }
}

// ── Trajectories tab
async function loadTrajectories() {
    const trajList = document.getElementById('traj-list');
    if (!trajList) return;
    try {
        const data = await fetch(kapi('/evolution/trajectories')).then(r => r.json());
        const trajs = data.trajectories || [];
        const trajCount = document.getElementById('traj-count');
        if (trajCount) trajCount.textContent = trajs.length + ' trajectories';
        trajList.innerHTML = trajs.map(t =>
            `<div style="background:#161b22;border:1px solid #30363d;border-radius:8px;padding:10px 14px;cursor:pointer;" onclick="toggleTraj(${t.id})">
        <div style="display:flex;gap:8px;align-items:center;font-size:0.72em;">
          <span style="color:${(t.validation || '').startsWith('PASS') ? '#3fb950' : '#f85149'};">${(t.validation || '').startsWith('PASS') ? '✓ PASS' : '✗ FAIL'}</span>
          <span style="color:#58a6ff;font-weight:700;">${escHtml(t.skill_name || '—')}</span>
          <span style="color:#8b949e;flex:1;">${escHtml((t.task || '').slice(0, 80))}</span>
          <span style="color:#bc8cff;">${escHtml(t.provider || '')}</span>
          <span style="color:#8b949e;">${(t.ts || '').slice(0, 16).replace('T', ' ')}</span>
          ${t.critic_score != null ? `<span style="color:#d29922;">critic: ${Number(t.critic_score).toFixed(2)}</span>` : ''}
        </div>
        <div id="traj-detail-${t.id}" style="display:none;margin-top:8px;font-size:0.7em;">
          <pre style="background:#0d1117;border:1px solid #21262d;border-radius:4px;padding:8px;overflow-x:auto;color:#c9d1d9;max-height:300px;overflow-y:auto;">${escHtml(t.output_skill || '')}</pre>
          ${Array.isArray(t.critic_issues) && t.critic_issues.length ? `<div style="color:#f85149;margin-top:4px;">Issues: ${escHtml(t.critic_issues.join(', '))}</div>` : ''}
        </div>
      </div>`).join('') || '<div style="color:#8b949e;font-size:0.72em;">No trajectories yet.</div>';
    } catch (e) {
        trajList.innerHTML = '<div style="color:#f85149;font-size:0.72em;">Failed to load trajectories</div>';
    }
}
function toggleTraj(id) {
    const el = document.getElementById('traj-detail-' + id);
    if (el) el.style.display = el.style.display === 'none' ? '' : 'none';
}

// ── System tab
async function loadSystemData() {
    if (!document.getElementById('sys-backups')) return;
    try {
        const backupsRes = await fetch(kapi('/evolve/backups'));
        const backups = await backupsRes.json();
        const backupList = document.getElementById('backup-list');
        if (backups.backups && backups.backups.length) {
            backupList.innerHTML = backups.backups.slice(0, 5).map(b =>
                `<div style="margin-bottom:8px;font-size:0.75em;padding:6px;border:1px solid #30363d;border-radius:6px;">
          <strong>${b.timestamp}</strong> · ${b.size_mb} MB · ${b.description || ''}<br>
          <span style="color:#8b949e">${b.archive_path}</span>
        </div>`
            ).join('');
        } else {
            backupList.innerHTML = '<span style="color:#8b949e">No backups yet.</span>';
        }
        document.getElementById('sys-backups').textContent = backups.count || 0;

        const workspaceRes = await fetch(kapi('/evolve/init'));
        const workspace = await workspaceRes.json();
        const workspaceEl = document.getElementById('workspace-status');
        if (workspace.status === 'already_initialized' || workspace.status === 'initialized') {
            const paths = workspace.result?.paths || {};
            workspaceEl.innerHTML =
                `<span style="color:#3fb950">✅ Workspace initialized</span><br>
         Path: ${workspace.result?.workspace || 'unknown'}<br>
         Messages: ${workspace.result?.databases?.chat_history_messages || 0}<br>
         Promoted signals: ${workspace.result?.databases?.promoted_signals || 0}<br>
         Data dir: ${paths.data_dir || 'n/a'}<br>
         Memory dir: ${paths.memory_dir || 'n/a'}`;
            document.getElementById('sys-messages').textContent = workspace.result?.databases?.chat_history_messages || 0;
            document.getElementById('sys-promoted').textContent = workspace.result?.databases?.promoted_signals || 0;
            document.getElementById('sys-workspace').textContent = paths.workspace || workspace.result?.workspace || 'n/a';
        } else {
            const err = workspace.error ? ` (${workspace.error})` : '';
            workspaceEl.innerHTML = `<span style="color:#d29922">⚠️ Workspace not initialized${err}</span>`;
            document.getElementById('sys-workspace').textContent = 'n/a';
        }

        const ecosystemEl = document.getElementById('ecosystem-status');
        const health = await fetch(kapi('/health')).then(r => r.json());
        ecosystemEl.innerHTML =
            `Skills: ${health.skills || 0} · Routines: ${health.routines || 0}<br>
       Active replicas: ${health.active_replicas || 0}<br>
       VRAM free: ${health.vram_free_mb || 0} MB`;

    } catch (e) {
        document.getElementById('backup-list').innerHTML = '<span style="color:#f85149">Failed to load backup list</span>';
    }
}
async function createBackup() {
    const msgEl = document.getElementById('backup-msg');
    msgEl.textContent = '⏳ Creating backup…';
    try {
        const res = await fetch(kapi('/evolve/backup'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ description: "Dashboard backup", full: true })
        });
        const data = await res.json();
        if (data.status === 'created') {
            msgEl.textContent = `✅ Backup created: ${data.backup?.backup_path}`;
            setTimeout(() => msgEl.textContent = '', 4000);
            loadSystemData();
        } else {
            msgEl.textContent = `❌ Backup failed: ${data.error || 'unknown'}`;
        }
    } catch (e) {
        msgEl.textContent = `❌ ${e.message}`;
    }
}
async function runInit() {
    const wsEl = document.getElementById('workspace-status');
    wsEl.innerHTML = '<span style="color:#d29922">⏳ Initializing…</span>';
    try {
        const res = await fetch(kapi('/evolve/init'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ force: false })
        });
        const data = await res.json();
        wsEl.innerHTML =
            `<span style="color:#3fb950">✅ ${data.status}</span><br>
       Workspace: ${data.result?.workspace || 'n/a'}<br>
       Data dir: ${data.result?.paths?.data_dir || 'n/a'}<br>
       Memory dir: ${data.result?.paths?.memory_dir || 'n/a'}`;
        loadSystemData();
    } catch (e) {
        wsEl.innerHTML = `<span style="color:#f85149">❌ ${e.message}</span>`;
    }
}

// Auto-refresh intervals — guard: only poll when the tab's container is in the DOM and the agent is reachable
setInterval(() => {
    if (window.__kernelAgentOnline !== false && document.getElementById('ins-stats')) loadInsights();
}, 30000);
setInterval(() => {
    if (window.__kernelAgentOnline !== false && document.getElementById('rep-list')) loadReplicas();
}, 10000);

// -- Memory & Workspace tab --------------------------------------------------
let _memCurrentFile = null;
let _memEditMode = false;
let _memTreeData = null;
let _memAllFiles = [];

function _memFormatSize(b) {
    if (b == null) return "\u2014";
    if (b < 1024) return b + " B";
    if (b < 1048576) return (b / 1024).toFixed(1) + " KB";
    return (b / 1048576).toFixed(2) + " MB";
}

async function loadMemoryStats() {
    try {
        const r = await fetch(kapi("/memory/stats"));
        if (!r.ok) throw new Error("HTTP " + r.status);
        const text = await r.text();
        if (text.startsWith("<!DOCTYPE") || text.startsWith("<html")) {
            ["mem-stat-files", "mem-stat-size", "mem-stat-sessions", "mem-stat-dbsize"].forEach(id => {
                const el = document.getElementById(id); if (el) el.textContent = "—";
            });
            return;
        }
        const d = JSON.parse(text);
        const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
        el("mem-stat-files", d.files != null ? d.files : "\u2014");
        el("mem-stat-size", _memFormatSize(d.total_size));
        el("mem-stat-sessions", d.chat_sessions != null ? d.chat_sessions : "\u2014");
        el("mem-stat-dbsize", _memFormatSize(d.db_size));
    } catch (e) {
        ["mem-stat-files", "mem-stat-size", "mem-stat-sessions", "mem-stat-dbsize"].forEach(id => {
            const el = document.getElementById(id); if (el) el.textContent = "—";
        });
    }
}

async function loadWorkspaceTree(path) {
    const treeEl = document.getElementById("mem-file-tree");
    if (!treeEl) return;
    try {
        const r = await fetch(kapi("/workspace/tree?path=") + encodeURIComponent(path || "/") + "&depth=6");
        if (!r.ok) throw new Error("HTTP " + r.status);
        const text = await r.text();
        if (text.startsWith("<!DOCTYPE") || text.startsWith("<html")) {
            treeEl.innerHTML = "<div style='color:#d29922;padding:8px 12px;font-size:0.75em;'>⚠ Backend server not running — start the Kernel server to browse files.</div>";
            return;
        }
        _memTreeData = JSON.parse(text);
        const fr = await fetch(kapi("/memory/files"));
        if (!fr.ok) throw new Error("HTTP " + fr.status);
        const ft = await fr.text();
        if (ft.startsWith("<!DOCTYPE") || ft.startsWith("<html")) {
            _memAllFiles = [];
        } else {
            const fd = JSON.parse(ft);
            _memAllFiles = fd.files || [];
        }
        treeEl.innerHTML = _memRenderTree(_memTreeData, 0, "");
    } catch (e) {
        treeEl.innerHTML = "<div style='color:#d29922;padding:8px 12px;font-size:0.75em;'>⚠ Backend server not running — start the Kernel server to browse files.</div>";
    }
}

const _MEM_EXT_COLORS = { ".md": "#79c0ff", ".json": "#d29922", ".yaml": "#3fb950", ".yml": "#3fb950", ".txt": "#8b949e" };

function _memRenderTree(node, depth, parentPath) {
    if (!node) return "";
    let html = "";
    const indent = depth * 14;
    if (node.type === "dir" || node.children) {
        const name = node.name || "/";
        const currentPath = parentPath ? (parentPath + "/" + name) : name;
        const isMobile = window.innerWidth <= 700;
        const openAttr = (depth < 2 && !isMobile) ? "open" : (depth < 1 && isMobile ? "" : "");
        html += `<details ${openAttr} style="margin-left:${indent}px;">`;
        html += `<summary style="cursor:pointer;padding:3px 8px;font-size:0.78em;color:#8b949e;list-style:none;display:flex;align-items:center;gap:5px;">`;
        html += `<span style="opacity:0.6;">&#128193;</span> <span>${_memEsc(name)}</span></summary>`;
        if (node.children && node.children.length) {
            html += "<div>";
            for (const child of node.children) {
                html += _memRenderTree(child, depth + 1, currentPath);
            }
            html += "</div>";
        }
        html += "</details>";
    } else {
        const ext2 = node.ext || (node.name || "").replace(/^.*\./, ".");
        const color = _MEM_EXT_COLORS[ext2] || "#c9d1d9";
        const sizeTxt = node.size != null ? _memFormatSize(node.size) : "";
        const matched = _memAllFiles.find(f => f.name === node.name);
        const filePath = matched ? matched.path : (parentPath ? (parentPath.replace(/^\//, "") + "/" + node.name) : node.name);
        html += `<div class="mem-tree-file" data-path="${_memEsc(filePath)}" onclick="_memClickFile(this)" ondblclick="_memStartRename(this)" oncontextmenu="_memShowCtxMenu(event,this)"`;
        html += ` style="margin-left:${indent + 14}px;padding:3px 8px;cursor:pointer;font-size:0.76em;font-family:monospace;border-radius:4px;display:flex;align-items:center;gap:6px;" title="${_memEsc(node.name)}"`;
        html += ` onmouseover="this.style.background='#161b22'" onmouseout="this.style.background='';">`;
        html += `<span style="color:${color};">${_memEsc(node.name)}</span>`;
        html += `<span style="margin-left:auto;color:#555;font-size:0.88em;">${sizeTxt}</span></div>`;
    }
    return html;
}

function filterMemoryTree(query) {
    const treeEl = document.getElementById("mem-file-tree");
    if (!treeEl) return;
    query = (query || "").trim().toLowerCase();
    if (!query) {
        if (_memTreeData) treeEl.innerHTML = _memRenderTree(_memTreeData, 0, "");
        return;
    }
    const matches = _memAllFiles.filter(f => f.path.toLowerCase().includes(query));
    if (!matches.length) {
        treeEl.innerHTML = "<div style='color:#555;font-size:0.75em;padding:8px 12px;'>No matches</div>";
        return;
    }
    treeEl.innerHTML = matches.slice(0, 80).map(f => {
        const ext3 = (f.name.match(/\.[^.]+$/) || [""])[0];
        const c = _MEM_EXT_COLORS[ext3] || "#c9d1d9";
        return `<div class="mem-tree-file" data-path="${_memEsc(f.path)}" onclick="_memClickFile(this)"`
            + ` style="padding:4px 10px;cursor:pointer;font-size:0.76em;font-family:monospace;border-radius:4px;"`
            + ` onmouseover="this.style.background='#161b22'" onmouseout="this.style.background=''">`
            + `<span style="color:${c};">${_memEsc(f.name)}</span>`
            + `<span style="color:#555;margin-left:4px;">${_memEsc(f.path)}</span></div>`;
    }).join("");
}

function _memEsc(s) {
    return String(s || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function _memClickFile(el) {
    if (!el || !el.dataset || !el.dataset.path) return;
    document.querySelectorAll(".mem-tree-file").forEach(e => e.style.background = "");
    el.style.background = "#17273f";
    loadMemoryFile(el.dataset.path);
}

async function loadMemoryFile(path) {
    const viewEl = document.getElementById("mem-editor-view");
    const fnEl = document.getElementById("mem-editor-filename");
    const metaEl = document.getElementById("mem-editor-meta");
    const editBtn = document.getElementById("mem-edit-btn");
    const deleteBtn = document.getElementById("mem-delete-btn");
    if (!viewEl) return;
    _memEditMode = false;
    viewEl.style.display = "";
    const textarea = document.getElementById("mem-editor-textarea");
    if (textarea) textarea.style.display = "none";
    const saveBtn = document.getElementById("mem-save-btn");
    const cancelBtn = document.getElementById("mem-cancel-btn");
    if (saveBtn) saveBtn.style.display = "none";
    if (cancelBtn) cancelBtn.style.display = "none";
    if (deleteBtn) deleteBtn.style.display = "none";
    // Check if it's a SQLite file
    const sqliteExts = ['.sqlite', '.sqlite3', '.db', '.db3'];
    const fileExt = path.toLowerCase().includes('.') ? '.' + path.split('.').pop().toLowerCase() : '';
    if (sqliteExts.includes(fileExt)) {
        sqliteOpen(path);
        return;
    }
    viewEl.innerHTML = "<div style='color:#8b949e;font-size:0.78em;font-family:monospace;'>Loading...</div>";
    try {
        const d = await _memFetchJson("/memory/file?path=" + encodeURIComponent(path));
        _memCurrentFile = d;
        if (fnEl) fnEl.textContent = d.name || path;
        if (metaEl) metaEl.textContent = _memFormatSize(d.size) + " \u00b7 " + (d.modified || "").replace("T", " ");
        if (editBtn) editBtn.style.display = "";
        if (deleteBtn) deleteBtn.style.display = "";
        const ext4 = (d.name || "").toLowerCase().replace(/^.*\./, ".");
        const content = d.content || "";
        if (ext4 === "json") {
            let pretty = content;
            try { pretty = JSON.stringify(JSON.parse(content), null, 2); } catch (e) { }
            viewEl.innerHTML = "<pre style='font-family:monospace;font-size:0.78em;color:#d29922;white-space:pre-wrap;word-break:break-all;'>" + _memEsc(pretty) + "</pre>";
        } else if (ext4 === "md") {
            viewEl.innerHTML = "<div style='font-size:0.82em;line-height:1.65;'>" + _memRenderMarkdown(content) + "</div>";
        } else {
            viewEl.innerHTML = "<pre style='font-family:monospace;font-size:0.78em;color:#c9d1d9;white-space:pre-wrap;word-break:break-all;'>" + _memEsc(content) + "</pre>";
        }
    } catch (e) {
        const msg = e.message === "Backend not available"
            ? "⚠ Backend server not running — start the Kernel server to browse files."
            : "Error: " + _memEsc(e.message);
        viewEl.innerHTML = "<div style='color:#d29922;font-size:0.78em;font-family:monospace;'>" + msg + "</div>";
    }
}

function _memRenderMarkdown(md) {
    let html = _memEsc(md);
    // Code blocks
    html = html.replace(/```[\s\S]*?```/g, m =>
        "<pre style='background:#161b22;border:1px solid #30363d;border-radius:4px;padding:8px 10px;font-size:0.9em;overflow-x:auto;'>"
        + m.replace(/```\w*\n?/g, "").replace(/```/g, "") + "</pre>");
    // Inline code
    html = html.replace(/`([^`]+)`/g, "<code style='background:#161b22;padding:1px 4px;border-radius:3px;font-family:monospace;color:#79c0ff;'>$1</code>");
    // Headers
    html = html.replace(/^### (.+)$/gm, "<h3 style='color:#c9d1d9;margin:12px 0 4px;font-size:0.9em;'>$1</h3>");
    html = html.replace(/^## (.+)$/gm, "<h2 style='color:#c9d1d9;margin:14px 0 6px;font-size:1.0em;'>$1</h2>");
    html = html.replace(/^# (.+)$/gm, "<h1 style='color:#79c0ff;margin:16px 0 8px;font-size:1.1em;'>$1</h1>");
    // Bold & italic
    html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
    html = html.replace(/\*([^*]+)\*/g, "<em style='color:#d29922;'>$1</em>");
    // Lists
    html = html.replace(/^[-*] (.+)$/gm, "<li style='margin-left:16px;list-style:disc;'>$1</li>");
    // Newlines
    html = html.replace(/\n/g, "<br>");
    return html;
}

function toggleMemoryEdit() {
    if (!_memCurrentFile) return;
    _memEditMode = true;
    const viewEl = document.getElementById("mem-editor-view");
    const textarea = document.getElementById("mem-editor-textarea");
    const editBtn = document.getElementById("mem-edit-btn");
    const saveBtn = document.getElementById("mem-save-btn");
    const cancelBtn = document.getElementById("mem-cancel-btn");
    if (viewEl) viewEl.style.display = "none";
    if (textarea) { textarea.style.display = ""; textarea.value = _memCurrentFile.content || ""; }
    if (editBtn) editBtn.style.display = "none";
    if (saveBtn) saveBtn.style.display = "";
    if (cancelBtn) cancelBtn.style.display = "";
}

function cancelMemoryEdit() {
    _memEditMode = false;
    const viewEl = document.getElementById("mem-editor-view");
    const textarea = document.getElementById("mem-editor-textarea");
    const editBtn = document.getElementById("mem-edit-btn");
    const saveBtn = document.getElementById("mem-save-btn");
    const cancelBtn = document.getElementById("mem-cancel-btn");
    if (viewEl) viewEl.style.display = "";
    if (textarea) textarea.style.display = "none";
    if (editBtn) editBtn.style.display = "";
    if (saveBtn) saveBtn.style.display = "none";
    if (cancelBtn) cancelBtn.style.display = "none";
}

async function saveMemoryFile(path, content) {
    try {
        const r = await fetch(kapi("/memory/file"), {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ path: path, content: content })
        });
        const d = await r.json();
        if (!r.ok) throw new Error(d.error || r.statusText);
        return { ok: true, size: d.size };
    } catch (e) { return { ok: false, error: e.message }; }
}

async function saveCurrentMemoryFile() {
    if (!_memCurrentFile) return;
    const textarea = document.getElementById("mem-editor-textarea");
    const saveBtn = document.getElementById("mem-save-btn");
    const metaEl = document.getElementById("mem-editor-meta");
    if (!textarea) return;
    const content = textarea.value;
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = "Saving\u2026"; }
    const result = await saveMemoryFile(_memCurrentFile.path, content);
    if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = "Save"; }
    if (result.ok) {
        _memCurrentFile.content = content;
        _memCurrentFile.size = result.size;
        if (metaEl) metaEl.textContent = _memFormatSize(result.size) + " \u00b7 saved";
        cancelMemoryEdit();
        await loadMemoryFile(_memCurrentFile.path);
    } else {
        alert("Save failed: " + (result.error || "unknown error"));
    }
}
// -- CRUD: delete, rename, new file, tree toggle, context menu ----------------

let _memCtxTarget = null;
let _memTreeCollapsed = false;

function _memToggleTree() {
    const panel = document.getElementById("mem-tree-panel");
    const btn = document.getElementById("mem-collapse-btn");
    if (!panel) return;
    _memTreeCollapsed = !_memTreeCollapsed;
    panel.classList.toggle("collapsed", _memTreeCollapsed);
    if (btn) btn.textContent = _memTreeCollapsed ? "\u00bb Files" : "\u00ab";
}

function _memShowCtxMenu(e, el) {
    e.preventDefault();
    e.stopPropagation();
    _memCtxTarget = el;
    const menu = document.getElementById("mem-ctx-menu");
    if (!menu) return;
    menu.style.display = "block";
    menu.style.left = e.clientX + "px";
    menu.style.top = e.clientY + "px";
}
document.addEventListener("click", () => {
    const menu = document.getElementById("mem-ctx-menu");
    if (menu) menu.style.display = "none";
});

function _memCtxOpen() {
    if (_memCtxTarget) _memClickFile(_memCtxTarget);
}
function _memCtxRename() {
    if (_memCtxTarget) _memStartRename(_memCtxTarget);
}
function _memCtxDelete() {
    if (_memCtxTarget) {
        const path = _memCtxTarget.dataset.path;
        const name = path.split("/").pop();
        if (!confirm("Delete " + name + "? This cannot be undone.")) return;
        _memDoDelete(path);
    }
}

async function deleteCurrentMemoryFile() {
    if (!_memCurrentFile) return;
    const name = _memCurrentFile.name || _memCurrentFile.path;
    if (!confirm("Delete " + name + "? This cannot be undone.")) return;
    await _memDoDelete(_memCurrentFile.path);
}

async function _memDoDelete(path) {
    try {
        const r = await fetch(kapi("/memory/file?path=") + encodeURIComponent(path), { method: "DELETE" });
        const d = await r.json();
        if (!r.ok) { alert("Delete failed: " + (d.error || r.statusText)); return; }
        // Clear editor if the deleted file was open
        if (_memCurrentFile && _memCurrentFile.path === path) {
            _memCurrentFile = null;
            const fn = document.getElementById("mem-editor-filename");
            const view = document.getElementById("mem-editor-view");
            const meta = document.getElementById("mem-editor-meta");
            if (fn) fn.textContent = "No file selected";
            if (meta) meta.textContent = "";
            if (view) view.innerHTML = "<div style='color:#555;font-size:0.8em;font-family:monospace;'>Select a file from the tree to view its contents.</div>";
            ["mem-edit-btn", "mem-save-btn", "mem-cancel-btn", "mem-delete-btn"].forEach(id => {
                const b = document.getElementById(id); if (b) b.style.display = "none";
            });
        }
        await loadWorkspaceTree("/");
    } catch (e) { alert("Delete failed: " + e.message); }
}

async function promptNewMemoryFile() {
    const relPath = prompt("File path (relative to workspace):");
    if (!relPath || !relPath.trim()) return;
    const path = relPath.trim();
    try {
        const r = await fetch(kapi("/memory/file/new"), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ path: path, content: "" })
        });
        const d = await r.json();
        if (!r.ok) { alert("Create failed: " + (d.error || r.statusText)); return; }
        await loadWorkspaceTree("/");
        await loadMemoryFile(path);
        // Immediately enter edit mode
        toggleMemoryEdit();
    } catch (e) { alert("Create failed: " + e.message); }
}

function _memStartRename(el) {
    if (!el || !el.dataset || !el.dataset.path) return;
    const path = el.dataset.path;
    const name = path.split("/").pop();
    const nameSpan = el.querySelector("span");
    if (!nameSpan) return;
    const origText = nameSpan.textContent;
    const input = document.createElement("input");
    input.value = name;
    input.style.cssText = "background:#0d1117;border:1px solid #58a6ff;color:#c9d1d9;font-size:0.78em;font-family:monospace;padding:1px 4px;border-radius:3px;width:140px;";
    nameSpan.replaceWith(input);
    input.focus(); input.select();

    const cancel = () => {
        input.replaceWith(nameSpan);
        nameSpan.textContent = origText;
    };
    const commit = async () => {
        const newName = input.value.trim();
        if (!newName || newName === name) { cancel(); return; }
        const dir = path.includes("/") ? path.substring(0, path.lastIndexOf("/") + 1) : "";
        const newPath = dir + newName;
        try {
            const r = await fetch(kapi("/memory/file/rename"), {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ from: path, to: newPath })
            });
            const d = await r.json();
            if (!r.ok) { alert("Rename failed: " + (d.error || r.statusText)); cancel(); return; }
            await loadWorkspaceTree("/");
            if (_memCurrentFile && _memCurrentFile.path === path) {
                await loadMemoryFile(newPath);
            }
        } catch (e) { alert("Rename failed: " + e.message); cancel(); }
    };
    input.addEventListener("keydown", e => {
        if (e.key === "Enter") { e.preventDefault(); commit(); }
        if (e.key === "Escape") { e.preventDefault(); cancel(); }
    });
    input.addEventListener("blur", () => { setTimeout(commit, 120); });
}
// -- End CRUD ----------------------------------------------------------------

// -- End Memory & Workspace tab ------------------------------------------

// ── SQLite viewer ──────────────────────────────────────────
let _sqlitePath = null;
let _sqliteTables = [];
let _sqliteActiveTable = null;
let _sqlitePage = 1;
let _sqlitePerPage = 50;
let _sqliteTotalPages = 1;
let _sqliteTotalRows = 0;
let _sqliteColumns = [];
let _sqliteColumnNames = [];
let _sqliteEditRowId = null;
let _sqliteEditPkColumn = null;
let _sqliteEditPkValue = null;

function sqliteOpen(path) {
    _sqlitePath = path;
    _sqliteActiveTable = null;
    _sqlitePage = 1;
    _sqliteEditRowId = null;
    // Hide text editor, show SQLite viewer
    document.getElementById('mem-editor-view').style.display = 'none';
    document.getElementById('mem-editor-textarea').style.display = 'none';
    const sqliteView = document.getElementById('mem-sqlite-view');
    sqliteView.style.display = 'flex';
    document.getElementById('mem-sqlite-filename').textContent = path.split('/').pop() || path;
    document.getElementById('mem-sqlite-meta').textContent = '';
    document.getElementById('mem-sqlite-tables-list').innerHTML = '<span style="color:#8b949e;font-size:0.72em;">Loading tables...</span>';
    document.getElementById('mem-sqlite-table-data').innerHTML = '<div style="color:#8b949e;font-size:0.8em;font-family:monospace;">Loading...</div>';
    document.getElementById('mem-sqlite-pagination').style.display = 'none';
    sqliteLoadTables();
}

function sqliteClose() {
    _sqlitePath = null;
    _sqliteActiveTable = null;
    _sqliteEditRowId = null;
    document.getElementById('mem-sqlite-view').style.display = 'none';
    document.getElementById('mem-editor-view').style.display = '';
    // Reload the file tree to ensure consistency
    loadWorkspaceTree('/');
}

async function _memFetchJson(url) {
    const r = await fetch(url);
    if (!r.ok) throw new Error("HTTP " + r.status);
    const text = await r.text();
    if (text.startsWith("<!DOCTYPE") || text.startsWith("<html")) {
        throw new Error("Backend not available");
    }
    return JSON.parse(text);
}

async function sqliteLoadTables() {
    if (!_sqlitePath) return;
    try {
        const d = await _memFetchJson('/sqlite/tables?path=' + encodeURIComponent(_sqlitePath));
        _sqliteTables = d.tables || [];
        const listEl = document.getElementById('mem-sqlite-tables-list');
        if (!_sqliteTables.length) {
            listEl.innerHTML = '<span style="color:#8b949e;font-size:0.72em;">No tables found</span>';
            document.getElementById('mem-sqlite-table-data').innerHTML = '<div style="color:#8b949e;font-size:0.8em;font-family:monospace;">This database has no tables.</div>';
            return;
        }
        listEl.innerHTML = _sqliteTables.map(t =>
            `<div style="display:inline-flex;align-items:center;gap:4px;margin:2px 0;">
            <button class="ctrl-btn sqlite-table-btn" data-table="${_memEsc(t)}" onclick="sqliteSelectTable('${_memEsc(t)}')" style="font-size:0.72em;padding:3px 10px;">${_memEsc(t)}</button>
            <button class="ctrl-btn danger" style="font-size:0.65em;padding:1px 5px;" onclick="sqliteClearTable('${_memEsc(t)}')" title="Clear all data from this table">🗑</button>
          </div>`
        ).join('');
        document.getElementById('mem-sqlite-meta').textContent = _sqliteTables.length + ' tables';
        // Auto-select first table if none selected
        if (!_sqliteActiveTable || !_sqliteTables.includes(_sqliteActiveTable)) {
            sqliteSelectTable(_sqliteTables[0]);
        } else {
            sqliteSelectTable(_sqliteActiveTable);
        }
    } catch (e) {
        const msg = e.message === "Backend not available"
            ? "⚠ Backend not running"
            : "Error: " + _memEsc(e.message);
        document.getElementById('mem-sqlite-tables-list').innerHTML = `<span style="color:#d29922;font-size:0.72em;">${msg}</span>`;
    }
}

async function sqliteSelectTable(table) {
    if (!table || !_sqlitePath) return;
    _sqliteActiveTable = table;
    _sqlitePage = 1;
    _sqliteEditRowId = null;
    // Highlight selected table
    document.querySelectorAll('.sqlite-table-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.table === table);
    });
    await sqliteLoadTableData();
}

async function sqliteLoadTableData() {
    if (!_sqlitePath || !_sqliteActiveTable) return;
    const dataEl = document.getElementById('mem-sqlite-table-data');
    dataEl.innerHTML = '<div style="color:#8b949e;font-size:0.8em;font-family:monospace;">Loading...</div>';
    try {
        const d = await _memFetchJson('/sqlite/table?path=' + encodeURIComponent(_sqlitePath) +
            '&table=' + encodeURIComponent(_sqliteActiveTable) +
            '&page=' + _sqlitePage + '&per_page=' + _sqlitePerPage);
        _sqliteColumns = d.columns || [];
        _sqliteColumnNames = d.column_names || [];
        _sqliteTotalRows = d.total_rows || 0;
        _sqliteTotalPages = d.total_pages || 1;
        const rows = d.rows || [];
        // Pagination
        const pagEl = document.getElementById('mem-sqlite-pagination');
        pagEl.style.display = 'flex';
        document.getElementById('sqlite-page-info').textContent = `Page ${_sqlitePage} of ${_sqliteTotalPages}`;
        document.getElementById('sqlite-total-rows').textContent = `Total rows: ${_sqliteTotalRows}`;
        document.getElementById('sqlite-prev-btn').disabled = _sqlitePage <= 1;
        document.getElementById('sqlite-next-btn').disabled = _sqlitePage >= _sqliteTotalPages;
        // Render table
        if (!_sqliteColumnNames.length) {
            dataEl.innerHTML = '<div style="color:#8b949e;font-size:0.8em;font-family:monospace;">No columns found.</div>';
            return;
        }
        // Determine PK column
        const pkCol = _sqliteColumns.find(c => c.pk) || _sqliteColumns[0];
        const pkName = pkCol ? pkCol.name : _sqliteColumnNames[0];
        let html = '<div style="overflow-x:auto;max-height:calc(100vh - 480px);overflow-y:auto;">';
        html += '<table style="border-collapse:collapse;width:100%;font-size:0.72em;font-family:monospace;">';
        // Header
        html += '<thead><tr style="background:#161b22;position:sticky;top:0;z-index:2;">';
        html += '<th style="padding:6px 8px;border:1px solid #30363d;color:#79c0ff;text-align:left;white-space:nowrap;">#</th>';
        _sqliteColumnNames.forEach(cn => {
            const isPk = _sqliteColumns.find(c => c.name === cn && c.pk);
            const label = isPk ? cn + ' 🔑' : cn;
            const colInfo = _sqliteColumns.find(c => c.name === cn);
            const colType = colInfo ? colInfo.type : '';
            html += `<th style="padding:6px 8px;border:1px solid #30363d;color:#58a6ff;text-align:left;white-space:nowrap;" title="${_memEsc(colType)}">${_memEsc(label)}</th>`;
        });
        html += '<th style="padding:6px 8px;border:1px solid #30363d;color:#d29922;text-align:center;white-space:nowrap;">Actions</th>';
        html += '</tr></thead><tbody>';
        if (!rows.length) {
            html += '<tr><td colspan="' + (_sqliteColumnNames.length + 2) + '" style="padding:20px;text-align:center;color:#8b949e;">No rows</td></tr>';
        } else {
            rows.forEach((row, idx) => {
                const pkValue = row[pkName];
                const rowNum = (_sqlitePage - 1) * _sqlitePerPage + idx + 1;
                const isEditing = _sqliteEditRowId === pkValue;
                html += `<tr style="border-bottom:1px solid #21262d;" data-pk="${_memEsc(pkValue)}">`;
                html += `<td style="padding:4px 8px;border:1px solid #30363d;color:#8b949e;white-space:nowrap;">${rowNum}</td>`;
                _sqliteColumnNames.forEach(cn => {
                    const val = row[cn];
                    const valStr = val === null ? 'NULL' : String(val);
                    const valClass = val === null ? 'color:#8b949e;font-style:italic;' : 'color:#c9d1d9;';
                    const maxLen = 120;
                    const displayStr = valStr.length > maxLen ? valStr.slice(0, maxLen) + '…' : valStr;
                    html += `<td style="padding:4px 8px;border:1px solid #30363d;${valClass}white-space:nowrap;max-width:300px;overflow:hidden;text-overflow:ellipsis;" title="${_memEsc(valStr)}">`;
                    if (isEditing) {
                        html += `<input class="sqlite-edit-input" data-col="${_memEsc(cn)}" value="${_memEsc(valStr)}" style="background:#0d1117;border:1px solid #58a6ff;color:#c9d1d9;padding:2px 4px;border-radius:3px;width:100%;min-width:60px;font-size:0.9em;font-family:monospace;" />`;
                    } else {
                        html += _memEsc(displayStr);
                    }
                    html += '</td>';
                });
                // Actions column
                html += '<td style="padding:4px 8px;border:1px solid #30363d;text-align:center;white-space:nowrap;">';
                if (isEditing) {
                    html += '<button class="ctrl-btn success" style="font-size:0.8em;padding:2px 6px;margin-right:4px;" onclick="sqliteSaveRow(\'' + escJs(pkName) + '\',\'' + escJs(pkValue) + '\')">✓</button>';
                    html += '<button class="ctrl-btn" style="font-size:0.8em;padding:2px 6px;" onclick="sqliteCancelEdit()">✕</button>';
                } else {
                    html += '<button class="ctrl-btn" style="font-size:0.8em;padding:2px 6px;margin-right:4px;" onclick="sqliteEditRow(\'' + escJs(pkName) + '\',\'' + escJs(pkValue) + '\')">✏️</button>';
                    html += '<button class="ctrl-btn danger" style="font-size:0.8em;padding:2px 6px;" onclick="sqliteDeleteRow(\'' + escJs(pkName) + '\',\'' + escJs(pkValue) + '\')">🗑</button>';
                }
                html += '</td>';
                html += '</tr>';
            });
            // Add row
            html += '<tr><td colspan="' + (_sqliteColumnNames.length + 2) + '" style="padding:6px 8px;border-top:2px solid #30363d;text-align:center;">';
            html += '<button class="ctrl-btn" style="font-size:0.72em;padding:2px 10px;" onclick="sqliteAddRow()">+ Add row</button>';
            html += '</td></tr>';
            // Add row form (hidden)
            if (_sqliteEditRowId === 'NEW') {
                html += '<tr id="sqlite-add-row-form" style="background:#0d1b2a;">';
                html += '<td style="padding:4px 8px;border:1px solid #30363d;color:#8b949e;">NEW</td>';
                _sqliteColumnNames.forEach(cn => {
                    const isPk = _sqliteColumns.find(c => c.name === cn && c.pk);
                    const defaultVal = isPk ? '' : '';
                    html += `<td style="padding:4px 8px;border:1px solid #30363d;"><input class="sqlite-edit-input" data-col="${_memEsc(cn)}" value="${defaultVal}" placeholder="${_memEsc(cn)}" style="background:#0d1117;border:1px solid #58a6ff;color:#c9d1d9;padding:2px 4px;border-radius:3px;width:100%;min-width:60px;font-size:0.9em;font-family:monospace;" /></td>`;
                });
                html += '<td style="padding:4px 8px;border:1px solid #30363d;text-align:center;">';
                html += '<button class="ctrl-btn success" style="font-size:0.8em;padding:2px 6px;margin-right:4px;" onclick="sqliteSaveNewRow()">✓</button>';
                html += '<button class="ctrl-btn" style="font-size:0.8em;padding:2px 6px;" onclick="sqliteCancelEdit()">✕</button>';
                html += '</td></tr>';
            }
            html += '</tbody></table></div>';
            dataEl.innerHTML = html;
        }
    } catch (e) {
        const msg = e.message === "Backend not available"
            ? "⚠ Backend server not running — start the Kernel server to browse SQLite databases."
            : "Error: " + _memEsc(e.message);
        dataEl.innerHTML = `<div style="color:#d29922;font-size:0.8em;font-family:monospace;">${msg}</div>`;
    }
}

function escJs(s) {
    return String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function sqliteEditRow(pkColumn, pkValue) {
    _sqliteEditRowId = pkValue;
    _sqliteEditPkColumn = pkColumn;
    _sqliteEditPkValue = pkValue;
    sqliteLoadTableData();
}

function sqliteCancelEdit() {
    _sqliteEditRowId = null;
    _sqliteEditPkColumn = null;
    _sqliteEditPkValue = null;
    sqliteLoadTableData();
}

async function sqliteSaveRow(pkColumn, pkValue) {
    if (!_sqlitePath || !_sqliteActiveTable) return;
    const inputs = document.querySelectorAll('.sqlite-edit-input');
    const updates = {};
    inputs.forEach(inp => {
        const col = inp.dataset.col;
        if (col) updates[col] = inp.value;
    });
    try {
        const r = await fetch(kapi('/sqlite/row'), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                path: _sqlitePath,
                table: _sqliteActiveTable,
                pk_column: pkColumn,
                pk_value: pkValue,
                updates: updates
            })
        });
        const d = await r.json();
        if (!r.ok) throw new Error(d.error || r.statusText);
        _sqliteEditRowId = null;
        sqliteLoadTableData();
    } catch (e) {
        alert('Save failed: ' + e.message);
    }
}

async function sqliteDeleteRow(pkColumn, pkValue) {
    if (!_sqlitePath || !_sqliteActiveTable) return;
    if (!confirm('Delete this row? This cannot be undone.')) return;
    try {
        const r = await fetch(kapi('/sqlite/row?path=') + encodeURIComponent(_sqlitePath) +
            '&table=' + encodeURIComponent(_sqliteActiveTable) +
            '&pk_column=' + encodeURIComponent(pkColumn) +
            '&pk_value=' + encodeURIComponent(pkValue), { method: 'DELETE' });
        const d = await r.json();
        if (!r.ok) throw new Error(d.error || r.statusText);
        sqliteLoadTableData();
    } catch (e) {
        alert('Delete failed: ' + e.message);
    }
}

function sqliteAddRow() {
    _sqliteEditRowId = 'NEW';
    sqliteLoadTableData();
}

async function sqliteSaveNewRow() {
    if (!_sqlitePath || !_sqliteActiveTable) return;
    const inputs = document.querySelectorAll('.sqlite-edit-input');
    const row = {};
    inputs.forEach(inp => {
        const col = inp.dataset.col;
        if (col) row[col] = inp.value;
    });
    try {
        const r = await fetch(kapi('/sqlite/row'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                path: _sqlitePath,
                table: _sqliteActiveTable,
                row: row
            })
        });
        const d = await r.json();
        if (!r.ok) throw new Error(d.error || r.statusText);
        _sqliteEditRowId = null;
        sqliteLoadTableData();
    } catch (e) {
        alert('Add row failed: ' + e.message);
    }
}

async function sqliteClearAllTables() {
    if (!_sqlitePath) return;
    const dbName = _sqlitePath.split('/').pop() || _sqlitePath;
    if (!confirm(`Clear ALL data from ${dbName}? This will delete all rows from all tables while keeping the structure intact.`)) return;
    try {
        const r = await fetch(kapi('/sqlite/db/data'), {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path: _sqlitePath })
        });
        const d = await r.json();
        if (!r.ok) throw new Error(d.error || r.statusText);
        alert(`Cleared ${d.total_rows_deleted} rows from ${d.tables_cleared.length} tables.`);
        sqliteLoadTableData();
    } catch (e) {
        alert('Clear failed: ' + e.message);
    }
}

async function sqliteClearTable(table) {
    if (!_sqlitePath || !table) return;
    if (!confirm(`Delete ALL rows from table "${table}"? This cannot be undone.`)) return;
    try {
        const r = await fetch(kapi('/sqlite/table/data?path=') + encodeURIComponent(_sqlitePath) +
            '&table=' + encodeURIComponent(table), { method: 'DELETE' });
        const d = await r.json();
        if (!r.ok) throw new Error(d.error || r.statusText);
        alert(`Cleared ${d.rows_deleted} rows from "${table}".`);
        sqliteLoadTableData();
    } catch (e) {
        alert('Clear table failed: ' + e.message);
    }
}

function sqlitePrevPage() {
    if (_sqlitePage > 1) { _sqlitePage--; sqliteLoadTableData(); }
}

function sqliteNextPage() {
    if (_sqlitePage < _sqliteTotalPages) { _sqlitePage++; sqliteLoadTableData(); }
}

function sqliteRefresh() {
    if (_sqlitePath) {
        sqliteLoadTables();
    }
}

// ── Voice tab ───────────────────────────────────────────────
let _vMediaStream = null, _vRecorder = null, _vChunks = [], _vProcessing = false;
let _vSpaceDown = false, _vRecordTimeout = null, _vAudioCtx = null;
let _vSessionId = 'evo-voice-' + Math.random().toString(36).slice(2, 8);
let _vInitDone = false;
let _vIsRecording = false, _vRecordStartedAt = 0;
let _vVoiceReady = false;
let _vRecordMime = 'audio/webm';
let _vLastAssistantText = '';
let _vLastTtsError = '';
let _vWaveBars = [];
let _vWaveAnimRaf = null;
let _vWaveMode = 'idle';
let _vWaveAnalyser = null;
let _vWaveSourceNode = null;
let _vWaveData = null;
let _vPlaybackAudio = null;
let _vPlaybackUrl = '';
let _vAutoTtsTimer = null;
let _vAutoTtsAttempts = 0;
const _vAutoTtsMaxAttempts = 1;
// ElevenLabs realtime mode state
let _vRtMode = false;          // true when the ElevenLabs Realtime (:8768) server is selected
let _vRtSessionId = null;      // active ElevenLabs realtime session id
let _vRtPollTimer = null;      // interval handle for GET /rt/receive polling
let _vRtChunkTimer = null;     // interval handle for streaming mic segments up
let _vVoiceEngine = 'default'; // voice transport: 'default' (native) | 'elevenlabs'
let _vElTransport = false;     // true when ElevenLabs is the voice transport (STT+TTS) + a real brain

function vSetMicEnabled(enabled) {
    const btn = document.getElementById('v-mic-btn');
    if (!btn) return;
    btn.disabled = !enabled;
    btn.style.opacity = enabled ? '1' : '0.45';
    btn.style.cursor = enabled ? 'pointer' : 'not-allowed';
}

function vSetRetryEnabled(enabled) {
    const btn = document.getElementById('v-retry-tts');
    if (!btn) return;
    btn.disabled = !enabled;
    btn.style.opacity = enabled ? '1' : '0.5';
}

function vClearAutoTtsRetry() {
    if (_vAutoTtsTimer) {
        clearTimeout(_vAutoTtsTimer);
        _vAutoTtsTimer = null;
    }
}

function vScheduleAutoTtsRetry(delayMs = 3500) {
    vClearAutoTtsRetry();
    if (!_vLastAssistantText.trim()) return;
    if (_vAutoTtsAttempts >= _vAutoTtsMaxAttempts) return;

    _vAutoTtsTimer = setTimeout(async () => {
        _vAutoTtsTimer = null;
        if (_vProcessing) {
            vScheduleAutoTtsRetry(1800);
            return;
        }
        _vAutoTtsAttempts += 1;
        await vRetryLastTts({ auto: true, silentSuccess: true });
    }, delayMs);
}

function vInitWaveBars() {
    const container = document.getElementById('v-wave-bars');
    if (!container || _vWaveBars.length) return;
    for (let i = 0; i < 30; i++) {
        const bar = document.createElement('span');
        bar.className = 'voice-wave-bar';
        bar.style.transform = 'scaleY(0.3)';
        container.appendChild(bar);
        _vWaveBars.push(bar);
    }
    vAnimateWave();
}

function vSetWaveMode(mode, label = '') {
    _vWaveMode = mode || 'idle';
    const wrap = document.getElementById('v-wave');
    const state = document.getElementById('v-wave-state');
    if (!wrap || !state) return;
    wrap.classList.remove('idle', 'listening', 'thinking', 'cloning', 'speaking');
    wrap.classList.add(_vWaveMode);
    state.textContent = label || _vWaveMode;
}

async function vEnsureAudioContext() {
    if (!_vAudioCtx) {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        _vAudioCtx = new Ctx();
    }
    if (_vAudioCtx.state === 'suspended') {
        try { await _vAudioCtx.resume(); } catch (_) { }
    }
    return _vAudioCtx;
}

async function vAttachWaveAnalyserFromStream(stream) {
    const ctx = await vEnsureAudioContext();
    if (!ctx || !stream) return;
    try {
        if (_vWaveSourceNode) {
            try { _vWaveSourceNode.disconnect(); } catch (_) { }
            _vWaveSourceNode = null;
        }
        const analyser = ctx.createAnalyser();
        analyser.fftSize = 128;
        analyser.smoothingTimeConstant = 0.86;
        const source = ctx.createMediaStreamSource(stream);
        source.connect(analyser);
        _vWaveAnalyser = analyser;
        _vWaveSourceNode = source;
        _vWaveData = new Uint8Array(analyser.frequencyBinCount);
    } catch (e) {
        console.warn('vAttachWaveAnalyserFromStream', e);
    }
}

async function vAttachWaveAnalyserFromAudio(audioEl) {
    const ctx = await vEnsureAudioContext();
    if (!ctx || !audioEl) return;
    try {
        if (_vWaveSourceNode) {
            try { _vWaveSourceNode.disconnect(); } catch (_) { }
            _vWaveSourceNode = null;
        }
        const analyser = ctx.createAnalyser();
        analyser.fftSize = 128;
        analyser.smoothingTimeConstant = 0.82;
        const source = ctx.createMediaElementSource(audioEl);
        source.connect(analyser);
        analyser.connect(ctx.destination);
        _vWaveAnalyser = analyser;
        _vWaveSourceNode = source;
        _vWaveData = new Uint8Array(analyser.frequencyBinCount);
    } catch (e) {
        console.warn('vAttachWaveAnalyserFromAudio', e);
    }
}

function vAnimateWave() {
    if (_vWaveAnimRaf) cancelAnimationFrame(_vWaveAnimRaf);
    const tick = () => {
        const bars = _vWaveBars;
        if (bars.length) {
            if (_vWaveAnalyser && _vWaveData && (_vWaveMode === 'listening' || _vWaveMode === 'speaking')) {
                _vWaveAnalyser.getByteFrequencyData(_vWaveData);
                for (let i = 0; i < bars.length; i++) {
                    const idx = Math.min(_vWaveData.length - 1, Math.floor(i * (_vWaveData.length / bars.length)));
                    const amp = _vWaveData[idx] / 255;
                    const scale = 0.2 + amp * 1.85;
                    bars[i].style.transform = `scaleY(${scale.toFixed(3)})`;
                    bars[i].style.opacity = `${0.45 + amp * 0.55}`;
                }
            } else {
                const t = Date.now() / 220;
                const base = _vWaveMode === 'thinking' ? 0.55 : (_vWaveMode === 'cloning' ? 0.7 : 0.32);
                const amp = _vWaveMode === 'thinking' ? 0.25 : (_vWaveMode === 'cloning' ? 0.32 : 0.14);
                for (let i = 0; i < bars.length; i++) {
                    const w = Math.sin(t + i * 0.45) * 0.5 + 0.5;
                    const scale = Math.max(0.16, base + w * amp);
                    bars[i].style.transform = `scaleY(${scale.toFixed(3)})`;
                    bars[i].style.opacity = `${0.45 + Math.min(0.5, w * 0.4)}`;
                }
            }
        }
        _vWaveAnimRaf = requestAnimationFrame(tick);
    };
    _vWaveAnimRaf = requestAnimationFrame(tick);
}

function initVoiceTab() {
    if (_vInitDone) return;
    _vInitDone = true;
    vInitWaveBars();
    vSetWaveMode('idle', 'ready');
    vSetRetryEnabled(false);
    checkVoiceServer();
    _vVoiceEngine = vVoiceEngine();
    document.getElementById('v-server-select').addEventListener('change', checkVoiceServer);
    document.getElementById('v-voice-engine').addEventListener('change', () => {
        _vVoiceEngine = vVoiceEngine();
        checkVoiceServer();
    });
    document.getElementById('v-self-test').addEventListener('click', runVoiceSelfTest);
    document.getElementById('v-retry-tts').addEventListener('click', async () => {
        await vRetryLastTts();
    });
    const micBtn = document.getElementById('v-mic-btn');
    micBtn.addEventListener('click', async e => {
        e.preventDefault();
        if (_vIsRecording) await vStopRecording(); else await vStartRecording();
    });
    window.addEventListener('keydown', e => {
        if (e.code === 'Space' && !_vSpaceDown && document.getElementById('voice-panel')?.style.display !== 'none') {
            e.preventDefault(); _vSpaceDown = true; vStartRecording();
        }
    });
    window.addEventListener('keyup', e => {
        if (e.code === 'Space' && _vSpaceDown) { _vSpaceDown = false; vStopRecording(); }
    });
    window.addEventListener('blur', () => {
        if (_vIsRecording) vStopRecording();
    });
    document.getElementById('v-new-conv').addEventListener('click', async () => {
        if (_vRtMode && _vRtSessionId) { try { await vRtStop(); } catch (_) { } }
        const base = vServerBase();
        try { await fetch(`${base}/voice/history/${_vSessionId}`, { method: 'DELETE' }); } catch (_) { }
        _vSessionId = 'evo-voice-' + Math.random().toString(36).slice(2, 8);
        vClearAutoTtsRetry();
        _vAutoTtsAttempts = 0;
        document.getElementById('v-thread').innerHTML = '';
        _vLastAssistantText = '';
        _vLastTtsError = '';
        vSetRetryEnabled(false);
        vSetWaveMode('idle', 'ready');
        vSetStatus('Ready');
    });
}

function vServerBase() {
    return document.getElementById('v-server-select')?.value || 'http://localhost:8779';
}

function vVoiceEngine() {
    return document.getElementById('v-voice-engine')?.value || 'default';
}

async function vFetchWithTimeout(url, options = {}, timeoutMs = 60000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const merged = { ...options, signal: controller.signal };
        return await fetch(url, merged);
    } catch (e) {
        if (e && e.name === 'AbortError') throw new Error(`Request timed out after ${Math.round(timeoutMs / 1000)}s`);
        throw e;
    } finally {
        clearTimeout(timer);
    }
}

async function checkVoiceServer() {
    const base = vServerBase();
    _vRtMode = base.includes('8768'); // ElevenLabs Realtime server selected
    _vElTransport = _vVoiceEngine === 'elevenlabs' && !_vRtMode; // ElevenLabs transport + real brain
    const el = document.getElementById('v-server-status');
    try {
        const r = await fetch(`${base}/health`, { signal: AbortSignal.timeout(3000) });
        const j = await r.json();
        const mediaSupported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);

        if (_vRtMode) {
            // ElevenLabs realtime: health reports realtime_configured + agent_id.
            _vVoiceReady = Boolean(j.realtime_configured && j.agent_id && mediaSupported);
            if (!_vVoiceReady) {
                const reason = !mediaSupported
                    ? 'browser mic APIs unavailable'
                    : (!j.agent_id ? 'no ElevenLabs agent_id configured' : 'realtime not configured');
                el.textContent = `⚠ ${reason}`;
                el.style.color = '#d29922';
                vSetMicEnabled(false);
                vSetRetryEnabled(false);
                vSetWaveMode('idle', 'offline');
                vSetStatus('Voice not ready: ' + reason);
                return;
            }
            el.textContent = `✅ ElevenLabs Realtime (${j.active_sessions || 0} active)`;
            el.style.color = '#3fb950';
            vSetMicEnabled(true);
            vSetWaveMode('idle', 'ready');
            vSetStatus('Ready — click mic to start/stop, or hold SPACE');
            return;
        }

        if (_vElTransport) {
            // ElevenLabs as voice transport: brain is a real server, ElevenLabs
            // does STT + TTS. Verify the brain exposes /voice/chat and the
            // ElevenLabs server (:8768) is reachable with STT+TTS.
            let hasVoiceChat = false;
            try {
                const openapiRes = await fetch(`${base}/openapi.json`, { signal: AbortSignal.timeout(3000) });
                if (openapiRes.ok) {
                    const spec = await openapiRes.json();
                    hasVoiceChat = !!spec?.paths?.['/voice/chat'];
                }
            } catch (_) { }
            let elReady = false;
            try {
                const elRes = await fetch('http://localhost:8768/health', { signal: AbortSignal.timeout(3000) });
                if (elRes.ok) {
                    const elj = await elRes.json();
                    elReady = Boolean(elj.status === 'up' && elj.realtime_configured && mediaSupported);
                }
            } catch (_) { }
            _vVoiceReady = Boolean((j.status === 'ok' || j.llm) && hasVoiceChat && elReady);
            if (!_vVoiceReady) {
                const reason = !mediaSupported
                    ? 'browser mic APIs unavailable'
                    : (!elReady ? 'ElevenLabs server (:8768) not ready' : (!hasVoiceChat ? 'brain lacks /voice/chat' : 'server not ready'));
                el.textContent = `⚠ ${reason}`;
                el.style.color = '#d29922';
                vSetMicEnabled(false);
                vSetRetryEnabled(false);
                vSetWaveMode('idle', 'offline');
                vSetStatus('Voice not ready: ' + reason);
                return;
            }
            el.textContent = `✅ ${j.status === 'ok' ? 'Kernel-Evo' : 'Brain'} + ElevenLabs voice`;
            el.style.color = '#3fb950';
            vSetMicEnabled(true);
            vSetWaveMode('idle', 'ready');
            vSetStatus('Ready — ElevenLabs voice, brain does the thinking');
            return;
        }

        let hasVoiceEndpoints = false;
        try {
            const openapiRes = await fetch(`${base}/openapi.json`, { signal: AbortSignal.timeout(3000) });
            if (openapiRes.ok) {
                const spec = await openapiRes.json();
                hasVoiceEndpoints = !!(spec?.paths?.['/transcribe'] && spec?.paths?.['/voice/chat']);
            }
        } catch (_) { }

        _vVoiceReady = Boolean((j.status === 'ok' || j.llm) && hasVoiceEndpoints && mediaSupported);

        if (!_vVoiceReady) {
            const reason = !mediaSupported
                ? 'browser mic APIs unavailable'
                : (!hasVoiceEndpoints ? 'voice endpoints not found on selected server' : 'server not ready');
            el.textContent = `⚠ ${reason}`;
            el.style.color = '#d29922';
            vSetMicEnabled(false);
            vSetRetryEnabled(false);
            vSetWaveMode('idle', 'offline');
            vSetStatus('Voice not ready: ' + reason);
            return;
        }

        const label = j.status === 'ok'
            ? `✅ Kernel-Evo (${j.skills || ''} skills, ${j.vram_free_mb || 0}MB VRAM)`
            : (j.llm ? `✅ ${j.llm} / ${j.whisper} / ${j.tts}` : '✅ OK');
        el.textContent = label;
        el.style.color = '#3fb950';
        vSetMicEnabled(true);
        vSetWaveMode('idle', 'ready');
        vSetStatus('Ready — click mic to start/stop, or hold SPACE');
    } catch (e) {
        el.textContent = '❌ unreachable';
        el.style.color = '#f85149';
        _vVoiceReady = false;
        vSetMicEnabled(false);
        vSetRetryEnabled(false);
        vSetWaveMode('idle', 'offline');
        vSetStatus('Voice server unreachable');
    }
}

function vSetStatus(s) {
    const el = document.getElementById('v-status');
    if (el) el.textContent = s;
}

function vRenderSelfTest(lines, ok) {
    const box = document.getElementById('v-selftest-results');
    if (!box) return;
    const headColor = ok ? '#3fb950' : '#f85149';
    box.style.display = '';
    box.innerHTML = [
        `<div style="margin-bottom:8px;color:${headColor};font-weight:700;">${ok ? 'Voice self-test: PASS' : 'Voice self-test: FAIL'}</div>`,
        ...lines.map(l => `<div style="margin:3px 0;">${l}</div>`)
    ].join('');
}

async function runVoiceSelfTest() {
    const base = vServerBase();
    const btn = document.getElementById('v-self-test');
    if (btn) btn.disabled = true;
    const lines = [];
    let ok = true;

    vSetStatus('Running self-test…');

    try {
        const healthRes = await fetch(`${base}/health`, { signal: AbortSignal.timeout(3500) });
        if (!healthRes.ok) throw new Error(`health ${healthRes.status}`);
        lines.push('✅ server health reachable');
    } catch (e) {
        ok = false;
        lines.push(`❌ server health failed (${e.message})`);
    }

    try {
        const openapiRes = await fetch(`${base}/openapi.json`, { signal: AbortSignal.timeout(3500) });
        if (!openapiRes.ok) throw new Error(`openapi ${openapiRes.status}`);
        const spec = await openapiRes.json();
        const hasTranscribe = !!spec?.paths?.['/transcribe'];
        const hasVoiceChat = !!spec?.paths?.['/voice/chat'];
        if (!hasTranscribe || !hasVoiceChat) throw new Error('missing /transcribe or /voice/chat');
        lines.push('✅ voice endpoints exposed (/transcribe, /voice/chat)');
    } catch (e) {
        ok = false;
        lines.push(`❌ voice endpoint probe failed (${e.message})`);
    }

    try {
        if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) throw new Error('getUserMedia unavailable');
        if (!window.MediaRecorder) throw new Error('MediaRecorder unavailable');
        lines.push('✅ browser media APIs available');
    } catch (e) {
        ok = false;
        lines.push(`❌ browser media API check failed (${e.message})`);
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        lines.push('✅ mic permission granted');

        const m = MediaRecorder;
        const mime = m.isTypeSupported && m.isTypeSupported('audio/webm;codecs=opus')
            ? 'audio/webm;codecs=opus'
            : (m.isTypeSupported && m.isTypeSupported('audio/ogg;codecs=opus') ? 'audio/ogg;codecs=opus' : undefined);
        const rec = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
        const chunks = [];
        rec.ondataavailable = e => chunks.push(e.data);
        rec.start();
        await new Promise(res => setTimeout(res, 700));
        await new Promise(res => { rec.onstop = res; rec.stop(); });
        const testBlob = new Blob(chunks, { type: mime || 'audio/webm' });
        if (testBlob.size < 256) throw new Error('recorded sample too small');
        lines.push('✅ short mic recording succeeded');

        stream.getTracks().forEach(t => t.stop());
    } catch (e) {
        ok = false;
        lines.push(`❌ mic capture test failed (${e.message})`);
    }

    vRenderSelfTest(lines, ok);
    vSetStatus(ok ? 'Self-test passed. Ready for voice chat.' : 'Self-test failed. Check diagnostics above.');
    if (btn) btn.disabled = false;
    await checkVoiceServer();
}

function vAddBubble(role, text, audioBlob, isError) {
    const thread = document.getElementById('v-thread');
    const wrap = document.createElement('div');
    wrap.style.cssText = `display:flex;flex-direction:column;align-items:${role === 'user' ? 'flex-end' : 'flex-start'};gap:3px;`;
    const bubble = document.createElement('div');
    bubble.style.cssText = `max-width:78%;padding:9px 13px;border-radius:10px;font-size:0.82em;line-height:1.5;`
        + (role === 'user'
            ? 'background:linear-gradient(180deg,#0f3460,#1a6ed8);color:#e6eef6;border-bottom-right-radius:3px;align-self:flex-end;'
            : 'background:#161b22;border:1px solid #30363d;color:#c9d1d9;border-bottom-left-radius:3px;');
    bubble.textContent = isError ? '⚠️ ' + text : text;
    wrap.appendChild(bubble);
    if (audioBlob && role !== 'user') {
        const playBtn = document.createElement('button');
        playBtn.className = 'ctrl-btn';
        playBtn.style.fontSize = '0.72em';
        playBtn.textContent = '🔊 Play';
        playBtn.onclick = () => vPlayBlob(audioBlob);
        wrap.appendChild(playBtn);
    }
    const meta = document.createElement('div');
    meta.style.cssText = 'font-size:0.65em;color:#8b949e;margin-top:2px;';
    meta.textContent = (role === 'user' ? 'You' : 'Evo') + ' · ' + new Date().toLocaleTimeString();
    wrap.appendChild(meta);
    thread.appendChild(wrap);
    thread.scrollTop = thread.scrollHeight;
}

async function vPlayBlob(blob) {
    try {
        if (_vPlaybackAudio) {
            try { _vPlaybackAudio.pause(); } catch (_) { }
            _vPlaybackAudio = null;
        }
        if (_vPlaybackUrl) {
            try { URL.revokeObjectURL(_vPlaybackUrl); } catch (_) { }
            _vPlaybackUrl = '';
        }

        _vPlaybackUrl = URL.createObjectURL(blob);
        const audio = new Audio(_vPlaybackUrl);
        audio.preload = 'auto';
        _vPlaybackAudio = audio;
        await vAttachWaveAnalyserFromAudio(audio);
        vSetWaveMode('speaking', 'speaking');

        const playPromise = audio.play();
        if (playPromise && typeof playPromise.then === 'function') await playPromise;
        await new Promise((resolve, reject) => {
            audio.onended = () => resolve();
            audio.onerror = () => reject(new Error('Audio playback failed'));
        });
        vSetWaveMode('idle', 'ready');
    } catch (e) {
        console.warn('vPlayBlob', e);
        vSetWaveMode('idle', 'ready');
        throw e;
    }
}

async function vRetryLastTts(opts = {}) {
    const { auto = false, silentSuccess = false } = opts;
    if (_vProcessing) return;
    if (!_vLastAssistantText.trim()) {
        vSetStatus('No previous assistant reply available for voice generation');
        return;
    }

    _vProcessing = true;
    const base = vServerBase();
    vSetWaveMode('cloning', 'cloning');
    vSetStatus(auto ? 'Generating voice clone in background…' : 'Generating voice clone for last reply…');
    try {
        const rr = await vFetchWithTimeout(`${base}/voice/tts`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text: _vLastAssistantText, session_id: _vSessionId })
        }, 210000);
        if (!rr.ok) {
            let msg = `TTS ${rr.status}`;
            try {
                const ej = await rr.json();
                if (ej?.error) msg = ej.error;
            } catch (_) { }
            throw new Error(msg);
        }

        const ctype = rr.headers.get('Content-Type') || '';
        if (ctype.includes('audio')) {
            const buf = await rr.arrayBuffer();
            const blob = new Blob([buf], { type: ctype.split(';')[0] || 'audio/wav' });
            vSetStatus('Speaking cloned voice…');
            await vPlayBlob(blob);
            _vLastTtsError = '';
            vSetRetryEnabled(false);
            if (!silentSuccess) {
                vSetStatus('Ready — click mic to start/stop, or hold SPACE');
            } else {
                vSetStatus('Voice ready and playing');
            }
            _vAutoTtsAttempts = 0;
        } else {
            const j = await rr.json();
            _vLastTtsError = j.tts_error || 'voice generation unavailable';
            vSetRetryEnabled(true);
            vSetWaveMode('idle', 'ready');
            vSetStatus(auto
                ? `Voice clone still unavailable (${_vLastTtsError}). You can retry manually.`
                : `Voice clone still unavailable (${_vLastTtsError}).`);
        }
    } catch (e) {
        vSetWaveMode('idle', 'ready');
        vSetStatus(`Voice generation failed: ${e.message}`);
        if (!auto) {
            vAddBubble('assistant', `Voice generation failed: ${e.message}`, null, true);
        }
    }
    _vProcessing = false;
}

function vEncodeWavFromFloat32(samples, sampleRate = 16000) {
    const bytesPerSample = 2;
    const blockAlign = bytesPerSample;
    const byteRate = sampleRate * blockAlign;
    const dataSize = samples.length * bytesPerSample;
    const buffer = new ArrayBuffer(44 + dataSize);
    const view = new DataView(buffer);

    const writeStr = (offset, str) => {
        for (let i = 0; i < str.length; i++) view.setUint8(offset + i, str.charCodeAt(i));
    };

    writeStr(0, 'RIFF');
    view.setUint32(4, 36 + dataSize, true);
    writeStr(8, 'WAVE');
    writeStr(12, 'fmt ');
    view.setUint32(16, 16, true); // PCM chunk size
    view.setUint16(20, 1, true);  // PCM format
    view.setUint16(22, 1, true);  // mono
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, byteRate, true);
    view.setUint16(32, blockAlign, true);
    view.setUint16(34, 16, true); // 16-bit
    writeStr(36, 'data');
    view.setUint32(40, dataSize, true);

    let offset = 44;
    for (let i = 0; i < samples.length; i++, offset += 2) {
        const s = Math.max(-1, Math.min(1, samples[i]));
        const v = s < 0 ? s * 0x8000 : s * 0x7fff;
        view.setInt16(offset, v, true);
    }

    return new Blob([buffer], { type: 'audio/wav' });
}

async function vNormalizeBlobToWav(blob) {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    const ctx = new Ctx();
    try {
        const arr = await blob.arrayBuffer();
        const decoded = await ctx.decodeAudioData(arr.slice(0));
        const inData = decoded.getChannelData(0);
        const inRate = decoded.sampleRate || 16000;
        const targetRate = 16000;

        // Light linear resample to 16k mono for stable STT input.
        const ratio = inRate / targetRate;
        const outLen = Math.max(1, Math.floor(inData.length / ratio));
        const out = new Float32Array(outLen);
        for (let i = 0; i < outLen; i++) {
            const src = i * ratio;
            const i0 = Math.floor(src);
            const i1 = Math.min(i0 + 1, inData.length - 1);
            const frac = src - i0;
            out[i] = inData[i0] + (inData[i1] - inData[i0]) * frac;
        }
        return vEncodeWavFromFloat32(out, targetRate);
    } finally {
        try { await ctx.close(); } catch (_) { }
    }
}

// ── ElevenLabs Realtime mode (server :8768) ──────────────────────────────
// Encodes Float32 samples into raw 16-bit PCM mono bytes (no WAV header) at
// the given sample rate — matches the server's SAMPLE_RATE=44100 expectation.
function vEncodePcmFromFloat32(samples, sampleRate = 44100) {
    const bytes = new Int16Array(samples.length);
    for (let i = 0; i < samples.length; i++) {
        const s = Math.max(-1, Math.min(1, samples[i]));
        bytes[i] = s < 0 ? s * 0x8000 : s * 0x7fff;
    }
    return new Uint8Array(bytes.buffer);
}

// Decodes a recorded blob and resamples to the target rate, returning raw PCM.
async function vNormalizeBlobToPcm(blob, targetRate = 44100) {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    const ctx = new Ctx();
    try {
        const arr = await blob.arrayBuffer();
        const decoded = await ctx.decodeAudioData(arr.slice(0));
        const inData = decoded.getChannelData(0);
        const inRate = decoded.sampleRate || 44100;
        const ratio = inRate / targetRate;
        const outLen = Math.max(1, Math.floor(inData.length / ratio));
        const out = new Float32Array(outLen);
        for (let i = 0; i < outLen; i++) {
            const src = i * ratio;
            const i0 = Math.floor(src);
            const i1 = Math.min(i0 + 1, inData.length - 1);
            const frac = src - i0;
            out[i] = inData[i0] + (inData[i1] - inData[i0]) * frac;
        }
        return vEncodePcmFromFloat32(out, targetRate);
    } finally {
        try { await ctx.close(); } catch (_) { }
    }
}

function vRtB64(pcmBytes) {
    let binary = '';
    const chunk = 0x8000;
    for (let i = 0; i < pcmBytes.length; i += chunk) {
        binary += String.fromCharCode.apply(null, pcmBytes.subarray(i, i + chunk));
    }
    return btoa(binary);
}

async function vRtStart() {
    const base = vServerBase();
    const r = await vFetchWithTimeout(`${base}/rt/start`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    }, 15000);
    if (!r.ok) throw new Error(`rt/start ${r.status}`);
    const j = await r.json();
    _vRtSessionId = j.session_id;
    return _vRtSessionId;
}

async function vRtSendAudio(pcmBytes) {
    if (!_vRtSessionId) return;
    const base = vServerBase();
    await vFetchWithTimeout(`${base}/rt/audio`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: _vRtSessionId, pcm_b64: vRtB64(pcmBytes) })
    }, 20000);
}

async function vRtPoll() {
    if (!_vRtSessionId) return;
    const base = vServerBase();
    try {
        const r = await fetch(`${base}/rt/receive?session_id=${encodeURIComponent(_vRtSessionId)}`, { signal: AbortSignal.timeout(8000) });
        if (!r.ok) return;
        const j = await r.json();
        if (j.audio_b64) {
            const bin = atob(j.audio_b64);
            const pcm = new Uint8Array(bin.length);
            for (let i = 0; i < bin.length; i++) pcm[i] = bin.charCodeAt(i);
            // Server returns raw 16-bit signed PCM mono (little-endian).
            const samples = new Float32Array(Math.floor(pcm.length / 2));
            for (let i = 0, k = 0; i + 1 < pcm.length; i += 2, k++) {
                const lo = pcm[i], hi = pcm[i + 1];
                let s16 = (hi << 8) | lo;
                if (s16 & 0x8000) s16 = s16 - 0x10000; // sign extend
                samples[k] = s16 / 0x8000;
            }
            const wav = vEncodeWavFromFloat32(samples, 44100);
            vSetWaveMode('speaking', 'speaking');
            vSetStatus('Speaking…');
            await vPlayBlob(wav);
            vSetWaveMode('idle', 'ready');
        }
        if (j.transcripts && j.transcripts.length) {
            j.transcripts.forEach(t => { if (t) vAddBubble('assistant', t, null, false); });
        }
        if (j.done) vRtStopPolling();
    } catch (_) { }
}

function vRtStartPolling() {
    if (_vRtPollTimer) return;
    _vRtPollTimer = setInterval(() => vRtPoll(), 1200);
}

function vRtStopPolling() {
    if (_vRtPollTimer) { clearInterval(_vRtPollTimer); _vRtPollTimer = null; }
}

async function vRtStop() {
    vRtStopPolling();
    if (_vRtSessionId) {
        const base = vServerBase();
        try { await vFetchWithTimeout(`${base}/rt/end`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: _vRtSessionId })
        }, 10000);
        } catch (_) { }
    }
    _vRtSessionId = null;
}

// ── ElevenLabs as voice transport (STT + TTS) with a real brain ───────────
// Wraps raw 16-bit PCM mono bytes (little-endian) into a WAV blob for playback.
function vWavFromPcm(pcm, sampleRate = 44100) {
    const samples = new Float32Array(Math.floor(pcm.length / 2));
    for (let i = 0, k = 0; i + 1 < pcm.length; i += 2, k++) {
        const lo = pcm[i], hi = pcm[i + 1];
        let s16 = (hi << 8) | lo;
        if (s16 & 0x8000) s16 = s16 - 0x10000; // sign extend
        samples[k] = s16 / 0x8000;
    }
    return vEncodeWavFromFloat32(samples, sampleRate);
}

// Runs the full ElevenLabs-transport voice exchange: STT on :8768 -> brain
// /voice/chat -> ElevenLabs TTS on :8768 -> play. Called from vStopRecording
// when _vElTransport is true.
async function vElTransportProcess(rawBlob, blobType) {
    _vProcessing = true;
    const brain = vServerBase();
    const el = 'http://localhost:8768';
    try {
        // Step 1: STT via ElevenLabs (:8768 /stt).
        let uploadBlob = rawBlob;
        let uploadExt = blobType.includes('ogg') ? 'ogg' : 'webm';
        try {
            const wavBlob = await vNormalizeBlobToWav(rawBlob);
            if (wavBlob && wavBlob.size > 256) {
                uploadBlob = wavBlob;
                uploadExt = 'wav';
            }
        } catch (_) { }
        const fd = new FormData();
        fd.append('file', uploadBlob, `rec.${uploadExt}`);
        const stt = await vFetchWithTimeout(`${el}/stt`, { method: 'POST', body: fd }, 45000);
        if (!stt.ok) {
            let msg = `STT ${stt.status}`;
            try {
                const ej = await stt.json();
                if (ej?.detail) msg = ej.detail;
            } catch (_) { }
            throw new Error(msg);
        }
        const sj = await stt.json();
        const userText = (sj.text || '').trim();
        if (!userText) {
            vSetWaveMode('idle', 'ready');
            vSetStatus('Nothing heard — try again');
            return;
        }
        vAddBubble('user', userText, null, false);
        vSetWaveMode('thinking', 'thinking');
        vSetStatus('Thinking…');

        // Step 2: brain thinks via /voice/chat.
        const cr = await vFetchWithTimeout(`${brain}/voice/chat`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text: userText, session_id: _vSessionId })
        }, 210000);
        if (!cr.ok) {
            let msg = `Chat ${cr.status}`;
            try {
                const ej = await cr.json();
                if (ej?.error) msg = ej.error;
            } catch (_) { }
            throw new Error(msg);
        }
        const ctype = cr.headers.get('Content-Type') || '';
        let replyText = cr.headers.get('X-Assistant-Reply') || '';
        if (!replyText && !ctype.includes('audio')) {
            const j = await cr.json();
            replyText = j.reply || j.error || '(no reply)';
        }
        _vLastAssistantText = replyText;
        vAddBubble('assistant', replyText || '(audio reply)', null, false);

        // Step 3: synthesize the reply with ElevenLabs TTS (:8768 /tts).
        if (replyText.trim()) {
            vSetWaveMode('cloning', 'cloning');
            vSetStatus('Speaking…');
            const tr = await vFetchWithTimeout(`${el}/tts`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: replyText })
            }, 60000);
            if (tr.ok) {
                const buf = await tr.arrayBuffer();
                const pcm = new Uint8Array(buf);
                if (pcm.length > 44) {
                    const wav = vWavFromPcm(pcm, 44100);
                    await vPlayBlob(wav);
                    _vLastTtsError = '';
                    vSetRetryEnabled(false);
                } else {
                    _vLastTtsError = 'ElevenLabs TTS produced no audio';
                    vSetRetryEnabled(true);
                }
            } else {
                _vLastTtsError = `TTS ${tr.status}`;
                vSetRetryEnabled(true);
            }
        }
        vSetWaveMode('idle', 'ready');
        if (!_vLastTtsError) {
            vSetStatus('Ready — click mic to start/stop, or hold SPACE');
        } else {
            vSetStatus(`Text reply ready. Voice delayed (${_vLastTtsError}).`);
        }
    } catch (e) {
        vSetWaveMode('idle', 'ready');
        vAddBubble('assistant', e.message, null, true);
        vSetStatus('Error');
    } finally {
        _vProcessing = false;
    }
}

async function vStartRecording() {
    if (_vProcessing || _vIsRecording) return;
    vClearAutoTtsRetry();
    if (!_vVoiceReady) {
        vSetStatus('Voice not ready. Check selected server and mic permissions.');
        return;
    }
    vSetStatus('Listening…');
    vSetWaveMode('listening', 'listening');
    document.getElementById('v-mic-btn').style.background = 'linear-gradient(180deg,#c0392b,#922b21)';
    try {
        if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) || !window.MediaRecorder) {
            throw new Error('MediaRecorder/getUserMedia not supported in this browser');
        }
        if (!_vMediaStream) _vMediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        await vAttachWaveAnalyserFromStream(_vMediaStream);

        if (_vRtMode) {
            // ElevenLabs realtime: open a session, then stream segments up.
            _vRtSessionId = await vRtStart();
            vRtStartPolling();
        }

        const m = MediaRecorder;
        const mime = m.isTypeSupported && m.isTypeSupported('audio/webm;codecs=opus')
            ? 'audio/webm;codecs=opus'
            : (m.isTypeSupported && m.isTypeSupported('audio/ogg;codecs=opus') ? 'audio/ogg;codecs=opus' : undefined);
        _vRecordMime = mime || 'audio/webm';
        _vRecorder = mime ? new MediaRecorder(_vMediaStream, { mimeType: mime }) : new MediaRecorder(_vMediaStream);
        _vChunks = [];
        _vRecorder.ondataavailable = e => _vChunks.push(e.data);
        _vRecordStartedAt = Date.now();
        _vIsRecording = true;
        _vRecorder.start();

        if (_vRtMode) {
            // Stream short segments to the agent while recording.
            _vRtChunkTimer = setInterval(async () => {
                if (!_vIsRecording || _vChunks.length === 0) return;
                const seg = new Blob(_vChunks, { type: _vRecordMime });
                _vChunks = [];
                try {
                    const pcm = await vNormalizeBlobToPcm(seg, 44100);
                    if (pcm && pcm.length) await vRtSendAudio(pcm);
                } catch (_) { }
            }, 1500);
        }

        _vRecordTimeout = setTimeout(() => vStopRecording(), 30000);
    } catch (e) {
        vSetWaveMode('idle', 'ready');
        vSetStatus('Mic error: ' + e.message);
        document.getElementById('v-mic-btn').style.background = 'linear-gradient(180deg,#1a6ed8,#0b4fa8)';
        _vIsRecording = false;
    }
}

async function vStopRecording() {
    if (!_vRecorder || !_vIsRecording) return;
    clearTimeout(_vRecordTimeout);
    _vIsRecording = false;
    document.getElementById('v-mic-btn').style.background = 'linear-gradient(180deg,#1a6ed8,#0b4fa8)';

    if (_vRtMode) {
        // ElevenLabs realtime: flush remaining mic segments, then end the session.
        if (_vRtChunkTimer) { clearInterval(_vRtChunkTimer); _vRtChunkTimer = null; }
        vSetWaveMode('thinking', 'transcribing');
        vSetStatus('Finishing…');
        _vRecorder.onstop = async () => {
            const blobType = _vRecordMime || (_vRecorder && _vRecorder.mimeType) || 'audio/webm';
            const rawBlob = new Blob(_vChunks, { type: blobType });
            _vChunks = [];
            try {
                if (rawBlob.size >= 256) {
                    const pcm = await vNormalizeBlobToPcm(rawBlob, 44100);
                    if (pcm && pcm.length) await vRtSendAudio(pcm);
                }
                // Give the agent a moment to finish, then end + stop polling.
                setTimeout(async () => { await vRtStop(); }, 1500);
            } catch (_) {
                await vRtStop();
            }
        };
        _vRecorder.stop();
        return;
    }

    if (_vElTransport) {
        // ElevenLabs as voice transport (STT + TTS) with a real brain.
        // Feed the captured blob into the ElevenLabs transport exchange.
        vSetWaveMode('thinking', 'transcribing');
        vSetStatus('Transcribing…');
        _vRecorder.onstop = async () => {
            const durationMs = Date.now() - _vRecordStartedAt;
            if (durationMs < 300) {
                _vChunks = [];
                vSetWaveMode('idle', 'ready');
                vSetStatus('Recording too short — hold a bit longer');
                return;
            }
            const blobType = _vRecordMime || (_vRecorder && _vRecorder.mimeType) || 'audio/webm';
            const rawBlob = new Blob(_vChunks, { type: blobType });
            _vChunks = [];
            if (rawBlob.size < 256) {
                vSetWaveMode('idle', 'ready');
                vSetStatus('No valid audio captured — try again');
                return;
            }
            await vElTransportProcess(rawBlob, blobType);
        };
        _vRecorder.stop();
        return;
    }

    vSetWaveMode('thinking', 'transcribing');
    vSetStatus('Transcribing…');
    _vRecorder.onstop = async () => {
        const durationMs = Date.now() - _vRecordStartedAt;
        if (durationMs < 300) {
            _vChunks = [];
            vSetWaveMode('idle', 'ready');
            vSetStatus('Recording too short — hold a bit longer');
            return;
        }
        const blobType = _vRecordMime || (_vRecorder && _vRecorder.mimeType) || 'audio/webm';
        const rawBlob = new Blob(_vChunks, { type: blobType });
        _vChunks = [];
        if (rawBlob.size < 256) {
            vSetWaveMode('idle', 'ready');
            vSetStatus('No valid audio captured — try again');
            return;
        }

        let uploadBlob = rawBlob;
        let uploadExt = blobType.includes('ogg') ? 'ogg' : 'webm';
        try {
            const wavBlob = await vNormalizeBlobToWav(rawBlob);
            if (wavBlob && wavBlob.size > 256) {
                uploadBlob = wavBlob;
                uploadExt = 'wav';
            }
        } catch (_) {
            // Keep original blob if browser-side decode/transcode fails.
        }

        _vProcessing = true;
        const base = vServerBase();
        try {
            // Step 1: transcribe
            const fd = new FormData();
            fd.append('file', uploadBlob, `rec.${uploadExt}`);
            const tr = await vFetchWithTimeout(`${base}/transcribe`, { method: 'POST', body: fd }, 45000);
            if (!tr.ok) {
                let msg = `Transcribe ${tr.status}`;
                try {
                    const ej = await tr.json();
                    if (ej?.error) msg = ej.error;
                } catch (_) { }
                throw new Error(msg);
            }
            const tj = await tr.json();
            const userText = tj.text || '';
            if (!userText.trim()) {
                vSetWaveMode('idle', 'ready');
                vSetStatus('Nothing heard — try again');
                _vProcessing = false;
                return;
            }
            vAddBubble('user', userText, null, false);
            vSetWaveMode('thinking', 'thinking');
            vSetStatus('Thinking…');
            // Step 2: chat + optional TTS clone
            const cr = await vFetchWithTimeout(`${base}/voice/chat`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: userText, session_id: _vSessionId })
            }, 210000);
            if (!cr.ok) {
                let msg = `Chat ${cr.status}`;
                try {
                    const ej = await cr.json();
                    if (ej?.error) msg = ej.error;
                } catch (_) { }
                throw new Error(msg);
            }
            // kernel-evo returns WAV + X-Assistant-Reply header, or JSON fallback if TTS unavailable
            const contentType = cr.headers.get('Content-Type') || '';
            let replyText = cr.headers.get('X-Assistant-Reply') || '';
            let audioBlob = null;
            if (contentType.includes('audio')) {
                const audioBuf = await cr.arrayBuffer();
                audioBlob = audioBuf.byteLength > 100
                    ? new Blob([audioBuf], { type: contentType.split(';')[0] || 'audio/wav' })
                    : null;
                _vLastAssistantText = replyText || '';
                _vLastTtsError = '';
                _vAutoTtsAttempts = 0;
                vSetRetryEnabled(false);
            } else {
                const j = await cr.json();
                replyText = j.reply || j.error || '(no reply)';
                _vLastAssistantText = replyText;
                _vLastTtsError = j.tts_error || '';
                _vAutoTtsAttempts = 0;
                vSetRetryEnabled(Boolean(_vLastAssistantText));
            }
            vAddBubble('assistant', replyText || '(audio reply)', audioBlob, false);
            if (audioBlob) {
                vSetWaveMode('speaking', 'speaking');
                vSetStatus('Speaking…');
                await vPlayBlob(audioBlob);
                vSetWaveMode('idle', 'ready');
            } else if (_vLastTtsError) {
                vSetWaveMode('idle', 'ready');
                vSetStatus(`Text reply ready. Voice clone delayed (${_vLastTtsError}). Auto-retrying now…`);
                vScheduleAutoTtsRetry(3800);
            } else {
                vSetWaveMode('idle', 'ready');
            }
            if (!_vLastTtsError) {
                vSetStatus('Ready — click mic to start/stop, or hold SPACE');
            }
        } catch (e) {
            vSetWaveMode('idle', 'ready');
            vAddBubble('assistant', e.message, null, true);
            vSetStatus('Error');
        }
        _vProcessing = false;
    };
    _vRecorder.stop();
}

// ── Desktop SPA: re-init on first load and wire:navigate ──
function initCurrentPage() {
    if (document.getElementById('graph-panel')) {
        if (typeof updateStats === 'function') updateStats(STATS, GAPS);
        if (typeof renderTaskOutcomes === 'function') renderTaskOutcomes(EVENTS);
        if (typeof renderLog === 'function') renderLog(EVENTS);
        if (typeof renderGaps === 'function') renderGaps(GAPS);
        if (typeof scheduleGraphRebuild === 'function') scheduleGraphRebuild();
        const ti = document.getElementById('task-input');
        if (ti) ti.addEventListener('keydown', ev => { if (ev.key === 'Enter') { ev.preventDefault(); triggerEvolution(); } });
        if (document.getElementById('to-direct')) {
            setInterval(fullRefresh, 10000);
            fullRefresh();
        }
    }
    if (document.getElementById('timeline-svg') && !document.getElementById('graph-panel')) {
        if (typeof fullRefresh === 'function') fullRefresh();
    }
    if (document.getElementById('agent-thread') && typeof initAgentTab === 'function') initAgentTab();
    if (document.getElementById('skill-list')) { loadSkills(); loadRoutines(); }
    if (document.getElementById('rep-list')) loadReplicas();
    if (document.getElementById('traj-list')) loadTrajectories();
    if (document.getElementById('ins-stats')) loadInsights();
    if (document.getElementById('sys-backups')) loadSystemData();
    if (document.getElementById('mem-file-tree')) { loadMemoryStats(); loadWorkspaceTree('/'); }
    if (document.getElementById('v-mic-btn')) initVoiceTab();
}

document.addEventListener('DOMContentLoaded', initCurrentPage);
document.addEventListener('livewire:navigated', initCurrentPage);

// Expose functions for inline onclick/ondblclick
const _exports = {
    _memClickFile, _memStartRename, _memShowCtxMenu, _memCtxOpen, _memCtxRename, _memCtxDelete, _memToggleTree,
    filterMemoryTree, promptNewMemoryFile, toggleMemoryEdit, cancelMemoryEdit, saveCurrentMemoryFile, deleteCurrentMemoryFile,
    sqliteSelectTable, sqliteAddRow, sqliteEditRow, sqliteSaveRow, sqliteSaveNewRow, sqliteCancelEdit, sqliteDeleteRow,
    sqliteClearTable, sqliteClearAllTables, sqliteRefresh, sqliteClose, sqliteNextPage, sqlitePrevPage,
    ctrlAction, triggerEvolution, toggleGraphLayer, createBackup, runInit,
    spawnReplica, stopReplica, launchPipeline, toggleTraj,
    applyAgentProviderControls,
    initCurrentPage, kernelAgentAction,
};
Object.assign(window, _exports);
