# Task Breakdown: Low Priority & Code Quality Fixes

## Overview
Total Tasks: 4 Task Groups, 15 Sub-tasks

This spec addresses code quality issues, removes unused configurations, implements missing features, and updates documentation for the Beer Slurper WordPress plugin.

## Task List

### Code Layer

#### Task Group 1: Code Fixes
**Dependencies:** None

- [x] 1.0 Complete code fixes
  - [x] 1.1 Fix syntax error and enable endpoint validation in api.php
    - **File**: `/Users/kraft/code/beer-slurper/includes/functions/api.php`
    - **Line 29**: Fix missing closing parenthesis in `is_wp_error( $endpoint )` - change `is_wp_error( $endpoint ) {` to `is_wp_error( $endpoint ) ) {`
    - **Lines 28-31**: Uncomment the validation block to enable endpoint validation
    - **Line 204**: Remove `// @todo verify this is the right function to use.` comment
    - **Line 207**: Remove `// @todo Make this do something.` comment
  - [x] 1.2 Implement excerpt generation in post.php
    - **File**: `/Users/kraft/code/beer-slurper/includes/functions/post.php`
    - **Line 109**: Replace `'excerpt' => '',` with excerpt generation logic
    - Use `wp_strip_all_tags()` to remove HTML from `$beer_all['beer_description']`
    - Use `wp_trim_words()` to limit to 25-30 words
    - Handle empty descriptions gracefully (return empty string if no description)

**Acceptance Criteria:**
- Endpoint validation is active and working
- No syntax errors in api.php
- Excerpts are generated from beer descriptions
- Empty descriptions result in empty excerpts (no fabricated content)
- PHP lint passes on both modified files

---

### Build Configuration Layer

#### Task Group 2: Cleanup
**Dependencies:** None (can run in parallel with Task Group 1)

- [x] 2.0 Complete build configuration cleanup
  - [x] 2.1 Remove SASS reference from Gruntfile.js
    - **File**: `/Users/kraft/code/beer-slurper/Gruntfile.js`
    - **Line 103**: Remove `'!assets/css/sass/**',` from the copy task exclusion list (directory does not exist)
  - [x] 2.2 Remove unused JavaScript build pipeline from Gruntfile.js
    - **File**: `/Users/kraft/code/beer-slurper/Gruntfile.js`
    - **Lines 6-21**: Remove entire `concat` task configuration
    - **Line 25**: Remove `'assets/js/src/**/*.js',` from jshint `all` array
    - **Lines 29-45**: Remove entire `uglify` task configuration
    - **Lines 81-87**: Remove `scripts` watch task
    - **Line 105**: Remove `'!assetsjs/src/**',` from copy exclusions (note: this has a typo, missing slash)
    - **Line 184**: Update default task to remove `concat` and `uglify` - change to `['jshint', 'cssmin', 'wp_readme_to_markdown', 'makepot']`
  - [x] 2.3 Delete unused JavaScript files
    - Delete `/Users/kraft/code/beer-slurper/assets/js/src/beer-slurper.js` (empty IIFE, 13 lines, no functionality)
    - Delete `/Users/kraft/code/beer-slurper/assets/js/beer-slurper.js` (compiled output) if it exists
    - Delete `/Users/kraft/code/beer-slurper/assets/js/beer-slurper.min.js` (minified output) if it exists
    - Delete `/Users/kraft/code/beer-slurper/assets/js/src/` directory if empty after file removal

**Acceptance Criteria:**
- No reference to non-existent SASS directory in Gruntfile
- No JavaScript build tasks remain in Gruntfile
- No unused JavaScript files remain in repository
- Grunt default and build tasks execute without errors

---

### Documentation Layer

#### Task Group 3: Documentation
**Dependencies:** Task Groups 1 and 2 (documentation should reflect completed changes)

- [x] 3.0 Complete documentation updates
  - [x] 3.1 Update README.md with current features and requirements
    - **File**: `/Users/kraft/code/beer-slurper/README.md`
    - Update "Requires at least" from 4.3.0 to 6.0
    - Update "Tested up to" to current WordPress version (6.x)
    - Add PHP requirement: 7.4+
    - Remove "Upcoming: Breweries" section (already implemented)
    - Remove "Upcoming: UI!" section (settings page exists)
    - Keep "Upcoming: New Beer Failsafe" if still relevant
    - Update Description section to include current features:
      - Checkin import from Untappd
      - Brewery support with term meta
      - Style taxonomy
      - Feature image selection
    - Update Installation section to reference settings page UI
    - Keep Manual Installation section
    - Update FAQ section as needed
  - [x] 3.2 Create CHANGELOG.md
    - **File**: `/Users/kraft/code/beer-slurper/CHANGELOG.md`
    - Use Keep a Changelog format (https://keepachangelog.com/)
    - Include sections: Added, Changed, Fixed, Removed as applicable
    - Document version history based on git commits:
      - 0.1.0: First development release
      - Subsequent changes: Brewery functionality, feature image support, SPDX license update, security fixes

**Acceptance Criteria:**
- README reflects current plugin state (WordPress 6.0+, PHP 7.4+)
- No outdated "Upcoming" sections for implemented features
- CHANGELOG.md exists with proper formatting
- All recent changes documented in CHANGELOG

---

### Verification Layer

#### Task Group 4: Verification
**Dependencies:** Task Groups 1, 2, and 3

- [x] 4.0 Complete verification
  - [x] 4.1 PHP lint modified PHP files
    - Run `php -l includes/functions/api.php`
    - Run `php -l includes/functions/post.php`
    - Verify no syntax errors
  - [x] 4.2 Verify Grunt tasks work
    - Run `grunt --version` to confirm Grunt is available
    - Run `grunt jshint` to verify jshint still works
    - Run `grunt cssmin` to verify CSS minification works
    - Run `grunt default` to verify full default task chain works
  - [x] 4.3 Review all changes
    - Run `git diff` to review all modifications
    - Verify no unintended changes
    - Confirm all @todo comments addressed per spec
    - Verify excerpt generation logic is correct

**Acceptance Criteria:**
- All PHP files pass lint check
- Grunt tasks execute without errors
- All changes reviewed and verified correct
- No regressions introduced

---

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: Code Fixes** - Fix PHP syntax error and implement excerpt generation
2. **Task Group 2: Cleanup** - Remove unused SASS/JS configuration (can run in parallel with Group 1)
3. **Task Group 3: Documentation** - Update README and create CHANGELOG (after code changes complete)
4. **Task Group 4: Verification** - Lint, test, and review all changes

## File Summary

| File | Action | Task |
|------|--------|------|
| `includes/functions/api.php` | Modify | 1.1 |
| `includes/functions/post.php` | Modify | 1.2 |
| `Gruntfile.js` | Modify | 2.1, 2.2 |
| `assets/js/src/beer-slurper.js` | Delete | 2.3 |
| `assets/js/beer-slurper.js` | Delete (if exists) | 2.3 |
| `assets/js/beer-slurper.min.js` | Delete (if exists) | 2.3 |
| `README.md` | Modify | 3.1 |
| `CHANGELOG.md` | Create | 3.2 |
