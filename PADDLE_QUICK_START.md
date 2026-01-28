# Paddle Quick Start Guide

## Quick Setup (5 Minutes)

### 1. Get Your Credentials from Paddle

1. **Customer Token**: 
   - Dashboard → Developer Tools → Authentication
   - Copy **Client Token**

2. **Webhook Secret**:
   - Dashboard → Developer Tools → Notifications
   - Create webhook: `https://your-domain.com/webhooks/paddle`
   - Copy **Signing Secret**

3. **Price IDs**:
   - Catalog → Products → Create products
   - Create prices for each product
   - Copy **Price ID** (starts with `pri_...`)

### 2. Update .env File

```env
# Replace these with your actual values
PADDLE_CUSTOMER_TOKEN=test_xxxxxxxxxxxxx
PADDLE_WEBHOOK_SECRET=your_webhook_secret_here

# Price IDs (replace with your actual price IDs)
PADDLE_PRICE_IDS_PRO=pri_xxxxxxxxxxxxx
PADDLE_PRICE_IDS_TEAM=pri_xxxxxxxxxxxxx
PADDLE_PRICE_IDS_ADDON_MONITOR_PACK=pri_xxxxxxxxxxxxx
PADDLE_PRICE_IDS_ADDON_SEAT_PACK=pri_xxxxxxxxxxxxx
PADDLE_PRICE_IDS_ADDON_FASTER_CHECKS=pri_xxxxxxxxxxxxx
```

### 3. Clear Config Cache

```bash
php artisan config:clear
php artisan config:cache
```

### 4. Test

1. Go to `/app/billing`
2. Try to subscribe to a plan
3. Use test card: `4242 4242 4242 4242`
4. Check webhooks in database: `billing_webhook_events` table

## Common Issues

**Checkout not working?**
- Check `PADDLE_CUSTOMER_TOKEN` is set
- Check price IDs are correct
- Check browser console for errors

**Webhooks not working?**
- Verify webhook URL is accessible
- Check `PADDLE_WEBHOOK_SECRET` matches
- Ensure queue is running: `php artisan queue:work`

## Full Documentation

See `PADDLE_SETUP_GUIDE.md` for complete setup instructions.
