/**
 * TOURNIVOX Bracketing Manager - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initDropdowns();
    initThemeToggle();
    initGlobalSearch();
    initNotifications();
    initConfirmDialogs();
});

// Sidebar toggle for mobile
function initSidebar() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!toggle) return;

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    });
    overlay?.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    });
}

// Dropdown menus
function initDropdowns() {
    document.querySelectorAll('[id$="Toggle"]').forEach(btn => {
        const dropdownId = btn.id.replace('Toggle', 'Dropdown');
        const dropdown = document.getElementById(dropdownId);
        if (!dropdown) return;

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('.dropdown-menu-custom.active').forEach(d => {
                if (d !== dropdown) d.classList.remove('active');
            });
            dropdown.classList.toggle('active');
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown-menu-custom.active').forEach(d => d.classList.remove('active'));
    });
}

// Theme toggle
function initThemeToggle() {
    const btn = document.getElementById('themeToggle');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const html = document.documentElement;
        const newTheme = html.dataset.theme === 'dark' ? 'light' : 'dark';
        html.dataset.theme = newTheme;
        btn.querySelector('i').className = `bi bi-${newTheme === 'dark' ? 'sun' : 'moon'}`;

        fetch(`${APP_URL}/api/auth.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_theme', theme: newTheme })
        });
    });
}

// Global search
function initGlobalSearch() {
    const input = document.getElementById('globalSearch');
    const results = document.getElementById('searchResults');
    if (!input) return;

    let timeout;
    input.addEventListener('input', () => {
        clearTimeout(timeout);
        const q = input.value.trim();
        if (q.length < 2) { results.classList.remove('active'); return; }

        timeout = setTimeout(async () => {
            try {
                const res = await fetch(`${APP_URL}/api/search.php?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                if (data.results?.length) {
                    results.innerHTML = data.results.map(r => `
                        <div class="search-result-item" onclick="window.location='${r.url}'">
                            <strong>${r.title}</strong><br>
                            <small>${r.type} · ${r.subtitle || ''}</small>
                        </div>
                    `).join('');
                    results.classList.add('active');
                } else {
                    results.innerHTML = '<div class="search-result-item"><small>No results found</small></div>';
                    results.classList.add('active');
                }
            } catch (e) { console.error(e); }
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !results.contains(e.target)) {
            results.classList.remove('active');
        }
    });
}

// Notifications polling
function initNotifications() {
    loadNotifications();
    setInterval(loadNotifications, 30000);
}

async function loadNotifications() {
    try {
        const res = await fetch(`${APP_URL}/api/notifications.php`);
        const data = await res.json();
        const badge = document.getElementById('notifCount');
        const list = document.getElementById('notifList');
        if (!badge || !list) return;

        const unread = data.notifications?.filter(n => !n.is_read) || [];
        badge.textContent = unread.length;
        badge.style.display = unread.length ? 'flex' : 'none';

        if (data.notifications?.length) {
            list.innerHTML = data.notifications.slice(0, 10).map(n => `
                <a href="${n.link || '#'}" class="search-result-item ${n.is_read ? '' : 'fw-bold'}" 
                   onclick="markNotifRead(${n.id})">
                    <strong>${n.title}</strong><br>
                    <small>${n.message}</small>
                </a>
            `).join('');
        }
    } catch (e) { /* silent */ }
}

async function markNotifRead(id) {
    await fetch(`${APP_URL}/api/notifications.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_read', id })
    });
}

// Toast notifications
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast-item ${type}`;
    toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info-circle'}"></i> ${message}`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 4000);
}

// Loading overlay
function showLoading() { document.getElementById('loadingOverlay').style.display = 'flex'; }
function hideLoading() { document.getElementById('loadingOverlay').style.display = 'none'; }

// Confirm dialogs
function initConfirmDialogs() {
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            if (!confirm(el.dataset.confirm)) e.preventDefault();
        });
    });
}

// API helper
async function apiCall(url, data = {}, method = 'POST') {
    showLoading();
    try {
        const options = {
            method,
            headers: { 'Content-Type': 'application/json' },
        };
        if (method !== 'GET') options.body = JSON.stringify(data);
        const res = await fetch(url, options);
        const result = await res.json();
        if (!result.success) showToast(result.message || 'An error occurred', 'error');
        return result;
    } catch (e) {
        showToast('Network error', 'error');
        return { success: false };
    } finally {
        hideLoading();
    }
}

// Tab switching
function initTabs(containerSelector) {
    const container = document.querySelector(containerSelector);
    if (!container) return;

    container.querySelectorAll('.tab-item').forEach(tab => {
        tab.addEventListener('click', () => {
            container.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const target = tab.dataset.tab;
            document.querySelectorAll('.tab-content-panel').forEach(p => p.classList.remove('active'));
            document.getElementById(target)?.classList.add('active');
        });
    });
}

// Form AJAX submit
function ajaxForm(formId, callback) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        showLoading();
        try {
            const res = await fetch(form.action, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                showToast(data.message || 'Success!', 'success');
                if (callback) callback(data);
                else if (data.redirect) window.location = data.redirect;
            } else {
                showToast(data.message || 'Error', 'error');
            }
        } catch (err) {
            showToast('Network error', 'error');
        } finally {
            hideLoading();
        }
    });
}

// Add player row for team registration
function addPlayerRow() {
    const container = document.getElementById('playersContainer');
    const count = container.children.length;
    if (count >= 6) { showToast('Maximum 6 players allowed', 'error'); return; }

    const row = document.createElement('div');
    row.className = 'player-row';
    row.innerHTML = `
        <div><label class="form-label">IGN</label><input type="text" name="players[${count}][ign]" class="form-control" required></div>
        <div><label class="form-label">Real Name</label><input type="text" name="players[${count}][real_name]" class="form-control" required></div>
        <div><label class="form-label">Role</label>
            <select name="players[${count}][role]" class="form-select" required>
                <option value="EXP">EXP</option><option value="Jungler">Jungler</option>
                <option value="Mid">Mid</option><option value="Gold">Gold</option>
                <option value="Roam">Roam</option><option value="Substitute">Substitute</option>
            </select>
        </div>
        <div><label class="form-label">&nbsp;</label>
            <button type="button" class="btn btn-danger btn-sm w-100" onclick="this.closest('.player-row').remove()"><i class="bi bi-trash"></i></button>
        </div>`;
    container.appendChild(row);
}
