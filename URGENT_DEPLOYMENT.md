# URGENT: Backend Deployment Required

## The following backend changes MUST be deployed to the server for the Request/Give feature to work:

### Files Changed:
1. **controllers/RequestGiveController.php**
   - Fixed `actionDetailedReport()` to use `Yii::$app->request->get()` for parameters
   - Added COALESCE for NULL handling in SQL queries
   - Added proper empty data handling

### Deployment Steps:

```bash
# 1. SSH into the server
ssh root@redjuniors.mooo.com

# 2. Navigate to backend directory
cd /var/www/donation_backend

# 3. Pull latest changes
git pull origin main

# 4. Clear cache
php yii cache/flush-all

# 5. Test the endpoint
curl -X GET "https://redjuniors.mooo.com/request-give/detailed-report?year=2025" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

## Expected API Response Format:

### For Yearly View (year only):
```json
{
  "status": "ok",
  "data": {
    "monthlyData": [
      {
        "month": "1",
        "totalrequest": 100,
        "totalgive": 80,
        "count": 5
      }
    ],
    "yearlyTotal": {
      "totalrequest": 1200,
      "totalgive": 960,
      "count": 60
    },
    "year": 2025
  }
}
```

### For Monthly View (year + month):
```json
{
  "status": "ok",
  "data": {
    "records": [...],
    "summary": {
      "year": 2025,
      "month": 1,
      "totalRequest": 100,
      "totalGive": 80,
      "count": 5
    }
  }
}
```

## Frontend is Ready
The frontend has been updated with:
- New modern UI matching donation list style
- Year/month selector tabs
- Edit functionality for each month
- Proper error handling
- API field name compatibility (handles both uppercase and lowercase)

## Current Issue
The API is returning 404 or unauthorized errors because the backend changes have not been deployed to the server yet.

## Contact
If you need help with deployment, please contact the server administrator.