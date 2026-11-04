# AGENTS.md

Instructions for AI tools working in this repository. Read this first.

## Project

<!-- One or two sentences: what this is, who uses it, what it is built with. -->

## Setup and commands

<!-- Only commands that work. Remove rows that do not apply. -->

| Task | Command |
| --- | --- |
| Install | |
| Run locally | |
| Test | |
| Lint / format | |
| Build | |

## Structure

<!-- The main directories and what lives in them. Mark generated or vendored directories as off-limits. -->

## Conventions

- Follow `docs/CODING_STANDARDS.md` for new and changed code.
- Do not refactor unrelated code. Deviations in older code are not a reason to rewrite it.
- Comments explain why, briefly. Never refer to documents from code comments; restate the relevant information in the comment instead.
- Write and update tests together with the code. Run the tests before saying work is done.

## Release

<!-- Only project-specific deviations from the shared release skill: branches, build step, tag style, commit message convention. -->

## Where to find more

<!-- Architecture notes, domain knowledge, ADRs, API docs, tickets. -->

## Security and data

- Secrets come from environment variables or the project's secret management. Never read, print or commit their values, and never put them in instructions, code or logs.
- Personal, customer and other sensitive data may only be used when approved by the customer and project owner. Use synthetic or anonymized data where possible.
- Project-specific restrictions: <!-- document them here -->

## Working with AI

- The developer is responsible for understanding, reviewing and verifying everything AI produces.
- If a guard or permission blocks an action, do not work around it. Report it and let the developer decide.
- If these instructions cause unnecessary steps or unwanted behavior, tell the developer so this file can be improved.
