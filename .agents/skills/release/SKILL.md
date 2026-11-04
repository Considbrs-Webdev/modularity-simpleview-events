---
name: release
description: Cut a new release of the current project - determine the next semantic version, draft a changelog entry, bump the version everywhere it is declared, verify with the project's tests and build, commit, tag, push and publish a GitHub release. Use when the user asks to "release", "cut a version", "bump the version" or "tag a release".
---

# Cutting a release

Works for any project. Nothing here assumes a language, branch model or repository name:
discover them, and let the project's `AGENTS.md` override anything below (look for a
"Release" section first).

Hard rules:
- Tests must pass before anything is committed, pushed, tagged or published. Never skip
  them because they cannot run: install dependencies instead. If they fail, stop, report,
  and fix in a separate commit that is not part of the release.
- Confirm with the user before the changelog is written (step 2) and before anything is
  pushed or published (step 6).
- Behavior changes get their own commits before the release starts. The release commit
  touches only version files and the changelog.
- Never force-push, and never rewrite or move an existing tag.

## 1. Discover the project

- **Version files.** Search for declared versions: `package.json`, `package-lock.json`,
  `composer.json`, `pyproject.toml`, `Cargo.toml`, `*.csproj`, `VERSION`, plugin/theme
  headers (`Version:` in PHP, `style.css`), `.version`, chart/manifest files. Show the user
  the list of files you will change. They must all move together.
- **Verification commands.** Take test, lint and build commands from `AGENTS.md`, then
  from scripts in `package.json`/`composer.json`, `Makefile`, CI workflows. If unclear, ask.
- **Branches and remote.** `git branch -a`, `git remote -v`, `gh repo view`. Determine the
  development branch and the release branch (they may be the same). Check that the working
  tree is clean and the release branch is up to date.
- **Changelog and history.** Read the top of `CHANGELOG.md` (if any) and `git tag` to learn
  the format, tag style (`1.2.3` vs `v1.2.3`) and tone. Follow the existing style.

## 2. Next version and changelog - confirm

```bash
git fetch --tags
git describe --tags --abbrev=0
git log --oneline <last-tag>..HEAD
```

Pick the bump by semver: fixes only is patch, backward-compatible features is minor, a
breaking change (renamed public API, config, route, CLI command, hook) is major. When in
doubt, ask.

Summarize the unreleased commits into user-facing bullets, grouped under Added / Changed /
Fixed (Keep a Changelog) unless the project uses another format. Describe the effect, not
the implementation. If there is no changelog, ask whether to create one.

Show the user the version number and the draft entry and wait for approval.

## 3. Apply the bump

Edit every version file found in step 1. Prefer the ecosystem's own tool where it keeps
lock files in sync (for example `npm version <X.Y.Z> --no-git-tag-version`). Insert the
approved entry at the top of the changelog.

## 4. Verify

Run the project's install, lint, test and build commands. Every one must succeed.

## 5. Commit

```bash
git add <version files> <changelog>
git commit -m "Release X.Y.Z"
```

Use the project's commit message convention if `AGENTS.md` defines one.

## 6. Push, merge, tag - confirm

This publishes to a shared remote. Confirm first, unless the user already asked for the
full release including publishing in this same request.

- If development and release branches differ: push the development branch, then merge into
  the release branch with `--ff-only`. If that fails, stop and investigate.
- Create an annotated tag in the project's tag style and push it.
- Return to the development branch afterwards.

## 7. Publish the release

Use `gh release create <tag> --title "<title>" --notes "<changelog entry>"`, with the
changelog entry verbatim as the body, and match the title style of earlier releases
(`gh release list`, `gh release view <previous>`). Skip this step if the project does not
use GitHub releases.

Finish by reporting the version, the tag, the release URL and any step that was skipped.
