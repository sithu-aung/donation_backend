# Deployment Instructions for Backend

## Quick Deploy (For Server Administrator)

To deploy the latest backend changes to the server, follow these steps:

1. **SSH into the server:**
```bash
ssh root@redjuniors.mooo.com
```

2. **Navigate to the backend directory:**
```bash
cd /var/www/donation_backend
```

3. **Pull the latest changes from GitHub:**
```bash
git pull origin main
```

4. **Clear Yii2 cache (if needed):**
```bash
php yii cache/flush-all
```

5. **Ensure proper permissions:**
```bash
chmod -R 755 web/assets
chmod -R 777 runtime
```

## Recent Changes That Need Deployment

### RequestGiveController.php
- Fixed `actionDetailedReport` to properly handle GET parameters
- Added COALESCE to SQL queries to handle NULL values
- Ensure empty arrays are returned instead of null

## Testing the API

After deployment, test the API endpoint:

```bash
# Test with authentication token
curl -X GET "https://redjuniors.mooo.com/request-give/detailed-report?year=2025" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

## Troubleshooting

If the API still returns errors:

1. Check PHP error logs:
```bash
tail -f /var/log/php/error.log
```

2. Check Yii2 application logs:
```bash
tail -f /var/www/donation_backend/runtime/logs/app.log
```

3. Verify database connection:
```bash
php yii migrate/status
```