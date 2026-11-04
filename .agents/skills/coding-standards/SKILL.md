---
name: coding-standards
description: Create or update docs/CODING_STANDARDS.md from the codebase, linter and formatter configs, and the team's shared guidelines. Use when a project lacks a coding standard, when it has drifted, or when asked to "write", "propose" or "update the coding standards".
---

# Coding standards

The output is a proposal. The responsible developers decide which rules apply. Existing
patterns do not automatically become rules, and deviations in old code are not a reason to
rewrite it.

## 1. Collect evidence

- Tooling that already enforces rules: linter, formatter, type checker, `.editorconfig`,
  CI checks, pre-commit hooks.
- The dominant patterns in the code: naming, structure, error handling, testing, how
  dependencies and configuration are handled. Sample several areas, and prefer recent code.
- `AGENTS.md`, existing contributing guides and review comments if available.
- Inconsistencies: where the code does the same thing in different ways.

## 2. Draft

Start from `docs/CODING_STANDARDS.md` if it exists, otherwise from the template in the
`project-setup` skill. For every rule, give the rule, a one-line reason, and its basis:
`enforced by tooling` or `observed pattern`. Prefer few, clear, checkable rules over long
lists. Cover: language and tooling, naming and structure, error handling, testing, security,
dependencies, git and reviews.

When existing patterns look problematic, put them in a separate "Proposed improvements"
section instead of codifying them.

## 3. Present

List the decisions the developers need to make (conflicting patterns, debatable rules) and
ask before finalizing. Do not commit. Rules about code comments must say: never refer to
documents from comments; restate the relevant information instead.
