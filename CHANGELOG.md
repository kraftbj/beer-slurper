# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Automatic excerpt generation from beer descriptions
- Endpoint validation for Untappd API calls

### Changed
- Updated WordPress requirement to 6.0+
- Updated PHP requirement to 7.4+

### Removed
- Unused JavaScript build pipeline
- Unused SASS configuration reference

### Fixed
- Critical security fixes for nonce verification and capability checks
- SQL injection prevention with proper escaping
- Syntax error in endpoint validation function
- Improved input sanitization throughout

## [0.1.0] - 2015-07-13

### Added
- Initial development release
- Untappd checkin import functionality
- Automatic hourly sync for new checkins
- Beer custom post type
- Style taxonomy for beer categorization
- Brewery taxonomy with term meta support
- Hierarchical brewery support (ownership relationships)
- Feature image selection from beer labels
- Settings page for API credentials and username
- Support for credential constants (`UNTAPPD_KEY`, `UNTAPPD_SECRET`)
- Gallery display following single beer content
- Internationalization support with text domain
- Grunt build tooling for development
