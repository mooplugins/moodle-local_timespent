# Changelog

All notable changes to the Time spent plugin are documented here.

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
