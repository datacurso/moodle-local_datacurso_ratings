# Changelog

All notable changes to this plugin will be documented in this file.

## [1.0.6]

### Added
- CSV export internationalization: localized filenames and headers across 7 languages (DE, EN, ES, FR, ID, PT-BR, RU).
- Configurable comment character limit via admin setting `maxcommentlength` (default: 200) with server-side truncation and frontend `maxlength` enforcement.
- Backup and restore support for plugin data.
- Course rating localization strings across all supported languages.
- Comment toggle ("Hide Comments") localization across all supported languages.
- Comprehensive PHPUnit test suite: access control, AI analysis, backup/restore, courselib, feedback service, helpers, hook callbacks, language, navigation, privacy provider, recommendations, save rating, update recommendations cache.
- Behat feature tests for AI button access control and widget visibility across activity types.
- AI services API stub for isolated PHPUnit testing.
- Feedback text length validation with corresponding tests.

### Changed
- Migrated external classes from legacy `require_once(externallib.php)` to `core_external\*` namespace.
- Added `get_ai_client()` factory method to AI analysis classes for testability.
- Updated plugin CI workflow with Behat step and faildump upload.

### Fixed
- Enrollment and unique constraint issues in tests.
- Mustache lint errors with example contexts in templates.
- PHPCS formatting in `save_rating`.
- Removed chat and survey Behat scenarios (modules removed in Moodle 5.0+).

## [1.0.5]

### Improved
- Filters and pagination in the general evaluation report for better data loading.
- CSV export of the general and course-level reports.
- AI analytics generation permissions now correctly apply to the teacher role view.
- UI bug fixes.

### Changed
- Added `$plugin->supported` to `version.php` to declare compatible Moodle versions.
- Version update to 1.0.5.
