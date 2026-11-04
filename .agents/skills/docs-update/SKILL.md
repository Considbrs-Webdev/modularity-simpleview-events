---
name: docs-update
description: Bring existing documentation (README, AGENTS.md, docs/) in line with the current code - find outdated commands, paths, setup steps and descriptions, and fix them. Use when docs may have drifted, after larger changes, or when asked to "update the docs".
---

# Documentation update

Documentation describes what the code does now. Do not invent; verify each claim.

## 1. Inventory

List `README.md`, `AGENTS.md`, `CLAUDE.md`, `CONTRIBUTING.md`, `CHANGELOG.md` (do not
rewrite history in it), `docs/`, and inline setup notes. Note the language each is written in
and keep it.

## 2. Verify claims against the code

For every concrete statement, check it:
- Commands: do the scripts exist in manifests, Makefile or CI? Run safe ones (install, test).
- Paths and file names, directory structure, config keys, environment variables (names
  only, never values), ports, URLs.
- Versions and requirements: language, runtime, platform, dependencies.
- Architecture descriptions and diagrams against the actual modules and flows.
- Links and cross-references.

Also look for the opposite: important things that are in the code but missing from the docs
(new commands, services, env vars, features).

## 3. Fix

Make minimal edits that correct or fill gaps and keep the existing voice. Do not restructure
documents unless asked. If something is unclear and the code does not settle it, leave a
`TODO:` rather than guessing. Never put documents' paths into code comments.

## 4. Report

Summarize what was wrong, what you changed, what remains uncertain, and any docs you think
should be deleted or merged. Do not commit.
