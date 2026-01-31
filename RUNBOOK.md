# Monitly Production Runbook

## Overview

This runbook provides step-by-step instructions for deploying, configuring, and operating Monitly in production.

---

## Quick Test Commands

```bash
# Run all tests
php artisan test

# Run specific phase tests
php artisan test --filter=AuthenticationTest      # Phase 1
php artisan test --filter=TeamPermissionsTest     # Phase 2
php artisan test --filter=BillingComprehensiveTest # Phase 3
php artisan test --filter=MonitorLimitsTest       # Phase 5
php artisan test --filter=CheckEngineTest         # Phase 6
php artisan test --filter=IncidentTest            # Phase 7
php artisan test --filter=NotificationDedupeTest  # Phase 8
php artisan test --filter=AdminAccessTest         # Phase 12
```

---

## 1. Prerequisites

### Required Software
- PHP 8.2+
- Composer 2.x
- Node.js 18+ & npm
- MySQL 8.0+
- Redis 6+
- Supervisor (for queue workers)
- Nginx or Apache

### Required Services
- Paddle account (billing)
- SMTP server (notifications)

---

## 2. Initial Deployment

### 2.1 Clone & Install Dependencies

```bash
cd /var/www
git clone <your-repo-url> monitly
cd monitly

composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### 2.2 Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=monitly
DB_USERNAME=monitly_user
DB_PASSWORD=<secure-password>

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis

# Paddle Billing
PADDLE_SELLER_ID=<your-paddle-seller-id>
PADDLE_API_KEY=<your-paddle-api-key>
PADDLE_WEBHOOK_SECRET=<your-paddle-webhook-secret>
PADDLE_SANDBOX=false

# Paddle Price IDs (from your Paddle dashboard)
PADDLE_PRICE_ID_PRO_MONTHLY=pri_xxx
PADDLE_PRICE_ID_PRO_YEARLY=pri_xxx
PADDLE_PRICE_ID_TEAM_MONTHLY=pri_xxx
PADDLE_PRICE_ID_TEAM_YEARLY=pri_xxx
PADDLE_PRICE_ID_BUSINESS_MONTHLY=pri_xxx
PADDLE_PRICE_ID_BUSINESS_YEARLY=pri_xxx

# Admin Access
ADMIN_OWNER_EMAIL=your-admin@example.com

# Mail
MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<smtp-user>
MAIL_PASSWORD=<smtp-password>
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Monitly"
```

### 2.3 Run Migrations

```bash
php artisan migrate --force
```

### 2.4 Optimize for Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 2.5 Set Permissions

```bash
chown -R www-data:www-data /var/www/monitly
chmod -R 755 /var/www/monitly
chmod -R 775 /var/www/monitly/storage
chmod -R 775 /var/www/monitly/bootstrap/cache
```

---

## 3. Queue Workers (Supervisor)

### 3.1 Create Supervisor Configuration

Create `/etc/supervisor/conf.d/monitly.conf`:

```ini
[program:monitly-checks-standard]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/monitly/artisan queue:work redis --queue=checks_standard --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/monitly/storage/logs/worker-checks-standard.log

[program:monitly-checks-priority]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/monitly/artisan queue:work redis --queue=checks_priority --sleep=1 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/monitly/storage/logs/worker-checks-priority.log

[program:monitly-incidents]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/monitly/artisan queue:work redis --queue=incidents --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/monitly/storage/logs/worker-incidents.log

[program:monitly-notifications]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/monitly/artisan queue:work redis --queue=notifications --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/monitly/storage/logs/worker-notifications.log

[program:monitly-webhooks]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/monitly/artisan queue:work redis --queue=webhooks_in,webhooks_out --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/monitly/storage/logs/worker-webhooks.log

[program:monitly-sla]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/monitly/artisan queue:work redis --queue=sla --sleep=5 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/monitly/storage/logs/worker-sla.log

[program:monitly-maintenance]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/monitly/artisan queue:work redis --queue=maintenance --sleep=10 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/monitly/storage/logs/worker-maintenance.log

[program:monitly-exports]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/monitly/artisan queue:work redis --queue=exports --sleep=10 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/monitly/storage/logs/worker-exports.log

[group:monitly-workers]
programs=monitly-checks-standard,monitly-checks-priority,monitly-incidents,monitly-notifications,monitly-webhooks,monitly-sla,monitly-maintenance,monitly-exports
```

### 3.2 Start Workers

```bash
supervisorctl reread
supervisorctl update
supervisorctl start monitly-workers:*
```

### 3.3 Verify Workers

```bash
supervisorctl status
```

---

## 4. Scheduler (Cron)

Add to crontab (`crontab -e` as www-data or root):

```cron
* * * * * cd /var/www/monitly && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Paddle Webhook Configuration

### 5.1 Configure Webhook in Paddle Dashboard

1. Log in to Paddle Dashboard
2. Go to **Developer Tools > Notifications**
3. Create a new notification destination:
   - **URL**: `https://yourdomain.com/paddle/webhook`
   - **Events**: Select all subscription and transaction events:
     - `subscription.created`
     - `subscription.updated`
     - `subscription.canceled`
     - `subscription.paused`
     - `subscription.resumed`
     - `transaction.completed`
     - `transaction.paid`
     - `transaction.payment_failed`

4. Copy the **Webhook Secret** to your `.env` as `PADDLE_WEBHOOK_SECRET`

### 5.2 Verify Webhook Endpoint

```bash
curl -X POST https://yourdomain.com/paddle/webhook \
  -H "Content-Type: application/json" \
  -d '{"event_id":"test","event_type":"test"}'
```

Expected response: `{"ok":true}`

---

## 6. Verification Checklist

### 6.1 Billing UX Flow

1. **Create test user** and log in
2. **Navigate to /app/billing** - verify plan cards display
3. **Select Pro plan** and click checkout
4. **Complete Paddle checkout** (use sandbox mode for testing)
5. **Verify /app/billing/success** shows "Syncing billing..." and polls
6. **Verify billing status updates** to "active" after webhook
7. **Test downgrade** - select Free plan, verify "canceling" status
8. **Test clear pending checkout** button

### 6.2 Monitor Limit Enforcement

```bash
php artisan tinker
```

```php
$user = User::find(1);
$user->billing_plan = 'free';
$user->save();

// Create 4 monitors (exceeds free limit of 3)
for ($i = 1; $i <= 4; $i++) {
    Monitor::create(['user_id' => $user->id, 'url' => "https://test{$i}.com", 'name' => "Test {$i}"]);
}

// Run enforcer
app(\App\Services\Billing\PlanEnforcer::class)->enforceMonitorCapForUser($user);

// Check locked monitors
Monitor::where('user_id', $user->id)->where('locked_by_plan', true)->count();
// Should return 1
```

### 6.3 Queue Priority Routing

```bash
php artisan tinker
```

```php
// Check queue for Business team monitor
$team = Team::where('billing_plan', 'business')->first();
$monitor = $team->monitors()->first();

// Dispatch check and verify queue
\Illuminate\Support\Facades\Queue::fake();
\App\Jobs\Monitoring\DispatchDueChecksJob::dispatchSync();
// Business monitors should route to checks_priority
```

### 6.4 Admin Console Access

1. Log in with `ADMIN_OWNER_EMAIL` user
2. Navigate to `/admin`
3. Verify access granted
4. Test with non-admin user - should receive 403

---

## 7. Monitoring & Alerts

### 7.1 Health Endpoint

```bash
curl https://yourdomain.com/_health
```

### 7.2 Queue Depth Monitoring

```bash
redis-cli LLEN queues:checks_standard
redis-cli LLEN queues:checks_priority
redis-cli LLEN queues:webhooks_in
```

### 7.3 Failed Jobs

```bash
php artisan queue:failed
php artisan queue:retry all
```

---

## 8. Common Operations

### 8.1 Deploy Updates

```bash
cd /var/www/monitly
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
supervisorctl restart monitly-workers:*
```

### 8.2 Clear Caches

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 8.3 Manual Billing Enforcement

```bash
php artisan billing:enforce-grace
```

### 8.4 Manual History Pruning

```bash
php artisan monitor-history:prune
```

### 8.5 Replay Webhook

```bash
php artisan tinker
```

```php
$event = \App\Models\BillingWebhookEvent::find($eventId);
$event->processed_at = null;
$event->save();
\App\Jobs\Billing\ProcessPaddleWebhookJob::dispatch($event->id);
```

---

## 9. Troubleshooting

### 9.1 Webhook Not Processing

1. Check webhook secret in `.env`
2. Check `billing_webhook_events` table for signature_valid = false
3. Check worker logs: `tail -f storage/logs/worker-webhooks.log`
4. Check Laravel logs: `tail -f storage/logs/laravel.log`

### 9.2 Billing Status Stuck

1. Check `billing_webhook_events` for processing_error
2. Check if webhook was received
3. Manually reset checkout lock:

```php
$user = User::find($id);
$user->checkout_in_progress_until = null;
$user->save();
```

### 9.3 Monitors Not Checking

1. Verify scheduler is running: `php artisan schedule:list`
2. Check maintenance queue depth
3. Check for paused or locked monitors

### 9.4 Queue Backlog

```bash
# Check queue sizes
redis-cli LLEN queues:checks_standard
redis-cli LLEN queues:checks_priority

# Increase workers if needed
# Edit /etc/supervisor/conf.d/monitly.conf numprocs
supervisorctl reread
supervisorctl update
```

---

## 10. Running Tests

### 10.1 Run All Tests

```bash
php artisan test
```

### 10.2 Run Billing Tests Only

```bash
php artisan test --filter=BillingComprehensiveTest
```

### 10.3 Run Specific Test

```bash
php artisan test --filter=test_normal_user_can_subscribe_to_pro_monthly
```

---

## 11. Security Checklist

- [ ] APP_DEBUG=false in production
- [ ] HTTPS enforced
- [ ] Paddle webhook secret configured
- [ ] Admin email restricted to owner only
- [ ] Database credentials secured
- [ ] Redis password set (if exposed)
- [ ] File permissions correct (755/775)
- [ ] SSRF protection enabled (default)

---

## 12. Backup & Recovery

### 12.1 Database Backup

```bash
mysqldump -u monitly_user -p monitly > /backups/monitly_$(date +%Y%m%d).sql
```

### 12.2 Restore from Backup

```bash
mysql -u monitly_user -p monitly < /backups/monitly_YYYYMMDD.sql
php artisan migrate --force
```

---

## Contact

For emergencies, contact the system administrator at the configured `ADMIN_OWNER_EMAIL`.
