# Specification: Low Priority & Code Quality Fixes

## Goal
Clean up code quality issues, remove unused configurations, implement small missing features, and update documentation to reflect the current state of the Beer Slurper WordPress plugin.

## User Stories
- As a developer, I want endpoint validation to be properly enabled or removed so the codebase has no disabled code blocks
- As a user, I want beer posts to have meaningful excerpts so they display properly in archive views

## Specific Requirements

**Enable or Remove Endpoint Validation**
- Located in `includes/functions/api.php` lines 28-31, the `validate_endpoint()` call is commented out with a @todo
- The `validate_endpoint()` function at lines 166-208 is complete and functional
- Fix the syntax error on line 29: missing closing parenthesis in `is_wp_error( $endpoint )`
- Enable the validation by uncommenting lines 28-31 after fixing the syntax
- Remove the @todo comments on lines 204 and 207 as validation will be active

**Implement Excerpt Generation**
- Located in `includes/functions/post.php` line 109, excerpt is hardcoded to empty string with @todo
- Generate excerpt from beer description (`$beer_all['beer_description']`)
- Use WordPress `wp_trim_words()` function to limit to 25-30 words
- Strip any HTML tags before trimming
- If description is empty, leave excerpt empty (do not fabricate content)

**Remove Unused SASS Configuration**
- No `.scss` or `.sass` files exist in the project (confirmed via filesystem search)
- Gruntfile.js references `assets/css/sass/**` in the copy exclusion (line 103)
- The `cssmin` task processes plain CSS files directly, which is correct
- Remove the `assets/css/sass/**` exclusion from the copy task as the directory does not exist
- No SASS compilation task exists in Gruntfile (only cssmin), so no additional cleanup needed

**Clean Up Empty JavaScript File**
- `assets/js/src/beer-slurper.js` contains only an empty IIFE wrapper (13 lines total, no functionality)
- The file is referenced in Gruntfile.js concat task (line 17) and produces compiled output files
- Since the plugin has no frontend JavaScript functionality currently, remove the entire JS build pipeline:
  - Delete `assets/js/src/beer-slurper.js`
  - Delete `assets/js/beer-slurper.js` (compiled output)
  - Delete `assets/js/beer-slurper.min.js` (minified output)
  - Remove concat task from Gruntfile.js (lines 6-21)
  - Remove uglify task from Gruntfile.js (lines 29-45)
  - Remove scripts watch task from Gruntfile.js (lines 81-87)
  - Update default task to remove concat and uglify references (line 184)
  - Remove jshint references to `assets/js/src/**/*.js` (line 25)

**Update README.md**
- Current README references WordPress 4.3.0; plugin now requires WordPress 6.0+ and PHP 7.4+
- "Upcoming: Breweries" section is outdated; breweries are already implemented
- Installation instructions reference command-line only; settings page exists
- Include sections: Description, Features (checkin import, brewery support, style taxonomy, feature images), Installation, Configuration (API credentials via UI or constants), FAQ, Changelog summary
- Remove "Upcoming" sections that are already implemented
- Keep the manual installation section but update it

**Create CHANGELOG.md**
- Document version history starting from 0.1.0
- Include recent changes: brewery functionality, feature image support, SPDX license update
- Use Keep a Changelog format with sections: Added, Changed, Fixed, Removed
- Reference git commit history for accurate dates and descriptions

## Existing Code to Leverage

**`validate_endpoint()` function in api.php**
- Already implements endpoint validation with a comprehensive list of valid Untappd API v4 endpoints
- Returns the endpoint if valid or a WP_Error if invalid
- Just needs to be enabled by uncommenting the caller code and fixing syntax

**WordPress excerpt utilities**
- `wp_trim_words()` is the standard WordPress function for generating excerpts
- `wp_strip_all_tags()` should be used to clean HTML before trimming
- These are core WordPress functions requiring no additional dependencies

**Existing Gruntfile patterns**
- The cssmin task shows the pattern for processing CSS without SASS
- The clean/copy/compress tasks demonstrate the release workflow
- Keep these patterns when removing JS-related tasks

## Out of Scope
- Adding CLI commands (requires WP-CLI integration design)
- Creating admin UI for import control (requires UI mockups)
- Adding CI/CD pipeline (requires infrastructure decisions)
- Implementing webhook support (depends on Untappd API capabilities)
- Consolidating options to array storage (requires migration strategy)
- Adding comprehensive test coverage (separate effort)
- Updating npm/Composer dependencies (separate maintenance task)
- JavaScript test implementation (no JS functionality exists)
- Adding rate limiting beyond current implementation
- Any changes to the core import/walker functionality
