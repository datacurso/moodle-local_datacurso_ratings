## 1.0.3

**Released on:** 2026-04-29

**Compatibility note:** This version is compatible **from Moodle 4.5 to Moodle 5.1**.

## Fixed
- **Database schema defaults aligned with install definition**
  Added an upgrade step that removes legacy default values from `local_datacurso_ratings.courseid`, `local_datacurso_ratings.categoryid`, and `local_datacurso_ratings_feedback.type` so upgraded sites match `db/install.xml` and pass Moodle schema checks

## Changed
- **Version bump**
  Internal version bumped to **2026042900** and release bumped to **1.0.3**
