# High Priority Fixes Requirements

## Overview
Fix 5 high priority issues in the Beer Slurper WordPress plugin.

## Requirements

### 1. Implement Cron Deactivation
- **File**: `includes/functions/core.php:75-77`
- **Issue**: `deactivate()` function is empty; orphaned cron jobs remain after plugin deactivation
- **Fix**: Add `wp_clear_scheduled_hook('beer_slurper_cron')` to properly clean up scheduled events

### 2. Add Comprehensive Test Coverage
- **Current state**: Only 4 tests exist in `Core_Tests.php`
- **Issue**: API, post insertion, brewery, and walker functions are untested
- **Fix**: Add unit tests for critical functions (can be deferred or simplified for this pass)

### 3. Add Rate Limiting for API Calls
- **Issue**: No protection against hitting Untappd API limits
- **Fix**: Implement basic rate limiting with transient-based tracking or exponential backoff

### 4. Fix Typo in validate_endpoint Parameter
- **File**: `includes/functions/api.php:28`
- **Issue**: `$paramteter` should be `$parameter`
- **Fix**: Rename the parameter to correct spelling

### 5. Add Error Logging
- **Issue**: No logging mechanism exists; API failures and import errors are silent
- **Fix**: Add basic error logging using `error_log()` or WordPress debug log for critical failures

## Success Criteria
- Cron jobs are properly cleared on deactivation
- Typo is fixed
- Basic error logging is in place for API failures
- Rate limiting provides basic protection (can be simple implementation)
- Test coverage improvement is optional for this pass
