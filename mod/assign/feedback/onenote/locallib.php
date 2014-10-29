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
 * This file contains the definition for the library class for onenote feedback plugin
 *
 *
 * @package   assignfeedback_onenote
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Library class for ONENOTE feedback plugin extending feedback plugin base class.
 *
 * @package   assignfeedback_onenote
 */
class assign_feedback_onenote extends assign_feedback_plugin {

    /**
     * Get the name of the onenote feedback plugin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('onenote', 'assignfeedback_onenote');
    }

    /**
     * Get file feedback information from the database.
     *
     * @param int $gradeid
     * @return mixed
     */
    public function get_onenote_feedback($gradeid) {
        global $DB;
        return $DB->get_record('assignfeedback_onenote', array('grade'=>$gradeid));
    }

    /**
     * File format options.
     *
     * @return array
     */
    private function get_file_options() {
        global $COURSE;

        $fileoptions = array('subdirs'=>1,
                             'maxbytes'=>$COURSE->maxbytes,
                             'accepted_types'=>'*',
                             'return_types'=>FILE_INTERNAL);
        return $fileoptions;
    }

    /**
     * Copy all the files from one file area to another.
     *
     * @param file_storage $fs - The source context id
     * @param int $fromcontextid - The source context id
     * @param string $fromcomponent - The source component
     * @param string $fromfilearea - The source filearea
     * @param int $fromitemid - The source item id
     * @param int $tocontextid - The destination context id
     * @param string $tocomponent - The destination component
     * @param string $tofilearea - The destination filearea
     * @param int $toitemid - The destination item id
     * @return boolean
     */
    private function copy_area_files(file_storage $fs,
                                     $fromcontextid,
                                     $fromcomponent,
                                     $fromfilearea,
                                     $fromitemid,
                                     $tocontextid,
                                     $tocomponent,
                                     $tofilearea,
                                     $toitemid) {

        $newfilerecord = new stdClass();
        $newfilerecord->contextid = $tocontextid;
        $newfilerecord->component = $tocomponent;
        $newfilerecord->filearea = $tofilearea;
        $newfilerecord->itemid = $toitemid;

        if ($files = $fs->get_area_files($fromcontextid, $fromcomponent, $fromfilearea, $fromitemid)) {
            foreach ($files as $file) {
                if ($file->is_directory() and $file->get_filepath() === '/') {
                    // We need a way to mark the age of each draft area.
                    // By not copying the root dir we force it to be created
                    // automatically with current timestamp.
                    continue;
                }
                $newfile = $fs->create_file_from_storedfile($newfilerecord, $file);
            }
        }
        return true;
    }

    /**
     * Get form elements for grading form.
     *
     * @param stdClass $grade
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @param int $userid The userid we are currently grading
     * @return bool true if elements were added to the form
     */
    public function get_form_elements_for_user($grade, MoodleQuickForm $mform, stdClass $data, $userid) {
        global $USER;
        
        $gradeid = $grade ? $grade->id : 0;
        $o = '<hr/><b>OneNote actions:</b>&nbsp;&nbsp;&nbsp;&nbsp;';
        
        if (microsoft_onenote::get_onenote_token()) {
            // show a link to open the OneNote page
            $submission = $this->assignment->get_user_submission($userid, false);
            $is_teacher = microsoft_onenote::is_teacher($this->assignment->get_course()->id, $USER->id);
            $o .= microsoft_onenote:: render_action_button(get_string('addfeedback', 'assignfeedback_onenote'),
                    $this->assignment->get_course_module()->id, true, $is_teacher,
                    $userid, $submission->id, $grade ? $grade->id : null);
            $o .= '<br/><p>' . get_string('addfeedbackhelp', 'assignfeedback_onenote') . '</p>';
        } else {
            $o .= microsoft_onenote::get_onenote_signin_widget();
            $o .= '<br/><br/><p>' . get_string('signinhelp1', 'assignfeedback_onenote') . '</p>';
        }
        
        $o .= '<hr/>';
        
        $mform->addElement('html', $o);
        
        return true;
    }

    /**
     * Count the number of files.
     *
     * @param int $gradeid
     * @param string $area
     * @return int
     */
    private function count_files($gradeid, $area) {

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->assignment->get_context()->id,
                                     'assignfeedback_onenote',
                                     $area,
                                     $gradeid,
                                     'id',
                                     false);

        return count($files);
    }

    /**
     * Update the number of files in the file area.
     *
     * @param stdClass $grade The grade record
     * @return bool - true if the value was saved
     */
    public function update_file_count($grade) {
        global $DB;

        $filefeedback = $this->get_onenote_feedback($grade->id);
        if ($filefeedback) {
            $filefeedback->numfiles = $this->count_files($grade->id, ASSIGNFEEDBACK_ONENOTE_FILEAREA);
            return $DB->update_record('assignfeedback_onenote', $filefeedback);
        } else {
            $filefeedback = new stdClass();
            $filefeedback->numfiles = $this->count_files($grade->id, ASSIGNFEEDBACK_ONENOTE_FILEAREA);
            $filefeedback->grade = $grade->id;
            $filefeedback->assignment = $this->assignment->get_instance()->id;
            return $DB->insert_record('assignfeedback_onenote', $filefeedback) > 0;
        }
    }

    /**
     * Save the feedback files.
     *
     * @param stdClass $grade
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $grade, stdClass $data) {
        global $DB;
        
        // get the OneNote page id corresponding to the teacher's feedback for this submission
        $record = $DB->get_record('assign_user_ext', array("assign_id" => $grade->assignment, "user_id" => $grade->userid));
        $temp_folder = microsoft_onenote::create_temp_folder();
        $temp_file = join(DIRECTORY_SEPARATOR, array(trim($temp_folder, DIRECTORY_SEPARATOR), uniqid('asg_'))) . '.zip';
        
        // Create zip file containing onenote page and related files
        $onenote_api = microsoft_onenote::get_onenote_api();
        $download_info = $onenote_api->download_page($record->feedback_teacher_page_id, $temp_file);
        
        if ($download_info) {
            $fs = get_file_storage();
            
            // delete any previous feedbacks
            $fs->delete_area_files($this->assignment->get_context()->id, 'assignfeedback_onenote', ASSIGNFEEDBACK_ONENOTE_FILEAREA, $grade->id);
            
            // Prepare file record object
            $fileinfo = array(
                'contextid' => $this->assignment->get_context()->id,
                'component' => 'assignfeedback_onenote',
                'filearea' => ASSIGNFEEDBACK_ONENOTE_FILEAREA,
                'itemid' => $grade->id,
                'filepath' => '/',
                'filename' => 'OneNote_' . time() . '.zip');
            
            // save it
            $fs->create_file_from_pathname($fileinfo, $download_info['path']);
            fulldelete($temp_folder);
        } else {
            if (microsoft_onenote::get_onenote_token())
                $this->set_error(get_string('feedbackdownloadfailed', 'assignfeedback_onenote'));
            else
                $this->set_error(get_string('notsignedin', 'assignfeedback_onenote'));
            
            return false;
        }
        
        return $this->update_file_count($grade);
    }

    /**
     * Display the list of files in the feedback status table.
     *
     * @param stdClass $grade
     * @param bool $showviewlink - Set to true to show a link to see the full list of files
     * @return string
     */
    public function view_summary(stdClass $grade, & $showviewlink) {
        global $USER;
        
        // Show a view all link if the number of files is over this limit.
        $count = $this->count_files($grade->id, ASSIGNFEEDBACK_ONENOTE_FILEAREA);
        $showviewlink = $count > ASSIGNFEEDBACK_ONENOTE_MAXSUMMARYFILES;
     
        $o = '';
        
        if ($count <= ASSIGNFEEDBACK_ONENOTE_MAXSUMMARYFILES) {
            if (($grade->grade !== null) && ($grade->grade >= 0)) {
                if (microsoft_onenote::get_onenote_token()) {                    
                    // show a link to open the OneNote page
                    $submission = $this->assignment->get_user_submission($grade->userid, false);
                    $is_teacher = microsoft_onenote::is_teacher($this->assignment->get_course()->id, $USER->id);
                    $o .= microsoft_onenote:: render_action_button(get_string('viewfeedback', 'assignfeedback_onenote'),
                            $this->assignment->get_course_module()->id, true, $is_teacher,
                            $submission->userid, $submission->id, $grade->id);
                } else {
                    $o .= microsoft_onenote::get_onenote_signin_widget();
                    $o .= '<br/><br/><p>' . get_string('signinhelp2', 'assignfeedback_onenote') . '</p>';
                }
            }
            
            // show standard link to download zip package
            $o .= '<p>Download:</p>';
            $o .= $this->assignment->render_area_files('assignfeedback_onenote',
                                                        ASSIGNFEEDBACK_ONENOTE_FILEAREA,
                                                        $grade->id);
            
            return $o;
        } else {
            return get_string('countfiles', 'assignfeedback_onenote', $count);
        }
    }

    /**
     * Display the list of files in the feedback status table.
     *
     * @param stdClass $grade
     * @return string
     */
    public function view(stdClass $grade) {
        return $this->assignment->render_area_files('assignfeedback_onenote',
                                                    ASSIGNFEEDBACK_ONENOTE_FILEAREA,
                                                    $grade->id);
    }

    /**
     * The assignment has been deleted - cleanup.
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        // Will throw exception on failure.
        $DB->delete_records('assignfeedback_onenote',
                            array('assignment'=>$this->assignment->get_instance()->id));

        return true;
    }

    /**
     * Return true if there are no feedback files.
     *
     * @param stdClass $grade
     */
    public function is_empty(stdClass $grade) {
        return $this->count_files($grade->id, ASSIGNFEEDBACK_ONENOTE_FILEAREA) == 0;
    }

    /**
     * Get file areas returns a list of areas this plugin stores files.
     *
     * @return array - An array of fileareas (keys) and descriptions (values)
     */
    public function get_file_areas() {
        return array(ASSIGNFEEDBACK_ONENOTE_FILEAREA=>$this->get_name());
    }
}