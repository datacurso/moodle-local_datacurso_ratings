# Changelog

All notable changes to this plugin will be documented in this file.

## [1.1.0-wp] - 2026-10-07

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

Brings `main` (1.0.5 to 1.1.0, entries below) into the Workplace branch, which was at 1.0.4-wp, and keeps ratings, feedback phrases and course settings per tenant.

### Fixed
- **Ratings and feedback phrases can be saved after an upgrade**: 1.0.4-wp declared `tenant_id` on the ratings and on the feedback phrases as a required column without a default, so a site upgraded to a release that does not set it failed with `Field 'tenant_id' doesn't have a default value` on every new rating and phrase. A new upgrade step gives the column a default of 0, adds it on sites that ran the plugin without Workplace, moves the course settings to one row per course and tenant, and assigns the ratings saved without a tenant to the tenant of the person who rated.
- **Course recommendations stay within the courses a user may see**: the recommendations picked from the 300 most recent visible courses of the whole site, so on Workplace a user was recommended courses of other tenants, with their names, although Workplace hides those course lists from them. Each candidate is now checked with `core_course_category::can_view_course_info()` for the user, and the fallback like ratio is computed per tenant.
- **The plugin runs without `tool_tenant`**: every tenant lookup goes through `local\tenancy`, which falls back to a single implicit tenant (0) and the site settings, instead of calling `\tool_tenant\tenancy` directly, which failed on the CI site.

### Changed
- **Ratings switched on per tenant, with a site default**: the tenant settings page decides for its tenant; a tenant that never saved it follows the "Enable plugin in courses" setting of the site, which is back on a site settings page together with the comment limit from `main`.
- **Shared phrases and course settings**: phrases and course settings saved under tenant 0 (those of a site that ran the plugin without tenancy) apply to every tenant that has not set its own.
- **The global report and the global AI analysis** count the ratings of the tenant of the administrator only.
- **Backup and restore** carry the tenant of the course settings, and restored ratings take the tenant of the user they belong to.
- **Declared support** is `[405, 405]` and Jenkins runs Moodle 4.5 only.

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
