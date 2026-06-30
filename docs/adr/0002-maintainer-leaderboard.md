# Weighted maintainer scoring from review activity

> Supersedes the earlier raw-count-only revision of this ADR (2026-06-20). Full build detail lives in `docs/features/leaderboard-scoring.md`.

> **Update (2026-06):** the `github-pr-timeline` index was subsequently built, so the items marked *deferred* below are now **implemented** — review-latency stats (time-to-review, time-to-claim), a staleness-weighted `pr_claimed` bonus (credited only once the maintainer reviews), and label/triage scoring (`label_applied`, deduped). See [Weighted Scoring](../features/leaderboard-scoring.md) and [GitHub Timeline-Events Sync](../features/github-timeline-events.md).

Maintainers are scored separately from contributors, on a **weighted Maintainer Score** built from review activity, with a bonus when a PR they approved is later merged.

## Context

Maintainers can review (approve / reject / comment) and apply labels; they cannot merge or close — **Adobe** does. The review workflow is not contributor-initiated: Adobe applies `Progress: pending review`, then a maintainer **self-assigns** as reviewer (possibly months later), then reviews. This shapes what we can fairly attribute to an individual maintainer.

## Decision

- **Maintainer Score** = weighted sum of: review approved, review rejected, review commented (lower weight), and an impact-weighted **approver bonus** when an approved PR is later merged. The merge bonus is attributed to the review's `submitted_at` for point-in-time company credit. Self-approval cannot earn the bonus (`author == reviewer` guard).
- Review data comes from the existing `github-pr-reviews` index (one document per review submission). Bot exclusion and Calendar Periods match the contributor boards.
- **Review latency / responsiveness was deferred** *(since implemented — see Update above).* The only fair per-maintainer span is self-assignment → first review; the `Progress: pending review` → self-assignment span is project-backlog health, not an individual's. Both need timeline events (label timing, reviewer assignment) that the sync did not then capture. `created_at` is not an acceptable proxy. The `github-pr-timeline` index now supplies these; time-to-claim additionally drives the `pr_claimed` bonus, deliberately rewarding the maintainer who clears a stale PR.
- **Label-applied / triage scoring was deferred** for the same reason (`labels[]` is a snapshot, not an event stream) *— since implemented* via timeline label events.

## Consequences

- Maintainer scores are precomputed in the same job as contributor scores and written to `leaderboard_entries` (board = `maintainer`).
- A timeline-events index is the prerequisite for review latency, responsiveness, and label/triage scoring — since built (`github-pr-timeline`), so these signals are now live alongside review submissions.
- A maintainer who also authors PRs accrues an independent Contributor Score (ADR 0001); the two are never combined.

## Considered options

**Raw approve/reject counts only (previous revision)** — rejected for the same reasons as ADR 0001: counts reward volume over impact and ignore whether reviewed work actually shipped. Raw-count review boards are retained as a transparency layer.

**Score `Progress: pending review` → review as maintainer latency** — rejected. That span is mostly queue time before any maintainer is responsible; charging it to the eventual reviewer punishes whoever finally picks up a neglected PR.

**Embed reviews in `github-pull-requests`** — rejected (unchanged from prior revision): a flat per-review index keeps query classes simple and consistent.
