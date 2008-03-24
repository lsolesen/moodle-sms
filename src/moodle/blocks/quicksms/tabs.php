<?php
/**
 * Tabs for quicksms
 *
 * Based upon http://docs.moodle.org/en/Quickmail_block, version 1.8
 *
 * @author Lars Olesen
 * @version
 * @package quicksms
 */

    if (empty($course)) {
        error('Programmer error: cannot call this script without $course set');
    }
    if (!isset($instanceid)) {
        $instanceid = 0;
    }
    if (empty($currenttab)) {
        $currenttab = 'compose';
    }

    $rows = array();
    $row = array();

    $row[] = new tabobject('compose', "$CFG->wwwroot/blocks/quicksms/sms.php?id=$course->id&amp;instanceid=$instanceid", get_string('compose', 'block_quicksms'));
    $row[] = new tabobject('history', "$CFG->wwwroot/blocks/quicksms/smslog.php?id=$course->id&amp;instanceid=$instanceid", get_string('history', 'block_quicksms'));
    $rows[] = $row;

    print_tabs($rows, $currenttab);