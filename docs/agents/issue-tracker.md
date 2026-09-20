# Issue Tracker: GitHub

This repo tracks issues in **GitHub Issues** (`bremspace/ute-parts-erp`).

## Workflow

- **CLI**: `gh` (GitHub CLI) — `gh issue create`, `gh issue list`, `gh issue view`, `gh issue edit`, `gh issue close`
- **Labels**: Uses the triage label vocabulary (see `docs/agents/triage-labels.md`)
- **PRs as request surface**: **OFF** — only issues created directly in GitHub Issues enter the triage queue. External PRs are not auto-triaged.

## Conventions

- Every ticket from `/to-tickets` creates one GitHub Issue with:
  - Title: `[T-XX] <short description>`
  - Labels: `ready-for-agent` + any blocking labels (e.g., `blocks:T-29`)
  - Body: Full task spec from implement-plan.md (Acceptance Criteria, Perubahan, Modul)
  - Milestone: "Fase 10" (or relevant phase)

## Blocking Edges

- Blocking relationships encoded as labels: `blocks:T-XX` and `blocked-by:T-XX`
- A ticket is "grabbable" when all its `blocked-by` labels are resolved (issues closed)

## Local Fallback

If GitHub is unavailable, issues can be mirrored locally under `.scratch/<feature>/issues/` as markdown files with frontmatter matching the GitHub Issue fields.