# Binance AI Trading Bot

A professional-grade cryptocurrency trading bot system with grid trading and AI-powered strategies.

## Features

- **Grid Trading Bot** - Automated buy/sell at predetermined price levels
- **AI Trading Strategies** - RSI, MACD, EMA based trading signals
- **VIP Licensing System** - Multiple subscription tiers with different features
- **Real-time Dashboard** - Monitor all your bots and trades
- **PWA Support** - Install as a mobile app on Android/iOS

## Tech Stack

- **Backend**: PHP 8.0+, MySQL, Apache/Nginx
- **Frontend**: Bootstrap 5, Vanilla JavaScript, PWA
- **Security**: JWT Authentication, Prepared Statements

## Quick, CSRF Protection Start

### 1. Database Setup

```bash
# Create MySQL database
mysql -u root -p < setup_database.sql

# Or use the web installer
php -S localhost:80
# Then visit http://localhost/install.php
```

### 2. Default Login

- **Demo User**: `demo@example.com` / `demo123`
- **Admin**: `admin@example.com` / `admin123`

### 3. Start Background Worker

```bash
php worker.php
```

For production, use a process manager:
```bash
# Using Supervisor
[program:trading-bot-worker]
command=php /path/to/worker.php
directory=/path/to/project
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true

# Or PM2 (requires Node.js)
pm2 start worker.php --name trading-bot --interpreter php
```

## Configuration

Copy `.env.example` to `.env` and configure:

```env
DB_HOST=localhost
DB_NAME=ai_trading_bot
DB_USER=root
DB_PASS=your_password
JWT_SECRET=your-super-secret-key
```

## License Codes (Demo)

| Tier | Code |
|------|------|
| VIP1 | V1-VIP1-ABC123-XY78Z9P1 |
| VIP2 | V1-VIP2-ABC123-XY78Z9P2 |
| VIP6 | V1-VIP6-UNLIMITED-2024 |

## VIP Tiers

| Tier | Max Bots | Trades/Day | Features |
|------|----------|------------|----------|
| User | 1 | 10 | Demo trading |
| VIP1 | 3 | 100 | Basic grid, RSI AI |
| VIP2 | 5 | 200 | Trend, indicators |
| VIP3 | 10 | 500 | Multi-bot |
| VIP4 | 20 | 1000 | Martingale, scalping |
| VIP5 | 50 | Unlimited | API, Telegram |
| VIP6 | Unlimited | Unlimited | White label |

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login
- `GET /api/auth/me` - Get current user

### Bots
- `GET /api/bots` - List user's bots
- `POST /api/bots` - Create bot
- `POST /api/bots/{id}/start` - Start bot
- `POST /api/bots/{id}/stop` - Stop bot
- `DELETE /api/bots/{id}` - Delete bot

### Trading
- `GET /api/trades` - Get trade history
- `GET /api/bots/{id}/logs` - Get bot logs
- `GET /api/server/status` - Check Binance connection

## Security

- All passwords hashed with `password_hash()` (bcrypt)
- JWT tokens with 24-hour expiry
- Prepared statements for all SQL queries
- CORS restricted to allowed origins
- Security headers via .htaccess:
  - X-Content-Type-Options: nosniff
  - X-Frame-Options: SAMEORIGIN
  - X-XSS-Protection: 1; mode=block
  - Content-Security-Policy

## Production Checklist

- [ ] Change `JWT_SECRET` to a strong random value
- [ ] Set `APP_ENV=production` in .env
- [ ] Configure proper CORS origins
- [ ] Enable HTTPS (HSTS)
- [ ] Set up log rotation for `logs/error.log`
- [ ] Configure database backups
- [ ] Set up monitoring for worker process
- [ ] Review and test all API endpoints

## License

MIT License
