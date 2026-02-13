# Binance AI Trading Bot - Changelog

## Version 1.0.0 (2026)

### Features Added

#### Core Trading Features
- ✅ Grid Trading Bot
- ✅ AI Trading Bot
- ✅ Trend Following Bot
- ✅ DCA (Dollar Cost Averaging) Bot
- ✅ Martingale Strategy
- ✅ Scalping Bot

#### User System
- ✅ User Registration & Login
- ✅ JWT Authentication
- ✅ VIP Licensing System (6 tiers)
- ✅ License Activation
- ✅ Role-based Access Control

#### Dashboard Features
- ✅ Real-time Stats Grid
- ✅ Balance Display
- ✅ Server Status Indicator (Testnet/Live/Disconnected)
- ✅ Recent Trades Table
- ✅ Bot Logs Panel
- ✅ Create/Start/Stop/Delete Bots

### Bug Fixes

#### Critical Fixes
- 🔧 Fixed infinite loop in worker.php (PHP0420)
- 🔧 Fixed database schema mismatch for bots table
- 🔧 Fixed missing columns in INSERT statements
- 🔧 Fixed API route parsing for Apache mod_rewrite

#### UI Fixes
- 🔧 Fixed dropdown white background issue
- 🔧 Fixed JavaScript template literal errors
- 🔧 Fixed missing toggleCreateBotForm function
- 🔧 Fixed form field ID mismatches

### API Routes Added

#### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login user
- `GET /api/auth/me` - Get current user

#### User
- `GET /api/user/balance` - Get user balance

#### Bots
- `GET /api/bots` - List all user bots
- `POST /api/bots` - Create new bot
- `GET /api/bots/:id` - Get bot details
- `POST /api/bots/:id/start` - Start bot
- `POST /api/bots/:id/stop` - Stop bot
- `DELETE /api/bots/:id` - Delete bot
- `GET /api/bots/:id/logs` - Get bot logs
- `GET /api/bots/:id/status` - Get bot status
- `GET /api/bots/:id/trades` - Get bot trades
- `GET /api/bots/logs` - Get all user bot logs

#### Trades
- `GET /api/trades` - Get user trades

#### Market Data
- `GET /api/market/:symbol` - Get price & ticker
- `GET /api/market/:symbol/klines` - Get candlestick data

#### Licenses
- `POST /api/licenses/activate` - Activate license code
- `GET /api/licenses` - Get user license info

### Database Schema

#### Tables
- `users` - User accounts & settings
- `licenses` - VIP license codes
- `features` - VIP features per tier
- `license_history` - License activation history
- `bots` - Trading bot configurations
- `trades` - Trade history
- `grid_configs` - Grid trading configurations
- `bot_logs` - Bot operation logs
- `api_calls_log` - API usage tracking

### VIP Tiers

| Tier | Max Bots | Trades/Day | API Calls | Features |
|------|----------|------------|-----------|----------|
| User | 1 | 10 | 100 | Basic Grid |
| VIP1 | 3 | 100 | 1,000 | 3 Bots, Advanced Grid, DCA |
| VIP2 | 5 | 200 | 5,000 | 5 Bots, AI Signals, Webhooks |
| VIP3 | 10 | 500 | 10,000 | 10 Bots, Custom Grid, Multi-Pair |
| VIP4 | 20 | 1,000 | 25,000 | 20 Bots, Priority Support |
| VIP5 | 50 | Unlimited | 100,000 | 50 Bots, Early Access |
| VIP6 | Unlimited | Unlimited | Unlimited | All Features |

### Demo Credentials

| Role | Email | Password |
|------|--------|----------|
| User | demo@example.com | demo123 |
| Admin | admin@example.com | admin123 |

### License Codes (Demo)

| Tier | Code |
|------|------|
| VIP1 | V1-XXXXXXXXXXXX |
| VIP2 | V2-XXXXXXXXXXXX |
| VIP3 | V3-XXXXXXXXXXXX |
| VIP4 | V4-XXXXXXXXXXXX |
| VIP5 | V5-XXXXXXXXXXXX |
| VIP6 | V6-XXXXXXXXXXXX |

### Installation

1. Set up MySQL database
2. Run database setup (install.php)
3. Configure API keys in Settings
4. Activate license or use demo mode
5. Create and start trading bots

### Known Issues

- ⚠️ Bot execution requires PHP CLI worker
- ⚠️ Real trading requires valid Binance API keys
- ⚠️ Testnet recommended for initial testing

### Changelog Format

- ✅ Feature added
- 🔧 Bug fixed
- ⚠️ Known issue/workaround
- ❌ Removed/deprecated

---

## Session / Work Log

### Session 1 (2024-02-10)
- ✅ Created initial CHANGELOG.md file
- ✅ Documented all features, bug fixes, and API routes
- ✅ Added VIP tiers documentation
- ✅ Added demo credentials and license codes
- ✅ Added installation instructions

### Session 2 (2024-02-10)
- ✅ Redesigned frontend with Bootstrap 5
- ✅ Added matrix/futuristic theme
- ✅ Professional dark UI with gradient accents
- ✅ Smooth animations (Animate.css)
- ✅ Responsive design (mobile-friendly)
- ✅ Toast notifications system
- ✅ Improved modal dialogs
- ✅ Better form styling
- ✅ Animated status indicators

### Session 3 (2024-02-10)
- ✅ Added PWA (Progressive Web App) support
- ✅ Created manifest.json for Android install
- ✅ Created service worker (sw.js) for offline support
- ✅ Added meta tags for iOS/Android web app
- ✅ Added safe-area insets for notched phones
- ✅ Mobile touch optimizations
- ✅ Responsive breakpoints (xs, sm, md, lg, xl)
- ✅ Landscape mode optimization
- ✅ Print styles for documentation

### Session 4 (2024-02-10)
- ✅ Moved server status to dashboard below stats grid
- ✅ Created cool animated status bar with pulse ring effect
- ✅ Added API mode display (Testnet/Live)
- ✅ Added refresh button for status
- ✅ Updated JavaScript to work with new status HTML

### Session 5 (2024-02-10)
- ✅ Added copyright footer at the bottom
- ✅ Includes branding, copyright text, and links
- ✅ Privacy Policy, Terms of Service, Support links
- ✅ Responsive layout for footer

### Session 6 (2024-02-10)
- ✅ Updated all buttons with cool animations
- ✅ Added shimmer effect on hover
- ✅ Added success and danger button variants
- ✅ Created dedicated login page (login.html)
- ✅ Created dedicated registration page (register.html)
- ✅ Added form validation
- ✅ Password match validation
- ✅ Responsive design for auth pages

### Session 7 (2024-02-10)
- ✅ Fully revised index.html with all demo features
- ✅ Added demo bots with running/stopped states
- ✅ Added demo trades table
- ✅ Added demo logs
- ✅ All buttons and forms work with JavaScript
- ✅ Updated login.html with demo authentication
- ✅ Updated register.html with form validation
- ✅ Toast notifications on all actions
- ✅ Bot start/stop/delete functions work
- ✅ License activation demo
- ✅ API settings save demo

### Session 8 (2024-02-10)
- ✅ Updated index.php with full routing
- ✅ Routes: /, /login, /register, /api/*
- ✅ Added 404 error page
- ✅ Fully revised public/app.js
- ✅ All functions exported to window
- ✅ localStorage for user/bots persistence
- ✅ Demo trades and logs rendered
- ✅ Toast notifications system
- ✅ All buttons work: start/stop/delete/create
- ✅ Settings modal and license activation work
- ✅ User logout functionality

### Session 9 (2024-02-11)
- ✅ Debug: Checked all PHP files for syntax errors
- ✅ index.php - No errors
- ✅ api.php - No errors
- ✅ db.php - No errors
- ✅ db_operations.php - No errors
- ✅ grid-bot.php - No errors
- ✅ binance-api.php - No errors
- ✅ app.js - No syntax errors (Node.js check)
- ✅ All frontend files ready for deployment

### Session 10 (2024-02-11)
- 📝 Add new session here

### Session 11 (2026-02-11) - Admin Panel Fixes & IP Tracking

#### Bug Fixes
- 🔧 Fixed duplicate worker processing code in api.php (worker status always returned 0)
- 🔧 Fixed user management - added missing activate/deactivate API endpoints
- 🔧 Fixed JavaScript error "Cannot set properties of null" - removed unused Edit button
- 🔧 Fixed table layout - "Deactivate" button now displays in correct column
- 🔧 Fixed button CSS styling - added !important flags to override Tailwind reset
- 🔧 Fixed license table loading - removed duplicate loadLicenses/deleteLicense functions
- 🔧 Fixed wrong table ID - changed licensesTable to licenseTable in admin.js
- 🔧 Fixed license expiry - added expires_at calculation in createLicense()

#### Features Added
- ✅ Added IP Address tracking for users (ip_address, last_ip columns)
- ✅ Added getClientIp() helper function in api.php
- ✅ Added updateUserIp() function in db_operations.php
- ✅ Added IP tracking on login and auth/me requests
- ✅ Added IP Address column to admin users table
- ✅ Added custom confirmation modal for delete actions
- ✅ Added showError()/showSuccess() notification functions
- ✅ License expiration dates now calculated and displayed

#### Files Modified
- **api.php**
  - Added getClientIp() function
  - Added IP tracking on login and auth/me
  - Fixed duplicate worker status code
  - Added admin/users/{id}/activate and /deactivate endpoints

- **db_operations.php**
  - Added updateUserIp() function
  - Fixed createLicense() to calculate and store expires_at

- **public/admin.html**
  - Fixed toggle button onclick handlers
  - Added IP Address column header
  - Added button CSS with !important flags
  - Removed unused toggleUser function
  - Added confirmation modal HTML and styles
  - Removed duplicate loadLicenses/deleteLicense functions

- **public/admin.js**
  - Updated loadUsers() to display IP address
  - Fixed loadUsers() table structure
  - Added deleteLicense() function with modal
  - Added showError()/showSuccess() functions
  - Added confirmation modal functions (showConfirmModal, closeConfirmModal)
  - Added console logging for debugging

- **add_ip_columns.php** (new)
  - Migration script to add ip_address and last_ip columns

- **add_license_expiry.php** (new)
  - Migration script to add expires_at column and update existing licenses

---

## Revert Log

### 2024-02-11 - Revert Server Status Feature

#### Files Modified
- **api.php**
  - Removed line 61: `'server/status' => 'server/status'` from actionMap
  - Removed lines 187-220: Entire SERVER STATUS ROUTES section (server status endpoint handler)

- **test_system.php**
  - Removed line 151: `'server/status' => 'GET'` from $apiEndpoints array

- **CHANGELOG.md**
  - Removed lines 51-52: Server Status API route documentation

#### Original Feature Description
The server/status endpoint was added to check Binance connection status. It authenticated the user, validated API keys, and tested connection to Binance API.

#### To Re-instate This Feature
1. Add to api.php actionMap (line ~61):
   ```php
   'server/status' => 'server/status',
   ```

2. Add to api.php routes section (after line ~185):
   ```php
   if ($uri === 'server/status' && $method === 'GET') {
       $user = authenticate();
       $mode = $user['trading_mode'] ?? 'test';
       $hasApiKeys = !empty($user['api_key']) && !empty($user['api_secret']);
       
       if (!$hasApiKeys) {
           echo json_encode([
               'connected' => false,
               'mode' => $mode,
               'message' => 'API keys not configured'
           ]);
           exit;
       }
       
       try {
           $binanceApi = new BinanceAPI($user['api_key'], $user['api_secret'], $mode === 'live');
           $balance = $binanceApi->getSpotBalance();
           
           echo json_encode([
               'connected' => true,
               'mode' => $mode,
               'message' => 'Connected to Binance '
           ]);
       } catch (Exception $e) {
           echo json_encode([
               'connected' => false,
               'mode' => $mode,
               'error' => $e->getMessage()
           ]);
       }
   }
   ```

3. Add to test_system.php (line ~151):
   ```php
   'server/status' => 'GET',
   ```

4. Add back to CHANGELOG.md API Routes section:
   ```markdown
   #### Server Status
   - `GET /api/server/status` - Check Binance connection status
   ```

### 2024-02-11 - Implement Real Worker Server Status (Monitoring)

#### Files Modified
- **api.php**
  - Added action mapping: `'worker/status' => 'worker/status'`
  - Added `GET /api/worker/status` endpoint to check worker process status
  - Endpoint checks for running PHP worker processes using `ps aux`
  - Returns worker count, PIDs, running bots count, and timestamp

- **public/admin.html**
  - Added `workerDetails` div to display worker status details
  - Shows worker count, running bots count, PIDs, and last check timestamp

- **public/admin.js**
  - Added `loadWorkerStatus()` function to fetch worker status from API
  - Updates status indicator dot and text in real-time
  - Displays worker count, running bots, PIDs, and last check time

#### Feature Description
The Worker Server Status now provides real-time monitoring of worker processes:
- Shows if worker is running or stopped
- Displays number of active worker processes
- Shows running bot count from database
- Lists worker PIDs for troubleshooting
- Last check timestamp for monitoring freshness

#### API Response Format
```json
{
    "status": "running",
    "statusText": "Running (2 worker(s))",
    "workerCount": 2,
    "workers": [
        {"pid": 1234, "cpu": 0.5, "memory": 2.3, "status": "running"}
    ],
    "runningBots": 5,
    "timestamp": "2024-02-11 19:30:00"
}
```

### 2024-02-11 - Fix Admin Dashboard API Response Format

#### Files Modified
- **api.php**
  - Fixed `GET /api/admin/dashboard` endpoint to return flat properties
  - Added error handling for missing database tables (trades)
  - Response now matches frontend expectations

#### API Response Format (Fixed)
```json
{
    "totalUsers": 10,
    "activeUsers": 5,
    "vipUsers": 3,
    "totalBots": 8,
    "runningBots": 2,
    "totalTrades": 150
}
```

**Last Updated**: 2024-02-11

### 2026-02-11 - Fix Subscription Distribution Table

#### Files Modified
- **api.php**
  - Updated `GET /api/admin/dashboard` endpoint to return `tierStats` with user/bot counts per VIP tier
  - Added query to get user count, bot count, and running bots per user role
  - Added orphaned bots tracking (bots without valid user_id)
  - Added debug logging for all roles found

- **public/admin.js**
  - Updated `loadDashboardStats()` to pass `tierStats` to `loadSubscriptionStats()`
  - Updated `loadSubscriptionStats()` to display Users, Bots, and Running counts per tier
  - Added role mapping: Free←User, Silver←VIP1, Gold←VIP2, Platinum←VIP3, Diamond←VIP4, Platinum+←VIP5, Diamond+←VIP6
  - Added 'Orphaned' tier for bots without valid users
  - Fixed duplicate entries by skipping already-mapped roles in unknown roles section
  - Fixed `loadLicenses()` table ID from `licensesTable` to `licenseTable`

#### API Response Format (Updated)
```json
{
    "totalUsers": 10,
    "activeUsers": 5,
    "vipUsers": 3,
    "totalBots": 8,
    "runningBots": 2,
    "totalTrades": 150,
    "tierStats": {
        "User": {"users": 5, "bots": 3, "running": 1},
        "VIP1": {"users": 2, "bots": 2, "running": 1},
        "VIP2": {"users": 2, "bots": 2, "running": 0},
        "VIP3": {"users": 1, "bots": 1, "running": 0},
        "VIP4": {"users": 0, "bots": 0, "running": 0},
        "VIP5": {"users": 0, "bots": 0, "running": 0},
        "VIP6": {"users": 0, "bots": 0, "running": 0},
        "Admin": {"users": 0, "bots": 0, "running": 0},
        "Orphaned": {"users": 0, "bots": 0, "running": 0}
    }
}
```

#### Bug Fixes
- Fixed role mapping direction (was backwards, causing Free tier to not show)
- Fixed duplicate entries by preventing already-mapped roles from appearing in unknown roles section
- Fixed orphaned bots tracking
- Fixed license table loading (wrong table ID)
- Fixed license table fields (wrong field names: license_key→code, is_active→is_used, vip_tier→type)
- Fixed license table to match HTML structure (added ID column, removed toggle button)
- Updated API to include user email for "Used By" column
- Fixed users table to match HTML structure (added Status, Last Login, IP Address columns)

#### Features Added
- Added "Reset Subscription" button to users table (replaces Delete)
- Added custom modal confirmation for Reset Subscription action
- Added custom modal confirmation for Delete License action
- Added `POST /api/admin/users/{id}/reset` endpoint to reset user subscription
- Added `resetUserSubscription()` function in db_operations.php
- Added `executeConfirmAction()` function to trigger modal confirm action

### 2026-02-12 - Admin Panel Modal Fixes

#### Files Modified
- **public/admin.js**
  - Fixed onclick handlers for Reset Subscription and Delete License buttons (escaped quotes with &quot;)
  - Fixed JavaScript syntax error causing "Unexpected end of input"
  - Updated API endpoint for license deletion to use POST /admin/licenses/delete
  - Removed unused loadHistory() function

- **public/admin.html**
  - Added onclick handler to confirm button (executeConfirmAction())

- **db_operations.php**
  - Added resetUserSubscription() function to reset user to Free tier

- **api.php**
  - Added POST /admin/users/{id}/reset endpoint for resetting user subscriptions

#### Features Added
- **Reset Subscription** - Admin can reset a user's subscription back to Free tier
- **Delete License Modal** - Confirmation modal before deleting license keys
- **Reset Subscription Modal** - Confirmation modal before resetting user subscription

#### Bug Fixes
- Fixed "Unexpected end of input" JavaScript syntax error
- Fixed modal confirm button not triggering action
- Fixed API endpoint for deleting licenses
- Fixed onclick handlers with proper quote escaping

#### System Overview (Verified Working)
- Total Users: 5
- Total Bots: 1
- Subscription Distribution: Free (3), Diamond+ (1), Admin (1)

#### License Key Management (Verified Working)
- All licenses display correctly with proper data
- Delete button opens confirmation modal
- Modal confirmation prevents accidental deletion

#### User Management (Verified Working)
- All users display with correct data
- Reset Subscription button opens confirmation modal
- Modal prevents accidental subscription reset

### 2026-02-12 - PHP Fixes & Database Schema Update

#### Bug Fixes
- 🔧 Fixed api.php opening tag from `<?p` to `<?php`
- 🔧 Added missing `ip_address` column to users table
- 🔧 Added missing `last_ip` column to users table
- 🔧 Added `vip_subscriptions` table for VIP plans
- 🔧 Fixed vip_subscriptions table with proper features JSON

#### Database Schema Updates
- **users table** - Added `ip_address VARCHAR(45)` and `last_ip VARCHAR(45)` columns
- **vip_subscriptions table** (new)
  - `id`, `code`, `name`, `type`, `price`, `duration_days`
  - `max_bots`, `max_trades_per_day`, `api_calls_limit`
  - `features` (JSON), `is_active`
  - Includes all 7 tiers: User, VIP1-VIP6

#### Files Modified
- **api.php** - Fixed PHP opening tag
- **setup_database.php** - Added ip_address, last_ip columns and vip_subscriptions table

#### Verification
- ✅ Login functionality working
- ✅ Worker status API responding correctly
- ✅ All database tables created successfully

### 2026-02-12 - Create Bot Confirmation Modal

#### Features Added
- ✅ Added confirmation modal for bot creation
- Modal displays all bot configuration details before creation
- Bot details shown: Name, Symbol, Type, Strategy, Grid Levels, Investment, Take Profit, Stop Loss

#### Modal Design
- **Title**: "Confirm Bot Creation" (white text)
- **Background**: Dark semi-transparent overlay
- **Modal Body**: Dark gradient (#1a1a2e to #16213e)
- **Accent**: Blue borders (#3b82f6)
- **Confirm Button**: Green gradient (#22c55e to #16a34a) with glow effect
- **Cancel Button**: Red gradient (#ef4444 to #dc2626) with glow effect

#### Files Modified
- **public/create-bot.html**
  - Added confirmation modal HTML
  - Added `showCreateBotModal()` function to populate modal with form values
  - Added `confirmCreateBot()` function to submit after confirmation
  - Added `escapeHtml()` helper for XSS protection
  - Added `getStrategyName()` helper for strategy display names

#### API Endpoint
- Uses existing `POST /api/bots/create` endpoint

### 2026-02-12 - VIP6 Unlimited Bots Fix

#### Bug Fixes
- 🔧 Fixed VIP6 users unable to create bots (validation error "maximum limit of -1 bots")
- 🔧 Fixed client-side display showing "-1" instead of "Unlimited" for max bots
- 🔧 Fixed server-side API validation blocking unlimited bot creation

#### Changes Made
- **public/create-bot.html**
  - Added `formatMaxBots()` function to display "Unlimited" for -1 values
  - Fixed validation check: `maxBots !== -1` to allow VIP6 unlimited creation

- **api.php**
  - Added server-side check: `if ($maxBots != -1 && $currentBotCount >= $maxBots)`
  - API now properly allows VIP6 users unlimited bot creation

#### Before Fix
```
Error: You have reached the maximum limit of -1 bots for your subscription level
Bots: 1/-1 | Max Bots Allowed: -1
```

#### After Fix
```
✅ Bot created successfully
Bots: 1/Unlimited | Max Bots Allowed: Unlimited
```

### 2026-02-12 - VIP Plan Display & Button Fix

#### Bug Fixes
- 🔧 Fixed VIP6 plan displaying "Max -1 bots" and "-1 trades/day"
- 🔧 Added red "Downgrade" button with consistent styling
- 🔧 Added `.btn` and `.btn-danger` CSS classes to match admin panel

#### Files Modified
- **public/vip.html**
  - Added `formatLimit()` helper function
  - Updated plan features display to use `formatLimit(plan.max_bots)`
  - Updated trades display to use `formatLimit(plan.max_trades_per_day)`
  - Added CSS classes `.btn` and `.btn-danger`
  - Added red "Downgrade" button for current plan

#### UI Changes
- ✅ Red delete/downgrade buttons now match admin panel style
- ✅ "Unlimited" displayed instead of "-1" for VIP6

### 2026-02-12 - Delete Button Styling Fix

#### Bug Fixes
- 🔧 Fixed Delete button color in licenses table
- 🔧 Added `.btn-sm` CSS class for smaller buttons

#### Files Modified
- **public/admin.html**
  - Added `.btn-sm` CSS styling for smaller button size
  - Delete buttons now display red as intended

  
  
### 2026-02-12 - Unified Confirmation Modal  
  
#### Bug Fixes  
- ?? Fixed Delete bot button red color on main dashboard  
- ?? Updated modal design to match admin panel style  
  
#### Features Added  
- ? Custom confirmation modal with consistent design across all pages  
- ? Red "YES, DELETE" / "NO, CANCEL" buttons for delete actions  
- ? Green "YES, CREATE" / "NO, CANCEL" buttons for create actions  
  
#### Files Modified  
- **public/app.js**  
  - Added `showConfirmModal()` function for unified modal  
  - Added `closeConfirmModal()` function  
  - Added `executeConfirmAction()` function  
  - Updated `deleteBot()` to use custom modal  
  - Exported modal functions to global scope  
  
- **public/index.html**  
  - Added modal CSS styles  
  
- **public/create-bot.html**  
  - Added modal CSS styles  
  - Updated form submission to use `showConfirmModal()`  
  
#### Modal Design  
- **Title**: White text, centered  
- **Body**: Light gray text  
- **Red Button**: "YES, DELETE" with red gradient (#ef4444  #dc2626)  
- **Green Button**: "YES, CREATE" with green gradient (#22c55e  #16a34a)  
- **Cancel Button**: "NO, CANCEL" with gray gradient (#475569  #334155) 
  
### 2026-02-12 - Remove Downgrade Button  
  
#### Bug Fixes  
- ?? Removed \"Downgrade\" button from VIP page for Admin users  
  
#### Files Modified  
- **public/vip.html**  
  - Removed Downgrade button from plan card 
  
### 2026-02-12 - User Dropdown Menu  
  
#### Features Added  
- ? Replaced logout button with dropdown menu  
- ? Added Update Account section (Name, Email, Password)  
- ? Added API Key section (Testnet, Real Binance API)  
- ? Added Credit Coins Beta (Under Development)  
  
#### Files Modified  
- **public/index.html**  
  - Replaced logout button with avatar dropdown  
  - Added dropdown CSS styles (matrix theme)  
  - Added dropdown menu with sections  
  
- **public/app.js**  
  - Added `toggleUserDropdown()` function  
  - Added click outside handler to close dropdown  
  - Updated `updateUserDisplay()` to update dropdown email 
