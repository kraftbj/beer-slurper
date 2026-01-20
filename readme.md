# Beer Slurper #

Slurp data from Untappd into your site!

## Description ##
This plugin will slurp in all of your old checkins from Untappd (in small batches to keep their API folks happy). It'll also check hourly for any new checkins. New checkins will either add a new "Beer" post or update an existing one, if you've already had that beer.

### Features ###
* **Checkin Import** - Automatically imports your Untappd checkins as WordPress posts
* **Brewery Support** - Links beers to breweries using Term Meta for rich taxonomy relationships
* **Style Taxonomy** - Categorizes beers by style (IPA, Stout, Lager, etc.)
* **Feature Images** - Imports beer label images as featured images
* **Auto Gallery** - Optionally appends a gallery shortcode to beer posts (configurable)
* **Rate Limiting** - Built-in protection against hitting Untappd API limits

## Installation ##

### Manual Installation ###

1. Upload the entire `/beer-slurper` directory to the `/wp-content/plugins/` directory.
2. Activate Beer Slurper through the 'Plugins' menu in WordPress.
3. Go to Settings > Beer Slurper to configure your Untappd API credentials and username.

## Configuration ##

### Settings Page ###
Navigate to Settings > Beer Slurper to configure:
* Untappd API Key
* Untappd API Secret
* Untappd Username
* Auto-append Gallery (enable/disable)

### Code Configuration ###
You can also set credentials via code in your `wp-config.php`:

```php
define( 'UNTAPPD_KEY',    'your-api-key' );
define( 'UNTAPPD_SECRET', 'your-api-secret' );
```

This will hide those settings from the Settings screen for situations where you don't want to reveal the keys to other admins without filesystem access.

## Frequently Asked Questions ##

### How do I get Untappd API credentials? ###
You'll need to register for API access at [Untappd for Developers](https://untappd.com/api/). As of 2026, they said their API is now closed except when there's a commericial agreement and existing keys may stop working. Mine hasn't but I think this isn't really going to be a helpful plugin to anyone else unless you have an old API key.

### Can I set my Untappd credentials by code? ###
Yes! See the Configuration section above.

### How often does it check for new checkins? ###
The plugin checks hourly for new checkins via WordPress cron.

## Screenshots ##


## Changelog ##

### 0.1.0 ###
* First development release

## Upgrade Notice ##

### 0.1.0 ###
First development release
