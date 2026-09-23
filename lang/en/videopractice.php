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
 * English strings for Video Practice.
 *
 * @package   mod_videopractice
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['actions'] = 'Actions';
$string['addstage'] = 'Add stage';
$string['allowrecording'] = 'Allow recording in the browser';
$string['allowseek'] = 'Allow seeking to portions not yet watched';
$string['assessed'] = 'Assessed';
$string['assessment'] = 'Assessment';
$string['assessmentnotavailable'] = 'Not submitted';
$string['assessmentsaved'] = 'Assessment saved.';
$string['assessmentstatus'] = 'Assessment status';
$string['assesspractice'] = 'Assess practice';
$string['attempt'] = 'Attempt';
$string['awaitingassessment'] = 'Awaiting assessment';
$string['backtoactivity'] = 'Back to activity';
$string['browservideonotsupported'] = 'Your browser cannot play this video.';
$string['cannotdeletelaststage'] = 'The activity must contain at least one stage.';
$string['cannotstartnewattempt'] = 'A new attempt cannot be started for this activity.';
$string['completionpercent'] = 'Reference video percentage required';
$string['completionpercentdesc'] = 'Watch at least {$a}% of the reference video';
$string['completionrequirepractice'] = 'Require a submitted practice video';
$string['completionrequirepracticedesc'] = 'Submit a practice video';
$string['completionrules'] = '';
$string['currentvideo'] = 'Current practice video';
$string['defaultstage'] = 'Practice execution';
$string['delete'] = 'Delete';
$string['draftsaved'] = 'Draft saved.';
$string['edit'] = 'Edit';
$string['editstage'] = 'Edit stage';
$string['errorcompletionpercent'] = 'The required percentage must be between 1 and 100.';
$string['errormaxfiles'] = 'Only one file can be uploaded.';
$string['errormaxscore'] = 'The maximum score must be greater than zero.';
$string['errorscorerange'] = 'The score must be between 0 and {$a}.';
$string['eventcoursemoduleviewed'] = 'Video Practice activity viewed';
$string['eventsubmissionsubmitted'] = 'Video Practice submission submitted';
$string['exportcsv'] = 'Export CSV';
$string['feedback'] = 'Feedback';
$string['grade'] = 'Grade';
$string['invaliddirecturl'] = 'The direct URL must point to an MP4, WebM, OGV, M4V, MOV or M3U8 file.';
$string['invalidsource'] = 'The selected reference video source is invalid.';
$string['invalidvideourl'] = 'Enter a valid HTTP or HTTPS video URL.';
$string['invalidvimeourl'] = 'Enter a valid Vimeo video URL.';
$string['invalidyoutubeurl'] = 'Enter a valid YouTube video URL.';
$string['lastupdate'] = 'Last update';
$string['manage'] = 'Manage';
$string['managestages'] = 'Manage practice stages';
$string['maxattempts'] = 'Maximum attempts';
$string['maxscore'] = 'Maximum score';
$string['modulename'] = 'Video Practice';
$string['modulenameplural'] = 'Video Practices';
$string['movedown'] = 'Move down';
$string['moveup'] = 'Move up';
$string['newattempt'] = 'Start a new attempt';
$string['newattemptcreated'] = 'A new attempt has been created.';
$string['noactivities'] = 'There are no Video Practice activities in this course.';
$string['overallfeedback'] = 'Overall feedback';
$string['pluginadministration'] = 'Video Practice administration';
$string['pluginname'] = 'Video Practice';
$string['practicestages'] = 'Practice stages';
$string['practicesubmitted'] = 'Practice submitted for assessment.';
$string['practicevideo'] = 'Practice video';
$string['practicevideorequired'] = 'Add or record a practice video before submitting.';
$string['practicevideosubmission'] = 'Practice video submission';
$string['privacy:metadata:practicevideo'] = 'The practice videos uploaded or recorded by students.';
$string['privacy:metadata:progress'] = 'Stores each student’s viewing progress for the reference video.';
$string['privacy:metadata:progress:percent'] = 'The calculated percentage of the reference video watched.';
$string['privacy:metadata:progress:userid'] = 'The user whose reference-video progress is stored.';
$string['privacy:metadata:progress:watchedsegments'] = 'The portions of the reference video that the user watched.';
$string['privacy:metadata:stagegrades'] = 'Stores per-stage scores and feedback for a practice-video attempt.';
$string['privacy:metadata:stagegrades:feedback'] = 'The feedback supplied for a practice stage.';
$string['privacy:metadata:stagegrades:score'] = 'The score awarded for a practice stage.';
$string['privacy:metadata:submissions'] = 'Stores practice-video attempts and assessment data.';
$string['privacy:metadata:submissions:feedback'] = 'The overall feedback supplied by the assessor.';
$string['privacy:metadata:submissions:grade'] = 'The final grade awarded to the attempt.';
$string['privacy:metadata:submissions:graderid'] = 'The user who assessed the attempt.';
$string['privacy:metadata:submissions:status'] = 'The current state of the attempt.';
$string['privacy:metadata:submissions:userid'] = 'The user who owns the practice-video attempt.';
$string['recordbrowser'] = 'Record in the browser';
$string['recording'] = 'Recording…';
$string['recordingdisabled'] = 'Browser recording is disabled for this activity.';
$string['recordinghelp'] = 'Use your camera and microphone to record the practice. After reviewing the preview, upload it to the current draft.';
$string['recordinginvalidtype'] = 'The recorded video format is not accepted.';
$string['recordingpermissionerror'] = 'Camera or microphone access could not be started.';
$string['recordingready'] = 'Recording ready for review.';
$string['recordingsaved'] = 'Recording saved in the current draft.';
$string['recordingtoolarge'] = 'The recorded video is larger than the allowed file size.';
$string['recordingunsupported'] = 'This browser does not support video recording with MediaRecorder.';
$string['recordinguploadfailed'] = 'The recorded video could not be uploaded.';
$string['recordinguploading'] = 'Uploading recording…';
$string['referenceheader'] = 'Reference video';
$string['referenceprogress'] = 'Reference video progress';
$string['referencesource'] = 'Reference video source';
$string['referencethreshold'] = 'Required';
$string['referenceurl'] = 'Reference video URL';
$string['referenceurl_help'] = 'For a direct URL, use an MP4, WebM, OGV, M4V, MOV or M3U8 URL. YouTube and Vimeo accept their normal video URLs.';
$string['referencevideo'] = 'Reference video file';
$string['referencevideorequired'] = 'Upload a reference video.';
$string['referencevideotitle'] = 'Reference demonstration';
$string['referencewatched'] = 'Reference watched';
$string['report'] = 'Report';
$string['requiredpercent'] = 'Required: {$a}%';
$string['resumeplayback'] = 'Resume reference video from last position';
$string['returnforediting'] = 'Return for editing';
$string['saveassessment'] = 'Save assessment';
$string['savedraft'] = 'Save draft';
$string['sourceupload'] = 'Upload a video';
$string['sourceurl'] = 'Direct video URL';
$string['sourcevimeo'] = 'Vimeo';
$string['sourceyoutube'] = 'YouTube';
$string['stagefeedback'] = 'Stage feedback';
$string['stageinstructions'] = 'Instructions';
$string['stagemaxscore'] = 'Maximum score';
$string['stagename'] = 'Stage name';
$string['stagesaved'] = 'Stage saved.';
$string['stagescore'] = 'Score (maximum {$a})';
$string['startrecording'] = 'Start recording';
$string['status'] = 'Status';
$string['statusdraft'] = 'Draft';
$string['statusgraded'] = 'Graded';
$string['statusnotstarted'] = 'Not started';
$string['statussubmitted'] = 'Submitted';
$string['stoprecording'] = 'Stop recording';
$string['student'] = 'Student';
$string['submissionheader'] = 'Student practice';
$string['submissionmaxbytes'] = 'Maximum practice video file size';
$string['submissionnoteditable'] = 'This submission can no longer be edited.';
$string['submissionnotready'] = 'This submission is not ready for assessment.';
$string['submissionreopened'] = 'The submission was returned to the student for editing.';
$string['submissionstatus'] = 'Practice status';
$string['submitpractice'] = 'Submit practice';
$string['unlimited'] = 'Unlimited';
$string['uploadrecording'] = 'Use this recording';
$string['videopractice:addinstance'] = 'Add a new Video Practice activity';
$string['videopractice:exportreport'] = 'Export Video Practice reports';
$string['videopractice:grade'] = 'Assess practice videos';
$string['videopractice:managestages'] = 'Manage Video Practice stages';
$string['videopractice:submit'] = 'Submit practice videos';
$string['videopractice:view'] = 'View Video Practice activities';
$string['videopractice:viewreport'] = 'View Video Practice reports';
$string['videopracticename'] = 'Video Practice name';
$string['vieworassess'] = 'View / assess';
$string['watchedpercent'] = '{$a}% watched';
$string['yourpractice'] = 'Your practice';
