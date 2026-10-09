# Community-picked PRs

## Purpose

Surface open PRs on the tracked repository that the community can vote to have merged. Replaces the hand-built GitHub search (`is:pr is:open sort:reactions-desc -label:"Release Line: 2.5" -label:"Project: Community Picked"`), ranking by 👍 only and excluding drafts.

## How it works

One public page, `GET /prs/community-picked` (`prs.communityPicked`), listed in the PRs menu dropdown and the footer.

`CommunityPickCandidatesQuery` reads the pull-requests index and returns Community Pick Candidates (see CONTEXT.md):

- `is_open` true, `is_draft` not true, no `labels.keyword` in `github.community_picked.exclude_labels`
- sorted by `thumbs_up_count` desc, then `created_at` asc
- 50 per page; `page` is validated to 1–200 because OpenSearch caps `from + size` at 10,000

Each row shows the 👍 count (a link to the PR on GitHub, new tab), number + title, Linked Issues, Effective Area/Component labels, author, and age.

### Filtering by Area / Component

Two GET dropdowns, `?area=Area: …` and `?component=Component: …`, combined with AND. Filtered URLs are shareable, and pagination keeps the filters. A PR matches a label when its **Effective Labels** (CONTEXT.md) include it: the PR carries the label itself, or any Linked Issue does.

OpenSearch has no joins, so each request makes three searches:

1. **Facets** — aggregate over all candidates: their `linked_issues` numbers and their own `Area:`/`Component:` labels.
2. **Linked Issue labels** — fetch those issues' labels from the issues index by `_id`.
3. **Page** — candidates, plus one `should` clause per selected label: `labels.keyword` = label, OR `linked_issues` in the issues carrying it.

The dropdown options are the union of steps 1 and 2. They are built from all candidates, not narrowed by the other filter, so every option has at least one candidate. A label taken from a shared link that no candidate carries any more stays selected and returns an empty list.

Linked Issue labels are read on every request rather than copied onto PR documents. Retagging an issue shows up after the next issue sync, with no PR re-sync needed.

`area` must start with `Area: ` and `component` with `Component: `; any other value fails validation and never reaches OpenSearch.

### Voting

Voting is link-out only: visitors react 👍 on the PR description on GitHub. Forger never writes to GitHub, stores no votes, and needs no extra OAuth scope. GitHub deduplicates votes.

### Data

The PR sync stores two fields on each PR document:

| Field | Source |
|-------|--------|
| `thumbs_up_count` | `reactions(content: THUMBS_UP) { totalCount }` on the PR body |
| `linked_issues` | issue numbers from `closingIssuesReferences` |

Both are written by `sync:github:prs`, and refreshed on every open PR every 15 minutes by `sync:github:pr-reactions` (which also refreshes `is_draft`).

Adding a reaction does **not** bump a PR's `updatedAt`. Checked against live data on 2026-10-09: for example, #41310's latest 👍 was on Oct 8, but its `updatedAt` was Sep 29. The incremental PR sync stops on `updatedAt`, so on its own it would miss new votes until the weekly full sync. Closing references added through the sidebar may not bump it either, so the reactions sync re-reads them too.

`sync:github:pr-reactions` sends plain bulk `update`s with no upsert. An open PR the main sync hasn't indexed yet is skipped, never stored as a stub, and the next PR sync picks it up. Bulk requests report item failures in the response rather than throwing, so the command reads that response. Any item failure other than `document_missing_exception` fails the page, and the run exits 1. PRs that neither sync has written yet sort last (`missing: _last`, `unmapped_type: long`).

## Key files

- `app/Http/Controllers/CommunityPickedController.php` — page controller, `page` validation
- `app/Queries/Dashboard/CommunityPickCandidatesQuery.php` — candidate rule, sort, pagination
- `resources/views/communityPicked/index.blade.php` — table view
- `app/Console/Commands/SyncGitHubPrReactions.php` — 15-minute 👍 / Linked Issue refresh
- `resources/graphql/github/github_pr_reactions.graphql` — open-PR reactions query
- `config/github.php` — `community_picked.exclude_labels`

## Configuration

| Key | Description |
|-----|-------------|
| `github.community_picked.exclude_labels` | Labels that disqualify an open PR. Change this when the skipped release line changes. |

## Gotchas / constraints

- 👍 counts lag GitHub by up to 15 minutes plus one `sync:github:pr-reactions` run (about 12 pages of 100 open PRs).
- `closingIssuesReferences` only captures closing keywords and sidebar links; a PR that mentions an issue without one has no Linked Issues, so it matches filters on its own labels only.
- The Linked Issue lookup fetches up to 10,000 issues in one page. Candidates link far fewer today; page the lookup if that changes.
- Linked Issues missing from the issues index (created before `github.history_start`, or not yet synced) contribute no labels.
- A missing index renders the standard "data missing" placeholder.
