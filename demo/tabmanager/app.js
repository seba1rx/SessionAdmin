/**
 * app.js — Demo application logic
 *
 * This file shows how a consumer app integrates with TabManagerClient.
 * Key integration points are marked with "INTEGRATION:" comments.
 */

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Renders the full session state returned by the server.
 * Shows data from all known tabs, highlighting the current one.
 */
function renderSessionData(session) {
    const currentTabId = TabManagerClient.tab.id;
    const tabs = session?.tabmanager?.tabs ?? {};
    const tabEntries = Object.entries(tabs);

    const countEl = document.getElementById('data-count');
    if (countEl) countEl.textContent = tabEntries.length;

    const container = document.getElementById('session_data');
    if (!container) return;

    if (tabEntries.length === 0) {
        container.innerHTML = `
            <p class="text-muted p-3 mb-0 empty-state">
                No data yet — click <strong>Add random data</strong> to populate this tab's session.
            </p>`;
        return;
    }

    // Current tab first, then others sorted by last_active desc
    tabEntries.sort(([aId, a], [bId, b]) => {
        if (aId === currentTabId) return -1;
        if (bId === currentTabId) return 1;
        return (b?.last_active ?? 0) - (a?.last_active ?? 0);
    });

    const sections = tabEntries.map(([tabId, tabInfo]) => {
        const isCurrent = tabId === currentTabId;
        const data = tabInfo?.data ?? {};
        const dataEntries = Object.entries(data);
        const isActive = tabInfo?.is_active ?? false;
        const lastActive = tabInfo?.last_active
            ? new Date(tabInfo.last_active * 1000).toLocaleTimeString()
            : '—';

        const statusBadge = isActive
            ? '<span class="badge bg-success">active</span>'
            : '<span class="badge bg-secondary">inactive</span>';

        const currentBadge = isCurrent
            ? '<span class="badge bg-primary">this tab</span>'
            : '';

        const rows = dataEntries.length === 0
            ? '<tr><td colspan="2" class="text-muted ps-3 py-2 small fst-italic">empty</td></tr>'
            : dataEntries.map(([key, value]) => `
                <tr>
                    <td class="key-cell ps-3">${escapeHtml(key)}</td>
                    <td class="value-cell">${escapeHtml(value)}</td>
                </tr>`).join('');

        return `
            <div class="border-bottom ${isCurrent ? 'bg-light' : ''}">
                <div class="px-3 pt-2 pb-1 d-flex align-items-center gap-2 flex-wrap">
                    <code class="small text-muted">${escapeHtml(tabId)}</code>
                    ${currentBadge}
                    ${statusBadge}
                    <span class="text-muted small ms-auto">last active: ${lastActive}</span>
                </div>
                <table class="table table-sm mb-0">
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    }).join('');

    container.innerHTML = sections;
}

/**
 * INTEGRATION: sending data to a PHP endpoint with tab isolation.
 *
 * The critical part is spreading TabManagerClient.getHeaders() into the
 * fetch headers. This adds the X-TabManager-TabId header so the PHP backend
 * can call $session->tabHandler->set() / ->get() and know which tab to
 * read/write.
 *
 * Omitting getHeaders() would cause the backend to fall back to the shared
 * cookie — all tabs would read/write the same session slot.
 */
async function addData() {
    const btn = document.getElementById('btn-add');
    const spinner = document.getElementById('btn-spinner');
    btn.disabled = true;
    spinner.classList.remove('d-none');

    try {
        const response = await fetch('addData.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...TabManagerClient.getHeaders(), // INTEGRATION: tab identification header
            },
        });

        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);

        const result = await response.json();
        renderSessionData(result);
    } catch (error) {
        console.error('Error sending data:', error);
    } finally {
        btn.disabled = false;
        spinner.classList.add('d-none');
    }
}

/**
 * INTEGRATION: fetching session state for this tab.
 *
 * session.php registers the tab if not already known (idempotent) and
 * returns the full $_SESSION so the UI can display all tabs' data.
 *
 * In a real app this would be your own endpoint. The only requirement is
 * that it includes ...TabManagerClient.getHeaders() in the request so the
 * PHP backend can identify the calling tab.
 *
 * Note: this demo uses session.php instead of the built-in /tabmanager/new-tab
 * endpoint because `php -S` does not route /tabmanager/* without a router.
 */
async function ensureTabRegistered() {
    const res = await fetch('session.php', {
        headers: TabManagerClient.getHeaders(), // INTEGRATION: tab identification header
    });
    const session = await res.json();
    renderSessionData(session);
}

function reset() {
    window.location.href = 'terminate.php';
}

/**
 * INTEGRATION: waiting for TabManagerClient.ready.
 *
 * TabManagerClient.init() is async — it waits ~80 ms for the BroadcastChannel
 * duplicate-tab check to complete before the UUID is confirmed.
 *
 * Any code that reads TabManagerClient.tab.id or calls getHeaders() must run
 * AFTER TabManagerClient.ready resolves. If you read tab.id synchronously on
 * DOMContentLoaded you will get null.
 */
/**
 * INTEGRATION: handling tab session loss.
 *
 * When autoCleanupTabs is set, SessionAdmin prunes inactive tabs on every
 * request. If the browser suspends this tab (Chrome Memory Saver, OS memory
 * pressure), the heartbeat pauses and the tab may be pruned while invisible.
 *
 * When the user returns, TabManagerClient checks /tabmanager/tab-status. If
 * the tab is no longer indexed, it fires this event and stops the heartbeat.
 * The app must decide what to do — here we show a warning banner.
 */
document.addEventListener('tabmanager:session-lost', () => {
    const banner = document.createElement('div');
    banner.className = 'alert alert-warning d-flex align-items-center gap-3 rounded-0 mb-0';
    banner.setAttribute('role', 'alert');
    banner.innerHTML = `
        <span>
            <strong>Tab session expired.</strong>
            This tab was inactive and its session data was removed.
        </span>
        <button class="btn btn-sm btn-warning ms-auto" onclick="location.reload()">Reload</button>
    `;
    document.body.prepend(banner);
});

document.addEventListener('DOMContentLoaded', async () => {
    await TabManagerClient.ready; // wait for UUID to be confirmed and unique

    // Safe to use tab.id from here
    const tabIdEl = document.getElementById('tabid');
    if (tabIdEl) tabIdEl.textContent = TabManagerClient.tab.id ?? '—';

    ensureTabRegistered();
});
