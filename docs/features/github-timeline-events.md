# GitHub Timeline-Events Sync

> **Status:** Partially implemented. The PR timeline sync (`github-pr-timeline` index) and issue `label { name }` capture are built; the derived latency metrics and the scoring compute are not. Prerequisite for three features deferred in [Weighted Scoring](leaderboard-scoring.md): **review latency**, **maintainer responsiveness**, and **label / triage scoring**.

## Why

Scoring impact and maintainer responsiveness needs *when* things happened, not just the current state. The Magento review workflow is entirely timeline-driven:

1. **Adobe** applies the `Progress: pending review` label → the PR enters the review pool.
2. A **maintainer self-assigns** as reviewer (actor == requested reviewer), possibly months later.
3. The maintainer submits a review.

None of steps 1–2 are captured today.

## What's missing today

| Need | Current state |
|---|---|
| When `Progress: pending review` was applied | ✅ PR `timelineItems` → `github-pr-timeline` (`label_name`) |
| When a maintainer self-assigned as reviewer | ✅ `ReviewRequestedEvent` → `github-pr-timeline` (`requested_reviewer`) |
| Which label a `LabeledEvent` was for | ✅ `label { name }` now captured in all four issue queries and persisted as `label_name` |
| When a maintainer first reviewed | ✅ `github-pr-reviews.submitted_at` (exists) |

## GraphQL additions

### `github_pull_requests.graphql` — filtered timeline (implemented)

`timelineItems` is now on the PR node, scoped to only the event types we score (kept narrow — magento2 timelines are long and node cost / secondary rate limits are real):

```graphql
timelineItems(first: 100, itemTypes: [
  LABELED_EVENT, UNLABELED_EVENT,
  REVIEW_REQUESTED_EVENT, REVIEW_REQUEST_REMOVED_EVENT
]) {
  nodes {
    __typename
    ... on LabeledEvent          { id actor { login } createdAt label { name } }
    ... on UnlabeledEvent        { id actor { login } createdAt label { name } }
    ... on ReviewRequestedEvent  { id actor { login } createdAt requestedReviewer { ... on User { login } } }
    ... on ReviewRequestRemovedEvent { id actor { login } createdAt requestedReviewer { ... on User { login } } }
  }
}
```

Note `id` on every node (used as the OpenSearch `_id`, same upsert pattern as reviews) and `label { name }` (the existing issue query's missing piece). `OpenSearchService::indexPullRequestTimeline()` writes these to `github-pr-timeline` as a side effect of the PR sync; the `type` field stores the GraphQL `__typename` (`LabeledEvent`, `UnlabeledEvent`, `ReviewRequestedEvent`, `ReviewRequestRemovedEvent`).

### Issue queries — label name (done)

`label { name }` is now captured on the `LabeledEvent`/`UnlabeledEvent` fragments in all four issue queries:

- `resources/graphql/github/github_issue_timeline_items.graphql`
- `resources/graphql/github/github_issues_with_events.graphql`
- `resources/graphql/github/github_issue_interactions.graphql`
- `resources/graphql/github/github_issues_with_interactions.graphql`

`GitHubInteractionService` surfaces it as a `label` key on each event/interaction record (null for non-label events), and `SyncGitHubEvents` / `SyncGitHubInteractions` write it as `label_name` on the `github-events` / `github-interactions` documents (only when present). What remains for triage scoring is the scoring logic itself, plus a keyword mapping if exact-match filtering on label strings is needed (see below). These four queries still overlap heavily; worth consolidating later.

### Confirmed

- **Self-assignment is via reviewers** → `REVIEW_REQUESTED_EVENT` with `requestedReviewer` is the right signal (self-assign means `actor == requestedReviewer`). `ASSIGNED_EVENT` is not needed.
- **Label string is `Progress: pending review`** — still read it from config rather than hard-coding, so a rename doesn't require a code change.

### Still to tune

- **Page-size / rate-limit**: adding `timelineItems(first: 100)` to `pullRequests(first: 100)` raises node cost. If GitHub returns node-limit or secondary-rate-limit errors, drop PR page size (the issue query already uses `first: 25` for this reason) or narrow `itemTypes` further.
- **Pagination**: `timelineItems(first: 100)` is not paginated here. A PR with >100 relevant events would truncate; acceptable for labels/review-requests but note it.

## New index: `github-pr-timeline`

One document per timeline event, upserted by GitHub event node `id` (mirrors `indexPullRequestReviews()`):

```json
{
  "pr_number": 12345,
  "type": "LabeledEvent",
  "actor": "adobe-bot-or-user",
  "created_at": "2026-01-15T10:00:00Z",
  "label_name": "Progress: pending review",   // null for review-request events
  "requested_reviewer": null                   // login for review-request events, else null
}
```

Written as a side effect of the PR sync, alongside `indexPullRequestReviews()` and `flagIssuesClosedByMergedPRs()`.

## Derived metrics

Computed in the leaderboard compute job, per PR and per maintainer:

| Metric | Definition | Attribution |
|---|---|---|
| `pending_review_at` | `created_at` of the most recent `LabeledEvent` with `label_name = Progress: pending review` **preceding** the maintainer's claim | per PR |
| `claimed_at` | `created_at` of the `ReviewRequestedEvent` where `requested_reviewer = maintainer` (self-assign: `actor == requested_reviewer`) | per maintainer |
| `first_review_at` | min `submitted_at` in `github-pr-reviews` for that maintainer on that PR | per maintainer |
| **Time-to-claim** | `claimed_at − pending_review_at` | **project/backlog health — NOT a maintainer board** |
| **Time-to-review** | `first_review_at − claimed_at` | per-maintainer responsiveness (the only span they control) |
| **Responsiveness** | share of a maintainer's self-assigned PRs reviewed within N days | per maintainer |

## Edge cases

- **Label removed & re-applied** → multiple `pending_review_at`; use the most recent one before the maintainer's claim.
- **Reviewer requested, removed, re-requested** → use the first claim that precedes the maintainer's first review; ignore `ReviewRequestRemovedEvent` except to detect churn.
- **Reviewed without a recorded self-assign** (data gaps, pre-timeline PRs) → `claimed_at` null → exclude from Time-to-review rather than charging queue time to the reviewer.
- **Multiple maintainers on one PR** → each gets their own `claimed_at` / `first_review_at`; metrics are per maintainer.
- **Bot actors** (`engcom-*`, `dependabot[bot]`, `github-actions[bot]`, `m2-assistant`) excluded, same as elsewhere.

## Relationship to the events / interactions indexes

The `github-events` and `github-interactions` indexes are **live** — restored and expanded with paginated comments and timeline items, sync commands (`SyncGitHubEvents`, `SyncGitHubInteractions`), and an enlarged `GitHubInteractionService`. Issue triage scoring (phase 3) extends that path; `label { name }` is now captured (see above), leaving only the scoring logic. The `github-pr-timeline` index here is PR-specific and independent of those.

## Build phases

1. **PR timeline sync** — ✅ done: `timelineItems` added to the PR query and written to `github-pr-timeline` via `OpenSearchService::indexPullRequestTimeline()`. Remaining: backfill (re-sync PRs).
2. **Latency metrics** — compute `pending_review_at` / `claimed_at` / `first_review_at` → `github_user_stats` (Time-to-review, Responsiveness).
3. **Label/triage scoring** — ✅ `label_name` now captured and persisted. Remaining: score label application (scored event #9 in the scoring spec) and "issues triaged"; add a keyword mapping for `label_name` if exact-match label filtering is required.
4. **UI** — surface Time-to-review on maintainer views; expose Time-to-claim as a project-health (not ranked) stat.

## Testing (per CLAUDE.md)

PHPUnit feature tests using fixtures: pending-review detection (including remove/re-apply), self-assign claim matching, Time-to-review vs Time-to-claim separation, null-claim exclusion, multi-maintainer PRs, and bot filtering.
