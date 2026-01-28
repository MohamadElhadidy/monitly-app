# Paddle Configuration Guide

This guide will walk you through setting up Paddle billing for your Monitly SaaS application.

## Step 1: Create a Paddle Account

1. Go to [https://vendors.paddle.com](https://vendors.paddle.com)
2. Sign up for a Paddle account
3. Complete the onboarding process
4. Verify your business information

## Step 2: Get Your API Credentials

### 2.1 Get Your Customer Token (Client Token)

1. Log in to your Paddle dashboard: [https://vendors.paddle.com](https://vendors.paddle.com)
2. Go to **Developer Tools** → **Authentication**
3. Find **Client Token** (also called Customer Token or Public Key)
4. Copy the token (it looks like: `test_xxxxx` or `live_xxxxx`)
5. This is used for frontend checkout initialization with Paddle.js

**Note**: 
- Test mode tokens start with `test_`
- Live mode tokens start with `live_`

### 2.2 Get Your API Key (Server-Side) - Optional

1. In Paddle dashboard, go to **Developer Tools** → **Authentication**
2. Find **API Key** (Server-side key)
3. Copy the API key
4. This is used for direct API calls (fallback in your code)

**Note**: Your application uses Laravel Cashier Paddle, which handles most operations automatically. The API key is only needed for fallback operations.

### 2.3 Get Your Webhook Secret

**Important**: You need to create the webhook first to get the secret!

1. Go to **Developer Tools** → **Notifications** (or **Webhooks**)
2. Click **Add Notification** or **Create Webhook**
3. Set the webhook URL (see Step 5.2 for details)
4. After creating, you'll see the **Signing Secret** - copy this immediately
5. Select the events you want to receive:
   - ✅ `subscription.created`
   - ✅ `subscription.updated`
   - ✅ `subscription.canceled`
   - ✅ `transaction.completed`
   - ✅ `transaction.payment_failed`
   - ✅ `invoice.payment_failed`
   - ✅ `subscription.payment_failed`

**Important**: 
- The webhook secret is shown only once when you create the webhook
- Save it immediately to your `.env` file
- If you lose it, you'll need to create a new webhook

## Step 3: Create Products and Prices in Paddle

### 3.1 Create Products

1. Go to **Catalog** → **Products**
2. Create products for each plan:
   - **Pro Plan** ($9/month)
   - **Team Plan** ($29/month)

### 3.2 Create Prices

For each product, create a recurring price:

1. Click on your product
2. Click **Add Price**
3. Set:
   - **Billing Cycle**: Monthly
   - **Price**: Set the amount (e.g., $9 for Pro, $29 for Team)
   - **Currency**: USD (or your preferred currency)
4. Copy the **Price ID** (starts with `pri_...`)

### 3.3 Create Add-on Products

Create separate products for add-ons:

1. **Extra Monitor Pack** - $5/month
2. **Extra Team Member Pack** - $6/month  
3. **Faster Check Interval** - $7/month

For each add-on:
- Create a product
- Create a monthly recurring price
- Copy the Price ID

## Step 4: Configure Your .env File

Update your `.env` file with the following values:

```env
# Paddle Authentication
PADDLE_CUSTOMER_TOKEN=your_client_token_here
PADDLE_API_KEY=your_api_key_here
PADDLE_WEBHOOK_SECRET=your_webhook_secret_here

# Paddle Price IDs (from Step 3)
PADDLE_PRICE_IDS_PRO=pri_01kfxbcqwjasbn01ft54yyh0dp
PADDLE_PRICE_IDS_TEAM=pri_01kfxbgfvqzppenrp6m0estanw

# Add-on Price IDs
PADDLE_PRICE_IDS_ADDON_MONITOR_PACK=pri_01kfxbj08xsdm2jtxgpaha6s19
PADDLE_PRICE_IDS_ADDON_SEAT_PACK=pri_01kfxbk3pb82ngatfjw719bbpc
PADDLE_PRICE_IDS_ADDON_FASTER_CHECKS=pri_01kfxbnx8pnchj09fzkk6z5x87
```

**Important Notes:**
- Replace all placeholder values with your actual Paddle credentials
- Price IDs should start with `pri_`
- If you have multiple price IDs for the same plan (e.g., different currencies), separate them with commas:
  ```
  PADDLE_PRICE_IDS_PRO=pri_xxx,pri_yyy
  ```

## Step 5: Configure Webhook Endpoint

### 5.1 Webhook URL

Your webhook endpoint is configured at:
- **Route**: `/webhooks/paddle`
- **Controller**: `App\Http\Controllers\Webhooks\PaddleWebhookController`
- **CSRF Protection**: Disabled (required for webhooks)

### 5.2 Set Webhook URL in Paddle

1. Go to **Developer Tools** → **Notifications** (or **Webhooks**)
2. Click **Add Notification** or **Create Webhook**
3. Set the webhook URL to: `https://your-domain.com/webhooks/paddle`
   - Replace `your-domain.com` with your actual domain
   - Example: `https://app.monitly.app/webhooks/paddle`
4. **Important**: Must use **HTTPS** (required for production)
5. For local testing, use [ngrok](https://ngrok.com):
   ```bash
   ngrok http 8000
   # Use the HTTPS URL: https://xxxx.ngrok.io/webhooks/paddle
   ```

### 5.3 Webhook Events to Subscribe To

Make sure you're subscribed to these events:
- ✅ `subscription.created`
- ✅ `subscription.updated`
- ✅ `subscription.canceled`
- ✅ `transaction.completed`
- ✅ `transaction.payment_failed`
- ✅ `invoice.payment_failed`

## Step 6: Test Your Configuration

### 6.1 Test Mode vs Live Mode

Paddle has two environments:

**Test Mode (Sandbox):**
- Use for development and testing
- Test cards: `4242 4242 4242 4242`
- Any future expiry date
- Any CVC

**Live Mode:**
- Use for production
- Real payment processing

### 6.2 Testing Checklist

1. **Test Checkout Flow:**
   - Try subscribing to Pro plan
   - Try subscribing to Team plan
   - Verify checkout redirects work

2. **Test Add-ons:**
   - Add add-ons during checkout
   - Verify multiple add-ons can be selected

3. **Test Webhooks:**
   - Check `billing_webhook_events` table in database
   - Verify webhooks are being received
   - Check signature validation is working

4. **Test Subscription Management:**
   - Test cancellation
   - Test payment method updates
   - Verify subscription status updates

## Step 7: Verify Configuration

### 7.1 Check Configuration Values

Run this command to verify your config is loaded:

```bash
php artisan tinker
```

Then run:
```php
config('billing.paddle_webhook_secret');
config('billing.plans.pro.price_ids');
config('billing.plans.team.price_ids');
```

### 7.2 Test Webhook Signature

The application automatically validates webhook signatures. Check the `billing_webhook_events` table:
- `signature_valid` should be `true` for valid webhooks
- Invalid signatures will be logged but not processed

## Step 8: Production Checklist

Before going live:

- [ ] All placeholder values replaced with real credentials
- [ ] Webhook URL is set to production domain (HTTPS)
- [ ] Webhook secret is configured correctly
- [ ] All price IDs are correct
- [ ] Tested checkout flow end-to-end
- [ ] Tested webhook processing
- [ ] Tested subscription cancellation
- [ ] Tested payment failure handling
- [ ] Switched from Test Mode to Live Mode in Paddle
- [ ] Updated `.env` with production credentials

## Troubleshooting

### Issue: Checkout not working

**Check:**
1. `PADDLE_CUSTOMER_TOKEN` is set correctly
2. Price IDs are valid and active in Paddle
3. Check browser console for JavaScript errors
4. Check Laravel logs: `storage/logs/laravel.log`

### Issue: Webhooks not being received

**Check:**
1. Webhook URL is accessible (test with curl or Postman)
2. Webhook URL uses HTTPS (required for production)
3. Webhook secret matches in Paddle dashboard
4. Check `billing_webhook_events` table for incoming events
5. Check queue workers are running: `php artisan queue:work`

### Issue: Invalid webhook signature

**Check:**
1. `PADDLE_WEBHOOK_SECRET` matches the secret in Paddle dashboard
2. Webhook secret hasn't been regenerated (if so, update it)
3. Check signature validation in `PaddleWebhookSignature::verify()`

### Issue: Subscription not updating

**Check:**
1. Webhook events are being processed (check `processed_at` column)
2. Check `processing_error` column for errors
3. Verify queue jobs are running
4. Check Laravel logs for errors

## Environment Variables Reference

```env
# Required
PADDLE_CUSTOMER_TOKEN=        # Client token from Paddle
PADDLE_WEBHOOK_SECRET=       # Webhook signing secret
PADDLE_PRICE_IDS_PRO=        # Pro plan price ID(s)
PADDLE_PRICE_IDS_TEAM=       # Team plan price ID(s)

# Optional (for server-side operations)
PADDLE_API_KEY=              # API key for direct API calls

# Add-ons
PADDLE_PRICE_IDS_ADDON_MONITOR_PACK=    # Monitor pack price ID
PADDLE_PRICE_IDS_ADDON_SEAT_PACK=        # Seat pack price ID
PADDLE_PRICE_IDS_ADDON_FASTER_CHECKS=    # Faster checks price ID
```

## Additional Resources

- [Paddle Documentation](https://developer.paddle.com/)
- [Paddle API Reference](https://developer.paddle.com/api-reference/overview)
- [Laravel Cashier Paddle](https://laravel.com/docs/billing#paddle)
- [Paddle Webhook Events](https://developer.paddle.com/webhooks/overview)

## Support

If you encounter issues:
1. Check Paddle dashboard for transaction logs
2. Check Laravel logs: `storage/logs/laravel.log`
3. Check database: `billing_webhook_events` table
4. Verify queue is processing: `php artisan queue:work`
