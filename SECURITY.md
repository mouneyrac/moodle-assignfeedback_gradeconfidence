# Security Policy

Grade Confidence is an open-source (GPLv3) grading-*assurance* plugin for Moodle. For an AI tool that
handles student work, security and privacy are the product — not an afterthought.

## Reporting a vulnerability

Please report suspected vulnerabilities **privately** — not via a public issue or pull request:

- Contact the maintainer (Jerome) via Moodle.org: <https://moodle.org/user/profile.php?id=542994>

We aim to acknowledge within a few days and to fix confirmed issues promptly. This is an **alpha**,
maintained best-effort; there is no bug bounty. Please allow reasonable time for a fix before any public
disclosure. Responsible reports are welcomed and credited (with your consent).

## Scope

This repository (`assignfeedback_gradeconfidence`, the assignment adapter) is reviewed together with its
companions:

- `aiplacement_gradeconfidence` — engine
- `assignfeedback_gradeconfidence` — assignment adapter (this repo)
- `qtype_aigraded` — quiz question type

## How we test (and how you can verify)

- **Executable security suite** — `vendor/bin/phpunit --group security` asserts the access-control,
  privacy-gating and attack-surface invariants on every CI run (e.g. that a student only ever sees the
  neutral assurance signal, never the internal flags or quotes).
- **CodeQL** static analysis runs on every push and weekly — see this repository's **Security** tab.
- A **threat-focused manual security review** (data theft, privilege escalation, abuse).
- **Zero third-party runtime dependencies** by design.

## Supported versions

The latest `main` is supported. Requires the `aiplacement_gradeconfidence` engine. Targets Moodle 5.1+,
PHP 8.3+.
