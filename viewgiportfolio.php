<?php
// This file is part of giportfolio module for Moodle - http://moodle.org/
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
 * giportfolio view page
 *
 * @package    mod_giportfolio
 * @copyright  2012 Synergy Learning / Manolescu Dorel based on book module
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
global $CFG, $DB, $PAGE, $OUTPUT, $SITE, $USER;
require_once($CFG->dirroot.'/mod/giportfolio/locallib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/comment/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$bid = optional_param('b', 0, PARAM_INT); // Giportfolio id.
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID.
$edit = optional_param('edit', -1, PARAM_BOOL); // Edit mode.
$useredit = optional_param('useredit', 0, PARAM_BOOL); // Edit mode.

// Security checks START - teachers edit; students view.

if ($id) {
    $cm = get_coursemodule_from_id('giportfolio', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $giportfolio = $DB->get_record('giportfolio', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $giportfolio = $DB->get_record('giportfolio', array('id' => $bid), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('giportfolio', $giportfolio->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/giportfolio:view', $context);

$allowedit = has_capability('mod/giportfolio:edit', $context);
$viewhidden = has_capability('mod/giportfolio:viewhiddenchapters', $context);
$allowcontribute = has_capability('mod/giportfolio:submitportfolio', $context);

if ($allowedit) {
    if ($edit != -1 and confirm_sesskey()) {
        $USER->editing = $edit;
    } else {
        if (isset($USER->editing)) {
            $edit = $USER->editing;
        } else {
            $edit = 0;
        }
    }
} else {
    $edit = 0;
}

// Read chapters.
$chapters = giportfolio_preload_chapters($giportfolio);
// SYNERGY - add fake user chapters.
$additionalchapters = giportfolio_preload_userchapters($giportfolio, $userid = null);
if ($additionalchapters) {
    $chapters = $chapters + $additionalchapters;
}
// SYNERGY.

if ($allowedit and !$chapters) {
    redirect('edit.php?cmid='.$cm->id); // No chapters - add new one.
}
// Check chapterid and read chapter data.
if ($chapterid == '0') { // Go to first chapter if no given.
    foreach ($chapters as $ch) {
        if ($edit) {
            $chapterid = $ch->id;
            break;
        }
        if (!$ch->hidden) {
            $chapterid = $ch->id;
            break;
        }
    }
}
// SYNERGY.

if (!$chapterid) {
    print_error('errorchapter', 'mod_giportfolio', new moodle_url('/course/viewgiportfolio.php', array('id' => $course->id)));
}

if (!$chapter = $DB->get_record('giportfolio_chapters', array('id' => $chapterid, 'giportfolioid' => $giportfolio->id)) and
    !$chapter = $DB->get_record('giportfolio_userchapters', array('id' => $chapterid, 'giportfolioid' => $giportfolio->id,
                                                                 'iduser' => $USER->id)) ) {

    print_error('errorchapter', 'mod_giportfolio', new moodle_url('/course/viewgiportfolio.php', array('id' => $course->id)));
}

// Chapter is hidden for students.
if ($chapter->hidden and !$viewhidden) {
    print_error('errorchapter', 'mod_giportfolio', new moodle_url('/course/viewgiportfolio.php', array('id' => $course->id)));
}

$PAGE->set_url('/mod/giportfolio/viewgiportfolio.php', array('id' => $id, 'chapterid' => $chapterid));

// Unset all page parameters.
unset($id);
unset($bid);
unset($chapterid);

// Security checks  END.
add_to_log($course->id, 'giportfolio', 'view', 'viewgiportfolio.php?id='.$cm->id.'&amp;chapterid='.$chapter->id,
           $giportfolio->id, $cm->id);

// Read standard strings.
$strgiportfolios = get_string('modulenameplural', 'mod_giportfolio');
$strgiportfolio = get_string('modulename', 'mod_giportfolio');
$strtoc = get_string('toc', 'mod_giportfolio');

// Prepare header.
$PAGE->set_title(format_string($giportfolio->name));
$PAGE->add_body_class('mod_giportfolio');
$PAGE->set_heading(format_string($course->fullname));

// Synergy add $userdit.
giportfolio_add_fake_block($chapters, $chapter, $giportfolio, $cm, $edit, $useredit);

// Prepare chapter navigation icons.
$previd = null;
$nextid = null;
$last = null;
foreach ($chapters as $ch) {
    if (!$edit and $ch->hidden) {
        continue;
    }
    if ($last == $chapter->id) {
        $nextid = $ch->id;
        break;
    }
    if ($ch->id != $chapter->id) {
        $previd = $ch->id;
    }
    $last = $ch->id;
}

$chnavigation = '';
if ($previd) {
    $chnavigation .= '<a title="'.get_string('navprev', 'giportfolio').'" href="viewgiportfolio.php?id='.$cm->id.
        '&amp;chapterid='.$previd.'">
        <img src="'.$OUTPUT->pix_url('nav_prev', 'mod_giportfolio').'" class="bigicon" alt="'.
        get_string('navprev', 'giportfolio').'"/></a>';
} else {
    $chnavigation .= '<img src="'.$OUTPUT->pix_url('nav_prev_dis', 'mod_giportfolio').'" class="bigicon" alt="" />';
}
if ($nextid) {
    $chnavigation .= '<a title="'.get_string('navnext', 'giportfolio').'" href="viewgiportfolio.php?id='.$cm->id.
        '&amp;chapterid='.$nextid.'">
        <img src="'.$OUTPUT->pix_url('nav_next', 'mod_giportfolio').'" class="bigicon" alt="'.
        get_string('navnext', 'giportfolio').'" /></a>';
} else {
    $sec = '';
    if ($section = $DB->get_record('course_sections', array('id' => $cm->section))) {
        $sec = $section->section;
    }
    if ($course->id == $SITE->id) {
        $returnurl = "$CFG->wwwroot/";
    } else {
        $returnurl = "$CFG->wwwroot/course/view.php?id=$course->id#section-$sec";
    }
    $chnavigation .= '<a title="'.get_string('navexit', 'giportfolio').'" href="'.$returnurl.'">
    <img src="'.$OUTPUT->pix_url('nav_exit', 'mod_giportfolio').'" class="bigicon" alt="'.
        get_string('navexit', 'giportfolio').'" /></a>';

    // We are cheating a bit here, viewing the last page means user has viewed the whole giportfolio.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

// Giportfolio display HTML code.

echo $OUTPUT->header();

// Upper nav.
echo '<div class="navtop">'.$chnavigation.'</div>';

// Chapter itself.
echo $OUTPUT->box_start('generalbox giportfolio_content');
if (!$giportfolio->customtitles) {
    $hidden = $chapter->hidden ? 'dimmed_text' : '';
    if (!$chapter->subchapter) {
        $currtitle = giportfolio_get_chapter_title($chapter->id, $chapters, $giportfolio, $context);
        echo '<p class="giportfolio_chapter_title '.$hidden.'">'.$currtitle.'</p>';
    } else {
        $currtitle = giportfolio_get_chapter_title($chapters[$chapter->id]->parent, $chapters, $giportfolio, $context);
        $currsubtitle = giportfolio_get_chapter_title($chapter->id, $chapters, $giportfolio, $context);
        echo '<p class="giportfolio_chapter_title '.$hidden.'">'.$currtitle.'<br />'.$currsubtitle.'</p>';
    }
}

// SYNERGY.
global $USER;
$pixpath = "$CFG->wwwroot/pix";
$contriblist = giportfolio_get_user_contributions($chapter->id, $chapter->giportfolioid, $USER->id);
$chaptertext = file_rewrite_pluginfile_urls($chapter->content, 'pluginfile.php', $context->id, 'mod_giportfolio',
                                            'chapter', $chapter->id);
echo format_text($chaptertext, $chapter->contentformat, array('noclean' => true, 'context' => $context));
echo '</br>';
if (!$allowedit) {
    $addurl = new moodle_url('/mod/giportfolio/editcontribution.php', array('id' => $cm->id, 'chapterid' => $chapter->id));
    echo $OUTPUT->single_button($addurl, get_string('addcontrib', 'mod_giportfolio'), 'GET');
}
echo '</br></br>';

if ($contriblist) {
    comment::init();
    $commentopts = (object)array(
        'context' => $context,
        'component' => 'mod_giportfolio',
        'area' => 'giportfolio_contribution',
        'cm' => $cm,
        'course' => $course,
        'autostart' => true,
        'showcount' => true,
        'displaycancel' => true
    );

    $align = 'right';
    foreach ($contriblist as $contrib) {
        $baseurl = new moodle_url('/mod/giportfolio/editcontribution.php',
                                  array('id' => $cm->id, 'contributionid' => $contrib->id, 'chapterid' => $contrib->chapterid));

        $editurl = new moodle_url($baseurl);
        $editicon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
        $editicon = html_writer::link($editurl, $editicon);

        $delurl = new moodle_url($baseurl, array('action' => 'delete'));
        $delicon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
        $delicon = html_writer::link($delurl, $delicon);

        if ($contrib->hidden) {
            $showurl = new moodle_url($baseurl, array('action' => 'show', 'sesskey' => sesskey()));
            $showicon = $OUTPUT->pix_icon('t/show', get_string('show'));
        } else {
            $showurl = new moodle_url($baseurl, array('action' => 'hide', 'sesskey' => sesskey()));
            $showicon = $OUTPUT->pix_icon('t/hide', get_string('hide'));
        }
        $showicon = html_writer::link($showurl, $showicon);

        $actions = array($editicon, $delicon, $showicon);
        echo '<strong>'.format_string($contrib->title).'</strong>  '.implode(' ', $actions).'</br>';
        echo date('l jS F Y', $contrib->timemodified);
        echo '</br></br>';
        $contribtext = file_rewrite_pluginfile_urls($contrib->content, 'pluginfile.php', $context->id, 'mod_giportfolio',
                                                    'contribution', $contrib->id);

        echo format_text($contribtext, $contrib->contentformat, array('noclean' => true, 'context' => $context));

        echo '</br>';
        $files = giportfolio_print_attachments($contrib, $cm, $type = null, $align = "right");
        if ($files) {
            echo "<table border=\"0\" width=\"100%\" align=\"$align\"><tr><td align=\"$align\" nowrap=\"nowrap\">\n";
            echo $files;
            echo "</td></tr></table>\n";
        }
        echo '</br>';

        $commentopts->itemid = $contrib->id;
        $commentbox = new comment($commentopts);
        echo $commentbox->output();

        echo '</br>';
        echo '</br>';
    }
}
// SYNERGY.
echo $OUTPUT->box_end();
echo '</br>';
// Lower navigation.
echo '<div class="navbottom">'.$chnavigation.'</div>';

echo $OUTPUT->footer();
