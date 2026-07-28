/* ═══════════════════════════════════════════════════════════════
   Kernel Desktop — Evolution Dashboard JavaScript
   Extracted from kernel-evolving/src/views/evolution_dashboard.html
   All API calls proxy to the local kernel-evolving agent (port 8779).
   ═══════════════════════════════════════════════════════════════ */

// Kernel-evolving API base URL (set by setup wizard, defaults to localhost:8779)
window.KERNEL_API_BASE = window.KERNEL_API_BASE || 'http://localhost:8779';

// Build a full API URL from a relative path
function kapi(path) {
    if (!path) return KERNEL_API_BASE;
    if (path.startsWith('http')) return path;
    return KERNEL_API_BASE + (path.startsWith('/') ? path : '/' + path);
}

// ── Data state (with fallback defaults for dev/Live Preview) ──
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

// ── Session helpers ──
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
    try { localStorage.setItem(AGENT_SESSION_STORAGE_KEY, value); }
    catch (_) { window.__agentSessionId = value; }
}

function setAgentSelectedPromptId(value) {
    try {
        if (value) localStorage.setItem(AGENT_SELECTED_PROMPT_KEY, value);
        else localStorage.removeItem(AGENT_SELECTED_PROMPT_KEY);
    } catch (_) { }
}

function getAgentSelectedPromptId() {
    try { return localStorage.getItem(AGENT_SELECTED_PROMPT_KEY) || ''; }
    catch (_) { return ''; }
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
        _agentProviderSnapshot = { ...routingResp, available: availabilityResp || {} };

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
    if (model) body.model_override = { [callType]: model };

    setAgentProviderMeta('Applying provider/model settings…');
    try {
        const res = await fetch(kapi('/provider/set'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || `HTTP ${res.status}`);
        const actions = Array.isArray(data.vram_actions) && data.vram_actions.length
            ? ` · ${data.vram_actions.join(' | ')}` : '';
        setAgentProviderMeta(`Updated ${callType} -> ${provider}${model ? ` / ${model}` : ''}${actions}`);
        await loadAgentProviderControls();
    } catch (e) {
        setAgentProviderMeta(`Provider update failed: ${e.message}`, true);
    }
}

function agentEscapeText(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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
    if (!history.length) { agentAppendBubble('assistant', 'No persisted conversation yet.'); return; }
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
    } catch (_) { }
}

function startAgentLiveThread() {
    if (_agentLiveThreadTimer) return;
    syncAgentLiveThread();
    _agentLiveThreadTimer = setInterval(syncAgentLiveThread, 2000);
}

function stopAgentLiveThread() {
    if (_agentLiveThreadTimer) { clearInterval(_agentLiveThreadTimer); _agentLiveThreadTimer = null; }
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
    if (sheet) { sheet.classList.toggle('open', isOpen); sheet.setAttribute('aria-hidden', isOpen ? 'false' : 'true'); }
    if (backdrop) backdrop.classList.toggle('open', isOpen);
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function setAgentProviderSheetOpen(open) {
    const sheet = document.getElementById('agent-provider-sheet');
    const backdrop = document.getElementById('agent-provider-sheet-backdrop');
    const toggleBtn = document.getElementById('agent-provider-menu-toggle');
    const isOpen = Boolean(open);
    if (sheet) { sheet.classList.toggle('open', isOpen); sheet.setAttribute('aria-hidden', isOpen ? 'false' : 'true'); }
    if (backdrop) backdrop.classList.toggle('open', isOpen);
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function agentBuildModelTurns(payload) {
    const turns = [];
    const base = Array.isArray(payload?.model_messages) ? payload.model_messages
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
            ? '<span style="margin-left:6px;font-size:0.74em;color:#58a6ff;">current input</span>' : '';
        return `<div class="agent-history-turn"><div class="agent-history-role"><span>${role}${sourceBadge}</span><span>#${idx + 1}</span></div><div class="agent-history-content">${content}</div></div>`;
    }).join('');
}

function renderAgentTrace(steps) {
    const el = document.getElementById('agent-trace');
    if (!el) return;
    const trace = Array.isArray(steps) ? steps : [];
    if (!trace.length) { el.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No tool calls captured yet.</div>'; return; }
    el.innerHTML = trace.map((step, idx) => {
        const argsText = typeof step.args === 'string' ? step.args : JSON.stringify(step.args || {}, null, 2);
        const resultText = typeof step.result === 'string' ? step.result : JSON.stringify(step.result ?? '', null, 2);
        return `<div class="agent-trace-step"><div class="agent-trace-head"><span>${agentEscapeText(step.tool || 'tool')}</span><span>Step ${idx + 1}</span></div><div class="agent-trace-body"><strong>Arguments</strong>\n${agentEscapeText(argsText)}</div><div class="agent-trace-result"><strong>Output</strong>\n${agentEscapeText(resultText)}</div></div>`;
    }).join('');
}

function renderAgentTrajectories(items) {
    const el = document.getElementById('agent-trajectories');
    if (!el) return;
    const rows = Array.isArray(items) ? items : [];
    if (!rows.length) { el.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No recent trajectories found.</div>'; return; }
    el.innerHTML = rows.map(row => {
        const toolCount = Array.isArray(row.tool_calls) ? row.tool_calls.length : 0;
        const artifactsCount = Array.isArray(row.artifacts) ? row.artifacts.length : 0;
        const score = row.critic_score == null ? 'n/a' : Number(row.critic_score).toFixed(2);
        const toolCalls = Array.isArray(row.tool_calls) ? row.tool_calls.map((call, idx) => {
            const argsText = typeof call.args === 'string' ? call.args : JSON.stringify(call.args || {}, null, 2);
            return `<div style="margin-top:8px;padding-top:8px;border-top:1px solid #21262d;"><div style="color:#58a6ff;font-weight:700;">${agentEscapeText(call.tool || call.name || `tool ${idx + 1}`)}</div><div style="color:#8b949e;white-space:pre-wrap;">${agentEscapeText(argsText)}</div><div style="margin-top:4px;color:#c9d1d9;white-space:pre-wrap;">${agentEscapeText(call.result || '')}</div></div>`;
        }).join('') : '';
        return `<details class="agent-trajectory-item"><summary class="agent-trajectory-head"><span>${agentEscapeText(row.task || 'task')}</span><span>${toolCount} tools · ${artifactsCount} artifacts · score ${score}</span></summary><div class="agent-trajectory-body"><div style="margin-top:8px;color:#8b949e;">Provider: ${agentEscapeText(row.provider || '')}</div><div style="margin-top:8px;color:#c9d1d9;white-space:pre-wrap;">${agentEscapeText(row.final_reply || '')}</div>${toolCalls}</div></details>`;
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
    if (!entries.length) { select.innerHTML = '<option value="">No prompt logs found</option>'; return; }
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
        const data = await fetchJsonWithTimeout(kapi(`/debug/prompt-logs?chat_id=${encodeURIComponent(sessionId)}&limit=20`), 7000);
        _agentLogs = data.logs || [];
        renderAgentPromptSelector(_agentLogs);
        if (_agentLogs.length) await loadAgentPromptLog(_agentLogs[0].id);
        else await loadAgentPromptPreview();
    } catch (e) {
        if (summary) summary.textContent = `Failed to load prompt logs: ${e.message}`;
    }
}

async function loadAgentPromptPreview() {
    const sessionId = agentSessionId();
    const summary = document.getElementById('agent-inspector-summary');
    try {
        const data = await fetchJsonWithTimeout(kapi(`/debug/current-prompt?chat_id=${encodeURIComponent(sessionId)}`), 7000);
        _agentCurrentPrompt = data;
        document.getElementById('agent-system-prompt').textContent = data.prompt || 'No prompt available.';
        const modelTurns = agentBuildModelTurns(data);
        renderAgentHistory(modelTurns, { source: data.source || 'live-preview', chatId: data.chat_id || sessionId, persistedTurns: data.persisted_turns || modelTurns.length });
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
        const data = await fetchJsonWithTimeout(kapi(`/debug/prompt-log/${encodeURIComponent(entryId)}`), 7000);
        _agentCurrentPrompt = data;
        document.getElementById('agent-system-prompt').textContent = data.prompt || 'No prompt available.';
        const modelTurns = agentBuildModelTurns(data);
        renderAgentHistory(modelTurns, { source: data.source || 'prompt-log', chatId: data.chat_id || agentSessionId(), persistedTurns: modelTurns.length });
        const summary = document.getElementById('agent-inspector-summary');
        if (summary) summary.textContent = `#${data.id} · ${data.ts || 'unknown'} · ${data.provider || 'unknown provider'} · ${data.model || 'unknown model'} · ${data.prompt_len || 0} chars · ${modelTurns.length} model turns`;
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

async function agentSend(text) {
    const payload = (text || document.getElementById('agent-input')?.value || '').trim();
    if (!payload || _agentBusy) return;
    _agentBusy = true;
    const input = document.getElementById('agent-input');
    if (input) input.value = '';
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
        let buffer = '', replyText = '', replyButtons = [];

        const flushLine = async (line) => {
            if (!line) return;
            let event = null;
            try { event = JSON.parse(line); } catch (_) { return; }
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
                .filter(Boolean).join(' | ');
            if (buttonsText) {
                const btn = document.createElement('div');
                btn.className = 'agent-bubble-meta';
                btn.textContent = `Buttons: ${buttonsText}`;
                assistantNode.appendChild(btn);
            }
        }

        if (payload.toLowerCase().startsWith('/new')) {
            resetAgentUi();
            agentAppendBubble('assistant', 'Session cleared. Start a new conversation when ready.');
            agentSetStatus('Session cleared');
            _agentBusy = false;
            return;
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

async function agentNewSession() { return agentSend('/new'); }

function initAgentTab() {
    if (_agentInitDone) return;
    _agentInitDone = true;
    const sessionId = agentSessionId();
    const sessionEl = document.getElementById('agent-session-id');
    if (sessionEl) sessionEl.textContent = sessionId;

    const input = document.getElementById('agent-input');
    const sendBtn = document.getElementById('agent-send-btn');
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
        if (input) { input.value = command; input.focus(); }
        setAgentCommandMenuOpen(false);
        agentSend(command);
    });

    commandMenuToggleBtn?.addEventListener('click', () => {
        const sheet = document.getElementById('agent-command-sheet');
        setAgentCommandMenuOpen(!sheet?.classList.contains('open'));
    });
    commandMenuCloseBtn?.addEventListener('click', () => setAgentCommandMenuOpen(false));
    commandMenuBackdrop?.addEventListener('click', () => setAgentCommandMenuOpen(false));
    providerMenuToggleBtn?.addEventListener('click', () => {
        const sheet = document.getElementById('agent-provider-sheet');
        setAgentProviderSheetOpen(!sheet?.classList.contains('open'));
    });
    providerSheetCloseBtn?.addEventListener('click', () => setAgentProviderSheetOpen(false));
    providerSheetBackdrop?.addEventListener('click', () => setAgentProviderSheetOpen(false));
    sendBtn?.addEventListener('click', () => agentSend());
    resetBtn?.addEventListener('click', () => agentNewSession());
    refreshBtn?.addEventListener('click', () => refreshAgentInspector());
    providerApplyBtn?.addEventListener('click', () => applyAgentProviderControls());
    providerRefreshBtn?.addEventListener('click', () => loadAgentProviderControls());
    callTypeSelect?.addEventListener('change', (ev) => applyAgentCallTypeSelection(ev.target.value));
    inspectorToggleBtn?.addEventListener('click', () => {
        const inspector = document.getElementById('agent-inspector');
        setAgentInspectorOpen(!inspector?.classList.contains('open'));
    });
    inspectorCloseBtn?.addEventListener('click', () => setAgentInspectorOpen(false));
    inspectorBackdrop?.addEventListener('click', () => setAgentInspectorOpen(false));
    input?.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') { ev.preventDefault(); agentSend(); }
    });
    window.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') { setAgentCommandMenuOpen(false); setAgentProviderSheetOpen(false); setAgentInspectorOpen(false); }
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

// ── Stat cards ──
function updateStats(stats, gapsArr) {
    if (!stats) return;
    const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    setText('s-total', stats.total_events ?? stats.total ?? 0);
    setText('s-resolved', stats.resolved ?? 0);
    setText('s-synth', stats.synthesised ?? 0);
    const gaps = gapsArr ?? [];
    setText('s-gaps', stats.unresolved_gaps ?? gaps.length);
    const prov = (stats.providers_used || stats.providers || []).filter(Boolean);
    setText('s-provider', prov.length ? prov.join(', ') : '—');
}

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
    const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    setText('to-direct', direct);
    setText('to-esc-ok', escOk);
    setText('to-esc-open', escOpen);

    const list = document.getElementById('task-outcome-list');
    if (!list) return;
    const recent = evoEvents.slice(0, 18);
    if (!recent.length) { list.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No evolution outcomes yet.</div>'; return; }
    list.innerHTML = recent.map(e => {
        const cls = classifyOutcome(e);
        const st = cls === 'direct_done' ? ['Direct done', '#3fb950']
            : cls === 'escalated_done' ? ['Escalated done', '#bc8cff']
            : cls === 'escalated_open' ? ['Escalated open', '#f85149']
            : ['Open gap', '#f85149'];
        const ts = fmtTs(e.timestamp || e.ts);
        const task = (e.task || '').substring(0, 120) || '(no task text)';
        return `<div class="task-outcome-item"><div class="task-outcome-ts">${ts.date}<br>${ts.time}</div><div class="task-outcome-task">${task}</div><div class="task-outcome-status" style="color:${st[1]};border-color:${st[1]}66;">${st[0]}</div></div>`;
    }).join('');
}

function renderLog(events) {
    const el = document.getElementById('log-entries');
    if (!el) return;
    el.innerHTML = '';
    sortEventsNewestFirst(events).slice(0, 100).forEach(e => {
        const isRoutineExec = e.event_type === 'routine_execution';
        const icon = isRoutineExec ? '🔁' : (e.found ? '✅' : (e.escalated ? '⬆️' : '❌'));
        const badge = isRoutineExec ? '<span class="log-badge badge-orange">routine run</span>'
            : (e.found ? (e.escalated ? '<span class="log-badge badge-purple">T2 synth</span>' : '<span class="log-badge badge-green">T1 acquire</span>')
                : (e.escalated ? '<span class="log-badge badge-orange">escalated</span>' : '<span class="log-badge badge-red">gap</span>'));
        const prov = e.provider_used ? `<span class="log-badge badge-blue">${e.provider_used}</span>` : '';
        const installed = (e.installed || []).join(', ');
        const text = isRoutineExec ? (e.entity_name ? `Routine: ${e.entity_name}` : (e.task || '')) : (e.task || '');
        const ts = fmtTs(e.timestamp || e.ts);
        const row = document.createElement('div');
        row.className = 'log-entry';
        row.innerHTML = `<span class="log-icon">${icon}</span><span class="log-time"><span class="d">${ts.date}</span><span class="t">${ts.time}</span></span><span class="log-text">${text.substring(0, 70)}${badge}${prov}${installed ? `<br><span style="color:#58a6ff">+${installed}</span>` : ''}</span>`;
        el.appendChild(row);
    });
}

function renderGaps(gaps) {
    const el = document.getElementById('gap-entries');
    if (!el) return;
    if (!gaps.length) { el.innerHTML = '<div class="gap-item" style="color:#3fb950">No open gaps ✔</div>'; return; }
    el.innerHTML = '';
    gaps.forEach(g => {
        const d = document.createElement('div');
        d.className = 'gap-item';
        d.innerHTML = `<span>⚠️</span>${(g.task || '').substring(0, 70)}`;
        el.appendChild(d);
    });
}

// ── D3 Force graph ──
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
    _graphRebuildTimer = setTimeout(() => { buildGraph(); }, 120);
}

async function refreshGraphContext() {
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
    _graphBusy = true;
    try {
        await refreshGraphContext();
        const panel = document.getElementById('graph-panel');
        if (!panel) return;
        const W = panel.clientWidth || window.innerWidth, H = 430;
        const svgEl = document.getElementById('graph');
        if (!svgEl) return;
        const svg = d3.select('#graph').attr('viewBox', `0 0 ${W} ${H}`);
        svg.selectAll('*').remove();
        const tip = document.getElementById('tooltip');

        const nodeMap = {};
        const links = [];
        nodeMap['kernel'] = { id: 'kernel', label: 'Kernel', type: 'core', r: 18 };

        if (_graphFilter.skills) {
            Array.from(_graphCtx.skillNames || []).forEach((skillName) => {
                if (!skillName || _graphCtx.routineNames.has(skillName)) return;
                if (!nodeMap[skillName]) {
                    nodeMap[skillName] = { id: skillName, label: skillName, type: 'acquired', provider: 'ecosystem', task: 'Loaded from skill registry', r: 11 };
                }
                links.push({ source: 'kernel', target: skillName, value: 0.62, link_type: 'catalog_skill' });
            });
        }
        if (_graphFilter.routines) {
            Array.from(_graphCtx.routineNames || []).forEach((routineName) => {
                if (!routineName) return;
                const rid = `routine:${routineName}`;
                if (!nodeMap[rid]) {
                    nodeMap[rid] = { id: rid, label: routineName, type: 'routine', provider: 'ecosystem', task: 'Loaded from routine registry', executions: 0, r: 11 };
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
                    nodeMap[rid] = { id: rid, label: routineName, type: 'routine', confidence: e.confidence, provider: e.provider_used, task: e.task, timestamp: e.timestamp, executions: 0, r: 11 };
                }
                nodeMap[rid].executions = (nodeMap[rid].executions || 0) + 1;
                nodeMap[rid].r = Math.min(18, 11 + Math.floor((nodeMap[rid].executions || 1) / 2));
                links.push({ source: 'kernel', target: rid, value: 0.75, link_type: 'routine_exec' });
                return;
            }
            (e.installed || []).forEach(skill => {
                const isRoutine = _graphCtx.routineNames.has(skill);
                const targetId = isRoutine ? `routine:${skill}` : skill;
                const nodeType = isRoutine ? 'routine' : ((e.escalated && e.found) ? 'synthesised' : 'acquired');
                if ((nodeType === 'routine' && !_graphFilter.routines) || (nodeType !== 'routine' && !_graphFilter.skills)) return;
                if (!nodeMap[targetId]) {
                    nodeMap[targetId] = { id: targetId, label: skill, type: nodeType, confidence: e.confidence, provider: e.provider_used, task: e.task, timestamp: e.timestamp, r: 12 };
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
                    nodeMap[rid] = { id: rid, label: `@${r.name}`, type: 'replica', task: r.task || '', r: 10 };
                    links.push({ source: 'kernel', target: rid, value: 0.8, link_type: 'replica_live' });
                }
            });
        }

        const nodes = Object.values(nodeMap);
        const color = { core: '#3fb950', acquired: '#58a6ff', synthesised: '#bc8cff', routine: '#d29922', replica: '#8b949e', gap: '#f85149' };

        if (nodes.length <= 1) {
            svg.append('text').attr('x', W / 2).attr('y', H / 2).attr('text-anchor', 'middle').attr('fill', '#30363d').attr('font-size', 13).text('No evolution events yet — waiting for Kernel to encounter unknown tasks');
            return;
        }

        const sim = d3.forceSimulation(nodes)
            .force('link', d3.forceLink(links).id(d => d.id).distance(100).strength(0.6))
            .force('charge', d3.forceManyBody().strength(-220))
            .force('center', d3.forceCenter(W / 2, H / 2))
            .force('collide', d3.forceCollide(d => d.r + 12));

        const g = svg.append('g');
        svg.call(d3.zoom().scaleExtent([0.3, 4]).on('zoom', e => g.attr('transform', e.transform)));

        svg.append('defs').append('marker').attr('id', 'arr').attr('viewBox', '0 -4 8 8').attr('refX', 22).attr('markerWidth', 6).attr('markerHeight', 6).attr('orient', 'auto').append('path').attr('d', 'M0,-4L8,0L0,4').attr('fill', '#30363d');

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
        const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = String(val); };
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
                if (tip) { tip.innerHTML = h; tip.style.opacity = 1; }
            })
            .on('mousemove', event => {
                if (!tip || !panel) return;
                const r = panel.getBoundingClientRect();
                tip.style.left = (event.clientX - r.left + 14) + 'px';
                tip.style.top = (event.clientY - r.top + 14) + 'px';
            })
            .on('mouseleave', () => { if (tip) tip.style.opacity = 0; });

        const defs = svg.select('defs');
        const filt = defs.append('filter').attr('id', 'glow');
        filt.append('feGaussianBlur').attr('stdDeviation', 3).attr('result', 'blur');
        const merge = filt.append('feMerge');
        merge.append('feMergeNode').attr('in', 'blur');
        merge.append('feMergeNode').attr('in', 'SourceGraphic');

        node.append('circle').attr('r', d => d.r).attr('fill', d => color[d.type] || '#58a6ff').attr('fill-opacity', 0.85).attr('stroke', d => color[d.type] || '#58a6ff').attr('stroke-width', 1.5).attr('stroke-opacity', 0.4).filter(d => d.type === 'core').attr('filter', 'url(#glow)');
        node.filter(d => d.type === 'core').append('circle').attr('r', 26).attr('fill', 'none').attr('stroke', '#3fb950').attr('stroke-width', 1).attr('stroke-opacity', 0.25);
        node.append('text').attr('dy', d => d.r + 12).attr('text-anchor', 'middle').attr('font-size', 9).attr('fill', '#8b949e').text(d => d.label.substring(0, 18));

        sim.on('tick', () => {
            link.attr('x1', d => d.source.x).attr('y1', d => d.source.y).attr('x2', d => d.target.x).attr('y2', d => d.target.y);
            node.attr('transform', d => `translate(${d.x},${d.y})`);
        });
    } finally {
        _graphBusy = false;
    }
}

// ── Live SSE updates ──
let _es = null;
let totalEvents = EVENTS.length;

function updateGaps(gaps) {
    if (!Array.isArray(gaps)) return;
    const el = document.getElementById('s-gaps');
    if (el) el.textContent = gaps.length;
    const gapEl = document.getElementById('gap-entries');
    if (!gapEl) return;
    if (!gaps.length) { gapEl.innerHTML = '<div class="gap-item" style="color:#3fb950">No open gaps ✓</div>'; return; }
    gapEl.innerHTML = gaps.map(g => `<div class="gap-item"><span>⚠</span>${String(g.gap || g.task || g).substring(0, 120)}</div>`).join('');
}

function rebuildTimeline(events) {
    const svgEl = document.getElementById('timeline-svg');
    if (!svgEl) return;
    while (svgEl.firstChild) svgEl.removeChild(svgEl.firstChild);
    const panel = document.getElementById('timeline-panel');
    const H = 90;
    const visW = (panel?.clientWidth || window.innerWidth) - 48;
    const W = Math.max(visW, events.length * 12, 800);
    svgEl.setAttribute('width', W); svgEl.setAttribute('height', H);
    const svg = d3.select('#timeline-svg');
    const times = events.map(e => new Date(e.ts || e.timestamp || 0));
    if (!times.length) return;
    const extent = d3.extent(times);
    const xScale = d3.scaleTime().domain([extent[1], extent[0]]).range([40, W - 20]);
    const col = { acquired: '#58a6ff', synthesised: '#bc8cff', gap: '#f85149', routine: '#d29922' };
    const yLane = { acquired: 10, synthesised: 27, gap: 44, routine: 61 };

    [['T1', 10, '#58a6ff'], ['T2', 27, '#bc8cff'], ['gap', 44, '#f85149'], ['routine', 61, '#d29922']].forEach(([l, y, c]) => {
        svg.append('text').attr('x', 2).attr('y', y + 4).attr('font-size', 8).attr('fill', c).attr('font-family', 'monospace').text(l);
    });

    events.forEach((e) => {
        const type = e.event_type === 'routine_execution' ? 'routine' : (!e.found ? 'gap' : (e.escalated ? 'synthesised' : 'acquired'));
        const t = new Date(e.ts || e.timestamp || 0);
        svg.append('circle').attr('cx', xScale(t)).attr('cy', yLane[type] || 27).attr('r', 4).attr('fill', col[type] || '#58a6ff').attr('opacity', 0.85);
    });

    const tickCount = Math.max(3, Math.min(10, Math.floor(W / 120)));
    svg.append('g').attr('transform', `translate(0,${H - 22})`)
        .call(d3.axisBottom(xScale).ticks(tickCount).tickSize(3).tickFormat(d => {
            const dt = new Date(d);
            const span = extent[1] - extent[0];
            return span > 86400000 ? dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) + ' ' + dt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : dt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
        }))
        .call(g => { g.select('.domain').attr('stroke', '#30363d'); g.selectAll('text').attr('fill', '#8b949e').attr('font-size', 8); g.selectAll('.tick line').attr('stroke', '#30363d'); });
}

function updateEvolutionStatsFromEvents() {
    const evoOnly = EVENTS.filter(e => !e.event_type || e.event_type === 'evolution');
    const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    setText('s-total', evoOnly.length);
    setText('s-resolved', evoOnly.filter(e => e.found).length);
    setText('s-synth', evoOnly.filter(e => e.escalated && e.found).length);
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
    requestAnimationFrame(() => { _uiFrameScheduled = false; renderActivityViews(); });
}

async function fetchJsonWithTimeout(url, timeoutMs = 6000) {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
        const res = await fetch(url, { signal: ctrl.signal, cache: 'no-store' });
        if (!res.ok) throw new Error(`${url} ${res.status}`);
        return await res.json();
    } finally { clearTimeout(timer); }
}

let _fullRefreshInFlight = false;
async function fullRefresh() {
    if (_fullRefreshInFlight) return;
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
    } catch (_) { }
    finally { _fullRefreshInFlight = false; }
}

function startEvolutionStream() {
    if (_es) { _es.close(); _es = null; }
    try {
        _es = new EventSource(kapi('/evolution/stream'));
        _es.onmessage = ev => {
            try {
                const event = JSON.parse(ev.data);
                if (!EVENTS.find(e => e.id === event.id)) { EVENTS.unshift(event); totalEvents++; }
                scheduleActivityRender();
                scheduleGraphRebuild();
            } catch (_) { }
        };
        _es.onerror = () => { /* SSE auto-reconnects */ };
    } catch (_) { }
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

function updateStateUI(s) {
    const badge = document.getElementById('state-badge');
    if (!badge) return;
    badge.textContent = s.state;
    badge.className = 'dash-state-badge dash-state-' + s.state;
    const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    setText('iter-count', s.iterations);
    setText('iter-cap', s.cap);
    const capInput = document.getElementById('cap-input');
    if (capInput) capInput.value = s.cap;
    const running = s.state === 'running';
    const paused = s.state === 'paused';
    const stopped = s.state === 'stopped';
    const setDisabled = (id, val) => { const el = document.getElementById(id); if (el) el.disabled = val; };
    setDisabled('btn-start', _evoRequestInFlight || running);
    setDisabled('btn-pause', _evoRequestInFlight || !running);
    setDisabled('btn-resume', _evoRequestInFlight || !paused);
    setDisabled('btn-stop', _evoRequestInFlight || stopped);
    const dot = document.querySelector('.live-dot');
    if (dot) dot.style.background = running ? '#3fb950' : (paused ? '#d29922' : '#f85149');
}

async function ctrlAction(action) {
    if (_evoRequestInFlight) return;
    const cap = parseInt(document.getElementById('cap-input')?.value) || 10;
    const msg = document.getElementById('ctrl-msg');
    if (msg) msg.textContent = '⏳ ' + action + '...';
    setEvolutionControlsBusy(true);
    try {
        const r = await fetch(kapi('/evolution/control'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, cap: (action === 'start' || action === 'reset') ? cap : null })
        });
        if (!r.ok) { const txt = await r.text(); throw new Error(`HTTP ${r.status}: ${txt.slice(0, 160)}`); }
        const s = await r.json();
        updateStateUI(s);
        if (msg) { msg.textContent = '✅ ' + action + ' — ' + s.iterations + '/' + s.cap + ' iterations'; setTimeout(() => { if (msg) msg.textContent = ''; }, 4000); }
    } catch (e) {
        if (msg) msg.textContent = '❌ ' + e.message;
    } finally {
        setEvolutionControlsBusy(false);
    }
}

async function triggerEvolution() {
    const task = document.getElementById('task-input')?.value?.trim();
    if (!task) return;
    const confirmEl = document.getElementById('confirm-evolve');
    const shouldConfirm = !confirmEl || confirmEl.checked;
    if (shouldConfirm && !confirm(`Trigger evolution for task:\n\n${task}\n\nProceed?`)) return;
    const msg = document.getElementById('ctrl-msg');
    if (msg) msg.textContent = '🧬 Evolving…';
    try {
        const r = await fetch(kapi('/evolution/trigger'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task })
        });
        if (!r.ok) { const txt = await r.text(); throw new Error(`HTTP ${r.status}: ${txt.slice(0, 160)}`); }
        const data = await r.json();
        if (msg) { msg.textContent = `🧬 ${data.message || 'Evolution triggered'}`; setTimeout(() => { if (msg) msg.textContent = ''; }, 5000); }
        await fullRefresh();
    } catch (e) {
        if (msg) msg.textContent = '❌ ' + e.message;
    }
}

// ── Skills tab ──
let _allSkills = [];
let _allRoutines = [];

async function loadSkills() {
    try {
        const data = await fetchJsonWithTimeout(kapi('/skills'), 7000);
        _allSkills = data.skills || data || [];
        renderSkills('');
    } catch (e) {
        const list = document.getElementById('skill-list');
        if (list) list.innerHTML = `<div style="color:#f85149;font-size:0.72em;">Failed to load skills: ${agentEscapeText(e.message)}</div>`;
    }
}

async function loadRoutines() {
    try {
        const data = await fetchJsonWithTimeout(kapi('/routines'), 7000);
        _allRoutines = data.routines || data || [];
        renderRoutines();
    } catch (e) {
        const list = document.getElementById('routine-list');
        if (list) list.innerHTML = `<div style="color:#f85149;font-size:0.72em;">Failed to load routines: ${agentEscapeText(e.message)}</div>`;
    }
}

function renderSkills(filter) {
    const list = document.getElementById('skill-list');
    if (!list) return;
    const f = (filter || '').toLowerCase();
    const filtered = _allSkills.filter(s => !f || (s.name || '').toLowerCase().includes(f) || (s.description || '').toLowerCase().includes(f));
    const countEl = document.getElementById('skill-count');
    if (countEl) countEl.textContent = `${filtered.length} / ${_allSkills.length}`;
    if (!filtered.length) { list.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No skills found.</div>'; return; }
    list.innerHTML = filtered.map(s => {
        const name = agentEscapeText(s.name || 'unnamed');
        const desc = agentEscapeText(s.description || '');
        const tier = s.tier ? `<span class="log-badge badge-${s.tier === 2 ? 'purple' : 'blue'}">T${s.tier}</span>` : '';
        const src = s.source ? `<span class="log-badge badge-orange">${agentEscapeText(s.source)}</span>` : '';
        return `<div style="border:1px solid #21262d;border-radius:8px;padding:10px 12px;background:#0d1117;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <strong style="color:#79c0ff;font-size:0.82em;">${name}</strong>
                <span style="font-size:0.66em;color:#8b949e;">${tier}${src}</span>
            </div>
            <div style="font-size:0.72em;color:#8b949e;line-height:1.4;">${desc}</div>
        </div>`;
    }).join('');
}

function renderRoutines() {
    const list = document.getElementById('routine-list');
    if (!list) return;
    if (!_allRoutines.length) { list.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No routines found.</div>'; return; }
    list.innerHTML = _allRoutines.map(r => {
        const name = agentEscapeText(r.name || 'unnamed');
        const desc = agentEscapeText(r.description || '');
        return `<div style="border:1px solid #21262d;border-radius:8px;padding:10px 12px;background:#0d1117;">
            <div style="margin-bottom:4px;"><strong style="color:#d29922;font-size:0.82em;">${name}</strong></div>
            <div style="font-size:0.72em;color:#8b949e;line-height:1.4;">${desc}</div>
        </div>`;
    }).join('');
}

function filterSkills() {
    const input = document.getElementById('skill-search');
    renderSkills(input?.value || '');
}

// ── Replicas tab ──
async function spawnReplica() {
    const name = document.getElementById('rep-name')?.value?.trim();
    const role = document.getElementById('rep-role')?.value || 'custom';
    const brief = document.getElementById('rep-brief')?.value?.trim();
    const msg = document.getElementById('rep-spawn-msg');
    if (!name) { if (msg) msg.textContent = 'Name is required.'; return; }
    if (msg) msg.textContent = 'Spawning…';
    try {
        const r = await fetch(kapi('/replica/spawn'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, role, brief })
        });
        if (!r.ok) { const txt = await r.text(); throw new Error(`HTTP ${r.status}: ${txt.slice(0, 160)}`); }
        const data = await r.json();
        if (msg) msg.textContent = `✅ Spawned ${name}`;
        await loadReplicas();
    } catch (e) {
        if (msg) msg.textContent = `❌ ${e.message}`;
    }
}

async function loadReplicas() {
    try {
        const data = await fetchJsonWithTimeout(kapi('/replica/active'), 7000);
        const replicas = data.replicas || data || [];
        const countEl = document.getElementById('rep-count');
        if (countEl) countEl.textContent = replicas.length;
        const list = document.getElementById('rep-list');
        if (!list) return;
        if (!replicas.length) { list.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No active replicas.</div>'; return; }
        list.innerHTML = replicas.map(r => {
            const name = agentEscapeText(r.name || 'unnamed');
            const role = agentEscapeText(r.role || 'custom');
            const task = agentEscapeText(r.task || '');
            return `<div style="border:1px solid #21262d;border-radius:8px;padding:10px 12px;background:#0d1117;display:flex;justify-content:space-between;align-items:center;">
                <div><strong style="color:#8b949e;">@${name}</strong> <span style="font-size:0.66em;color:#58a6ff;">${role}</span></div>
                <div style="font-size:0.66em;color:#8b949e;max-width:60%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${task}</div>
            </div>`;
        }).join('');
    } catch (e) {
        const list = document.getElementById('rep-list');
        if (list) list.innerHTML = `<div style="color:#f85149;font-size:0.72em;">Failed to load replicas: ${agentEscapeText(e.message)}</div>`;
    }
}

async function launchPipeline() {
    const task = document.getElementById('pipe-task')?.value?.trim();
    const msg = document.getElementById('pipe-status');
    if (!task) { if (msg) msg.textContent = 'Task is required.'; return; }
    if (msg) msg.textContent = 'Launching pipeline…';
    try {
        const r = await fetch(kapi('/pipeline/run'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task, stages: ['writer', 'critic'] })
        });
        if (!r.ok) { const txt = await r.text(); throw new Error(`HTTP ${r.status}: ${txt.slice(0, 160)}`); }
        const data = await r.json();
        if (msg) msg.textContent = `✅ Pipeline started: ${data.job_id || 'ok'}`;
    } catch (e) {
        if (msg) msg.textContent = `❌ ${e.message}`;
    }
}

// ── Trajectories tab ──
async function loadTrajectories() {
    try {
        const data = await fetchJsonWithTimeout(kapi('/debug/trajectories?limit=50'), 7000);
        const trajectories = data.trajectories || [];
        const countEl = document.getElementById('traj-count');
        if (countEl) countEl.textContent = trajectories.length;
        const list = document.getElementById('traj-list');
        if (!list) return;
        if (!trajectories.length) { list.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No trajectories found.</div>'; return; }
        list.innerHTML = trajectories.map(row => {
            const toolCount = Array.isArray(row.tool_calls) ? row.tool_calls.length : 0;
            const score = row.critic_score == null ? 'n/a' : Number(row.critic_score).toFixed(2);
            const task = agentEscapeText(row.task || 'task');
            const provider = agentEscapeText(row.provider || '');
            const reply = agentEscapeText((row.final_reply || '').substring(0, 200));
            return `<details style="border:1px solid #21262d;border-radius:8px;background:#0d1117;padding:10px;">
                <summary style="cursor:pointer;color:#c9d1d9;font-size:0.78em;display:flex;justify-content:space-between;">
                    <span>${task}</span>
                    <span style="color:#8b949e;">${toolCount} tools · score ${score}</span>
                </summary>
                <div style="margin-top:8px;font-size:0.72em;color:#8b949e;">Provider: ${provider}</div>
                <div style="margin-top:8px;font-size:0.72em;color:#c9d1d9;white-space:pre-wrap;">${reply}</div>
            </details>`;
        }).join('');
    } catch (e) {
        const list = document.getElementById('traj-list');
        if (list) list.innerHTML = `<div style="color:#f85149;font-size:0.72em;">Failed to load trajectories: ${agentEscapeText(e.message)}</div>`;
    }
}

// ── Insights tab ──
async function loadInsights() {
    try {
        const data = await fetchJsonWithTimeout(kapi('/debug/system-state'), 7000);
        const statsEl = document.getElementById('ins-stats');
        if (statsEl) {
            const stats = data.stats || {};
            statsEl.innerHTML = `
                <div class="dash-stat blue"><div class="num">${stats.total_skills ?? 0}</div><div class="lbl">Total Skills</div></div>
                <div class="dash-stat green"><div class="num">${stats.resolved ?? 0}</div><div class="lbl">Resolved</div></div>
                <div class="dash-stat purple"><div class="num">${stats.synthesised ?? 0}</div><div class="lbl">Synthesised</div></div>
                <div class="dash-stat red"><div class="num">${stats.unresolved_gaps ?? 0}</div><div class="lbl">Open Gaps</div></div>
            `;
        }
        const thoughtsEl = document.getElementById('ins-thoughts');
        const thoughtsCountEl = document.getElementById('ins-thought-count');
        const thoughts = data.thoughts || [];
        if (thoughtsCountEl) thoughtsCountEl.textContent = thoughts.length;
        if (thoughtsEl) {
            if (!thoughts.length) { thoughtsEl.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No thoughts today.</div>'; }
            else {
                thoughtsEl.innerHTML = thoughts.map(t => {
                    const ts = t.timestamp ? new Date(t.timestamp).toLocaleTimeString() : '';
                    const text = agentEscapeText(t.thought || t.text || '');
                    const score = t.score != null ? Number(t.score).toFixed(2) : '';
                    return `<div style="border:1px solid #21262d;border-radius:8px;padding:10px;background:#0d1117;margin-bottom:6px;">
                        <div style="display:flex;justify-content:space-between;font-size:0.66em;color:#8b949e;margin-bottom:4px;">
                            <span>${ts}</span><span>score: ${score}</span>
                        </div>
                        <div style="font-size:0.72em;color:#c9d1d9;line-height:1.4;">${text}</div>
                    </div>`;
                }).join('');
            }
        }
    } catch (e) {
        const statsEl = document.getElementById('ins-stats');
        if (statsEl) statsEl.innerHTML = `<div style="color:#f85149;font-size:0.72em;">Failed to load insights: ${agentEscapeText(e.message)}</div>`;
    }
}

// ── System tab ──
async function loadSystemStatus() {
    try {
        const data = await fetchJsonWithTimeout(kapi('/debug/system-state'), 7000);
        const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        setText('sys-backups', data.backups?.length ?? 0);
        setText('sys-workspace', (data.workspace_size_mb ?? 0) + ' MB');
        setText('sys-messages', data.chat_messages ?? 0);
        setText('sys-promoted', data.promoted_signals ?? 0);
        const backupList = document.getElementById('backup-list');
        if (backupList) {
            const backups = data.backups || [];
            if (!backups.length) backupList.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No backups yet.</div>';
            else backupList.innerHTML = backups.map(b => `<div style="font-size:0.72em;color:#c9d1d9;padding:4px 0;border-bottom:1px solid #21262d;">${agentEscapeText(b.name || b)} — ${agentEscapeText(b.size || '')}</div>`).join('');
        }
        const wsStatus = document.getElementById('workspace-status');
        if (wsStatus) wsStatus.innerHTML = `<div style="color:#c9d1d9;">${data.workspace_status || 'OK'}</div>`;
        const ecoStatus = document.getElementById('ecosystem-status');
        if (ecoStatus) ecoStatus.innerHTML = `<div style="color:#c9d1d9;">${data.ecosystem || 'OK'}</div>`;
    } catch (e) {
        const wsStatus = document.getElementById('workspace-status');
        if (wsStatus) wsStatus.innerHTML = `<div style="color:#f85149;">Failed: ${agentEscapeText(e.message)}</div>`;
    }
}

async function createBackup() {
    const msg = document.getElementById('backup-msg');
    if (msg) msg.textContent = 'Creating backup…';
    try {
        const r = await fetch(kapi('/backup/create'), { method: 'POST' });
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        if (msg) msg.textContent = '✅ Backup created';
        await loadSystemStatus();
    } catch (e) {
        if (msg) msg.textContent = `❌ ${e.message}`;
    }
}

async function runInit() {
    try {
        await fetch(kapi('/init'), { method: 'POST' });
        await loadSystemStatus();
    } catch (e) { }
}

// ── Memory tab ──
async function loadMemoryStats() {
    try {
        const data = await fetchJsonWithTimeout(kapi('/memory/stats'), 7000);
        const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        setText('mem-stat-files', data.files ?? '—');
        setText('mem-stat-size', data.total_size ?? '—');
        setText('mem-stat-sessions', data.chat_sessions ?? '—');
        setText('mem-stat-dbsize', data.db_size ?? '—');
    } catch (e) { }
}

async function loadWorkspaceTree(path) {
    try {
        const data = await fetchJsonWithTimeout(kapi(`/workspace/tree?path=${encodeURIComponent(path || '/')}`), 7000);
        const tree = document.getElementById('mem-file-tree');
        if (!tree) return;
        const entries = data.entries || data || [];
        tree.innerHTML = entries.map(e => {
            const isDir = e.type === 'dir' || e.is_dir;
            const name = agentEscapeText(e.name);
            const full = agentEscapeText(e.path || e.name);
            const icon = isDir ? '📁' : '📄';
            const clickHandler = isDir ? 'loadWorkspaceTree(\'' + full + '\')' : 'openMemoryFile(\'' + full + '\')';
            return '<div style="padding:3px 12px;cursor:pointer;font-size:0.78em;color:#c9d1d9;" onclick="' + clickHandler + '">' +
                '<span>' + icon + '</span> ' + name +
                '</div>';
        }).join('');
    } catch (e) {
        const tree = document.getElementById('mem-file-tree');
        if (tree) tree.innerHTML = `<div style="color:#f85149;font-size:0.72em;padding:6px 12px;">Failed: ${agentEscapeText(e.message)}</div>`;
    }
}

async function openMemoryFile(path) {
    try {
        const data = await fetchJsonWithTimeout(kapi(`/memory/file?path=${encodeURIComponent(path)}`), 7000);
        const nameEl = document.getElementById('mem-editor-filename');
        if (nameEl) nameEl.textContent = data.name || path;
        const view = document.getElementById('mem-editor-view');
        if (view) view.innerHTML = `<pre style="white-space:pre-wrap;font-family:monospace;font-size:0.82em;color:#c9d1d9;">${agentEscapeText(data.content || '')}</pre>`;
        const editBtn = document.getElementById('mem-edit-btn');
        const delBtn = document.getElementById('mem-delete-btn');
        if (editBtn) editBtn.style.display = '';
        if (delBtn) delBtn.style.display = '';
    } catch (e) {
        const view = document.getElementById('mem-editor-view');
        if (view) view.innerHTML = `<div style="color:#f85149;font-size:0.72em;">Failed: ${agentEscapeText(e.message)}</div>`;
    }
}

function filterMemoryTree(filter) {
    // Simple filter — could be enhanced
    const items = document.querySelectorAll('#mem-file-tree > div');
    const f = (filter || '').toLowerCase();
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = !f || text.includes(f) ? '' : 'none';
    });
}

function promptNewMemoryFile() {
    const name = prompt('New memory file name:');
    if (!name) return;
    fetch(kapi('/memory/create'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name })
    }).then(() => loadWorkspaceTree('/')).catch(() => { });
}

function toggleMemoryEdit() {
    const view = document.getElementById('mem-editor-view');
    const ta = document.getElementById('mem-editor-textarea');
    const editBtn = document.getElementById('mem-edit-btn');
    const saveBtn = document.getElementById('mem-save-btn');
    const cancelBtn = document.getElementById('mem-cancel-btn');
    if (!view || !ta) return;
    ta.value = view.textContent;
    view.style.display = 'none';
    ta.style.display = '';
    if (editBtn) editBtn.style.display = 'none';
    if (saveBtn) saveBtn.style.display = '';
    if (cancelBtn) cancelBtn.style.display = '';
}

async function saveCurrentMemoryFile() {
    const ta = document.getElementById('mem-editor-textarea');
    const nameEl = document.getElementById('mem-editor-filename');
    if (!ta || !nameEl) return;
    try {
        await fetch(kapi('/memory/save'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path: nameEl.textContent, content: ta.value })
        });
        cancelMemoryEdit();
        openMemoryFile(nameEl.textContent);
    } catch (e) { }
}

function cancelMemoryEdit() {
    const view = document.getElementById('mem-editor-view');
    const ta = document.getElementById('mem-editor-textarea');
    const editBtn = document.getElementById('mem-edit-btn');
    const saveBtn = document.getElementById('mem-save-btn');
    const cancelBtn = document.getElementById('mem-cancel-btn');
    if (view) view.style.display = '';
    if (ta) ta.style.display = 'none';
    if (editBtn) editBtn.style.display = '';
    if (saveBtn) saveBtn.style.display = 'none';
    if (cancelBtn) cancelBtn.style.display = 'none';
}

async function deleteCurrentMemoryFile() {
    const nameEl = document.getElementById('mem-editor-filename');
    if (!nameEl || !confirm(`Delete ${nameEl.textContent}?`)) return;
    try {
        await fetch(kapi('/memory/delete'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path: nameEl.textContent })
        });
        loadWorkspaceTree('/');
        const view = document.getElementById('mem-editor-view');
        if (view) view.innerHTML = '<div style="color:#555;font-size:0.8em;font-family:monospace;">Select a file from the tree to view its contents.</div>';
        nameEl.textContent = 'No file selected';
    } catch (e) { }
}

function _memToggleTree() {
    const panel = document.getElementById('mem-tree-panel');
    if (panel) panel.classList.toggle('collapsed');
}

// ── SQLite viewer ──
let _sqliteCurrentTable = '';
let _sqlitePage = 0;
const _sqlitePageSize = 50;

async function sqliteOpen(path) {
    const view = document.getElementById('mem-sqlite-view');
    const editor = document.getElementById('mem-editor-view');
    const ta = document.getElementById('mem-editor-textarea');
    if (view) view.style.display = 'flex';
    if (editor) editor.style.display = 'none';
    if (ta) ta.style.display = 'none';
    const nameEl = document.getElementById('mem-sqlite-filename');
    if (nameEl) nameEl.textContent = path;
    await sqliteRefresh();
}

async function sqliteRefresh() {
    const nameEl = document.getElementById('mem-sqlite-filename');
    if (!nameEl) return;
    try {
        const data = await fetchJsonWithTimeout(kapi(`/sqlite/tables?path=${encodeURIComponent(nameEl.textContent)}`), 7000);
        const tables = data.tables || [];
        const listEl = document.getElementById('mem-sqlite-tables-list');
        if (listEl) {
            listEl.innerHTML = tables.map(t => `<button class="dash-ctrl-btn" style="font-size:0.66em;padding:3px 8px;" onclick="sqliteLoadTable('${agentEscapeText(t)}')">${agentEscapeText(t)}</button>`).join('');
        }
    } catch (e) { }
}

async function sqliteLoadTable(table) {
    _sqliteCurrentTable = table;
    _sqlitePage = 0;
    await sqliteRenderTable();
}

async function sqliteRenderTable() {
    const nameEl = document.getElementById('mem-sqlite-filename');
    if (!nameEl || !_sqliteCurrentTable) return;
    try {
        const data = await fetchJsonWithTimeout(kapi(`/sqlite/query?path=${encodeURIComponent(nameEl.textContent)}&table=${encodeURIComponent(_sqliteCurrentTable)}&page=${_sqlitePage}&size=${_sqlitePageSize}`), 7000);
        const dataEl = document.getElementById('mem-sqlite-table-data');
        if (!dataEl) return;
        const rows = data.rows || [];
        const cols = data.columns || (rows.length ? Object.keys(rows[0]) : []);
        if (!rows.length) { dataEl.innerHTML = '<div style="color:#8b949e;font-size:0.72em;">No rows.</div>'; return; }
        let html = '<table style="width:100%;border-collapse:collapse;font-size:0.72em;font-family:monospace;"><thead><tr>';
        cols.forEach(c => html += `<th style="border:1px solid #21262d;padding:4px 8px;text-align:left;color:#79c0ff;background:#161b22;">${agentEscapeText(c)}</th>`);
        html += '</tr></thead><tbody>';
        rows.forEach(r => {
            html += '<tr>';
            cols.forEach(c => html += `<td style="border:1px solid #21262d;padding:4px 8px;color:#c9d1d9;">${agentEscapeText(String(r[c] ?? ''))}</td>`);
            html += '</tr>';
        });
        html += '</tbody></table>';
        dataEl.innerHTML = html;
        const pageInfo = document.getElementById('sqlite-page-info');
        if (pageInfo) pageInfo.textContent = `Page ${_sqlitePage + 1}`;
        const totalRows = document.getElementById('sqlite-total-rows');
        if (totalRows) totalRows.textContent = `${data.total ?? rows.length} rows`;
        const pagination = document.getElementById('mem-sqlite-pagination');
        if (pagination) pagination.style.display = 'flex';
    } catch (e) {
        const dataEl = document.getElementById('mem-sqlite-table-data');
        if (dataEl) dataEl.innerHTML = `<div style="color:#f85149;font-size:0.72em;">Failed: ${agentEscapeText(e.message)}</div>`;
    }
}

function sqlitePrevPage() { if (_sqlitePage > 0) { _sqlitePage--; sqliteRenderTable(); } }
function sqliteNextPage() { _sqlitePage++; sqliteRenderTable(); }

async function sqliteClearAllTables() {
    const nameEl = document.getElementById('mem-sqlite-filename');
    if (!nameEl || !confirm(`Clear all tables in ${nameEl.textContent}?`)) return;
    try {
        await fetch(kapi('/sqlite/clear'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path: nameEl.textContent })
        });
        await sqliteRefresh();
    } catch (e) { }
}

function sqliteClose() {
    const view = document.getElementById('mem-sqlite-view');
    const editor = document.getElementById('mem-editor-view');
    if (view) view.style.display = 'none';
    if (editor) editor.style.display = '';
}

// ── Voice tab (basic placeholder) ──
function initVoiceTab() {
    const micBtn = document.getElementById('v-mic-btn');
    const status = document.getElementById('v-status');
    if (status) status.textContent = 'Voice requires kernel-evolving voice server running.';
    micBtn?.addEventListener('click', () => {
        if (status) status.textContent = 'Voice feature requires the kernel-evolving voice server. Connect via setup wizard.';
    });
}

// ── Auto-init on DOMContentLoaded ──
document.addEventListener('DOMContentLoaded', () => {
    // Initialize any tab-specific UI that exists on the current page
    if (document.getElementById('graph')) {
        updateStats(STATS, GAPS);
        renderTaskOutcomes(EVENTS);
        renderLog(EVENTS);
        renderGaps(GAPS);
        scheduleGraphRebuild();
        startEvolutionStream();
        setInterval(fullRefresh, 10000);
        fullRefresh();

        document.getElementById('task-input')?.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') { ev.preventDefault(); triggerEvolution(); }
        });
    }
    if (document.getElementById('agent-thread')) initAgentTab();
    if (document.getElementById('skill-list')) { loadSkills(); loadRoutines(); }
    if (document.getElementById('rep-list')) loadReplicas();
    if (document.getElementById('traj-list')) loadTrajectories();
    if (document.getElementById('ins-stats')) loadInsights();
    if (document.getElementById('sys-backups')) loadSystemStatus();
    if (document.getElementById('mem-file-tree')) { loadMemoryStats(); loadWorkspaceTree('/'); }
    if (document.getElementById('v-mic-btn')) initVoiceTab();
});
