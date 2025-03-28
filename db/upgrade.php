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
 * Upgrade script for paygw_payone.
 *
 * @package    paygw_payone
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool always true
 */
function xmldb_paygw_payone_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // Automatically generated Moodle v3.11.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2022080801) {

        // Define field paymentbrand to be added to paygw_payone.
        $table = new xmldb_table('paygw_payone');
        $field = new xmldb_field('paymentbrand', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'pu_orderid');

        // Conditionally launch add field paymentbrand.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Payone savepoint reached.
        upgrade_plugin_savepoint(true, 2022080801, 'paygw', 'payone');
    }

    if ($oldversion < 2022080802) {

        // Define field pboriginal to be added to paygw_payone.
        $table = new xmldb_table('paygw_payone');
        $field = new xmldb_field('pboriginal', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'paymentbrand');

        // Conditionally launch add field pboriginal.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Payone savepoint reached.
        upgrade_plugin_savepoint(true, 2022080802, 'paygw', 'payone');
    }
    if ($oldversion < 2023032101) {

        // Define table paygw_payone_openorders to be created.
        $table = new xmldb_table('paygw_payone_openorders');

        // Adding fields to table paygw_payone_openorders.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('tid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('price', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table paygw_payone_openorders.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for paygw_payone_openorders.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Payone savepoint reached.
        upgrade_plugin_savepoint(true, 2023032101, 'paygw', 'payone');
    }

    if ($oldversion < 2023041000) {

        // Changing type of field tid on table paygw_payone_openorders to char.
        $table = new xmldb_table('paygw_payone_openorders');
        $field = new xmldb_field('tid', XMLDB_TYPE_CHAR, '256', null, XMLDB_NOTNULL, null, null, 'id');

        // Launch change of type for field tid.
        $dbman->change_field_type($table, $field);

        // Payone savepoint reached.
        upgrade_plugin_savepoint(true, 2023041000, 'paygw', 'payone');
    }

    if ($oldversion < 2023072702) {

        $table = new xmldb_table('paygw_payone_openorders');

        // Define field timecreated to be added to paygw_payone_openorders.
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'status');
        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timemodified to be added to paygw_payone_openorders.
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timecreated');
        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Payone savepoint reached.
        upgrade_plugin_savepoint(true, 2023072702, 'paygw', 'payone');
    }

    if ($oldversion < 2024052301) {

        // Define field merchantref to be added to paygw_payone_openorders.
        $table = new xmldb_table('paygw_payone_openorders');
        $field = new xmldb_field('merchantref', XMLDB_TYPE_CHAR, '256', null, null, null, null, 'timemodified');

        // Conditionally launch add field merchantref.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Payone savepoint reached.
        upgrade_plugin_savepoint(true, 2024052301, 'paygw', 'payone');
    }

    if ($oldversion < 2024120400) {

        // Define field customorderid to be added to paygw_payone_openorders.
        $table = new xmldb_table('paygw_payone_openorders');
        $field = new xmldb_field('customorderid', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'merchantref');

        // Conditionally launch add field customorderid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Payone savepoint reached.
        upgrade_plugin_savepoint(true, 2024120400, 'paygw', 'payone');
    }

    return true;
}
