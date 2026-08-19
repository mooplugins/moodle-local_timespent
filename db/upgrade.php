<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps for local_timespent.
 *
 * @package    local_timespent
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the local_timespent plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_timespent_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081001) {
        // Rename legacy unprefixed tables used before Moodle Plugins Directory packaging.
        $legacysession = new xmldb_table('timespent_session');
        if ($dbman->table_exists($legacysession) && !$dbman->table_exists('local_timespent_session')) {
            $dbman->rename_table($legacysession, 'local_timespent_session');
        }

        $legacyaggregate = new xmldb_table('timespent_aggregate');
        if ($dbman->table_exists($legacyaggregate) && !$dbman->table_exists('local_timespent_aggregate')) {
            $dbman->rename_table($legacyaggregate, 'local_timespent_aggregate');
        }

        upgrade_plugin_savepoint(true, 2026081001, 'local', 'timespent');
    }

    if ($oldversion < 2026081100) {
        // No schema changes — version bump to register admin Reports menu entry.
        upgrade_plugin_savepoint(true, 2026081100, 'local', 'timespent');
    }

    if ($oldversion < 2026081900) {
        // No schema changes — packaging and CI/HTML lint fixes for public release.
        upgrade_plugin_savepoint(true, 2026081900, 'local', 'timespent');
    }

    if ($oldversion < 2026081901) {
        // No schema changes — report navigation and Boost-aligned UI.
        upgrade_plugin_savepoint(true, 2026081901, 'local', 'timespent');
    }

    if ($oldversion < 2026081902) {
        // No schema changes — report page uses core Moodle form, table, and download UI.
        upgrade_plugin_savepoint(true, 2026081902, 'local', 'timespent');
    }

    if ($oldversion < 2026081903) {
        // No schema changes — restore AJAX report UI with Boost components.
        upgrade_plugin_savepoint(true, 2026081903, 'local', 'timespent');
    }

    if ($oldversion < 2026081904) {
        // No schema changes — restore original report layout; pagination summary only.
        upgrade_plugin_savepoint(true, 2026081904, 'local', 'timespent');
    }

    return true;
}
