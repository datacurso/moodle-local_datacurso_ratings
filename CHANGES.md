# Changelog

All notable changes to this plugin will be documented in this file.

## [1.1.0]

### Security
- Privacy API provider rewritten to use the module context, with userlist support and the Datacurso AI service declared as an external location.
- Event observers delete ratings when a course or course module is deleted.
- New `local/datacurso_ratings:rate` capability; `save_rating` now rejects guests, disabled courses and unsupported module types server side, and cleans feedback as `PARAM_TEXT`.
- Capability context levels corrected and two unused capabilities removed.
- AI analysis error message is escaped in the template instead of being rendered as raw HTML.
- CSV exports neutralise formula injection (`=`, `+`, `-`, `@`, tab, carriage return) via a shared `csv_utils` AMD helper.
- `get_activity_comments` clamps `perpage` to an allowed page size and `page` to a non-negative value (no division by zero on `perpage=0`).
- Inline `onclick` handlers removed from the report templates in favour of `data-action` listeners.
- `maxcommentlength` is bounded to 1..2000 (falls back to 200) through `local_datacurso_ratings_get_max_comment_length()`.

### Fixed
- Predefined feedback phrases are no longer double-escaped, so phrases containing `&` or `"` match the stored phrase and are exempt from the free-text limit.
- Stray backtick removed from the AI analysis response template.

### Changed
- `update_recommendations_cache` streams user ids with a recordset and computes the global like ratio once per run; `service::get_recommendations_for_user()` accepts an optional precomputed ratio.
- Version bumped to 2026092401.

## [1.0.7]

### Added
- Feedback length validation distinguishes free-text comments from predefined phrases: the `maxcommentlength` limit truncates free text only, while admin phrases are stored in full.

### Changed
- Release metadata updates.

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
