# Changelog

All notable changes to the Time spent plugin are documented here.

## 1.3.6 - 2026-09-22

### Security

- Course report user search matches names only (no email/username).
- Direct `courseid` access respects hidden-course visibility (`viewhiddencourses`).
- User report hides courses the viewer cannot see.
- Interactive reports and export use stored aggregates only (no logstore rebuild on view).
- Spreadsheet export capped at 10,000 rows and streamed in pages.
- Privacy metadata declares all session/aggregate columns present in the schema.
- Capability and report descriptions document site-wide access risk.
- Removed legacy `ajax/get_index_report.php` endpoint.

### Fixed

- Named SQL placeholders in `local_timespent_calculate_last_user_online_session_logout`.

## 1.3.5 - 2026-09-18

### Changed

- Export reads stored aggregates/progress in bulk (no logstore rebuild per row).
- Report table builds name links with DOM APIs (`textContent`) instead of `innerHTML`.
- Course/user pickers both browse on focus (first 25); user search remains name-only.

### Fixed

- CI Mustache Lint: picker inputs include `role="combobox"`.
- CI Grunt: AMD build is properly minified (`amd/build/report.min.js`).
- CI Grunt stylelint: removed `!important` from report CSS.
- Hide loader without Bootstrap `.d-flex` so `[hidden]` works on Moodle 4.5+.
- Restore filter border-radius overrides (stylelint-disabled) against Bootstrap input-group.

## 1.3.4 - 2026-09-18

### Security

- Revoke legacy `coursecreator` default for `local/timespent:viewreport` on upgrade.

## 1.3.3 - 2026-09-18

### Security

- Legacy AJAX endpoint always requires sesskey.
- Default `viewreport` capability limited to manager (coursecreator removed).
- User picker requires 2+ characters and searches names only (not email/username).
- Course search no longer returns hidden courses without `viewhiddencourses`.
- Suspended users excluded from the user picker.
- Export dataformat allowlisted; spreadsheet formula sanitisation hardened.
- Report header JSON encoded with `JSON_HEX_*` flags.

## 1.3.2 - 2026-09-18

### Changed

- Course and user pickers no longer preload full lists on page load.
- Typeahead search loads at most 25 matching courses/users via AJAX (debounced).

### Added

- External service `local_timespent_search_courses`.

## 1.3.1 - 2026-09-18

### Changed

- Report mode uses a Moodle button group (`By course` / `By user`).
- Course and user pickers use simple `form-select` dropdowns (no autocomplete badges).
- Course/user selectors are disabled while report data is loading.
- Loading indicator uses the core Moodle loading template.

## 1.3.0 - 2026-09-18

### Added

- User report mode: select a user and list all enrolled courses with total time online and last session end.
- External services `local_timespent_get_user_report` and `local_timespent_search_users`.
- CSV/Excel export for the user report.

## 1.2.1 - 2026-09-17

### Fixed

- Report showed no time for a visit until the session had been idle for 15 minutes: the session still in
  progress is now included in the total time online and last session end.
- Logging out did not end the tracked session. A `user_loggedout` observer now closes every open session
  of that user at the logout time.
- Session end is the last recorded activity (or the logout) instead of that time plus half the idle
  timeout, which added roughly 7.5 minutes to every session.
- An incremental recalculation could delete an already finished session that started exactly on the
  processing watermark without adjusting the stored totals.

## 1.2.0 - 2026-09-09

### Fixed

- Safer, incremental logstore queries (batched, course-scoped, limited columns) to avoid timeouts on large sites (#1).
- Report data loading now uses Moodle External Services instead of a dedicated AJAX page (#2).
- Report JavaScript migrated to a Moodle AMD module (#3).

### Added

- `local_timespent_progress` table for per-user/course processing watermarks.
- Event observers for `course_viewed` and `course_module_viewed` to update time spent without full log scans.
- External function `local_timespent_get_index_report`.

### Changed

- Report page prefers stored aggregates and only recalculates when stale.
- Legacy `ajax/get_index_report.php` removed in 1.3.6 (was a thin compatibility wrapper until then).

## 1.1.5 - 2026-08-19

### Added

- Initial public release of Time spent for Moodle.
- Session calculation from standard course log entries.
- Session and aggregate storage tables.
- Site report with search, pagination, and CSV/Excel export.
- PHP API for themes and other plugins (`local_timespent_*`).
- Privacy API provider for stored session and aggregate data.
- Capability `local/timespent:viewreport`.
- GitHub Actions Moodle Plugin CI workflow.
- `LICENSE`, `CHANGES.md`, and `thirdpartylibs.xml`.

### Changed

- Renamed public APIs to the `local_timespent_*` prefix.
- Renamed database tables to `local_timespent_session` and `local_timespent_aggregate` (with upgrade rename from legacy table names).
- Self-contained report UI (no shared third-party report frameworks).
- Plugin metadata aligned with Moodle 4.5–5.2 (`$plugin->supported`).
