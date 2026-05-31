# Grade Confidence — assignment adapter (`assignfeedback_gradeconfidence`)

A `mod_assign` feedback subplugin that wires the Grade Confidence into assignment grading. When a
teacher saves a grade, it reviews the rubric selections for consistency (via the
`aiplacement_gradeconfidence` engine) and notifies the teacher **only** when something looks materially off.

- Auto-review on save; exception-based notification (Moodle Message API).
- v0.1: rubric advanced grading + online-text submissions.
- Reads the teacher's native rubric/fillings + submission and delegates to the engine.

## Requirements
- Moodle 5.1+ (targets 5.2). PHP 8.3+.
- **Depends on** `aiplacement_gradeconfidence` (the engine).

## Install
Place this repository's contents at `public/mod/assign/feedback/gradeconfidence/` in your Moodle, then
complete the upgrade. (Distribution repo name: `moodle-assignfeedback_gradeconfidence`.)

## License
GNU GPL v3 or later.
