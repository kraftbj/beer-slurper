# Critical Fixes Requirements

## Overview
Fix 5 critical security and bug issues in the Beer Slurper WordPress plugin.

## Requirements

### 1. Fix Missing Return Statement in insert_beer()
- **File**: `includes/functions/post.php:92`
- **Issue**: Function returns void when successful, should return post ID
- **Fix**: Add `return $post_id;` after successful post insertion

### 2. Fix in_array() Parameter Order
- **File**: `includes/functions/api.php:187`
- **Issue**: Arguments are reversed: `in_array($valid_endpoints, $endpoint)` should be `in_array($endpoint, $valid_endpoints)`
- **Fix**: Swap the parameters to correct order

### 3. Escape Output in Settings Page (XSS Vulnerability)
- **File**: `includes/functions/core.php:156-190`
- **Issue**: Form fields use `echo $html` with unescaped option values (potential stored XSS)
- **Fix**: Use `esc_attr()` and `esc_html()` for all output in settings fields

### 4. Fix Duplicate Detection Logic
- **File**: `includes/functions/post.php:28`
- **Issue**: `get_post_meta()` returns single values, not arrays; `in_array()` comparison won't work correctly
- **Fix**: Use direct comparison or proper meta query to check for existing posts

### 5. Return WP_Error Instead of Null for API Errors
- **File**: `includes/functions/api.php:56-57`
- **Issue**: Silent failures make debugging impossible; propagate error objects to callers
- **Fix**: Return WP_Error objects with descriptive messages instead of null

## Success Criteria
- All 5 critical issues are fixed
- No regressions in existing functionality
- Code follows WordPress coding standards
- Changes are tested
