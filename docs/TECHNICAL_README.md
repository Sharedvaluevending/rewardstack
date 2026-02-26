# QR Revenue - Technical Documentation

## Quick Start

### Prerequisites

- PHP 8.1+
- Composer 2.x
- Node.js 18+
- MySQL 8.0 or PostgreSQL 14+
- Redis 6.0+

### Installation

```bash
# Clone repository
git clone [repository-url]
cd qr-revenue

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed

# Build frontend assets
npm run build

# Start development server
php artisan serve
```

### Environment Configuration

Key environment variables:

```env
# Application
APP_NAME="Revenue QR"
APP_ENV=production
APP_DEBUG=false

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=qrrevenue
DB_USERNAME=root
DB_PASSWORD=

# Redis (Required for production)
REDIS_HOST=127.0.0.1
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Stripe
STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx

# Printful
PRINTFUL_API_KEY=xxx

# SendGrid
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.xxx

# Merch Pricing
MERCH_MARKUP_PERCENT=15
MERCH_RETAIL_MARKUP=30
```

---

## Architecture Overview

### Directory Structure

```
app/
├── Console/
│   └── Commands/           # Artisan commands
│       └── SyncPrintfulProducts.php
├── Exceptions/
│   └── Handler.php         # Custom error handling
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # Admin controllers
│   │   ├── Business/       # Business controllers
│   │   ├── Employee/       # Employee controllers
│   │   ├── Portal/         # Customer portal controllers
│   │   └── Auth/           # Authentication controllers
│   └── Middleware/
│       └── SecurityHeaders.php
├── Models/                 # Eloquent models
├── Providers/              # Service providers
└── Services/               # Business logic services
    └── PrintfulService.php

resources/
├── js/
│   ├── Components/         # Reusable Vue components
│   ├── Layouts/            # Page layouts
│   └── Pages/              # Inertia pages
│       ├── Admin/
│       ├── Business/
│       ├── Employee/
│       ├── Portal/
│       ├── Public/
│       └── Errors/
└── css/
    └── app.css             # Tailwind CSS

database/
├── migrations/             # Database migrations
└── seeders/                # Database seeders

routes/
├── web.php                 # Web routes
└── api.php                 # API routes

config/
├── merch.php               # Merch configuration
└── ...                     # Laravel configs
```

### Request Lifecycle

```
1. HTTP Request
       │
       ▼
2. Nginx (reverse proxy)
       │
       ▼
3. PHP-FPM
       │
       ▼
4. Laravel Bootstrap
       │
       ▼
5. Middleware Stack
   ├── SecurityHeaders
   ├── Authentication
   ├── Rate Limiting
   └── CSRF Protection
       │
       ▼
6. Route Resolution
       │
       ▼
7. Controller Action
       │
       ▼
8. Inertia Response
       │
       ▼
9. Vue.js Rendering
```

---

## Database

### Running Migrations

```bash
# Run all migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Fresh migration (drops all tables)
php artisan migrate:fresh

# With seeders
php artisan migrate:fresh --seed
```

### Key Tables

| Table | Purpose |
|-------|---------|
| `users` | All user accounts (admin, business, employee, customer) |
| `businesses` | Business tenant accounts |
| `qr_codes` | Generated QR codes |
| `scans` | QR scan tracking |
| `promotions` | Promotion configurations |
| `redemptions` | Promotion usage tracking |
| `game_sessions` | Game play sessions |
| `game_plays` | Individual game plays |
| `orders` | Merchandise orders |
| `order_items` | Order line items |
| `products` | Printful product catalog |

### Indexes

Performance indexes are defined in:
- `2024_12_08_000004_add_performance_indexes.php`

---

## Queue Processing

### Configuration

Queue workers are managed by Supervisor:

```ini
# /etc/supervisor/conf.d/laravel-worker.conf
[program:qrrevenue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/rewardstack/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=2
```

### Managing Workers

```bash
# Start workers
supervisorctl start qrrevenue-worker:*

# Stop workers
supervisorctl stop qrrevenue-worker:*

# Restart workers
supervisorctl restart qrrevenue-worker:*

# Check status
supervisorctl status
```

### Queued Jobs

- Email notifications
- Printful order creation
- Analytics aggregation
- Webhook processing

---

## Caching

### Cache Configuration

Redis is used for all caching:

```php
// Cache a value
Cache::put('key', 'value', 3600);

// Retrieve cached value
$value = Cache::get('key');

// Cache with callback
$value = Cache::remember('key', 3600, function () {
    return DB::table('expensive_query')->get();
});
```

### Cache Clearing

```bash
# Clear all cache
php artisan cache:clear

# Clear config cache
php artisan config:clear

# Clear route cache
php artisan route:clear

# Clear view cache
php artisan view:clear

# Rebuild caches (production)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Authentication

### User Roles

| Role | Access |
|------|--------|
| `admin` | Full platform access |
| `business` | Business dashboard, QR codes, promotions |
| `employee` | Redemption validation, limited dashboard |
| `customer` | Customer portal, games, rewards |

### Middleware

```php
// Require authentication
Route::middleware('auth')->group(...);

// Require specific role
Route::middleware(['auth', 'role:admin'])->group(...);

// Rate limiting
Route::middleware('throttle:login')->post('/login', ...);
```

---

## API Integrations

### Stripe

```php
// Create subscription
$subscription = $user->newSubscription('default', $priceId)->create($paymentMethod);

// Charge one-time
$user->charge($amount, $paymentMethod);

// Webhook handling
// POST /stripe/webhook
```

### Printful

```php
// Sync products
php artisan printful:sync --markup=15

// Create order
$printfulService->createOrder($order, $items);

// Get shipping rates
$printfulService->getShippingRates($address, $items);
```

### SendGrid

Email is sent via Laravel's mail system:

```php
Mail::to($user)->send(new WelcomeEmail($user));
```

---

## Frontend

### Vue.js + Inertia.js

Pages are Vue single-file components:

```vue
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

defineProps({
    businesses: Array,
});

const form = useForm({
    name: '',
    email: '',
});
</script>

<template>
    <Head title="Dashboard" />
    <MainLayout>
        <!-- Content -->
    </MainLayout>
</template>
```

### Shared Data

Global data passed to all pages via `HandleInertiaRequests` middleware:

```php
public function share(Request $request): array
{
    return [
        'auth' => [
            'user' => $request->user(),
        ],
        'flash' => [
            'message' => fn () => $request->session()->get('message'),
        ],
    ];
}
```

### Asset Building

```bash
# Development (with hot reload)
npm run dev

# Production build
npm run build
```

---

## Testing

### Running Tests

```bash
# All tests
php artisan test

# Specific test
php artisan test --filter=UserTest

# With coverage
php artisan test --coverage
```

### Test Database

Configure a separate test database in `.env.testing`:

```env
DB_DATABASE=qrrevenue_test
```

---

## Deployment

### Production Checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `LOG_LEVEL=error`
- [ ] Redis configured for cache/session/queue
- [ ] Supervisor running queue workers
- [ ] Cron job for scheduler
- [ ] SSL certificate installed
- [ ] Database backups configured
- [ ] Error monitoring (Sentry) configured

### Deployment Commands

```bash
# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
supervisorctl restart qrrevenue-worker:*

# Restart PHP-FPM
systemctl restart php8.1-fpm
```

---

## Troubleshooting

### Common Issues

**500 Error**
```bash
# Check logs
tail -f storage/logs/laravel.log

# Check permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

**Queue Jobs Not Processing**
```bash
# Check supervisor status
supervisorctl status

# Check Redis connection
redis-cli ping

# Process jobs manually
php artisan queue:work --once
```

**Cache Issues**
```bash
# Clear all caches
php artisan optimize:clear
```

### Log Locations

| Log | Location |
|-----|----------|
| Laravel | `storage/logs/laravel.log` |
| Nginx | `/var/log/nginx/error.log` |
| PHP-FPM | `/var/log/php8.1-fpm.log` |
| Supervisor | `/var/log/supervisor/` |

---

## Security

### Best Practices Implemented

- CSRF protection on all forms
- XSS prevention via Vue.js escaping
- SQL injection prevention via Eloquent ORM
- Rate limiting on authentication endpoints
- Secure session configuration (HTTP-only, secure cookies)
- Security headers (CSP, HSTS, etc.)
- Password hashing with bcrypt

### Sensitive Files

Never commit to version control:
- `.env`
- `storage/logs/*`
- `storage/backups/*`

---

## Support

For technical support:
- Email: support@revenueqr.com
- Documentation: `/docs/`

---

*Last updated: December 2024*
