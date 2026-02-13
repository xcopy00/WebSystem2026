// Binance AI Trade - Frontend JavaScript
// Full-stack implementation with real API calls

// API Configuration
const API_BASE = window.location.origin;

// Global state
let currentUser = null;
let authToken = null;
let bots = [];
let trades = [];
let logs = [];

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    console.log('App.js loaded - Full Stack Mode');
    loadAuthState();
    setupEventListeners();
    
    if (currentUser && authToken) {
        loadAllData();
    }
});

// Load authentication state from localStorage
function loadAuthState() {
    const token = localStorage.getItem('authToken');
    const userData = localStorage.getItem('currentUser');
    
    if (token && userData) {
        authToken = token;
        currentUser = JSON.parse(userData);
        updateUserDisplay();
        showDashboard();
    }
}

// Save authentication state
function saveAuthState(token, user) {
    authToken = token;
    currentUser = user;
    localStorage.setItem('authToken', token);
    localStorage.setItem('currentUser', JSON.stringify(user));
}

// Clear authentication state
function clearAuthState() {
    authToken = null;
    currentUser = null;
    bots = [];
    trades = [];
    logs = [];
    localStorage.removeItem('authToken');
    localStorage.removeItem('currentUser');
}

// Setup event listeners
function setupEventListeners() {
    // Login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    // Register form
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
    
    // Bot creation form
    const botForm = document.getElementById('botForm');
    if (botForm) {
        botForm.addEventListener('submit', handleCreateBot);
    }
}

// ==================== AUTHENTICATION ====================

async function handleLogin(e) {
    e.preventDefault();
    
    const email = document.getElementById('loginEmail')?.value;
    const password = document.getElementById('loginPassword')?.value;
    
    if (!email || !password) {
        showToast('Please enter email and password', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/api.php?action=login&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`, {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.error) {
            showToast(data.error, 'error');
            return;
        }
        
        saveAuthState(data.token, data.user);
        showDashboard();
        loadAllData();
        showToast('Login successful!', 'success');
        
    } catch (error) {
        console.error('Login error:', error);
        showToast('Login failed. Please try again.', 'error');
    }
}

async function handleRegister(e) {
    e.preventDefault();
    
    const name = document.getElementById('registerName')?.value;
    const email = document.getElementById('registerEmail')?.value;
    const password = document.getElementById('registerPassword')?.value;
    const confirmPassword = document.getElementById('confirmPassword')?.value;
    
    if (!name || !email || !password) {
        showToast('Please fill in all fields', 'error');
        return;
    }
    
    if (password !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/api.php?action=register&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`, {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.error) {
            showToast(data.error, 'error');
            return;
        }
        
        saveAuthState(data.token, data.user);
        showDashboard();
        loadAllData();
        showToast('Registration successful!', 'success');
        
    } catch (error) {
        console.error('Registration error:', error);
        showToast('Registration failed. Please try again.', 'error');
    }
}

function logout() {
    clearAuthState();
    showLogin();
    showToast('Logged out successfully', 'info');
}

// ==================== API HELPER ====================

async function apiRequest(endpoint, options = {}) {
    const headers = {};
    
    if (authToken) {
        headers['Authorization'] = `Bearer ${authToken}`;
    }
    
    // Convert endpoint to action query param
    let action = endpoint;
    
    // Handle parameterized endpoints
    const botIdMatch = endpoint.match(/^bots\/(\d+)\/(start|stop|status|trades|logs)$/);
    if (botIdMatch) {
        action = `bots/${botIdMatch[1]}/${botIdMatch[2]}`;
    } else if (endpoint.match(/^bots\/(\d+)$/)) {
        action = endpoint;
    }
    
    let url = `${API_BASE}/api.php?action=${action}`;
    
    const fetchOptions = {
        method: options.method || 'GET',
        headers: headers
    };
    
    // For POST requests with body, use form-encoded data
    if (fetchOptions.method === 'POST' && options.body) {
        const params = new URLSearchParams();
        Object.entries(options.body).forEach(([key, value]) => {
            params.append(key, value);
        });
        fetchOptions.body = params.toString();
        headers['Content-Type'] = 'application/x-www-form-urlencoded';
    }
    
    const response = await fetch(url, fetchOptions);
    
    const data = await response.json();
    
    if (data.error && response.status === 401) {
        // Token expired
        clearAuthState();
        showLogin();
        showToast('Session expired. Please login again.', 'error');
    }
    
    return data;
}

// ==================== DATA LOADING ====================

async function loadAllData() {
    await Promise.all([
        loadBots(),
        loadTrades(),
        loadLogs(),
        loadUserProfile(),
        checkServerStatus()
    ]);
    renderAll();
}

async function loadUserProfile() {
    try {
        const user = await apiRequest('auth/me');
        if (user.id) {
            currentUser = { ...currentUser, ...user };
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            updateUserDisplay();
        }
    } catch (error) {
        console.error('Error loading user profile:', error);
    }
}

async function loadBots() {
    try {
        bots = await apiRequest('bots');
        if (!Array.isArray(bots)) bots = [];
    } catch (error) {
        console.error('Error loading bots:', error);
        bots = [];
    }
}

async function loadTrades() {
    try {
        trades = await apiRequest('trades');
        if (!Array.isArray(trades)) trades = [];
    } catch (error) {
        console.error('Error loading trades:', error);
        trades = [];
    }
}

async function loadLogs() {
    try {
        logs = await apiRequest('bots/logs');
        if (!Array.isArray(logs)) logs = [];
    } catch (error) {
        console.error('Error loading logs:', error);
        logs = [];
    }
}

// ==================== BOT OPERATIONS ====================

async function handleCreateBot(e) {
    e.preventDefault();
    
    const name = document.getElementById('botName')?.value;
    const symbol = document.getElementById('botSymbol')?.value;
    const type = document.getElementById('botType')?.value;
    const strategy = document.getElementById('botStrategy')?.value;
    const investment = document.getElementById('botInvestment')?.value;
    const gridLevels = document.getElementById('botGridLevels')?.value;
    const lowerPrice = document.getElementById('botLowerPrice')?.value;
    const upperPrice = document.getElementById('botUpperPrice')?.value;
    
    if (!name || !symbol || !type || !strategy || !investment) {
        showToast('Please fill in all required fields', 'error');
        return;
    }
    
    try {
        const botData = {
            name,
            symbol: symbol.replace('/', '').toUpperCase(),
            type,
            strategy,
            investment: parseFloat(investment),
            grid_levels: parseInt(gridLevels) || 10,
            lower_price: parseFloat(lowerPrice) || 0,
            upper_price: parseFloat(upperPrice) || 0
        };
        
        const response = await apiRequest('bots', {
            method: 'POST',
            body: {
                name,
                symbol: symbol.replace('/', '').toUpperCase(),
                type,
                strategy,
                investment: parseFloat(investment),
                grid_levels: parseInt(gridLevels) || 10,
                lower_price: parseFloat(lowerPrice) || 0,
                upper_price: parseFloat(upperPrice) || 0
            }
        });
        
        if (response.success === false) {
            showToast(response.error || 'Failed to create bot', 'error');
            return;
        }
        
        // Refresh bots list
        await loadBots();
        renderBots();
        renderStats();
        
        // Close form
        const form = document.getElementById('createBotForm');
        if (form) form.style.display = 'none';
        
        const botForm = document.getElementById('botForm');
        if (botForm) botForm.reset();
        
        showToast('Bot created successfully!', 'success');
        
    } catch (error) {
        console.error('Create bot error:', error);
        showToast('Failed to create bot', 'error');
    }
}

async function startBot(botId) {
    try {
        const response = await apiRequest(`bots/${botId}/start`, { method: 'POST' });
        
        if (response.success) {
            await loadBots();
            renderBots();
            showToast('Bot started successfully!', 'success');
        } else {
            showToast(response.error || 'Failed to start bot', 'error');
        }
    } catch (error) {
        console.error('Start bot error:', error);
        showToast('Failed to start bot', 'error');
    }
}

async function stopBot(botId) {
    try {
        const response = await apiRequest(`bots/${botId}/stop`, { method: 'POST' });
        
        if (response.success) {
            await loadBots();
            renderBots();
            showToast('Bot stopped successfully!', 'success');
        } else {
            showToast(response.error || 'Failed to stop bot', 'error');
        }
    } catch (error) {
        console.error('Stop bot error:', error);
        showToast('Failed to stop bot', 'error');
    }
}

async function deleteBot(botId) {
    showConfirmModal(
        'Are you sure?',
        'This action cannot be undone.',
        'DELETE',
        async () => {
            try {
                const response = await apiRequest(`bots/${botId}`, { method: 'DELETE' });
                
                if (response.success) {
                    await loadBots();
                    renderBots();
                    renderStats();
                    showToast('Bot deleted successfully!', 'success');
                } else {
                    showToast(response.error || 'Failed to delete bot', 'error');
                }
            } catch (error) {
                console.error('Delete bot error:', error);
                showToast('Failed to delete bot', 'error');
            }
        }
    );
}

function viewBotDetails(botId) {
    const bot = bots.find(b => b.id === botId);
    if (bot) {
        showToast(`${bot.name}: ${bot.symbol} - ${bot.strategy} - $${bot.investment}`, 'info');
    }
}

// ==================== SERVER STATUS ====================

async function checkServerStatus() {
    const statusEl = document.getElementById('serverStatus');
    const statusText = document.getElementById('statusText');
    const apiMode = document.getElementById('apiMode');
    
    if (!statusEl) return;
    
    try {
        const data = await apiRequest('server/status');
        
        statusEl.classList.remove('connected', 'testnet', 'disconnected');
        
        if (data.connected) {
            statusEl.classList.add(data.mode === 'live' ? 'connected' : 'testnet');
            if (statusText) statusText.textContent = 'Connected';
            if (apiMode) apiMode.textContent = data.mode === 'live' ? 'Live' : 'Testnet';
        } else {
            statusEl.classList.add('disconnected');
            if (statusText) statusText.textContent = 'No API Keys';
            if (apiMode) apiMode.textContent = 'Demo Mode';
        }
    } catch (error) {
        console.error('Server status error:', error);
        if (statusEl) {
            statusEl.classList.remove('connected', 'testnet');
            statusEl.classList.add('disconnected');
        }
        if (statusText) statusText.textContent = 'Disconnected';
    }
}

// ==================== TRADING ====================

async function loadMarketData(symbol) {
    try {
        const data = await apiRequest(`market/${symbol}`);
        return data;
    } catch (error) {
        console.error('Market data error:', error);
        return null;
    }
}

// ==================== UI FUNCTIONS ====================

function showDashboard() {
    // Dashboard is always visible, just ensure user info is updated
}

function showLogin() {
    // Redirect to login page
    window.location.href = 'login.html';
}

function logout() {
    clearAuthState();
    showLogin();
}

function toggleCreateBotForm() {
    const form = document.getElementById('createBotForm');
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
}

function updateUserDisplay() {
    if (!currentUser) return;
    
    const userAvatar = document.getElementById('userAvatar');
    const userEmail = document.getElementById('userEmail');
    const userVipTier = document.getElementById('userVipTier');
    const vipTier = document.getElementById('vipTier');
    const dropdownUserEmail = document.getElementById('dropdownUserEmail');
    
    if (userAvatar) {
        userAvatar.textContent = currentUser.name ? currentUser.name.charAt(0).toUpperCase() : (currentUser.email ? currentUser.email.charAt(0).toUpperCase() : 'U');
    }
    if (userEmail) {
        userEmail.textContent = currentUser.email || 'User';
    }
    if (dropdownUserEmail) {
        dropdownUserEmail.textContent = currentUser.email || 'User';
    }
    if (userVipTier) {
        userVipTier.textContent = 'VIP: ' + (currentUser.role || 'User');
    }
    if (vipTier) {
        vipTier.textContent = currentUser.role || 'User';
    }
}

function renderAll() {
    renderBots();
    renderTrades();
    renderLogs();
    renderStats();
}

function renderBots() {
    const container = document.getElementById('botsList');
    if (!container) return;
    
    if (bots.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <p>No bots yet. Create your first bot!</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = bots.map(bot => `
        <div class="bot-item">
            <div class="bot-info">
                <h4>${escapeHtml(bot.name)}</h4>
                <p>${bot.symbol} - ${bot.strategy}</p>
            </div>
            <div class="bot-actions">
                <span style="background: ${bot.status === 'running' ? '#10b981' : '#6b7280'}; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem;">
                    ${bot.status}
                </span>
                <span style="color: #888; font-size: 0.875rem;">
                    ${parseFloat(bot.investment || 0).toFixed(2)} | P/L: ${parseFloat(bot.profit || 0).toFixed(2)}
                </span>
                ${bot.status === 'running' 
                    ? `<button class="btn-warning" onclick="stopBot(${bot.id})">Stop</button>`
                    : `<button class="btn-success" onclick="startBot(${bot.id})">Start</button>`
                }
                <button class="btn-danger" onclick="deleteBot(${bot.id})">Delete</button>
            </div>
        </div>
    `).join('');
}

function renderTrades() {
    const container = document.getElementById('tradesList');
    if (!container) return;
    
    if (trades.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-graph-up" style="font-size: 48px;"></i>
                <p class="mt-2">No trades yet</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = trades.slice(0, 10).map(trade => `
        <div class="trade-item d-flex justify-content-between align-items-center py-2 border-bottom">
            <div>
                <span class="badge ${trade.side === 'buy' ? 'bg-success' : 'bg-danger'}">${trade.side.toUpperCase()}</span>
                <span class="ms-2">${trade.symbol}</span>
            </div>
            <div class="text-end">
                <small>${trade.quantity} @ $${parseFloat(trade.price || 0).toFixed(2)}</small>
                <br>
                <small class="${parseFloat(trade.profit_loss || 0) >= 0 ? 'text-success' : 'text-danger'}">
                    $${parseFloat(trade.profit_loss || 0).toFixed(2)}
                </small>
            </div>
        </div>
    `).join('');
}

function renderLogs() {
    const container = document.getElementById('logsList');
    if (!container) return;
    
    if (logs.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p>No logs yet</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = logs.slice(0, 20).map(log => `
        <div style="padding: 12px 0; border-bottom: 1px solid #222;">
            <small style="color: #888;">${new Date(log.created_at).toLocaleString()}</small>
            <br>
            <span style="background: ${log.type === 'error' ? '#ef4444' : log.type === 'warning' ? '#f59e0b' : '#6366f1'}; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; margin-right: 8px;">
                ${log.type}
            </span>
            <span style="color: #fff;">${escapeHtml(log.message)}</span>
        </div>
    `).join('');
}

function renderStats() {
    const totalBots = bots.length;
    const activeBots = bots.filter(b => b.status === 'running').length;
    const totalInvestment = bots.reduce((sum, b) => sum + parseFloat(b.investment || 0), 0);
    const totalProfit = bots.reduce((sum, b) => sum + parseFloat(b.profit || 0), 0);
    
    const balanceEl = document.getElementById('balanceValue');
    const activeBotsEl = document.getElementById('activeBotsCount');
    const todayProfitEl = document.getElementById('todayProfit');
    
    if (balanceEl) balanceEl.textContent = '$10,000';
    if (activeBotsEl) activeBotsEl.textContent = activeBots;
    if (todayProfitEl) {
        todayProfitEl.textContent = `${totalProfit.toFixed(2)}`;
        todayProfitEl.style.color = totalProfit >= 0 ? '#10b981' : '#ef4444';
    }
}

// User Dropdown Functions
function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdownMenu');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) {
        console.log(`[${type}] ${message}`);
        return;
    }
    
    toast.textContent = message;
    toast.className = 'toast ' + type;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Helper: Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Custom Confirmation Modal
function showConfirmModal(title, message, confirmText, onConfirm) {
    // Remove existing modal if any
    const existingModal = document.getElementById('confirmModal');
    if (existingModal) existingModal.remove();
    
    const isDelete = confirmText === 'DELETE';
    const confirmBtnClass = isDelete ? 'btn-confirm-danger' : 'btn-confirm-success';
    const confirmBtnText = isDelete ? 'YES, DELETE' : 'YES, CREATE';
    
    const modalHtml = `
        <div id="confirmModal" class="modal-overlay" onclick="closeConfirmModal(event)">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h2>${escapeHtml(title)}</h2>
                </div>
                <div class="modal-body">
                    <p>${escapeHtml(message)}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="closeConfirmModal()">NO, CANCEL</button>
                    <button class="${confirmBtnClass}" onclick="executeConfirmAction()">${confirmBtnText}</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Store confirm action
    window.pendingConfirmAction = onConfirm;
}

function closeConfirmModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.remove();
    }
    window.pendingConfirmAction = null;
}

function executeConfirmAction() {
    const action = window.pendingConfirmAction;
    closeConfirmModal();
    if (action && typeof action === 'function') {
        action();
    }
}

// Export functions to global scope for onclick handlers
window.stopBot = stopBot;
window.startBot = startBot;
window.deleteBot = deleteBot;
window.viewBotDetails = viewBotDetails;
window.toggleCreateBotForm = toggleCreateBotForm;
window.checkServerStatus = checkServerStatus;
window.logout = logout;
window.handleLogin = handleLogin;
window.handleRegister = handleRegister;
window.showConfirmModal = showConfirmModal;
window.closeConfirmModal = closeConfirmModal;
window.executeConfirmAction = executeConfirmAction;
window.toggleUserDropdown = toggleUserDropdown;

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('userDropdownMenu');
    const avatar = document.getElementById('userAvatar');
    if (dropdown && dropdown.classList.contains('show')) {
        if (!dropdown.contains(event.target) && !avatar.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    }
});
