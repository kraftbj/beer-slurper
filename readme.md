# Beer Slurper #
**Contributors:** [kraftbj](https://profiles.wordpress.org/kraftbj/)  
**Donate link:**       https://kraft.im/donate/  
**Tags:**              beer, untappd  
**Requires at least:** 5.9  
**Tested up to:**      7.0  
**Stable tag:**        0.1.0  
**License:**           GPLv2 or later  
**License URI:**       http://www.gnu.org/licenses/gpl-2.0.html  

Slurp data from Untappd into your site!

## Description ##
This plugin will slurp in all of your old checkins from Untappd (in small batches to keep their API folks happy). It'll also check hourly for any new checkins. New checkins will either add a new "Beer" post or update an existing one, if you've already had that beer.

Upcoming: Breweries.

This is in progress now and the next addition. Add unique breweries to each beer (and collaborating breweries!) all tied together using the relatively new Term Meta.

Upcoming: UI!
Amazingly, I don't want to have to use the command line to install this. I need to add UI to indicate user name,
to start the import, to stop an existing import (less important, frankly).

Upcoming: New Beer Failsafe
Right now, if you have more than 25 checkins within an hour, you'll lose checkins 26+. Not a big deal normally, but if you deactivate the plugin for any length of time...


## Installation ##
For now, to start the whole shebang, need to run bs_start_import( $user ) somehow. This sets up a cron job that will backfill all old checkins for that user and import all new ones.


### Manual Installation ###

1. Upload the entire `/beer-slurper` directory to the `/wp-content/plugins/` directory.
2. Activate Beer Slurper through the 'Plugins' menu in WordPress.

## Data Sources ##

Beer Slurper supports multiple ways to get your Untappd data:

### With API Access (Full Features)
If you have Untappd API credentials, you get complete data including badges, tagged friends, beer descriptions, brewery details, and more.

### Without API Access
If you don't have API credentials (Untappd has limited new API access since ~2023), you can still use Beer Slurper:

1. **Import from Untappd Export**: Download your data from Untappd (requires Untappd Insider subscription or GDPR request) and import the CSV/JSON file.

2. **Scraper Mode**: Automatically fetches your recent public checkins (~25 at a time) without needing API access.

### Data Comparison

| Data | API | Export | Scraper |
|------|-----|--------|---------|
| Checkins (rating, comment, date) | ✅ | ✅ | ✅ |
| Beer name, style, ABV, IBU | ✅ | ✅ | ⚠️ Partial |
| Beer description & label | ✅ | ❌ | ❌ |
| Brewery name & location | ✅ | ✅ | ✅ |
| Brewery details (logo, social) | ✅ | ❌ | ❌ |
| Venue name & coordinates | ✅ | ✅ | ⚠️ Partial |
| Photos | ✅ | ✅ | ✅ |
| **Badges** | ✅ | ❌ | ❌ |
| **Tagged Friends** | ✅ | ❌ | ❌ |
| Historical backfill | ✅ Full | ✅ Full | ⚠️ ~25 recent |

See [docs/DATA-SOURCES.md](docs/DATA-SOURCES.md) for detailed documentation.

## Frequently Asked Questions ##

Q. Can I use Beer Slurper without API access?
A. Yes! Set the Data Source to "Scraper Only" in Settings → Beer. For historical data, export your checkins from Untappd and import the file.

Q. Can I set my Untappd credentials by code?
A. Yes!
define( 'UNTAPPD_KEY',    'XYZ' );
define( 'UNTAPPD_SECRET', 'XYZ' );

This will also hide those settings from the Setting screen for situations where you don't want to reveal the keys to other admins without filesystem access.

## Screenshots ##


## Changelog ##

### 0.1.0 ###
* First development release

## Upgrade Notice ##

### 0.1.0 ###
First development release
