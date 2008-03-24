<?php
/**
 * Quicksms - Allows teachers and students to send sms one another
 *      at a course level.  Also supports group mode so students
 *      can only email their group members if desired.  Both group
 *      mode and student access to Quicksms are configurable by
 *      editing a Quicksms instance.
 *
 * Based upon http://docs.moodle.org/en/Quickmail_block, version 1.8
 *
 * @author  Lars Olesen
 * @version
 * @package quicksms
 */

/**
 * This is the Quicksms block class.  Contains the necessary
 * functions for a Moodle block.  Has some extra functions as well
 * to increase its flexibility and usability
 *
 * @todo Make a global config so that admins can set the defaults (default for student (yes/no) default for groupmode (select a groupmode or use the courses groupmode)) NOTE: make sure email.php and emaillog.php use the global config settings
 *
 * @author Lars Olesen
 * @package quicksms
 */
class block_quicksms extends block_list {

    /**
     * Sets the block name and version number
     *
     * @return void
     **/
    function init() {
        $this->title = get_string('blockname', 'block_quicksms');
        $this->version = 2006021501;  // YYYYMMDDXX
    }

    /**
     * Gets the contents of the block (course view)
     *
     * @return object An object with an array of items, an array of icons, and a string for the footer
     **/
    function get_content() {
        global $USER, $CFG;

        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->footer = '';
        $this->content->items = array();
        $this->content->icons = array();

        if (empty($this->instance) or !$this->check_permission()) {
            return $this->content;
        }

    /// link to composing an email
        $this->content->items[] = "<a href=\"$CFG->wwwroot/blocks/quicksms/sms.php?id={$this->course->id}&amp;instanceid={$this->instance->id}\">".
                                    get_string('compose', 'block_quicksms').'</a>';

        $this->content->icons[] = '<img src="'.$CFG->pixpath.'/i/email.gif" height="16" width="16" alt="'.get_string('email').'" />';

    /// link to history log
        $this->content->items[] = "<a href=\"$CFG->wwwroot/blocks/quicksms/smslog.php?id={$this->course->id}&amp;instanceid={$this->instance->id}\">".
                                    get_string('history', 'block_quicksms').'</a>';

        $this->content->icons[] = '<img src="'.$CFG->pixpath.'/t/log.gif" height="14" width="14" alt="'.get_string('log').'" />';

        return $this->content;
    }

    /**
     * Loads the course
     *
     * @return void
     **/
    function specialization() {
        global $COURSE;

        $this->course = $COURSE;
    }

    /**
     * Cleanup the history
     *
     * @return boolean
     **/
    function instance_delete() {
        return delete_records('block_quicksms_log', 'courseid', $this->course->id);
    }

    /**
     * Set defaults for new instances
     *
     * @return boolean
     **/
    function instance_create() {
        $this->config = new stdClass;
        $this->config->groupmode = $this->course->groupmode;
        $pinned = (!isset($this->instance->pageid));
        return $this->instance_config_commit($pinned);
    }

    /**
     * Allows the block to be configurable at an instance level.
     *
     * @return boolean
     **/
    function instance_allow_config() {
        return true;
    }

    /**
     * Check to make sure that the current user is allowed to use Quickmail.
     *
     * @return boolean True for access / False for denied
     **/
    function check_permission() {
        return has_capability('block/quicksms:cansend', get_context_instance(CONTEXT_BLOCK, $this->instance->id));
    }

    /**
     * Get the groupmode of Quickmail.  This function pays
     * attention to the course group mode force.
     *
     * @return int The group mode of the block
     **/
    function groupmode() {
        return groupmode($this->course, $this->config);
    }
}
