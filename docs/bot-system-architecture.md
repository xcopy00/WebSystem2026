# Binance AI Trade - Trading Bot System Architecture

## Overview
A full-featured trading bot system supporting Spot and Futures trading on Binance, with 24/7 operation, VIP subscriptions, and comprehensive trading features.

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend (public/)                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐   │
│  │ login.html  │  │ index.html  │  │ register.html   │   │
│  └─────────────┘  └─────────────┘  └─────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                      API Layer (api.php)                    │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Auth Routes    │ Bot Routes │ Trade Routes │ VIP   │  │
│  └─────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                   Business Logic Layer                       │
│  ┌───────────────┐  ┌─────────────────┐  ┌─────────────┐  │
│  │ DBOperations  │  │ BinanceAPI      │  │ GridBot     │  │
│  │               │  │ Spot + Futures  │  │ DCABot      │  │
│  └───────────────┘  └─────────────────┘  └─────────────┘  │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                   Background Worker                          │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ worker.php - Runs 24/7, manages all bot processes    │  │
│  │ - Spawns bot processes for each running bot          │  │
│  │ - Monitors bot health and restarts if needed        │  │
│  │ - Processes trading signals and executes trades      │  │
│  └─────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                    External Services                         │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Binance API (Spot + Futures)                       │  │
│  │ - Real-time price feeds                            │  │
│  │ - Order execution                                 │  │
│  │ - Balance management                              │  │
│  └─────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

## Database Schema

### Users Table (already exists)
```sql
users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE,
  password VARCHAR(255),
  name VARCHAR(100),
  api_key TEXT,
  api_secret TEXT,
  role ENUM('User', 'VIP1', 'VIP2', 'VIP3', 'VIP4', 'VIP5', 'VIP6', 'Admin'),
  license_expires DATETIME,
  trading_mode ENUM('testnet', 'live') DEFAULT 'testnet',
  is_active TINYINT DEFAULT 1,
  max_bots INT DEFAULT 1,
  max_trades_per_day INT DEFAULT 10,
  api_calls_limit INT DEFAULT 100,
  created_at DATETIME,
  updated_at DATETIME
)
```

### Bots Table
```sql
bots (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  name VARCHAR(100),
  type ENUM('spot', 'future') DEFAULT 'spot',
  strategy ENUM('grid', 'dca', 'arbitrage', 'smart') DEFAULT 'grid',
  symbol VARCHAR(20),
  status ENUM('running', 'stopped', 'paused', 'error') DEFAULT 'stopped',
  
  -- Configuration
  investment DECIMAL(20, 8) DEFAULT 0,
  leverage INT DEFAULT 1,
  
  -- Grid Settings
  grid_count INT DEFAULT 10,
  grid_interval DECIMAL(20, 8) DEFAULT 0,
  lower_price DECIMAL(20, 8),
  upper_price DECIMAL(20, 8),
  
  -- DCA Settings
  dca_amount DECIMAL(20, 8),
  dca_interval INT,
  dca_multiplier DECIMAL(10, 4) DEFAULT 1.0,
  max_dca_orders INT,
  
  -- Take Profit / Stop Loss
  take_profit DECIMAL(10, 4),
  stop_loss DECIMAL(10, 4),
  
  -- Filters
  min_profit DECIMAL(10, 4) DEFAULT 0.1,
  
  -- Status
  current_price DECIMAL(20, 8),
  total_invested DECIMAL(20, 8) DEFAULT 0,
  total_profit DECIMAL(20, 8) DEFAULT 0,
  open_orders INT DEFAULT 0,
  last_trade_at DATETIME,
  
  -- Timestamps
  started_at DATETIME,
  stopped_at DATETIME,
  created_at DATETIME,
  updated_at DATETIME,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
```

### Bot Orders Table
```sql
bot_orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  bot_id INT,
  order_id VARCHAR(100),
  side ENUM('buy', 'sell'),
  type ENUM('limit', 'market', 'stop_limit'),
  status ENUM('open', 'filled', 'cancelled', 'rejected'),
  price DECIMAL(20, 8),
  quantity DECIMAL(20, 8),
  filled_price DECIMAL(20, 8),
  filled_quantity DECIMAL(20, 8),
  profit DECIMAL(20, 8) DEFAULT 0,
  created_at DATETIME,
  filled_at DATETIME,
  
  FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
)
```

### Bot Trades Table
```sql
bot_trades (
  id INT PRIMARY KEY AUTO_INCREMENT,
  bot_id INT,
  order_id INT,
  side ENUM('buy', 'sell'),
  price DECIMAL(20, 8),
  quantity DECIMAL(20, 8),
  fee DECIMAL(20, 8),
  profit DECIMAL(20, 8) DEFAULT 0,
  created_at DATETIME,
  
  FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
)
```

### Bot Logs Table
```sql
bot_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  bot_id INT,
  type ENUM('info', 'warning', 'error', 'trade'),
  message TEXT,
  data JSON,
  created_at DATETIME,
  
  FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
)
```

### VIP Subscriptions Table
```sql
vip_subscriptions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(50) UNIQUE,
  name VARCHAR(100),
  type ENUM('VIP1', 'VIP2', 'VIP3', 'VIP4', 'VIP5', 'VIP6'),
  price DECIMAL(10, 2),
  duration_days INT,
  max_bots INT,
  max_trades_per_day INT,
  api_calls_limit INT,
  features JSON,
  is_active TINYINT DEFAULT 1,
  created_at DATETIME
)
```

## VIP Subscription Features

| VIP Level | Max Bots | Trades/Day | Features |
|-----------|----------|------------|----------|
| User | 1 | 10 | Basic Grid Bot |
| VIP1 | 2 | 50 | Grid + DCA |
| VIP2 | 5 | 100 | Grid + DCA + Arbitrage |
| VIP3 | 10 | 500 | All features + Smart Bot |
| VIP4 | 20 | 1000 | All features + API access |
| VIP5 | 50 | 5000 | All + Priority support |
| VIP6 | Unlimited | Unlimited | All + Custom strategies |

## Bot Strategies

### 1. Grid Trading Bot
- Places buy and sell orders at regular intervals
- Profits from price oscillations
- Best for sideways markets

**Settings:**
- Grid Count: Number of grid levels
- Grid Interval: Price difference between grids
- Lower/Upper Price: Price range

### 2. DCA (Dollar Cost Averaging) Bot
- Buys at regular intervals regardless of price
- Averages down position
- Best for long-term accumulation

**Settings:**
- DCA Amount: Amount per order
- DCA Interval: Time between orders
- DCA Multiplier: Increase amount after each buy
- Max DCA Orders: Maximum buy orders

### 3. Arbitrage Bot
- Profits from price differences between exchanges
- Triangular arbitrage on Binance
- High speed required

### 4. Smart Bot (AI-powered)
- Uses technical indicators
- Adaptive parameters
- Signal-based trading

## Binance API Integration

### Spot Trading
```php
class BinanceSpotAPI {
    // Place order
    public function placeOrder($symbol, $side, $type, $quantity, $price = null);
    
    // Cancel order
    public function cancelOrder($symbol, $orderId);
    
    // Get order status
    public function getOrder($symbol, $orderId);
    
    // Get current price
    public function getPrice($symbol);
    
    // Get balance
    public function getBalance($asset);
    
    // Get open orders
    public function getOpenOrders($symbol);
}
```

### Futures Trading
```php
class BinanceFuturesAPI {
    // Set leverage
    public function setLeverage($symbol, $leverage);
    
    // Open position
    public function openPosition($symbol, $side, $quantity, $leverage);
    
    // Close position
    public function closePosition($symbol);
    
    // Get position info
    public function getPosition($symbol);
}
```

## Worker Process (24/7 Operation)

```php
// worker.php - Main worker loop
while (true) {
    // Check for new bot commands
    $commands = getBotCommands();
    
    foreach ($commands as $command) {
        if ($command['action'] === 'start') {
            startBot($command['bot_id']);
        } elseif ($command['action'] === 'stop') {
            stopBot($command['bot_id']);
        }
    }
    
    // Run active bots
    $runningBots = getRunningBots();
    foreach ($runningBots as $bot) {
        runBot($bot);
    }
    
    // Sleep before next iteration
    sleep(1);
}

// Bot execution
function runBot($bot) {
    if ($bot['strategy'] === 'grid') {
        $gridBot = new GridBot($bot);
        $gridBot->execute();
    } elseif ($bot['strategy'] === 'dca') {
        $dcaBot = new DCABot($bot);
        $dcaBot->execute();
    }
}
```

## API Endpoints

```
POST   /api.php?action=bots              - Create bot
GET    /api.php?action=bots              - List user's bots
GET    /api.php?action=bots/{id}        - Get bot details
POST   /api.php?action=bots/{id}/start  - Start bot
POST   /api.php?action=bots/{id}/stop    - Stop bot
DELETE /api.php?action=bots/{id}        - Delete bot

GET    /api.php?action=trades           - Get trade history
GET    /api.php?action=bots/{id}/logs   - Get bot logs

GET    /api.php?action=server/status    - Binance connection status
```

## Frontend Pages

### Create Bot Page
- Bot name input
- Type selection (Spot/Futures)
- Strategy selection (Grid/DCA/Arbitrage/Smart)
- Symbol input with autocomplete
- Investment amount
- Strategy-specific settings
- VIP feature check

### Dashboard
- Bot list with status
- Start/Stop buttons
- P/L display
- Trade history
- Real-time status updates

## Implementation Priority

1. **Phase 1** - Core Infrastructure
   - Database schema
   - Basic bot CRUD
   - Simple Grid Bot

2. **Phase 2** - Trading Features
   - Binance API integration
   - Order execution
   - Trade history

3. **Phase 3** - Advanced Features
   - DCA Bot
   - VIP subscriptions
   - Background worker

4. **Phase 4** - AI Features
   - Smart Bot
   - Arbitrage detection
   - Performance analytics
