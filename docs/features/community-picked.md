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

Both are written by `sync:github:prs`. PRs indexed before these fields existed sort last (`missing: _last`, `unmapped_type: long`) and show 0 👍 until re-synced.

## Key files

- `app/Http/Controllers/CommunityPickedController.php` — page controller, `page` validation
- `app/Queries/Dashboard/CommunityPickCandidatesQuery.php` — candidate rule, sort, pagination
- `resources/views/communityPicked/index.blade.php` — table view
- `config/github.php` — `community_picked.exclude_labels`

## Configuration

| Key | Description |
|-----|-------------|
| `github.community_picked.exclude_labels` | Labels that disqualify an open PR. Change this when the skipped release line changes. |

## Gotchas / constraints

- 👍 counts are only as fresh as the last PR sync that touched the PR. Reactions may not bump a PR's `updatedAt`, in which case the 15-minute incremental sync misses new votes until the weekly full sync.
- `closingIssuesReferences` only captures closing keywords and sidebar links; a PR that mentions an issue without one has no Linked Issues, so it matches filters on its own labels only.
- The Linked Issue lookup fetches up to 10,000 issues in one page. Candidates link far fewer today; page the lookup if that changes.
- Linked Issues missing from the issues index (created before `github.history_start`, or not yet synced) contribute no labels.
- A missing index renders the standard "data missing" placeholder.
