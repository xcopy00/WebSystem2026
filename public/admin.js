// Global state
let currentUser = null;

// Check authentication
document.addEventListener('DOMContentLoaded', async () => {
    if (!token) {
        alert('Please login first');
        window.location.href = '/';
        return;
    }

    try {
        // Get user profile
        const profileRes = await fetch('/api/auth/me', {
            headers: { 'Authorization': `Bearer ${token}` }
        });

        if (!profileRes.ok) {
            throw new Error('Not authenticated');
        }

        currentUser = await profileRes.json();

        // Check if admin
        if (currentUser.role !== 'Admin') {
            alert('Access denied. Admin only.');
            window.location.href = '/';
            return;
        }

        // Setup tabs
        setupTabs();

        // Load data
        loadDashboardStats();
        loadWorkerStatus();
        loadUsers();
        loadLicenses();
    } catch (error) {
        console.error('Auth error:', error);
        localStorage.removeItem('token');
        window.location.href = '/';
    }
});

// Setup tabs
function setupTabs() {
    document.querySelectorAll('.admin-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.admin-panel').forEach(p => p.classList.remove('active'));

            tab.classList.add('active');
            document.getElementById(tab.dataset.tab + 'Panel').classList.add('active');
        });
    });
}

// API helper
async function api(url, options = {}) {
    // Try the original URL first
    let res = await fetch(url, {
        ...options,
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            ...options.headers
        }
    });

    // If 404 and URL starts with /api/, try direct api.php endpoint
    if (res.status === 404 && url.startsWith('/api/')) {
        const route = url.replace(/^\/api\//, '').replace(/\?.*$/, '');
        const queryString = url.includes('?') ? url.substring(url.indexOf('?')) : '';
        const directUrl = '/api.php?route=' + route + queryString;
        
        res = await fetch(directUrl, {
            ...options,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                ...options.headers
            }
        });
    }

    if (!res.ok) {
        const error = await res.json().catch(() => ({ error: 'Request failed' }));
        throw new Error(error.error || 'Request failed');
    }

    return res.json();
}

// Load dashboard stats
async function loadDashboardStats() {
    try {
        const stats = await api('/api/admin/dashboard');
        
        // Update individual stats with proper ID matching
        const totalUsersEl = document.getElementById('totalUsers');
        if (totalUsersEl) totalUsersEl.textContent = stats.totalUsers || 0;
        
        const onlineUsersEl = document.getElementById('onlineUsers');
        if (onlineUsersEl) onlineUsersEl.textContent = stats.activeUsers || 0;
        
        const totalBotsEl = document.getElementById('totalBots');
        if (totalBotsEl) totalBotsEl.textContent = stats.totalBots || 0;
        
        const runningBotsEl = document.getElementById('runningBots');
        if (runningBotsEl) runningBotsEl.textContent = stats.runningBots || 0;
        
        // Also update stat- prefixed elements if they exist
        const statTotalUsers = document.getElementById('statTotalUsers');
        if (statTotalUsers) statTotalUsers.textContent = stats.totalUsers || 0;
        
        // Load subscription stats
        loadSubscriptionStats(stats.tierStats);
        
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
}

// Load subscription distribution stats
async function loadSubscriptionStats(tierStats = null) {
    try {
        // If tierStats not provided, try to get from dashboard stats
        if (!tierStats) {
            const stats = await api('/api/admin/dashboard');
            tierStats = stats.tierStats || {};
        }
        
        const tbody = document.getElementById('subscriptionStats');
        if (!tbody) return;
        
        // Map display names to database role names
        const dbRoleMapping = {
            'Free': 'User',
            'Silver': 'VIP1',
            'Gold': 'VIP2',
            'Platinum': 'VIP3',
            'Diamond': 'VIP4',
            'Platinum+': 'VIP5',
            'Diamond+': 'VIP6',
            'Admin': 'Admin',
            'Orphaned': 'Orphaned'
        };
        
        // Known display order
        const knownDisplayOrder = ['Free', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Platinum+', 'Diamond+', 'Admin', 'Orphaned'];
        
        let html = '';
        
        // First, add known tiers
        knownDisplayOrder.forEach(displayTier => {
            const dbRole = dbRoleMapping[displayTier];
            const data = tierStats[dbRole] || tierStats[displayTier] || { users: 0, bots: 0, running: 0 };
            if (data.users > 0 || data.bots > 0 || displayTier === 'Orphaned') {
                html +=
                    '<tr>' +
                    '<td>' + displayTier + '</td>' +
                    '<td>' + data.users + '</td>' +
                    '<td>' + data.bots + '</td>' +
                    '<td>' + data.running + '</td>' +
                    '</tr>';
            }
        });
        
        tbody.innerHTML = html || '<tr><td colspan="4">No subscription data found</td></tr>';
    } catch (error) {
        console.error('Failed to load subscription stats:', error);
        const tbody = document.getElementById('subscriptionStats');
        if (tbody) tbody.innerHTML = '<tr><td colspan="4">Error loading data</td></tr>';
    }
}

// Load worker status
async function loadWorkerStatus() {
    try {
        const status = await api('/api/worker/status');
        
        const statusDot = document.getElementById('workerStatusDot');
        const statusText = document.getElementById('workerStatusText');
        const details = document.getElementById('workerDetails');
        
        // Update status indicator
        if (status.status === 'running') {
            statusDot.classList.add('status-online');
            statusDot.classList.remove('status-offline', 'status-checking');
        } else {
            statusDot.classList.remove('status-online');
            statusDot.classList.add('status-offline');
        }
        
        statusText.textContent = status.statusText || status.status;
        
        // Update details
        let detailsHtml = '';
        detailsHtml += `<strong>Workers:</strong> ${status.workerCount || 0}<br>`;
        detailsHtml += `<strong>Running Bots:</strong> ${status.runningBots || 0}<br>`;
        detailsHtml += `<strong>Last Check:</strong> ${status.timestamp || '-'}`;
        
        // Show worker PIDs if available
        if (status.workers && status.workers.length > 0) {
            detailsHtml += `<br><strong>PIDs:</strong> ${status.workers.map(w => w.pid).join(', ')}`;
        }
        
        details.innerHTML = detailsHtml;
    } catch (error) {
        console.error('Failed to load worker status:', error);
        document.getElementById('workerStatusText').textContent = 'Error';
        document.getElementById('workerDetails').innerHTML = `<span style="color: #c00;">Failed to load: ${error.message}</span>`;
    }
}

// Load users
async function loadUsers() {
    try {
        const users = await api('/api/admin/users');
        const tbody = document.getElementById('usersTable');
 
        tbody.innerHTML = users.map(user => `
            <tr>
                <td>${user.id}</td>
                <td>${user.email}</td>
                <td>${user.name || '-'}</td>
                <td><span class="badge badge-success">${user.role}</span></td>
                <td>${user.bots_count || 0}</td>
                <td><span class="badge ${user.is_active ? 'badge-success' : 'badge-warning'}">${user.is_active ? 'Active' : 'Inactive'}</span></td>
                <td>${user.last_login || '-'}</td>
                <td>${user.ip_display || '-'}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="resetSubscription(${user.id}, &quot;${user.email}&quot;)">Reset Subscription</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="9">No users found</td></tr>';
    } catch (error) {
        console.error('Failed to load users:', error);
        document.getElementById('usersTable').innerHTML = '<tr><td colspan="9">Error loading users</td></tr>';
    }
}

// Delete user
async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    
    try {
        await api(`/api/admin/users/${userId}`, { method: 'DELETE' });
        loadUsers();
    } catch (error) {
        alert('Failed to delete user: ' + error.message);
    }
}

// Modal functions for confirmation
let currentAction = null;
let currentId = null;

function showConfirmModal(title, text, confirmText, onConfirm) {
    const modal = document.getElementById('confirmModal');
    const titleEl = document.getElementById('confirmTitle');
    const textEl = document.getElementById('confirmText');
    const btnEl = document.getElementById('confirmBtn');
    
    titleEl.textContent = title;
    textEl.textContent = text;
    btnEl.textContent = confirmText;
    
    currentAction = onConfirm;
    modal.classList.add('active');
}

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    modal.classList.remove('active');
    currentAction = null;
    currentId = null;
}

function executeConfirmAction() {
    console.log('executeConfirmAction called, currentAction:', typeof currentAction);
    if (typeof currentAction === 'function') {
        currentAction();
    } else {
        console.error('currentAction is not a function:', currentAction);
    }
}

// Reset user subscription with modal
function resetSubscription(userId, userEmail) {
    showConfirmModal(
        'Reset Subscription',
        `Are you sure you want to reset the subscription for "${userEmail}"? This will downgrade them to Free tier.`,
        'Reset',
        async () => {
            try {
                await api(`/api/admin/users/${userId}/reset`, { method: 'POST' });
                closeConfirmModal();
                loadUsers();
            } catch (error) {
                alert('Failed to reset subscription: ' + error.message);
            }
        }
    );
}

// Delete license with modal
function deleteLicense(licenseId, licenseCode) {
    showConfirmModal(
        'Delete License',
        `Are you sure you want to delete license "${licenseCode}"? This action cannot be undone.`,
        'Delete',
        async () => {
            try {
                await api('/api/admin/licenses/delete', { 
                    method: 'POST',
                    body: JSON.stringify({ id: licenseId })
                });
                closeConfirmModal();
                loadLicenses();
            } catch (error) {
                alert('Failed to delete license: ' + error.message);
            }
        }
    );
}

// Load licenses
async function loadLicenses() {
    try {
        const licenses = await api('/api/admin/licenses');
        const tbody = document.getElementById('licenseTable');
        
        tbody.innerHTML = licenses.map(lic => `
            <tr>
                <td>${lic.id}</td>
                <td>${lic.code}</td>
                <td>${lic.type}</td>
                <td><span class="badge ${lic.is_used ? 'badge-success' : 'badge-warning'}">${lic.is_used ? 'Active' : 'Inactive'}</span></td>
                <td>${lic.used_by_email || '-'}</td>
                <td>${lic.expires_at}</td>
                <td>${lic.created_at}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="deleteLicense(${lic.id}, &quot;${lic.code}&quot;)">Delete</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="8">No licenses found</td></tr>';
    } catch (error) {
        console.error('Failed to load licenses:', error);
        document.getElementById('licenseTable').innerHTML = '<tr><td colspan="8">Error loading licenses</td></tr>';
    }
}
