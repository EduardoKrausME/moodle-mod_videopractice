# Moodle Video Practice — `mod_videopractice`

Video Practice is a Moodle activity for demonstration-and-practice learning. A teacher provides a reference video
showing a procedure, technique or practical task. Students watch the reference and then upload or record their own video
performing the same practice.

The activity supports reference videos uploaded to Moodle, direct HTML5 video URLs, YouTube and Vimeo. Reference viewing
is tracked by watched intervals so progress is calculated from portions actually played rather than from the last
playback position alone.

Teachers can divide the practice into stages such as preparation, organisation, execution and finalisation. Every stage
has instructions and a maximum score, allowing a simple rubric-style assessment. The final activity grade is calculated
proportionally from the sum of stage scores and published to the Moodle gradebook.

Students can upload a video or, when enabled, record with the browser camera and microphone using MediaRecorder. Draft
videos can be replaced until submission. Teachers may configure a fixed number of attempts or unlimited attempts. After
assessment, another attempt can be opened when the configured limit allows it. Teachers can also return a submitted or
graded attempt for editing.

The report shows each enrolled student, percentage watched of the reference video, practice submission status,
assessment status, grade and last activity update. The report can be exported as CSV.

Custom activity completion can require a minimum reference-video percentage and/or a submitted practice video.
Backup/restore and Moodle Privacy API support are included.

## Requirements

- Moodle 4.4 or newer.
- PHP version supported by the installed Moodle release.
- HTTPS is required by modern browsers for camera/microphone recording outside localhost.

## Installation

Copy the plugin directory to:

`mod/videopractice`

Then visit Site administration > Notifications and complete the Moodle upgrade process.

## Reference

The player/progress concepts and source handling were adapted from:

https://github.com/EduardoKrausME/moodle-mod_videoprogress

## Validation

The plugin is intended to pass the static validation rules from:

https://github.com/EduardoKrausME/moodle-plugin-validate

## License

GNU GPL v3 or later.
