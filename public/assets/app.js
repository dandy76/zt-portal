/* ── ZT Portal — Frontend JS ── */

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const KEEPALIVE_INTERVAL = 5 * 60 * 1000; // 5 minutes

// ── API Helper ──
async function api(method, url, data = null) {
    const opts = {
        method,
        headers: { 'X-CSRF-TOKEN': CSRF },
        credentials: 'same-origin',
    };
    if (data && method !== 'GET') {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify({ ...data, csrf_token: CSRF });
    }
    const res = await fetch(url, opts);
    const json = await res.json();
    if (res.status === 401) {
        window.location.href = '/login';
        throw new Error('Not authenticated');
    }
    return json;
}

// ── Theme Toggle ──
function initTheme() {
    const saved = localStorage.getItem('zt-theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);

    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('zt-theme', next);
    });
}

// ── Login ──
function initLogin() {
    const form = document.getElementById('login-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errEl = document.getElementById('login-error');
        errEl.style.display = 'none';

        const btn = document.getElementById('btn-login');
        btn.disabled = true;
        btn.textContent = 'Signing in...';

        try {
            const res = await api('POST', '/api/login', {
                username: document.getElementById('username').value,
                password: document.getElementById('password').value,
            });

            if (res.success && res.redirect) {
                window.location.href = res.redirect;
            } else {
                errEl.textContent = res.error || 'Login failed';
                errEl.style.display = 'block';
            }
        } catch (err) {
            errEl.textContent = 'Connection error';
            errEl.style.display = 'block';
        }

        btn.disabled = false;
        btn.textContent = 'Sign In';
    });
}

// ── 2FA Verify ──
function init2FA() {
    const form = document.getElementById('verify-2fa-form');
    if (!form) return;

    const input = document.getElementById('totp-code');

    // Auto-submit on 6 digits
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '');
        if (input.value.length === 6) {
            form.dispatchEvent(new Event('submit'));
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errEl = document.getElementById('2fa-error');
        errEl.style.display = 'none';

        try {
            const res = await api('POST', '/api/2fa/verify', { code: input.value });
            if (res.success && res.redirect) {
                window.location.href = res.redirect;
            } else {
                errEl.textContent = res.error || 'Verification failed';
                errEl.style.display = 'block';
                input.value = '';
                input.focus();
            }
        } catch (err) {
            errEl.textContent = 'Connection error';
            errEl.style.display = 'block';
        }
    });
}

// ── 2FA Enroll ──
function initEnroll2FA() {
    const form = document.getElementById('enroll-2fa-form');
    if (!form) return;

    // Generate QR code from otpauth URI
    const qrEl = document.getElementById('qr-code');
    if (qrEl && typeof QRCode !== 'undefined') {
        const uri = qrEl.dataset.uri;
        if (uri) {
            const canvas = document.getElementById('qr-canvas');
            QRCode.toCanvas(canvas, uri, { width: 200, margin: 1 });
        }
    }

    const input = document.getElementById('enroll-code');
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '');
        if (input.value.length === 6) {
            form.dispatchEvent(new Event('submit'));
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errEl = document.getElementById('setup-error');
        errEl.style.display = 'none';

        try {
            const res = await api('POST', '/api/2fa/enroll', { code: input.value });
            if (res.success && res.redirect) {
                window.location.href = res.redirect;
            } else {
                errEl.textContent = res.error || 'Enrollment failed';
                errEl.style.display = 'block';
                input.value = '';
                input.focus();
            }
        } catch (err) {
            errEl.textContent = 'Connection error';
            errEl.style.display = 'block';
        }
    });
}

// ── Logout ──
function initLogout() {
    document.getElementById('btn-logout')?.addEventListener('click', async () => {
        await api('POST', '/api/logout');
        window.location.href = '/login';
    });
}

// ── Portal ──
let keepaliveTimer = null;
let countdownTimers = {};

function initPortal() {
    const grid = document.getElementById('resources-grid');
    if (!grid) return;

    // Grant buttons
    grid.addEventListener('click', async (e) => {
        const grantBtn = e.target.closest('.btn-grant');
        if (grantBtn) {
            grantBtn.disabled = true;
            grantBtn.textContent = 'Activating...';
            const rid = grantBtn.dataset.resourceId;
            const card = grantBtn.closest('.resource-card');
            const autoOpenUrl = card?.dataset.autoOpenUrl || '';
            const res = await api('POST', '/api/access/grant', { resource_id: parseInt(rid) });
            if (res.success) {
                // Open the target service in a new tab before reload (browsers block popups triggered after async ops only if they lose the user-gesture context; opened synchronously in the click handler scope)
                if (autoOpenUrl) {
                    window.open(autoOpenUrl, '_blank', 'noopener');
                }
                location.reload();
            } else {
                alert(res.error || 'Failed to activate');
                grantBtn.disabled = false;
                grantBtn.textContent = 'Activate';
            }
        }

        const revokeBtn = e.target.closest('.btn-revoke');
        if (revokeBtn) {
            revokeBtn.disabled = true;
            revokeBtn.textContent = 'Deactivating...';
            const rid = revokeBtn.dataset.resourceId;
            const res = await api('POST', '/api/access/revoke', { resource_id: parseInt(rid) });
            if (res.success) {
                location.reload();
            } else {
                alert(res.error || 'Failed to deactivate');
                revokeBtn.disabled = false;
                revokeBtn.textContent = 'Deactivate';
            }
        }
    });

    // Grant All / Revoke All
    document.getElementById('btn-grant-all')?.addEventListener('click', async () => {
        const res = await api('POST', '/api/access/grant-all');
        location.reload();
    });

    document.getElementById('btn-revoke-all')?.addEventListener('click', async () => {
        await api('POST', '/api/access/revoke-all');
        location.reload();
    });

    // Start countdown timers
    startCountdowns();

    // Start keepalive if there are active sessions
    const activeCards = grid.querySelectorAll('.resource-active');
    if (activeCards.length > 0) {
        startKeepalive();
    }
}

function startCountdowns() {
    document.querySelectorAll('.timer-value[data-seconds]').forEach(el => {
        let seconds = parseInt(el.dataset.seconds);
        const card = el.closest('.resource-card');

        const timer = setInterval(() => {
            seconds--;
            if (seconds <= 0) {
                clearInterval(timer);
                location.reload();
                return;
            }

            const mm = Math.floor(seconds / 60).toString().padStart(2, '0');
            const ss = (seconds % 60).toString().padStart(2, '0');
            el.textContent = `${mm}:${ss}`;

            // Color transitions
            el.classList.remove('timer-warning', 'timer-danger');
            if (seconds < 60) {
                el.classList.add('timer-danger');
                card?.classList.remove('resource-active');
                card?.classList.add('resource-warning');
            } else if (seconds < 120) {
                el.classList.add('timer-warning');
            }
        }, 1000);
    });
}

function startKeepalive() {
    keepaliveTimer = setInterval(async () => {
        try {
            const res = await api('POST', '/api/keepalive');
            if (res.success && res.sessions) {
                // Update timers with fresh data
                res.sessions.forEach(s => {
                    const card = document.querySelector(`[data-resource-id="${s.resource_id}"]`);
                    const timer = card?.querySelector('.timer-value');
                    if (timer) {
                        timer.dataset.seconds = s.expires_in_seconds;
                    }
                });
                // Hide warning if shown
                const warn = document.getElementById('portal-warning');
                if (warn) warn.style.display = 'none';
            }
        } catch (err) {
            const warn = document.getElementById('portal-warning');
            if (warn) {
                warn.textContent = 'Keepalive failed — access may expire soon';
                warn.style.display = 'block';
            }
        }
    }, KEEPALIVE_INTERVAL);
}

// ── Admin Dashboard ──
async function initAdminDashboard() {
    if (!document.getElementById('dashboard-stats')) return;

    try {
        const data = await api('GET', '/api/admin/dashboard');
        document.getElementById('stat-active-sessions').textContent = data.active_sessions;
        document.getElementById('stat-recent-logins').textContent = data.recent_logins;
        document.getElementById('stat-failed-logins').textContent = data.failed_logins;
        document.getElementById('stat-total-users').textContent = data.total_users;
    } catch (e) {}

    // Recent audit
    try {
        const audit = await api('GET', '/api/admin/audit?page=1');
        const tbody = document.getElementById('recent-audit-body');
        if (audit.rows && audit.rows.length) {
            tbody.innerHTML = audit.rows.slice(0, 15).map(r => `
                <tr>
                    <td class="mono">${escTime(r.created_at)}</td>
                    <td>${esc(r.username || '--')}</td>
                    <td><span class="badge badge-${actionBadge(r.action)}">${esc(r.action)}</span></td>
                    <td class="mono" style="max-width:300px;overflow:hidden;text-overflow:ellipsis">${esc(r.details || '')}</td>
                    <td class="mono">${esc(r.source_ip || '')}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted">No recent activity</td></tr>';
        }
    } catch (e) {}
}

// ── Password Generator ──
function generatePassword(length = 16) {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower = 'abcdefghjkmnpqrstuvwxyz';
    const digits = '23456789';
    const symbols = '!@#$%&*?';
    const all = upper + lower + digits + symbols;

    const arr = new Uint8Array(length);
    crypto.getRandomValues(arr);

    // Ensure at least one of each type
    let pw = '';
    pw += upper[arr[0] % upper.length];
    pw += lower[arr[1] % lower.length];
    pw += digits[arr[2] % digits.length];
    pw += symbols[arr[3] % symbols.length];

    for (let i = 4; i < length; i++) {
        pw += all[arr[i] % all.length];
    }

    // Shuffle
    return pw.split('').sort(() => 0.5 - Math.random()).join('');
}

// ── Admin Users ──
function initAdminUsers() {
    const table = document.getElementById('users-table');
    if (!table) return;

    loadUsers();

    document.getElementById('btn-add-user')?.addEventListener('click', () => {
        document.getElementById('user-form-panel').style.display = 'block';
        document.getElementById('user-form-title').textContent = 'Add User';
        document.getElementById('user-form').reset();
        document.getElementById('user-edit-id').value = '';
        document.getElementById('uf-password').required = true;
    });

    document.getElementById('btn-gen-password')?.addEventListener('click', () => {
        const pw = generatePassword(16);
        const input = document.getElementById('uf-password');
        input.value = pw;
        input.type = 'text';
        input.select();
    });

    document.getElementById('btn-cancel-user')?.addEventListener('click', () => {
        document.getElementById('user-form-panel').style.display = 'none';
    });

    document.getElementById('user-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const editId = document.getElementById('user-edit-id').value;
        const data = {
            username: document.getElementById('uf-username').value,
            password: document.getElementById('uf-password').value,
            display_name: document.getElementById('uf-display-name').value,
            wireguard_ip: document.getElementById('uf-wg-ip').value,
            role: document.getElementById('uf-role').value,
            allowed_source_ips: document.getElementById('uf-allowed-ips').value,
        };

        if (editId) {
            await api('PUT', `/api/admin/users/${editId}`, data);
        } else {
            await api('POST', '/api/admin/users', data);
        }

        document.getElementById('user-form-panel').style.display = 'none';
        loadUsers();
    });

    // Table actions via delegation
    table.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;

        const id = btn.dataset.id;
        if (btn.classList.contains('btn-edit-user')) {
            editUser(id);
        } else if (btn.classList.contains('btn-toggle-user')) {
            await api('POST', `/api/admin/users/${id}/toggle`);
            loadUsers();
        } else if (btn.classList.contains('btn-reset-2fa')) {
            if (confirm('Reset 2FA for this user?')) {
                await api('POST', `/api/admin/users/${id}/reset-2fa`);
                loadUsers();
            }
        } else if (btn.classList.contains('btn-delete-user')) {
            if (confirm('Delete this user?')) {
                await api('DELETE', `/api/admin/users/${id}`);
                loadUsers();
            }
        }
    });
}

async function loadUsers() {
    const res = await api('GET', '/api/admin/users');
    const tbody = document.getElementById('users-body');
    if (!res.users?.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-muted">No users</td></tr>';
        return;
    }
    tbody.innerHTML = res.users.map(u => `
        <tr>
            <td class="mono">${u.id}</td>
            <td class="mono">${esc(u.username)}</td>
            <td>${esc(u.display_name || '')}</td>
            <td class="mono">${esc(u.wireguard_ip)}</td>
            <td><span class="badge badge-${u.role === 'admin' ? 'admin' : 'user'}">${u.role}</span></td>
            <td>${u.totp_enabled ? '<span class="text-active">Yes</span>' : '<span class="text-muted">No</span>'}</td>
            <td><span class="badge ${u.enabled ? 'badge-active' : 'badge-inactive'}">${u.enabled ? 'Active' : 'Disabled'}</span></td>
            <td>
                <button class="btn btn-sm btn-secondary btn-edit-user" data-id="${u.id}">Edit</button>
                <button class="btn btn-sm btn-secondary btn-toggle-user" data-id="${u.id}">${u.enabled ? 'Disable' : 'Enable'}</button>
                <button class="btn btn-sm btn-secondary btn-reset-2fa" data-id="${u.id}">Reset 2FA</button>
                <button class="btn btn-sm btn-danger btn-delete-user" data-id="${u.id}">Delete</button>
            </td>
        </tr>
    `).join('');
}

async function editUser(id) {
    const res = await api('GET', '/api/admin/users');
    const user = res.users.find(u => u.id == id);
    if (!user) return;

    document.getElementById('user-form-panel').style.display = 'block';
    document.getElementById('user-form-title').textContent = 'Edit User';
    document.getElementById('user-edit-id').value = user.id;
    document.getElementById('uf-username').value = user.username;
    document.getElementById('uf-password').value = '';
    document.getElementById('uf-password').required = false;
    document.getElementById('uf-display-name').value = user.display_name || '';
    document.getElementById('uf-wg-ip').value = user.wireguard_ip;
    document.getElementById('uf-role').value = user.role;
    document.getElementById('uf-allowed-ips').value = user.allowed_source_ips || '';
}

// ── Admin Resources ──
function initAdminResources() {
    const table = document.getElementById('resources-table');
    if (!table) return;

    loadResources();

    document.getElementById('btn-add-resource')?.addEventListener('click', () => {
        document.getElementById('resource-form-panel').style.display = 'block';
        document.getElementById('resource-form-title').textContent = 'Add Resource';
        document.getElementById('resource-form').reset();
        document.getElementById('res-edit-id').value = '';
    });

    document.getElementById('btn-cancel-resource')?.addEventListener('click', () => {
        document.getElementById('resource-form-panel').style.display = 'none';
    });

    document.getElementById('resource-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const editId = document.getElementById('res-edit-id').value;
        const data = {
            name: document.getElementById('rf-name').value,
            address_list_name: document.getElementById('rf-list').value,
            dst_address: document.getElementById('rf-dst').value,
            dst_port: document.getElementById('rf-port').value,
            protocol: document.getElementById('rf-proto').value,
            timeout_minutes: parseInt(document.getElementById('rf-timeout').value),
            description: document.getElementById('rf-desc').value,
            domain_name: document.getElementById('rf-domain')?.value || '',
        };

        if (editId) {
            await api('PUT', `/api/admin/resources/${editId}`, data);
        } else {
            await api('POST', '/api/admin/resources', data);
        }

        document.getElementById('resource-form-panel').style.display = 'none';
        loadResources();
    });

    table.addEventListener('click', async (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const id = btn.dataset.id;

        if (btn.classList.contains('btn-edit-resource')) {
            editResource(id);
        } else if (btn.classList.contains('btn-delete-resource')) {
            if (confirm('Delete this resource?')) {
                await api('DELETE', `/api/admin/resources/${id}`);
                loadResources();
            }
        }
    });
}

async function loadResources() {
    const res = await api('GET', '/api/admin/resources');
    const tbody = document.getElementById('resources-body');
    if (!res.resources?.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-muted">No resources</td></tr>';
        return;
    }
    tbody.innerHTML = res.resources.map(r => `
        <tr>
            <td class="mono">${r.id}</td>
            <td>${esc(r.name)}</td>
            <td class="mono">${esc(r.address_list_name)}</td>
            <td class="mono">${esc(r.dst_address)}</td>
            <td class="mono">${esc(r.dst_port)}</td>
            <td class="mono">${esc(r.domain_name || '—')}</td>
            <td>${esc(r.protocol)}</td>
            <td>${r.timeout_minutes}min</td>
            <td><span class="badge ${r.enabled ? 'badge-active' : 'badge-inactive'}">${r.enabled ? 'Active' : 'Disabled'}</span></td>
            <td>
                <button class="btn btn-sm btn-secondary btn-edit-resource" data-id="${r.id}">Edit</button>
                <button class="btn btn-sm btn-danger btn-delete-resource" data-id="${r.id}">Delete</button>
            </td>
        </tr>
    `).join('');
}

async function editResource(id) {
    const res = await api('GET', '/api/admin/resources');
    const r = res.resources.find(r => r.id == id);
    if (!r) return;

    document.getElementById('resource-form-panel').style.display = 'block';
    document.getElementById('resource-form-title').textContent = 'Edit Resource';
    document.getElementById('res-edit-id').value = r.id;
    document.getElementById('rf-name').value = r.name;
    document.getElementById('rf-list').value = r.address_list_name;
    document.getElementById('rf-dst').value = r.dst_address;
    document.getElementById('rf-port').value = r.dst_port;
    document.getElementById('rf-proto').value = r.protocol;
    document.getElementById('rf-timeout').value = r.timeout_minutes;
    document.getElementById('rf-desc').value = r.description || '';
    const dom = document.getElementById('rf-domain');
    if (dom) dom.value = r.domain_name || '';
}

// ── Admin Permissions ──
function initAdminPermissions() {
    const table = document.getElementById('permissions-table');
    if (!table) return;

    loadPermissions();

    document.getElementById('btn-add-permission')?.addEventListener('click', async () => {
        document.getElementById('perm-form-panel').style.display = 'block';
        // Load users and resources for dropdowns
        const [users, resources] = await Promise.all([
            api('GET', '/api/admin/users'),
            api('GET', '/api/admin/resources'),
        ]);

        const userSel = document.getElementById('pf-user');
        userSel.innerHTML = '<option value="">Select user...</option>' +
            (users.users || []).map(u => `<option value="${u.id}">${esc(u.username)}</option>`).join('');

        const resSel = document.getElementById('pf-resource');
        resSel.innerHTML = '<option value="">Select resource...</option>' +
            (resources.resources || []).map(r => `<option value="${r.id}">${esc(r.name)}</option>`).join('');
    });

    document.getElementById('btn-cancel-permission')?.addEventListener('click', () => {
        document.getElementById('perm-form-panel').style.display = 'none';
    });

    document.getElementById('permission-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await api('POST', '/api/admin/permissions', {
            user_id: parseInt(document.getElementById('pf-user').value),
            resource_id: parseInt(document.getElementById('pf-resource').value),
        });
        document.getElementById('perm-form-panel').style.display = 'none';
        loadPermissions();
    });

    table.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-delete-perm');
        if (btn) {
            await api('DELETE', `/api/admin/permissions/${btn.dataset.id}`);
            loadPermissions();
        }
    });
}

async function loadPermissions() {
    const res = await api('GET', '/api/admin/permissions');
    const tbody = document.getElementById('permissions-body');
    if (!res.permissions?.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted">No permissions</td></tr>';
        return;
    }
    tbody.innerHTML = res.permissions.map(p => `
        <tr>
            <td class="mono">${p.id}</td>
            <td>${esc(p.username)}</td>
            <td>${esc(p.resource_name)}</td>
            <td class="mono">${escTime(p.created_at)}</td>
            <td><button class="btn btn-sm btn-danger btn-delete-perm" data-id="${p.id}">Revoke</button></td>
        </tr>
    `).join('');
}

// ── Admin Sessions ──
function initAdminSessions() {
    const table = document.getElementById('sessions-table');
    if (!table) return;

    loadSessions();

    document.getElementById('btn-refresh-sessions')?.addEventListener('click', loadSessions);

    table.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-kill-session');
        if (!btn) return;

        // Inline confirm
        if (!btn.dataset.confirmed) {
            btn.textContent = 'Confirm Kill?';
            btn.dataset.confirmed = '1';
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-danger');
            setTimeout(() => {
                btn.textContent = 'Kill';
                delete btn.dataset.confirmed;
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-secondary');
            }, 3000);
            return;
        }

        await api('POST', `/api/admin/sessions/${btn.dataset.id}/kill`);
        loadSessions();
    });
}

async function loadSessions() {
    const res = await api('GET', '/api/admin/sessions');
    const tbody = document.getElementById('sessions-body');
    if (!res.sessions?.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-muted">No active sessions</td></tr>';
        return;
    }
    tbody.innerHTML = res.sessions.map(s => `
        <tr>
            <td class="mono">${s.id}</td>
            <td>${esc(s.username)}</td>
            <td>${esc(s.resource_name)}</td>
            <td class="mono">${esc(s.source_ip)}</td>
            <td class="mono">${escTime(s.granted_at)}</td>
            <td class="mono">${escTime(s.expires_at)}</td>
            <td class="mono">${escTime(s.last_keepalive)}</td>
            <td><button class="btn btn-sm btn-secondary btn-kill-session" data-id="${s.id}">Kill</button></td>
        </tr>
    `).join('');
}

// ── Admin Audit ──
let auditPage = 1;

function initAdminAudit() {
    const table = document.getElementById('audit-table');
    if (!table) return;

    loadAudit();
    loadAuditFilters();

    document.getElementById('audit-filters')?.addEventListener('submit', (e) => {
        e.preventDefault();
        auditPage = 1;
        loadAudit();
    });

    document.getElementById('btn-reset-filters')?.addEventListener('click', () => {
        document.getElementById('audit-filters').reset();
        auditPage = 1;
        loadAudit();
    });
}

async function loadAudit() {
    const params = new URLSearchParams();
    params.set('page', auditPage);

    const action = document.getElementById('af-action')?.value;
    const userId = document.getElementById('af-user')?.value;
    const from = document.getElementById('af-from')?.value;
    const to = document.getElementById('af-to')?.value;

    if (action) params.set('action', action);
    if (userId) params.set('user_id', userId);
    if (from) params.set('from', from);
    if (to) params.set('to', to);

    const res = await api('GET', `/api/admin/audit?${params}`);
    const tbody = document.getElementById('audit-body');

    if (!res.rows?.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted">No entries</td></tr>';
        document.getElementById('audit-pagination').innerHTML = '';
        return;
    }

    tbody.innerHTML = res.rows.map(r => `
        <tr>
            <td class="mono">${escTime(r.created_at)}</td>
            <td>${esc(r.username || '--')}</td>
            <td><span class="badge badge-${actionBadge(r.action)}">${esc(r.action)}</span></td>
            <td class="mono" style="max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.details || '')}</td>
            <td class="mono">${esc(r.source_ip || '')}</td>
        </tr>
    `).join('');

    // Pagination
    const pagDiv = document.getElementById('audit-pagination');
    if (res.total_pages > 1) {
        let html = '';
        for (let i = 1; i <= res.total_pages; i++) {
            html += `<button class="${i === res.page ? 'active' : ''}" onclick="auditPage=${i};loadAudit()">${i}</button>`;
        }
        pagDiv.innerHTML = html;
    } else {
        pagDiv.innerHTML = '';
    }
}

async function loadAuditFilters() {
    try {
        const [users, actions] = await Promise.all([
            api('GET', '/api/admin/users'),
            api('GET', '/api/admin/audit?page=1'),
        ]);

        const userSel = document.getElementById('af-user');
        if (userSel && users.users) {
            users.users.forEach(u => {
                userSel.innerHTML += `<option value="${u.id}">${esc(u.username)}</option>`;
            });
        }
    } catch (e) {}
}

// ── Admin Settings ──
async function initAdminSettings() {
    const form = document.getElementById('settings-form');
    if (!form) return;

    // Load current settings
    try {
        const res = await api('GET', '/api/admin/settings');
        if (res.settings) {
            const s = res.settings;
            if (s.portal_title) document.getElementById('sf-title').value = s.portal_title;
            if (s.totp_issuer) document.getElementById('sf-issuer').value = s.totp_issuer;
            if (s.session_timeout) document.getElementById('sf-timeout').value = s.session_timeout;
            if (s.default_timeout_minutes) document.getElementById('sf-def-timeout').value = s.default_timeout_minutes;
        }
    } catch (e) {}

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = {
            portal_title: document.getElementById('sf-title').value,
            totp_issuer: document.getElementById('sf-issuer').value,
            session_timeout: document.getElementById('sf-timeout').value,
            default_timeout_minutes: document.getElementById('sf-def-timeout').value,
        };
        const res = await api('POST', '/api/admin/settings', data);
        if (res.success) {
            alert('Settings saved');
        }
    });

    // MikroTik test
    document.getElementById('btn-test-mt')?.addEventListener('click', async () => {
        const resultEl = document.getElementById('mt-result');
        resultEl.textContent = 'Testing...';
        try {
            const res = await api('GET', '/api/admin/mt-test');
            if (res.success) {
                resultEl.innerHTML = '<span class="text-active">Connected: ' + esc(res.identity) + '</span>';
            } else {
                resultEl.innerHTML = '<span class="text-danger">' + esc(res.error) + '</span>';
            }
        } catch (e) {
            resultEl.innerHTML = '<span class="text-danger">Connection failed</span>';
        }
    });
}

// ── Helpers ──
function esc(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function escTime(dt) {
    if (!dt) return '--';
    return dt.replace('T', ' ').substring(0, 19);
}

function actionBadge(action) {
    if (action?.includes('fail') || action?.includes('mismatch') || action?.includes('block') || action?.includes('kill')) return 'danger';
    if (action?.includes('login') || action?.includes('grant') || action?.includes('enroll')) return 'active';
    if (action?.includes('admin')) return 'admin';
    return 'user';
}

// ── Help Toggle ──
function initHelp() {
    document.querySelectorAll('.help-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const panel = btn.closest('.admin-header')?.nextElementSibling;
            if (panel?.classList.contains('help-panel')) {
                panel.classList.toggle('visible');
                btn.classList.toggle('active');
            }
        });
    });
}

// ── Init ──
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initHelp();
    initLogin();
    init2FA();
    initEnroll2FA();
    initLogout();
    initPortal();
    initAdminDashboard();
    initAdminUsers();
    initAdminResources();
    initAdminPermissions();
    initAdminSessions();
    initAdminAudit();
    initAdminSettings();
});
