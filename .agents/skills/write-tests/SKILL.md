---
name: write-tests
description: Write or extend automated tests for existing or new code, following the project's test setup and conventions, and run them. Use when the user asks to add tests, improve coverage, or cover a bug fix or feature with tests.
---

# Write tests

Tests must be usable by both developers and AI to check that the system works.

## 1. Learn the setup

Find the test framework, config, directory layout, naming, fixtures/factories, mocking
approach and the command to run a single test and the whole suite (see `AGENTS.md`, CI,
manifests). Read two or three existing tests that are closest to the code under test and
mirror their style.

## 2. Decide what to test

Read the code under test and identify behavior, not lines: normal paths, edge cases (empty,
null, boundaries, invalid input), error paths and permissions. For a bug fix, write the test
that fails first, and confirm it fails for the right reason. Prefer testing public behavior
over internals.

## 3. Write

- One behavior per test, with a name that describes it.
- Deterministic: no dependence on time, order, network or shared state without control.
- Minimal mocking; mock at system boundaries only.
- Synthetic data only. Never use real customer or personal data or secrets.
- Do not change production code to make tests pass unless the code is wrong; if it is,
  report it.

## 4. Run and report

Run the new tests, then the full suite. Fix failures that you caused. State what is covered,
what is not, and any bug or untestable design you found. Do not weaken or delete existing
tests to get green.
