<?php
/**
 * smslog.php - displays a log (or history) of all smss sent by
 *      a specific in a specific course.  Each sms log can be viewed
 *      or deleted.
 *
 * Based upon http://docs.moodle.org/en/Quickmail_block, version 1.8
 *
 * @todo Add a print option?
 *
 * @author Lars Olesen
 * @version
 * @package quicksms
 **/

    require_once('../../config.php');
    require_once($CFG->libdir.'/blocklib.php');
    require_once($CFG->libdir.'/tablelib.php');

    $id = required_param('id', PARAM_INT);    // course id
    $action = optional_param('action', '', PARAM_ALPHA);
    $instanceid = optional_param('instanceid', 0, PARAM_INT);

    $instance = new stdClass;

    if (!$course = get_record('course', 'id', $id)) {
        error('Course ID was incorrect');
    }

    require_login($course->id);

    if ($instanceid) {
        $instance = get_record('block_instance', 'id', $instanceid);
    } else {
        if ($quicksmsblock = get_record('block', 'name', 'quicksms')) {
            $instance = get_record('block_instance', 'blockid', $quicksmsblock->id, 'pageid', $course->id);
        }
    }

/// This block of code ensures that quicksms will run
///     whether it is in the course or not
    if (empty($instance)) {
        if (has_capability('block/quicksms:cansend', get_context_instance(CONTEXT_BLOCK, $instanceid))) {
            $haspermission = true;
        } else {
            $haspermission = false;
        }
    } else {
        // create a quicksms block instance
        $quicksms = block_instance('quicksms', $instance);
        $haspermission = $quicksms->check_permission();
    }

    if (!$haspermission) {
        error('Sorry, you do not have the correct permissions to use quicksms.');
    }

    // log deleting happens here (NOTE: reporting is handled below)
    $dumpresult = false;
    if ($action == 'dump') {
        confirm_sesskey();

        // delete a single log or all of them
        if ($smsid = optional_param('smsid', 0, PARAM_INT)) {
            $dumpresult = delete_records('block_quicksms_log', 'id', $smsid);
        } else {
            $dumpresult = delete_records('block_quicksms_log', 'userid', $USER->id);
        }
    }

/// set table columns and headers
    $tablecolumns = array('timesent', 'subject', '');
    $tableheaders = array(get_string('date', 'block_quicksms'), get_string('subject', 'forum'),
                         get_string('action', 'block_quicksms'));

    $table = new flexible_table('bocks-quicksms-smslog');

/// define table columns, headers, and base url
    $table->define_columns($tablecolumns);
    $table->define_headers($tableheaders);
    $table->define_baseurl($CFG->wwwroot.'/blocks/quicksms/smslog.php?id='.$course->id.'&amp;instanceid='.$instanceid);

/// table settings
    $table->sortable(true, 'timesent', SORT_DESC);
    $table->collapsible(true);
    $table->initialbars(false);
    $table->pageable(true);

/// column styles (make sure date does not wrap) NOTE: More table styles in styles.php
    $table->column_style('timesent', 'width', '40%');
    $table->column_style('timesent', 'white-space', 'nowrap');

/// set attributes in the table tag
    $table->set_attribute('cellspacing', '0');
    $table->set_attribute('id', 'smslog');
    $table->set_attribute('class', 'generaltable generalbox');
    $table->set_attribute('align', 'center');
    $table->set_attribute('width', '80%');

    $table->setup();

/// SQL
    $sql = "SELECT *
              FROM {$CFG->prefix}block_quicksms_log
             WHERE courseid = $course->id
               AND userid = $USER->id ";

    if ($table->get_sql_where()) {
        $sql .= 'AND '.$table->get_sql_where();
    }

    $sql .= ' ORDER BY '. $table->get_sql_sort();

/// set page size
    $total = count_records('block_quicksms_log', 'courseid', $course->id, 'userid', $USER->id);
    $table->pagesize(10, $total);

    if ($pastsmss = get_records_sql($sql, $table->get_page_start(), $table->get_page_size())) {
        foreach ($pastsmss as $pastsms) {
            $table->add_data( array(userdate($pastsms->timesent),
                                    s($pastsms->subject),
                                    "<a href=\"sms.php?id=$course->id&amp;instanceid=$instanceid&amp;smsid=$pastsms->id&amp;action=view\">".
                                    "<img src=\"$CFG->pixpath/i/search.gif\" height=\"14\" width=\"14\" alt=\"".get_string('view').'" /></a> '.
                                    "<a href=\"smslog.php?id=$course->id&amp;instanceid=$instanceid&amp;sesskey=$USER->sesskey&amp;action=dump&amp;smsid=$pastsms->id\">".
                                    "<img src=\"$CFG->pixpath/t/delete.gif\" height=\"11\" width=\"11\" alt=\"".get_string('delete').'" /></a>'));
        }
    }

/// Start printing everyting
    $strquicksms = get_string('blockname', 'block_quicksms');
    if (empty($pastsmss)) {
        $disabled = 'disabled="disabled" ';
    } else {
        $disabled = '';
    }
    $button = "<form method=\"post\" action=\"$CFG->wwwroot/blocks/quicksms/smslog.php\">
               <input type=\"hidden\" name=\"id\" value=\"$course->id\" />
               <input type=\"hidden\" name=\"instanceid\" value=\"$instanceid\" />
               <input type=\"hidden\" name=\"sesskey\" value=\"".sesskey().'" />
               <input type="hidden" name="action" value="confirm" />
               <input type="submit" name="submit" value="'.get_string('clearhistory', 'block_quicksms')."\" $disabled/>
               </form>";

/// Header setup
    if ($course->category) {
        $navigation = "<a href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$course->shortname</a> ->";
    } else {
        $navigation = '';
    }

    print_header("$course->fullname: $strquicksms", $course->fullname, "$navigation $strquicksms", '', '', true, $button);

    print_heading($strquicksms);

    $currenttab = 'history';
    include($CFG->dirroot.'/blocks/quicksms/tabs.php');

/// delete reporting happens here
    if ($action == 'dump') {
        if ($dumpresult) {
            notify(get_string('deletesuccess', 'block_quicksms'), 'notifysuccess');
        } else {
            notify(get_string('deletefail', 'block_quicksms'));
        }
    }

    if ($action == 'confirm') {
        notice_yesno(get_string('areyousure', 'block_quicksms'),
                     "$CFG->wwwroot/blocks/quicksms/smslog.php?id=$course->id&amp;instanceid=$instanceid&amp;sesskey=".sesskey()."&amp;action=dump",
                     "$CFG->wwwroot/blocks/quicksms/smslog.php?id=$course->id&amp;instanceid=$instanceid");
    } else {
        echo '<div id="tablecontainer">';
        $table->print_html();
        echo '</div>';
    }

    print_footer();