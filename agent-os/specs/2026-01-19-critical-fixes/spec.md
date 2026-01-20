# Specification: Critical Bug and Security Fixes

## Goal
Fix 5 critical issues in the Beer Slurper plugin including a security vulnerability (XSS), broken logic errors, and missing return values that cause silent failures and data integrity problems.

## User Stories
- As a developer, I want API errors to propagate correctly so that I can debug issues and handle failures gracefully
- As a site administrator, I want the settings page to be secure against XSS attacks so that malicious data cannot be injected

## Specific Requirements

**Fix Missing Return Statement in insert_beer()**
- Location: `includes/functions/post.php` line 92
- The function currently ends without returning a value after successful post creation/update
- Add `return $post_id;` as the final statement before the closing brace
- This enables callers to use the created post ID for further operations
- Aligns with existing pattern in the function where early returns provide `WP_Error` objects

**Fix in_array() Parameter Order in validate_endpoint()**
- Location: `includes/functions/api.php` line 187
- Current code: `in_array( $valid_endpoints, $endpoint )` - parameters are reversed
- Correct code: `in_array( $endpoint, $valid_endpoints, true )`
- Add strict type checking (third parameter `true`) to prevent type coercion issues
- This fix ensures endpoint validation actually works as intended

**Fix Duplicate Detection Logic in insert_beer()**
- Location: `includes/functions/post.php` line 28
- Current code uses `in_array()` with `get_post_meta()` but the meta function returns an array of values when third parameter is false
- The checkin_id needs to be compared against all stored meta values for that key
- Use `get_post_meta( $post_id, '_beer_slurper_untappd_id', false )` explicitly to get array of all values
- The `in_array()` call structure is correct if `get_post_meta()` returns multiple values as an array

**Return WP_Error Instead of Null for API Errors**
- Location: `includes/functions/api.php` lines 56-57
- Current code returns nothing (void) when `is_wp_error( $response )` is true
- Change `return;` to `return $response;` to propagate the WP_Error object
- This allows callers to detect and handle specific API failures
- Follows the "Fail Early and Explicitly" error handling standard

**Escape Output in Settings Page (XSS Fix)**
- Location: `includes/functions/core.php` lines 156, 172, 188
- Three settings field callbacks output option values without escaping
- `setting_key()` line 156: wrap `get_option()` value with `esc_attr()`
- `setting_secret()` line 172: wrap `get_option()` value with `esc_attr()`
- `setting_user()` line 188: wrap `get_option()` value with `esc_attr()`
- Use `esc_attr()` specifically because values are inside HTML attribute context (value="...")
- This prevents stored XSS attacks if malicious data were saved to options

## Existing Code to Leverage

**WP_Error Pattern in post.php**
- Lines 11, 15, 29, 96 demonstrate consistent WP_Error usage with error codes and translatable messages
- Follow same pattern: `return new \WP_Error( 'error_code', __( "Message", 'beer_slurper' ) );`
- API fix should return existing WP_Error object rather than creating new one

**WordPress Escaping Functions**
- `esc_attr()` - for escaping attribute values (use for input value attributes)
- `esc_html()` - for escaping text content (not needed in these specific fixes)
- These are WordPress core functions available globally

**Settings Field Registration Pattern in core.php**
- Lines 91-98 show consistent pattern for registering settings with sanitization callbacks
- The `strip_tags` and `sanitize_user` callbacks provide input sanitization
- Output escaping with `esc_attr()` complements existing input sanitization

## Out of Scope
- Adding new features or functionality
- Refactoring code beyond the specific fixes
- Adding input validation beyond current sanitization
- Modifying the commented-out endpoint validation call in api.php (lines 28-31)
- Adding error logging or monitoring
- Changing the database schema or meta key names
- Adding unit tests (deferred per testing standards)
- Modifying other files not mentioned in requirements
- Adding backwards compatibility code
- Changing function signatures or adding new parameters
