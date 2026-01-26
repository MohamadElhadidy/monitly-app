# Pre-Deployment Checklist

Use this checklist before deploying to production.

## Critical (Must Do)

- [ ] **Environment Configuration**
  - [ ] Set `APP_ENV=production` in production `.env`
  - [ ] Set `APP_DEBUG=false` in production `.env`
  - [ ] Set `LOG_LEVEL=info` or `warning` in production `.env`
  - [ ] Verify `APP_KEY` is set and unique
  - [ ] Verify `APP_URL` matches production domain

- [ ] **Paddle Configuration**
  - [ ] Replace `PADDLE_WEBHOOK_SECRET` with actual secret
  - [ ] Replace `PADDLE_CUSTOMER_TOKEN` with actual token
  - [ ] Verify all `PADDLE_PRICE_IDS_*` are correct
  - [ ] Test webhook endpoint with Paddle

- [ ] **Database**
  - [ ] Run migrations: `php artisan migrate --force`
  - [ ] Verify database backups are configured
  - [ ] Test database connection
  - [ ] Check database user has proper permissions

- [ ] **Security**
  - [ ] Verify `.env` is in `.gitignore`
  - [ ] Set `.env` file permissions to 600 or 640
  - [ ] Remove any test/development credentials
  - [ ] Verify no sensitive data in codebase
  - [ ] Check SSL certificate is valid
  - [ ] Test HTTPS redirect works

- [ ] **Queue & Cache**
  - [ ] Verify Redis is running and accessible
  - [ ] Test queue connection: `php artisan queue:work --once`
  - [ ] Set up queue worker process (supervisor/systemd)
  - [ ] Verify cache is working: `php artisan cache:clear && php artisan config:cache`

- [ ] **Optimization**
  - [ ] Run `php artisan config:cache`
  - [ ] Run `php artisan route:cache`
  - [ ] Run `php artisan view:cache`
  - [ ] Run `php artisan optimize`
  - [ ] Build assets: `npm run build`

## Important (Should Do)

- [ ] **Monitoring**
  - [ ] Set up error tracking (Sentry, Bugsnag, etc.)
  - [ ] Configure application monitoring
  - [ ] Set up uptime monitoring
  - [ ] Configure log aggregation
  - [ ] Set up alerts for critical errors

- [ ] **Backups**
  - [ ] Configure automated database backups
  - [ ] Test backup restoration process
  - [ ] Set up file storage backups (if using local storage)
  - [ ] Document backup retention policy

- [ ] **Email**
  - [ ] Test email delivery
  - [ ] Verify SMTP credentials
  - [ ] Set up email monitoring
  - [ ] Configure SPF/DKIM records

- [ ] **Testing**
  - [ ] Run full test suite: `php artisan test`
  - [ ] Test critical user flows manually
  - [ ] Test billing integration
  - [ ] Test webhook processing
  - [ ] Test notification delivery

- [ ] **Documentation**
  - [ ] Update API documentation (if applicable)
  - [ ] Document deployment process
  - [ ] Document rollback procedure
  - [ ] Create runbook for common issues

## Recommended (Nice to Have)

- [ ] **Performance**
  - [ ] Enable OPcache
  - [ ] Configure CDN for static assets
  - [ ] Set up database query monitoring
  - [ ] Review and optimize slow queries

- [ ] **Scaling**
  - [ ] Plan for horizontal scaling (if needed)
  - [ ] Configure load balancer (if applicable)
  - [ ] Set up session storage (Redis/database)
  - [ ] Plan for queue worker scaling

- [ ] **Compliance**
  - [ ] Review privacy policy
  - [ ] Review terms of service
  - [ ] Ensure GDPR compliance (if applicable)
  - [ ] Review data retention policies

## Post-Deployment

- [ ] **Verification**
  - [ ] Test all critical user flows
  - [ ] Verify monitoring is working
  - [ ] Check error logs
  - [ ] Verify queue processing
  - [ ] Test email notifications
  - [ ] Verify webhook processing

- [ ] **Monitoring**
  - [ ] Watch error rates
  - [ ] Monitor response times
  - [ ] Check queue backlog
  - [ ] Monitor database performance
  - [ ] Watch for failed jobs

## Rollback Plan

If issues occur:
1. Revert code deployment
2. Restore database from backup (if needed)
3. Clear all caches: `php artisan optimize:clear`
4. Restart queue workers
5. Verify application is functional

## Quick Commands

```bash
# Before deployment
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
npm run build

# After deployment
php artisan queue:restart
php artisan cache:clear  # Only if needed

# Health check
curl https://your-domain.com/_health
```
