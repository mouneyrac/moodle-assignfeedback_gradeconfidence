# Contributing to Grade Confidence — assignment adapter

Contributions are welcome. This is the **assignment adapter** (`assignfeedback_gradeconfidence`); the
surface-agnostic logic lives in the engine (`aiplacement_gradeconfidence`), whose
[`CONTRIBUTING.md`](https://github.com/mouneyrac/moodle-aiplacement_gradeconfidence) holds the full ground
rules, coding standards, and licensing terms — please read it.

## In short

- **Isolation:** adapter-specific glue (read the native rubric/submission, render the teacher panel, store
  results) lives here; anything reusable belongs in the engine.
- **Tests are not optional**; keep the suite green; a bug fix adds a regression test first.
- **Standards:** PSR-12/PSR-1 + Moodle style; type hints required; `moodle-cs` + `moodlecheck` clean;
  capability + `sesskey` + `required_param` on new actions; strings in `lang/`.

```bash
vendor/bin/phpunit --testsuite assignfeedback_gradeconfidence_testsuite
vendor/bin/behat --tags @assignfeedback_gradeconfidence
```

## Licensing & commercial intent

By contributing you agree your contribution is **GNU GPL v3 or later** — permanently free software anyone
may use, modify and **redistribute, including for free**. This is an **alpha** released to gauge interest; a
paid edition may later appear on the Moodle Marketplace to fund maintenance and support, but that never
changes the freedom of this code or your contributions.
