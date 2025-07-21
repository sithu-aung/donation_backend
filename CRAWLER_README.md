# Red Juniors Data Crawler

This crawler fetches the latest member and donation data from the Red Juniors API and saves them as timestamped JSON files.

## Setup

1. Ensure Python 3 is installed on your system:
   ```bash
   python3 --version
   ```

2. Install required dependencies:
   ```bash
   pip3 install -r requirements.txt
   ```

## Running the Crawler

### Method 1: Direct Python execution
```bash
python3 crawler.py
```

### Method 2: Execute as script (Unix/Linux/macOS)
```bash
./crawler.py
```

## Output

The crawler will:
1. Login using the credentials (finance@gmail.com / password)
2. Fetch all members from the API
3. Fetch all donations from the API
4. Save the data to timestamped JSON files in the `data` directory

Output files:
- `data/Member_YYYYMMDD_HHMMSS.json` - All member records
- `data/Donation_YYYYMMDD_HHMMSS.json` - All donation records

## Features

- Automatic pagination handling to fetch all records
- Progress reporting during data fetching
- Error handling for network issues
- Data transformation to match existing JSON format
- Timestamped filenames to preserve historical data

## Notes

- The crawler uses the production API at http://16.176.19.197
- It fetches data in batches of 100 records per request
- The data is transformed to match the exact format of the existing Member.json and Donation.json files
- All timestamps in filenames are in local time

## Troubleshooting

If you encounter any issues:

1. **Connection errors**: Check if the API server is accessible
2. **Authentication errors**: Verify the email and password are correct
3. **Permission errors**: Ensure you have write permissions in the data directory
4. **Import errors**: Make sure requests library is installed (`pip3 install requests`)