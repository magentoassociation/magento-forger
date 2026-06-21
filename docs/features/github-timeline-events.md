# GitHub Timeline-Events Sync

> **Status:** Build spec. Prerequisite for three features deferred in [Weighted Scoring](leaderboard-scoring.md): **review latency**, **maintainer responsiveness**, and **label / triage scoring**. Until this lands, those use review submissions only.

## Why

Scoring impact and maintainer responsiveness needs *when* things happened, not just the current state. The Magento review workflow is entirely timeline-driven:

1. **Adobe** applies the `Progress: pending review` label → the PR enters the review pool.
2. A **maintainer self-assigns** as reviewer (actor == requested reviewer), possibly months later.
3. The maintainer submits a review.

None of steps 1–2 are captured today.

## What's missing today

| Need | Current state |
|---|---|
| When `Progress: pending review` was applied | ⛔ PR query has no `timelineItems`; only a current `labels[]` snapshot |
| When a maintainer self-assigned as reviewer | ⛔ not captured anywhere |
| Which label a `LabeledEvent` was for | ⛔ `github_issues_with_events.graphql` fetches `LabeledEvent` but omits `label { name }` |
| When a maintainer first reviewed | ✅ `github-pr-reviews.submitted_at` (exists) |

## GraphQL additions

### `github_pull_requests.graphql` — add a filtered timeline

Add `timelineItems` to the PR node, scoped to only the event types we score (keep it narrow — magento2 timelines are long and node cost / secondary rate limits are real):

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

Note `id` on every node (used as the OpenSearch `_id`, same upsert pattern as reviews) and `label { name }` (the existing issue query's missing piece).

### `github_issues_with_events.graphql` — add label name

If issue triage scoring is wanted, add `label { name }` to its `LabeledEvent`/`UnlabeledEvent` fragments. Without it the event is unattributable to a specific label.

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
  "type": "LABELED_EVENT",
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
| `pending_review_at` | `created_at` of the most recent `LABELED_EVENT` with `label_name = Progress: pending review` **preceding** the maintainer's claim | per PR |
| `claimed_at` | `created_at` of the `REVIEW_REQUESTED_EVENT` where `requested_reviewer = maintainer` (self-assign: `actor == requested_reviewer`) | per maintainer |
| `first_review_at` | min `submitted_at` in `github-pr-reviews` for that maintainer on that PR | per maintainer |
| **Time-to-claim** | `claimed_at − pending_review_at` | **project/backlog health — NOT a maintainer board** |
| **Time-to-review** | `first_review_at − claimed_at` | per-maintainer responsiveness (the only span they control) |
| **Responsiveness** | share of a maintainer's self-assigned PRs reviewed within N days | per maintainer |

## Edge cases

- **Label removed & re-applied** → multiple `pending_review_at`; use the most recent one before the maintainer's claim.
- **Reviewer requested, removed, re-requested** → use the first claim that precedes the maintainer's first review; ignore `REVIEW_REQUEST_REMOVED_EVENT` except to detect churn.
- **Reviewed without a recorded self-assign** (data gaps, pre-timeline PRs) → `claimed_at` null → exclude from Time-to-review rather than charging queue time to the reviewer.
- **Multiple maintainers on one PR** → each gets their own `claimed_at` / `first_review_at`; metrics are per maintainer.
- **Bot actors** (`engcom-*`, `dependabot[bot]`, `github-actions[bot]`, `m2-assistant`) excluded, same as elsewhere.

## Relationship to the events / interactions indexes

The `github-events` and `github-interactions` indexes and `github_issues_with_events.graphql` are **live** (restored). Issue triage scoring (phase 3) extends that existing path — it needs `label { name }` added to the `LabeledEvent`/`UnlabeledEvent` fragments. The `github-pr-timeline` index here is PR-specific and independent of those.

## Build phases

1. **PR timeline sync** — add `timelineItems` to the PR query, write `github-pr-timeline` in the PR indexer; backfill.
2. **Latency metrics** — compute `pending_review_at` / `claimed_at` / `first_review_at` → `github_user_stats` (Time-to-review, Responsiveness).
3. **Label/triage scoring** — add `label { name }` to the issue events query; score label application (scored event #9 in the scoring spec) and "issues triaged".
4. **UI** — surface Time-to-review on maintainer views; expose Time-to-claim as a project-health (not ranked) stat.

## Testing (per CLAUDE.md)

PHPUnit feature tests using fixtures: pending-review detection (including remove/re-apply), self-assign claim matching, Time-to-review vs Time-to-claim separation, null-claim exclusion, multi-maintainer PRs, and bot filtering.
