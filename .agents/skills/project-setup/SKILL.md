---
name: project-setup
description: Set up the Consid Borås AI toolkit in a project - map the codebase, install the shared skills tool-agnostically, create AGENTS.md and a CLAUDE.md pointer, adapt README.md and propose docs/CODING_STANDARDS.md. Use when starting a project, onboarding an existing codebase to the toolkit, or when asked to "set up", "init" or "bootstrap" AI instructions.
---

# Project setup

Adapts the toolkit's base instructions to the codebase in the current directory. Run it from
the project root. It is re-runnable: on a project that is already set up, update rather than
overwrite, and show what changed.

Principles:
- Everything is tool-agnostic. `AGENTS.md` is the source of truth; tool-specific files only
  point to it.
- Do not guess. Anything you cannot determine from the code is marked `TODO:` and listed in
  the final summary.
- Preserve existing content. Change existing text only where it is wrong or missing, and
  say so.
- Never read secrets. Do not open `.env` or credential files; note only that they exist and
  how they are loaded.
- Never reference documents from code comments. If a comment needs an explanation, state it
  in the comment.
- Do not commit. Leave all changes for the developer to review.

The toolkit's files live next to this skill: `templates/AGENTS.md` and
`templates/CODING_STANDARDS.md`. The toolkit's other skills are siblings of this skill's
directory.

## 1. Map the codebase

Read, do not guess: manifests and lock files, README, existing `AGENTS.md`/`CLAUDE.md`/
`.cursor/rules`, CI workflows, test config, linters/formatters, Docker/dev-environment
files, top-level directory layout, and a sample of source files per main area. Establish:
purpose, languages and frameworks, how to install, run, test, lint and build, branch and
release conventions, where architecture or domain knowledge lives, and what is
generated, vendored or off-limits.

## 2. Ask only what the code cannot tell you

Ask in one batch: which AI tools the team uses beyond Claude Code (Codex, Cursor, others),
project-specific restrictions on customer or personal data, which skills to include (see
below), and anything marked unclear in step 1.

Skills to include: every skill in the toolkit's `skills/` directory except `project-setup`
itself (it is always installed so the setup can be re-run to update). Each skill must be
selectable on its own. Never collapse the choice into a few grouped options, "all"/"none"
buckets, or a picker that cannot show every skill; many question tools cap the number of
options, so do not rely on one for this choice.

Make a recommendation per skill based on step 1 (for example `dependency-update` where there
is a package manager, `release` where versions or tags exist, `write-tests` where a test
setup exists), with a short reason, and ask which way the developer wants to answer:

- **As one list:** print all skills as a numbered list with the one-line description, the
  recommendation and its reason, and let the developer answer in plain text, for example
  "1, 3, 5-7", "recommended", "all" or "all except 4". Echo back the resulting list per
  skill and ask for confirmation before installing.
- **One at a time:** go through the skills one by one, each with its description and
  recommendation, and ask yes or no per skill. Skip nothing, and show the final selection
  for confirmation at the end.

Remember the choice; on a re-run, show each skill's installed state and offer to add or
remove skills the same way.

## 3. Install the shared skills, tool-agnostically

The toolkit is the directory that contains this skill's siblings (resolve symlinks to find
it). Before copying, note the toolkit's source (`git remote get-url origin`) and commit
(`git rev-parse HEAD`).

1. Copy only the chosen skills into `.agents/skills/<name>/`. This is the single real
   location. Copy, do not link, so the project is pinned to a reviewable version. Write the
   toolkit source, commit and date to `.agents/skills/.toolkit-version`. On a re-run, compare
   it with the toolkit's current state, show what changed per skill and update only after the
   developer approves; keep project-specific edits.
2. Adapt each copied skill only where this project truly differs (e.g. a different release
   flow). Prefer recording the difference in `AGENTS.md` under a "Release" heading over
   editing the skill, so the skill stays easy to update from the toolkit.
3. Create relative symlinks so each tool finds the same files, for example:
   `.claude/skills -> ../.agents/skills`, and the equivalent for `.cursor`, `.codex` or
   others the team uses. Skip tools that already read `.agents/skills`. Never replace an
   existing non-empty skills directory; merge its contents into `.agents/skills` first and
   tell the user.
4. Check the symlinks resolve, and that they are committed as links, not copies.

## 4. Create AGENTS.md and the CLAUDE.md pointer

Start from `templates/AGENTS.md` and fill in every section from step 1. Remove sections that
do not apply instead of leaving them empty. Keep it short and concrete: commands that
actually work, real paths, real conventions. If an `AGENTS.md` exists, merge into it.

Create `CLAUDE.md` containing only a line that points to `AGENTS.md` (`@AGENTS.md`), unless a
`CLAUDE.md` with real content exists, in which case move that content into `AGENTS.md`
after showing the user.

## 5. Adapt README.md

Make the README match the codebase: what it is, requirements, local setup, how to run tests,
how to build, how to release, and a pointer to `AGENTS.md` for AI work. Verify every command
by reading the scripts it refers to, and run the safe ones (install and test) when possible.
Keep the existing voice and language of the README.

## 6. Propose docs/CODING_STANDARDS.md

Start from `templates/CODING_STANDARDS.md`. Derive proposed rules from the linters, formatter
configs and the dominant patterns in the code. Mark each rule as `enforced by tooling` or
`observed pattern`. Existing patterns are proposals, not decisions: flag the ones that look
like they are worth improving, and do not demand that legacy code be rewritten. The
developer decides what stays.

## 7. Summarize

Report what was created and changed, every `TODO:`, anything you could not verify, and the
decisions that need a human. Remind the developer to review everything before it is committed.
