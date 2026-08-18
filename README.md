# Time spent for Moodle

See how much time learners spend in your courses — based on their Moodle activity, not estimates from unfinished quizzes or self-reported study hours.

## Why Time spent?

Moodle records when learners click around a course, but it does not turn that into a clear “time spent learning” figure out of the box. Teachers and admins often want a simple answer: *How long has this learner been engaged in this course?*

Time spent bridges that gap. It turns course activity into online study sessions and totals, so you can review engagement in a dedicated report and show time spent in other learner-facing experiences that use this plugin.

## Features

### Meaningful session totals

Activity in a course is grouped into online sessions. Quiet gaps end a session and a new one starts when the learner returns. Totals reflect engaged study time, not every second since enrolment.

### Course report for staff

Managers and teachers with permission can open a **Time spent** report, pick a course, and see enrolled users with:

- Total time online
- When their last session ended

Search, browse pages of results, and export to spreadsheet formats when you need to share numbers with colleagues.

### Ready for other plugins and themes

Other parts of Moodle can display a learner’s time spent (for example on a course completion celebration page). Those integrations use the totals calculated by this plugin when it is installed.

### Privacy-aware

Stored session and total data is covered by Moodle’s Privacy API, so your site can include it in privacy exports and deletion where required.

## Requirements

- Moodle 4.5 or later
- Moodle’s standard log store enabled (the usual activity log most sites already use)

## Installation

1. Download or clone this plugin into your Moodle site as `local/timespent`.
2. Go to **Site administration → Notifications** and complete the installation.
3. Assign access to the Time spent report to the roles that should see it (for example managers).

## Using the report

After install, open the Time spent report from your site reports area (or ask your administrator for the link). Choose a course to load enrolled users and their totals. Use search and export if you need to find someone quickly or download the list.

## Tips for accurate results

- Learners must generate activity in the course (opening resources, activities, and so on) — sitting idle with no Moodle clicks is not counted.
- Totals update when time spent is calculated for that user and course (for example when viewing the report or when another feature asks for the figure).
- Keep the standard log store enabled; without course activity logs, sessions cannot be built.

## License

GNU GPL v3 or later.
