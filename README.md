# Grade Confidence — assignment adapter (`assignfeedback_gradeconfidence`)

A `mod_assign` feedback subplugin that wires **Grade Confidence** into assignment grading. When a teacher
saves a grade, it reviews the rubric selections for consistency (via the `aiplacement_gradeconfidence`
engine) and notifies the teacher **only** when something looks materially off. Students never see the
internal flags or quotes.

- Auto-review on save (or manual/off, per the engine's mode); exception-based notification (Message API).
- Reads the teacher's **native** advanced-grading rubric + fillings and online-text submissions, and
  delegates to the engine. It does not re-implement rubric editing.
- An optional per-activity "model answer / exemplar" can be supplied as a reference for the standard.

## Project status — alpha, gauging interest

This is part of an **alpha** project published to gauge real interest in an AI grading-assurance tool. It
has not yet been through a real-world pilot. If the project reaches a stable release it **may later be
offered as a paid product on the [Moodle Marketplace](https://marketplace.moodle.com/)** — selling
maintenance, professional review, and business support, **never** exclusivity over the code (see
*License & reuse*). The need for a paid edition reflects how time-consuming ongoing maintenance, compliance
upkeep across Moodle upgrades, and customer support are, and may require a change in the maintainer
situation.

## A note on compliance — please read

Grade Confidence is **designed to follow** sound privacy and EU AI Act practices, but it is **NOT
certified, audited, or conformity-assessed**, and we make **no legal compliance claim**. A formal EU AI Act
conformity assessment (~€30k–€80k) **has not been done**. It is built in good faith to *support* your own
compliance work — and we would hope it would pass an audit — but **you, the deployer, remain responsible**
for your compliance, DPAs, and any required assessment. Do not present anything here as proof of conformity.

## Requirements

- Moodle **5.1+** (developed against 5.2). **PHP 8.3+**.
- **Depends on** `aiplacement_gradeconfidence` (the engine), which must be installed and configured with a
  working `core_ai` provider.

## Install

Place this repository's contents at `public/mod/assign/feedback/gradeconfidence/` in your Moodle, then
complete the upgrade via **Site administration → Notifications**. Enable it per assignment under the
assignment's **Feedback types**. (Distribution repo: `moodle-assignfeedback_gradeconfidence`.)

## Developing

Adapter-specific glue (reading the rubric/submission, rendering the teacher panel, storing results) lives
here; all surface-agnostic logic lives in the engine. Keep that split.

```bash
vendor/bin/phpunit --testsuite assignfeedback_gradeconfidence_testsuite
vendor/bin/behat --tags @assignfeedback_gradeconfidence
```

See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## License & reuse

GNU **GPL v3 or later** — free software you may use, study, modify, and **redistribute, including for
free**. A future paid edition cannot remove those rights for this code. Only the name "Grade Confidence"
(trademark) is outside the GPL: a redistribution must not imply endorsement or reuse the branding.
