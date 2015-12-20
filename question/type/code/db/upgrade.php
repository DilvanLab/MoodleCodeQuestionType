<?php
function xmldb_qtype_code_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2015090700) {

        // Define table qtype_code_options to be created.
        $table = new xmldb_table('qtype_code_options');

        // Adding fields to table qtype_code_options.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('responselang', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table qtype_code_options.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('questionid', XMLDB_KEY_FOREIGN_UNIQUE, array('questionid'), 'question', array('id'));

        // Conditionally launch create table for qtype_code_options.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Code savepoint reached.
        upgrade_plugin_savepoint(true, 2015090700, 'qtype', 'code');

    }

    if ($oldversion < 2015090701) {

        // Define field responselines to be added to qtype_code_options.
        $table = new xmldb_table('qtype_code_options');
        $field = new xmldb_field('responselines', XMLDB_TYPE_INTEGER, '3', null, null, null, '30', 'responselang');

        // Conditionally launch add field responselines.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('resultexpected', XMLDB_TYPE_TEXT, null, null, null, null, null, 'responselines');

        // Conditionally launch add field resultexpected.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('autocorrected', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'resultexpected');

        // Conditionally launch add field autocorrected.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Code savepoint reached.
        upgrade_plugin_savepoint(true, 2015090701, 'qtype', 'code');
    }

    if ($oldversion < 2015090702) {

        // Define field responsetemplate to be added to qtype_code_options.
        $table = new xmldb_table('qtype_code_options');
        $field = new xmldb_field('responsetemplate', XMLDB_TYPE_TEXT, null, null, null, null, null, 'autocorrected');

        // Conditionally launch add field responsetemplate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Code savepoint reached.
        upgrade_plugin_savepoint(true, 2015090702, 'qtype', 'code');
    }

    if ($oldversion < 2015091701) {

        // Define field programinput to be added to qtype_code_options.
        $table = new xmldb_table('qtype_code_options');
        $field = new xmldb_field('programinput', XMLDB_TYPE_TEXT, null, null, null, null, null, 'responsetemplate');

        // Conditionally launch add field programinput.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Code savepoint reached.
        upgrade_plugin_savepoint(true, 2015091701, 'qtype', 'code');
    }


    return true;
}
