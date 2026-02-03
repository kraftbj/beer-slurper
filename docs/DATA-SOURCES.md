# Data Sources

Beer Slurper supports multiple ways to import your Untappd checkin history. This document explains the different data sources available and their tradeoffs.

## Overview

| Feature | API | Untappd Export | Scraper |
|---------|-----|----------------|---------|
| **Requires API credentials** | Yes | No | No |
| **Historical backfill** | Full history | Full history | ~25 recent |
| **Ongoing sync** | Automatic | Manual re-export | Automatic |
| **Data completeness** | Complete | Partial | Minimal |

## Data Source Modes

### 1. API Only (Default)

The original mode. Requires Untappd API credentials (Client ID and Secret).

**How to get API access:**
- Apply at [untappd.com/api](https://untappd.com/api)
- Note: Untappd has limited new API access since ~2023

**Features:**
- Full automatic sync (hourly)
- Complete historical backfill
- All data fields available

### 2. Scraper Only (No API Required)

For users without API access. Uses RSS feed (preferred) or page scraping.

**How it works:**
1. **RSS Feed (preferred)** - If you configure your RSS URL, Beer Slurper polls it for new checkins. This is the "polite" method using Untappd's official RSS feature.
2. **Page Scraping (fallback)** - If no RSS URL is configured, falls back to scraping your public profile page.

**Setting up RSS (recommended):**
1. Go to [untappd.com/account/settings](https://untappd.com/account/settings)
2. Find "RSS Private Feed URL"
3. Copy the full URL (includes your personal key)
4. Paste it into Beer Slurper settings

**Why RSS is better than scraping:**
- RSS is an official Untappd feature with its own TOS
- Structured data (XML) vs parsing HTML
- More reliable - won't break when Untappd updates their website
- More respectful to Untappd's servers

**Features:**
- Works without API credentials
- Automatic sync of recent checkins
- Limited to ~25 most recent checkins per sync

**Limitations:**
- Cannot backfill full history (use Export Import for that)
- Missing detailed metadata (see Data Comparison below)

### 3. Hybrid (Recommended)

Uses API when available, falls back to scraper if API fails.

**Best for:**
- Users with API access who want a fallback
- Transitioning between data sources

## Import from Untappd Export

Regardless of which data source mode you use, you can import historical data from an Untappd export file.

### How to Export Your Data from Untappd

1. **Untappd Insider ($4.99/month):**
   - Go to [untappd.com/user](https://untappd.com/user) → Settings → Export Data
   - Choose JSON or CSV format
   - Data is emailed to you

2. **GDPR Data Request (Free for EU residents):**
   - Contact Untappd support
   - Request your data under GDPR
   - Received via email in CSV format

3. **Support Ticket (Anyone):**
   - Email Untappd support requesting your data
   - May take several days

### Importing Your Export

1. Go to **Settings → Beer** in WordPress admin
2. Scroll to **Import from Untappd Export**
3. Drag and drop your CSV or JSON file
4. Wait for import to complete

The importer handles duplicate detection automatically.

---

## Data Comparison

### What Each Source Provides

#### Checkin Data

| Field | API | Export | Scraper |
|-------|-----|--------|---------|
| Checkin ID | ✅ | ✅ | ✅ |
| Rating | ✅ | ✅ | ✅ |
| Comment | ✅ | ✅ | ✅ |
| Date/Time | ✅ | ✅ | ✅ |
| Serving Type | ✅ | ✅ | ⚠️ Maybe |
| Photo | ✅ | ✅ | ✅ |

#### Beer Data

| Field | API | Export | Scraper |
|-------|-----|--------|---------|
| Beer ID | ✅ | ✅ | ✅ |
| Beer Name | ✅ | ✅ | ✅ |
| Style | ✅ | ✅ | ✅ |
| ABV | ✅ | ✅ | ❌ |
| IBU | ✅ | ✅ | ❌ |
| Description | ✅ | ❌ | ❌ |
| Label Image | ✅ | ❌ | ❌ |

#### Brewery Data

| Field | API | Export | Scraper |
|-------|-----|--------|---------|
| Brewery ID | ✅ | ✅ | ✅ |
| Brewery Name | ✅ | ✅ | ✅ |
| City/State/Country | ✅ | ✅ | ❌ |
| Coordinates | ✅ | ❌ | ❌ |
| Description | ✅ | ❌ | ❌ |
| Logo | ✅ | ❌ | ❌ |
| Type (Micro, Macro, etc.) | ✅ | ❌ | ❌ |
| Social Links | ✅ | ❌ | ❌ |
| Parent/Owner Brewery | ✅ | ❌ | ❌ |

#### Venue Data

| Field | API | Export | Scraper |
|-------|-----|--------|---------|
| Venue ID | ✅ | ❌ | ⚠️ Maybe |
| Venue Name | ✅ | ✅ | ⚠️ Maybe |
| City/State/Country | ✅ | ✅ | ❌ |
| Coordinates | ✅ | ✅ | ❌ |
| Address | ✅ | ❌ | ❌ |
| Category | ✅ | ❌ | ❌ |
| Foursquare ID | ✅ | ❌ | ❌ |

#### API-Only Features

These features are **only available with API access**:

| Feature | API | Export | Scraper |
|---------|-----|--------|---------|
| **Badges** | ✅ | ❌ | ❌ |
| **Tagged Friends (Companions)** | ✅ | ❌ | ❌ |
| **Collaboration Breweries** | ✅ | ❌ | ❌ |
| **Beer Stats (global checkin count)** | ✅ | ❌ | ❌ |

---

## Recommended Setup

### If You Have API Access

1. Set Data Source to **API Only** or **Hybrid**
2. Connect via OAuth in Settings
3. Let the automatic sync handle everything

### If You Don't Have API Access

1. Set Data Source to **Scraper Only**
2. Export your data from Untappd (see above)
3. Import the export file for historical data
4. Scraper will automatically sync new checkins (~25 at a time)
5. Periodically re-export and re-import for full sync

### Migrating from API to No-API

If your API access stops working:

1. Export your data from Untappd while you still can
2. Change Data Source to **Scraper Only**
3. Import your export file (duplicates are skipped)
4. Scraper takes over for new checkins

---

## Technical Details

### Scraper User-Agent

The scraper identifies itself as:
```
personal-beerlog-backup/1.0 (WordPress plugin for personal beer history; non-commercial)
```

This makes the personal, non-commercial nature of the tool clear.

### Scraper Limitations

- Only fetches publicly visible checkins
- Private profiles cannot be scraped
- Rate limited to avoid overwhelming Untappd's servers
- HTML structure changes may require updates

### Import Source Tracking

Each checkin is tagged with its import source in post meta:
- `_beer_slurper_import_source`: `api`, `scraper`, or `untappd_export`

This allows you to identify which checkins might benefit from API enrichment later.

---

## FAQ

**Q: Can I use the scraper if my profile is private?**
A: No, the scraper only works with public profiles. You'll need to use the export import method.

**Q: Will I lose my badges if I switch from API to scraper?**
A: No, existing data is preserved. But new checkins won't include badge data.

**Q: How often does the scraper sync?**
A: By default, hourly (same as API sync). This is configurable via the Action Scheduler.

**Q: The scraper isn't finding my checkins. Why?**
A: Check that:
1. Your Untappd profile is public
2. Your username is correct in the settings
3. Untappd hasn't changed their HTML structure (check for plugin updates)

**Q: Can I enrich scraped data with API data later?**
A: Yes, if you gain API access later, the backfill maintenance jobs will fill in missing metadata over time.
