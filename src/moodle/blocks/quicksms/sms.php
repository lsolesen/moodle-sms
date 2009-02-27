<?php
/**
 * sms.php - Used by quicksms for sending smss to users enrolled in a specific course.
 *      Calls sms.html at the end.
 *
 * Based upon http://docs.moodle.org/en/Quickmail_block, version 1.8
 *
 * PHP version 4
 *
 * Works with Moodle 1.9
 *
 * @author Lars Olesen <lars@legestue.net>
 * @version @@VERSION@@
 * @package quicksms
 */


    require_once('../../config.php');
    require_once($CFG->libdir.'/blocklib.php');
    require_once('smsfunction.php');

    $id         = required_param('id', PARAM_INT);  // course ID
    $instanceid = optional_param('instanceid', 0, PARAM_INT);
    $action     = optional_param('action', '', PARAM_ALPHA);

    $instance = new stdClass;

    if (!$course = get_record('course', 'id', $id)) {
        error('Course ID was incorrect');
    }

    require_login($course->id);
    $context = get_context_instance(CONTEXT_COURSE, $course->id);

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
        $groupmode = groupmode($course);
        if (has_capability('block/quicksms:cansend', get_context_instance(CONTEXT_BLOCK, $instanceid))) {
            $haspermission = true;
        } else {
            $haspermission = false;
        }
    } else {
        // create a quicksms block instance
        $quicksms = block_instance('quicksms', $instance);

        $groupmode     = $quicksms->groupmode();
        $haspermission = $quicksms->check_permission();
    }

    if (!$haspermission) {
        error('Sorry, you do not have the correct permissions to use quicksms.');
    }

    if (!$courseusers = get_users_by_capability($context, 'moodle/course:view', 'u.*', 'u.lastname, u.firstname', '', '', '', '', false)) {
        error('No course users found to sms');
    }

    if ($action == 'view') {
        // viewing an old sms.  Hitting the db and puting it into the object $form
        $smsid = required_param('smsid', PARAM_INT);
        $form = get_record('block_quicksms_log', 'id', $smsid);
        $form->mailto = explode(',', $form->mailto); // convert mailto back to an array
    } else if ($form = data_submitted()) {   // data was submitted to be mailed
        confirm_sesskey();

        if (!empty($form->cancel)) {
            // cancel button was hit...
            redirect("$CFG->wwwroot/course/view.php?id=$course->id");
        }

        // prepare variables for sms
        $form->subject = stripslashes($form->subject);
        $form->subject = clean_param(strip_tags($form->subject), PARAM_RAW); // Strip all tags except multilang

        // make sure the user didn't miss anything
        if (!isset($form->mailto)) {
            $form->error = get_string('toerror', 'block_quicksms');
        } else if (!$form->subject) {
            $form->error = get_string('subjecterror', 'block_quicksms');
        }

        // no errors, then sms
        if(!isset($form->error)) {
            $mailedto = array(); // holds all the userid of successful smss

            // get the correct formating for the smss

            // run through each user id and send a copy of the sms to him/her
            // not sending 1 sms with CC to all user ids because smss were required to be kept private
            foreach ($form->mailto as $userid) {
                if (!$courseusers[$userid]->smsstop) {
                    $mailresult = sms_to_user($courseusers[$userid]->phone1, $USER, $form->subject);
                    // checking for errors, if there is an error, store the name
                    if (!$mailresult || (string) $mailresult == 'smsstop') {
                        $form->error = get_string('smsfailerror', 'block_quicksms');
                        $form->usersfail['smsfail'][] = $courseusers[$userid]->lastname.', '.$courseusers[$userid]->firstname;
                    } else {
                        // success
                        $mailedto[] = $userid;
                    }
                } else {
                    // blocked sms
                    $form->error = get_string('smsfailerror', 'block_quicksms');
                    $form->usersfail['smsstop'][] = $courseusers[$userid]->lastname.', '.$courseusers[$userid]->firstname;
                }
            }

            // prepare an object for the insert_record function
            $log = new stdClass;
            $log->courseid   = $course->id;
            $log->userid     = $USER->id;
            $log->mailto     = implode(',', $mailedto);
            $log->subject    = addslashes($form->subject);
            $log->message    = '';
            $log->attachment = '';
            $log->format     = 'sms';
            $log->timesent   = time();
            if (!insert_record('block_quicksms_log', $log)) {
                error('sms not logged.');
            }

            if(!isset($form->error)) {  // if no smsing errors, we are done
                // inform of success and continue
                redirect("$CFG->wwwroot/course/view.php?id=$course->id", get_string('successfulsms', 'block_quicksms'));
            }
        }
        // so people can use quotes.  It will display correctly in the subject input text box
        $form->subject = s($form->subject);

    } else {
        // set them as blank
        $form->subject ='';
    }

/// Create the table object for holding course users in the To section of sms.html

    // table object used for printing the course users
    $table              = new stdClass;
    $table->cellpadding = '10px';
    $table->width       = '100%';

    $t    = 1;    // keeps track of the number of users printed (used for javascript)
    $cols = 4;    // number of columns in the table

    if ($groupmode == NOGROUPS) { // no groups, basic view
        $table->head  = array();
        $table->align = array('left', 'left', 'left', 'left');
        $cells        = array();

        foreach($courseusers as $user) {
            if (isset($form->mailto) && in_array($user->id, $form->mailto)) {
                $checked = 'checked="checked"';
            } else {
                $checked = '';
            }

            $cells[] = "<input type=\"checkbox\" $checked id=\"mailto$t\" value=\"$user->id\" name=\"mailto[]\" />".
                        "<label for=\"mailto$t\">".fullname($user, true).'</label>';
            $t++;
        }
        $table->data = array_chunk($cells, $cols);
    } else {
        $groups      = new stdClass;    // holds the groups to be displayed
        $buttoncount = 1;               // counter for the buttons (used by javascript)
        $ingroup     = array();         // keeps track of the users that belong to groups

        // determine the group mode
        if (has_capability('moodle/site:accessallgroups', $context)) {
            // teachers/admins default to the more liberal group mode
            $groupmode = VISIBLEGROUPS;
        }

        // set the groups variable
        switch ($groupmode) {
            case VISIBLEGROUPS:
                $groups = groups_get_all_groups($course->id);
                break;

            case SEPARATEGROUPS:
                $groups = groups_get_groups_for_current_user($course->id);
                break;
        }

        // Add a fake group for those who are not group members
        $groups[] = 0;

        $notingroup = array();
        if ($allgroups = groups_get_all_groups($course->id)) {
            foreach ($courseusers as $user) {
                $nomembership = true;

                foreach ($allgroups as $group) {

                    if (groups_is_member($group->id, $user->id)) {
                        $nomembership = false;
                        break;
                    }
                }
                if ($nomembership) {
                    $notingroup[] = $user->id;
                }
            }
        }

        // set up the table
        $table->head        = array(get_string('groups'), get_string('groupmembers'));
        $table->align       = array('center', 'left');
        $table->size        = array('100px', '*');

        foreach($groups as $group) {
            $start = $t;
            $cells = array();  // table cells (each is a check box next to a user name)
            foreach($courseusers as $user) {
                if (groups_is_member($group->id, $user->id) or                    // is a member of the group or
                   ($group == 0 and in_array($user->id, $notingroup)) ) {     // this is our fake group and this user is not a member of another group

                    if (isset($form->mailto) && in_array($user->id, $form->mailto)) {
                        $checked = 'checked="checked"';
                    } else {
                        $checked = '';
                    }

                    $cells[] = "<input type=\"checkbox\" $checked id=\"mailto$t\" value=\"$user->id\" name=\"mailto[$user->id]\" />".
                                "<label for=\"mailto$t\">".fullname($user, true).'</label>';
                    $t++;
                }
            }
            $end = $t;

            // cell1 has the group picture, name and check button
            $cell1 = '';
            if ($group) {
                $groupobj = groups_get_group($group->id);
                $cell1   .= print_group_picture($groupobj, $course->id, false, true).'<br />';
            }
            if ($group) {
                $cell1 .= groups_get_group_name($group->id);
            } else {
                $cell1 .= get_string('notingroup', 'block_quicksms');
            }
            if (count($groups) > 1 and !empty($cells)) {
                $selectlinks = '<a href="javascript:void(0);" onclick="block_quicksms_toggle(true, '.$start.', '.$end.');">'.get_string('selectall').'</a> /
                                <a href="javascript:void(0);" onclick="block_quicksms_toggle(false, '.$start.', '.$end.');">'.get_string('deselectall').'</a>';
            } else {
                $selectlinks = '';
            }
            $buttoncount++;

            // cell2 has the checkboxes and the user names inside of a table
            if (empty($cells) and !$group) {
                // there is no one that is not in a group, so no need to print our 'nogroup' group
                continue;
            } else if (empty($cells)) {
                // cells is empty, so there are no group members for that group
                $cell2 = get_string('nogroupmembers', 'block_quicksms');
            } else {
                $cell2 = '<table cellpadding="5px">';
                $rows = array_chunk($cells, $cols);
                foreach ($rows as $row) {
                    $cell2 .= '<tr><td nowrap="nowrap">'.implode('</td><td nowrap="nowrap">', $row).'</td></tr>';
                }
                $cell2 .= '</table>';
            }
            // add the 2 cells to the table
            $table->data[] = array($cell1, $selectlinks.$cell2);
        }
    }

    // get the default format
    if ($usehtmleditor = can_use_richtext_editor()) {
        $defaultformat = FORMAT_HTML;
    } else {
        $defaultformat = FORMAT_MOODLE;
    }

    // set up some strings
    $readonly       = '';
    $strchooseafile = get_string('chooseafile', 'resource');
    $strquicksms   = get_string('blockname', 'block_quicksms');

/// Header setup
    if ($course->category) {
        $navigation = "<a href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$course->shortname</a> ->";
    } else {
        $navigation = '';
    }

    print_header($course->fullname.': '.$strquicksms, $course->fullname, "$navigation $strquicksms", '', '', true);

    // print the sms form START
    print_heading($strquicksms);

    // error printing
    if (isset($form->error)) {
        notify($form->error);
        if (isset($form->usersfail)) {
            $errorstring = '';

            if (isset($form->usersfail['smsfail'])) {
                $errorstring .= get_string('smsfail', 'block_quicksms').'<br />';
                foreach($form->usersfail['smsfail'] as $user) {
                    $errorstring .= $user.'<br />';
                }
            }

            if (isset($form->usersfail['smsstop'])) {
                $errorstring .= get_string('smsstop', 'block_quicksms').'<br />';
                foreach($form->usersfail['smsstop'] as $user) {
                    $errorstring .= $user.'<br />';
                }
            }
            notice($errorstring, "$CFG->wwwroot/course/view.php?id=$course->id", $course);
        }
    }

    $currenttab = 'compose';
    include($CFG->dirroot.'/blocks/quicksms/tabs.php');

    print_simple_box_start('center');
    require($CFG->dirroot.'/blocks/quicksms/sms.html');
    print_simple_box_end();

    print_footer($course);