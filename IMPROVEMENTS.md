# Beer Slurper - Improvement Task List

A prioritized list of improvements for the Beer Slurper WordPress plugin.

---

## Critical (Security & Bugs)

- [ ] **Fix missing return statement in `insert_beer()`** - `includes/functions/post.php:92` - Function returns void when successful, should return post ID
- [ ] **Fix `in_array()` parameter order** - `includes/functions/api.php:187` - Arguments are reversed: `in_array($valid_endpoints, $endpoint)` should be `in_array($endpoint, $valid_endpoints)`
- [ ] **Escape output in settings page** - `includes/functions/core.php:156-190` - Form fields use `echo $html` with unescaped option values (potential stored XSS)
- [ ] **Fix duplicate detection logic** - `includes/functions/post.php:28` - `get_post_meta()` returns single values, not arrays; `in_array()` comparison won't work correctly
- [ ] **Return WP_Error instead of null** - `includes/functions/api.php:56-57` - Silent failures make debugging impossible; propagate error objects to callers

---

## High Priority

- [ ] **Implement cron deactivation** - `includes/functions/core.php:75-77` - `deactivate()` function is empty; orphaned cron jobs remain after deactivation
- [ ] **Add comprehensive test coverage** - Only 4 tests exist in `Core_Tests.php`; API, post insertion, brewery, and walker functions are untested
- [ ] **Add rate limiting for API calls** - No protection against hitting Untappd API limits; implement exponential backoff and retry logic
- [ ] **Fix typo in validate_endpoint parameter** - `includes/functions/api.php:28` - `$paramteter` should be `$parameter`
- [ ] **Add error logging** - No logging mechanism exists; API failures and import errors are silent

---

## Medium Priority

- [ ] **Update WordPress compatibility** - Plugin targets WordPress 4.3.0 (2015); update to WordPress 6.0+
- [ ] **Update PHP minimum version** - PHP 5.4 is EOL since 2014; require PHP 7.4+ minimum
- [ ] **Make gallery shortcode configurable** - `beer-slurper.php:105-111` - Gallery is always appended to beer posts with no user control
- [ ] **Remove error suppression** - `includes/functions/post.php:210` - Replace `@unlink()` with proper error handling
- [ ] **Add input validation for Untappd user** - `includes/functions/walker.php` - User input is sanitized but not validated
- [ ] **Implement async image processing** - `download_url()` calls are blocking; consider background processing for image imports
- [ ] **Complete batch handling for high-volume imports** - @todo exists for handling >25 checkins/hour

---

## Low Priority

- [ ] **Add inline documentation** - Most functions lack docblocks; add PHPDoc comments
- [ ] **Implement excerpt generation** - @todo exists in `includes/functions/post.php`
- [ ] **Use array storage for related options** - @todo in `core.php` suggests consolidating options
- [ ] **Complete endpoint validation** - @todo in `api.php`; validation function is disabled
- [ ] **Create admin UI for import control** - @todo mentions UI for starting/stopping imports
- [ ] **Add CLI commands** - WP-CLI commands for manual import management
- [ ] **Implement JavaScript tests** - QUnit test file is a placeholder with only "hello test"
- [ ] **Add CI/CD pipeline** - No GitHub Actions or Travis CI configuration exists
- [ ] **Consider webhook support** - Replace polling cron with Untappd webhooks if available

---

## Code Quality

- [ ] **Modernize JavaScript** - `assets/js/src/beer-slurper.js` is mostly empty; complete or remove
- [ ] **Remove unused SASS configuration** - Grunt is configured for SASS but no `.scss` files exist
- [ ] **Update npm dependencies** - `package.json` dependencies may be outdated
- [ ] **Update Composer dependencies** - Development dependencies may have newer versions

---

## Documentation

- [ ] **Update README.md** - Document current features, installation, and configuration
- [ ] **Add CHANGELOG.md** - Track version history and changes
- [ ] **Document API integration** - Explain Untappd API setup and credentials
- [ ] **Add contributing guidelines** - If accepting contributions

---

## Notes

**Current Version**: 0.1.0 (Early Development)
**Status**: Not production-ready due to critical bugs and security issues

### Files with Most Issues
1. `includes/functions/api.php` - Validation bugs, silent errors
2. `includes/functions/post.php` - Missing return, broken duplicate detection
3. `includes/functions/core.php` - XSS vulnerability, empty deactivation
