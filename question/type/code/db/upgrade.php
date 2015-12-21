<?php
function xmldb_qtype_code_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2015122000) {

        // Define table qtype_code_options to be created.
        $table = new xmldb_table('qtype_code_options');

        // Adding fields to table qtype_code_options.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('autocorrectenv', XMLDB_TYPE_CHAR, '128', null, null, null, null, null);
        $table->add_field('envoptions', XMLDB_TYPE_TEXT, null, null, null, null, null, null);

        // Adding keys to table qtype_code_options.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('questionid', XMLDB_KEY_FOREIGN_UNIQUE, array('questionid'), 'question', array('id'));

        // Conditionally launch create table for qtype_code_options.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Code savepoint reached.
        upgrade_plugin_savepoint(true, 2015122000, 'qtype', 'code');

    }

    return true;
}
