#!/usr/bin/env python3
"""
Red Juniors Data Crawler
Fetches member and donation data from the API and saves to timestamped JSON files
"""

import requests
import json
from datetime import datetime
import os
import sys
import urllib3

# Disable SSL warnings for self-signed certificates
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# API Configuration
BASE_URL = "https://redjuniors.mooo.com"
LOGIN_EMAIL = "finance@gmail.com"
LOGIN_PASSWORD = "password"

# File paths
DATA_DIR = "data"

def ensure_data_dir():
    """Ensure data directory exists"""
    if not os.path.exists(DATA_DIR):
        os.makedirs(DATA_DIR)
        print(f"Created {DATA_DIR} directory")

def login():
    """Login to get access token"""
    login_url = f"{BASE_URL}/auth/login"
    login_data = {
        "email": LOGIN_EMAIL,
        "password": LOGIN_PASSWORD
    }
    
    print(f"Logging in with {LOGIN_EMAIL}...")
    print(f"URL: {login_url}")
    
    try:
        response = requests.post(login_url, json=login_data, headers={'Content-Type': 'application/json'}, verify=False, timeout=10)
        response.raise_for_status()
        
        result = response.json()
        if result['status'] == 'ok':
            access_token = result['data']['access_token']
            print("Login successful!")
            return access_token
        else:
            print(f"Login failed: {result.get('message', 'Unknown error')}")
            return None
    except requests.exceptions.RequestException as e:
        print(f"Login request failed: {e}")
        return None
    except KeyError as e:
        print(f"Unexpected response format: {e}")
        print(f"Response: {response.text}")
        return None

def fetch_all_members(access_token):
    """Fetch all members from the API"""
    all_members = []
    page = 0
    limit = 500  # Reduced batch size
    
    print("\nFetching members...")
    
    while True:
        members_url = f"{BASE_URL}/member/index"
        params = {
            'page': page,
            'limit': limit,
            'q': ''  # Empty search query to get all members
        }
        print(f"  Fetching page {page + 1} (limit={limit})...")
        headers = {
            'Authorization': f'Bearer {access_token}',
            'Content-Type': 'application/json'
        }
        
        try:
            response = requests.get(members_url, params=params, headers=headers, verify=False, timeout=30)
            response.raise_for_status()
            
            result = response.json()
            if result['status'] == 'ok':
                members = result['data']
                total = result.get('total', 0)
                
                if not members:
                    break
                
                all_members.extend(members)
                print(f"  Fetched page {page + 1}: {len(members)} members (Total so far: {len(all_members)})")
                
                # Check if we've fetched all members
                if len(all_members) >= total or len(members) < limit:
                    break
                    
                page += 1
                
                # Safety limit to prevent infinite loops
                if page >= 500:
                    print("  Reached page limit (500 pages)")
                    break
            else:
                print(f"Failed to fetch members: {result.get('message', 'Unknown error')}")
                break
        except requests.exceptions.RequestException as e:
            print(f"Request failed: {e}")
            break
    
    print(f"Total members fetched: {len(all_members)}")
    return all_members

def fetch_all_donations(access_token):
    """Fetch all donations from the API"""
    all_donations = []
    page = 0
    limit = 500  # Reduced batch size
    
    print("\nFetching donations...")
    
    while True:
        donations_url = f"{BASE_URL}/donation/index"
        params = {
            'page': page,
            'limit': limit,
            'q': '',  # Empty search query to get all donations
            'order': 'asc'  # Get oldest first
        }
        print(f"  Fetching page {page + 1} (limit={limit})...")
        headers = {
            'Authorization': f'Bearer {access_token}',
            'Content-Type': 'application/json'
        }
        
        try:
            response = requests.get(donations_url, params=params, headers=headers, verify=False, timeout=30)
            response.raise_for_status()
            
            result = response.json()
            if result['status'] == 'ok':
                donations = result['data']
                total = result.get('total', 0)
                
                if not donations:
                    break
                
                all_donations.extend(donations)
                print(f"  Fetched page {page + 1}: {len(donations)} donations (Total so far: {len(all_donations)})")
                
                # Check if we've fetched all donations
                if len(all_donations) >= total or len(donations) < limit:
                    break
                    
                page += 1
                
                # Safety limit to prevent infinite loops
                if page >= 500:
                    print("  Reached page limit (500 pages)")
                    break
            else:
                print(f"Failed to fetch donations: {result.get('message', 'Unknown error')}")
                break
        except requests.exceptions.RequestException as e:
            print(f"Request failed: {e}")
            break
    
    print(f"Total donations fetched: {len(all_donations)}")
    return all_donations

def transform_member_data(members):
    """Transform member data to match the existing format"""
    transformed = []
    
    for member in members:
        # Convert database fields to match existing JSON format
        transformed_member = {
            "_id": str(member.get('id', '')),
            "address": member.get('address', ''),
            "birthDate": member.get('birth_date', ''),
            "bloodBankCard": member.get('blood_bank_card', ''),
            "bloodType": member.get('blood_type', ''),
            "fatherName": member.get('father_name', ''),
            "lastDate": member.get('last_date', None),
            "memberCount": str(member.get('member_count', '0')),
            "memberId": member.get('member_id', ''),
            "name": member.get('name', ''),
            "note": member.get('note', '-'),
            "nrc": member.get('nrc', ''),
            "owner_id": str(member.get('owner_id', '1')),
            "phone": member.get('phone', ''),
            "registerDate": member.get('register_date', ''),
            "status": member.get('status', 'available'),
            "totalCount": str(member.get('total_count', '0')),
            "gender": member.get('gender', '')
        }
        
        # Add profile_url if exists
        if 'profile_url' in member and member['profile_url']:
            transformed_member['profileUrl'] = member['profile_url']
            
        transformed.append(transformed_member)
    
    return transformed

def transform_donation_data(donations):
    """Transform donation data to match the existing format"""
    transformed = []
    
    for donation in donations:
        # Convert database fields to match existing JSON format
        transformed_donation = {
            "_id": str(donation.get('id', '')),
            "date": donation.get('date', ''),
            "donationDate": donation.get('donation_date', ''),
            "hospital": donation.get('hospital', ''),
            "member": str(donation.get('member', '')),
            "memberId": donation.get('member_id', ''),
            "memberObj": str(donation.get('member', '')),
            "owner_id": str(donation.get('owner_id', '1')),
            "patientAddress": donation.get('patient_address', ''),
            "patientAge": donation.get('patient_age', ''),
            "patientDisease": donation.get('patient_disease', ''),
            "patientName": donation.get('patient_name', '')
        }
        
        # If memberObj data is included, use it
        if 'memberObj' in donation and donation['memberObj']:
            transformed_donation['memberObj'] = str(donation['memberObj'].get('id', donation.get('member', '')))
        elif 'member0' in donation and donation['member0']:
            transformed_donation['memberObj'] = str(donation['member0'].get('id', donation.get('member', '')))
            
        transformed.append(transformed_donation)
    
    return transformed

def save_to_file(data, filename):
    """Save data to JSON file"""
    filepath = os.path.join(DATA_DIR, filename)
    
    try:
        with open(filepath, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=4)
        print(f"Saved {len(data)} records to {filepath}")
        return True
    except Exception as e:
        print(f"Failed to save to {filepath}: {e}")
        return False

def main():
    """Main crawler function"""
    print("Red Juniors Data Crawler")
    print("========================")
    
    # Ensure data directory exists
    ensure_data_dir()
    
    # Generate timestamp for filenames
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    # Login
    access_token = login()
    if not access_token:
        print("Failed to login. Exiting.")
        sys.exit(1)
    
    # Fetch members
    members = fetch_all_members(access_token)
    if members:
        # Transform and save members
        transformed_members = transform_member_data(members)
        member_filename = f"Member_{timestamp}.json"
        save_to_file(transformed_members, member_filename)
    
    # Fetch donations
    donations = fetch_all_donations(access_token)
    if donations:
        # Transform and save donations
        transformed_donations = transform_donation_data(donations)
        donation_filename = f"Donation_{timestamp}.json"
        save_to_file(transformed_donations, donation_filename)
    
    print("\nCrawling completed!")
    print(f"Files saved in {DATA_DIR} directory:")
    print(f"  - Member_{timestamp}.json")
    print(f"  - Donation_{timestamp}.json")

if __name__ == "__main__":
    main()