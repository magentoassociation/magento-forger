# Community-picked PRs

## Purpose

Surface open PRs on the tracked repository that the community can vote to have merged into Magento 2. After each voting period, the top-voted PRs are prioritized for merging. This page is where voters browse and vote; it doesn't run the voting period or the selection. Replaces the hand-built GitHub search (`is:pr is:open sort:reactions-desc -label:"Release Line: 2.5" -label:"Project: Community Picked"`), ranking by 👍 only and excluding drafts.

## How it works

One public page, `GET /prs/community-picked` (`prs.communityPicked`), listed in the PRs menu dropdown and the footer.

`CommunityPickCandidatesQuery` reads the pull-requests index and returns Community Pick Candidates (see CONTEXT.md):

- `is_open` true, `is_draft` not true, no `labels.keyword` in `github.community_picked.exclude_labels`
- sorted by `?sort=`:
  - `votes` (default): `thumbs_up_count` desc, then `created_at` asc
  - `oldest`: `created_at` asc, then `thumbs_up_count` desc
  - `newest`: `created_at` desc, then `thumbs_up_count` desc
  - Any other value fails validation.
- `?rows=` is the list depth (default 50, validated to 1–10,000 because OpenSearch caps `from + size` at 10,000). The query always fetches from the top. "Show 50 more" links to the next depth; with JavaScript it fetches that URL and appends only the new rows. The button goes at the end of the list.

There's no Filter button. Selects apply on change; the title and author fields apply on Enter or after a 300ms typing pause. With JavaScript the page fetches its own URL and swaps in the results and the dependent filter options, so focus stays in the field; the URL is updated with `replaceState`. Without JavaScript, Enter submits the form. **Clear filters** shows while the title, Area, Component or Author filter is set. It resets those four, keeps the sort and moves focus to the title field. Controls holding a filter get a dark border and a bold value.

Each row (design spec `design/specs/README-community-picked.md`) shows the 👍 count, then two lines: the PR number (links to GitHub, new tab) and title, then outlined Area pills, filled Component pills (prefixes stripped) and Linked Issues. The second line is omitted when a PR has none. Author (linked to their Forger contributor page, `leaderboard.detail`) and age sit in the right column, or on a third line below 700px.

### Filtering by Area / Component

Two GET dropdowns, `?area=Area: …` and `?component=Component: …`, combined with AND (and with the author filter below). Filtered URLs are shareable, and Show 50 more keeps the filters. A PR matches a label when its **Effective Labels** (CONTEXT.md) include it: the PR carries the label itself, or any Linked Issue does.

OpenSearch has no joins, so each request makes three searches:

1. **Facets** — aggregate over all candidates: their `linked_issues` numbers and their own `Area:`/`Component:` labels.
2. **Linked Issue labels** — fetch those issues' labels from the issues index by `_id`.
3. **Page** — candidates, plus one `should` clause per selected label: `labels.keyword` = label, OR `linked_issues` in the issues carrying it.

The dropdown options are the union of steps 1 and 2. They're **dependent**: when an Area is selected, the Component options come from a second facet aggregation over just the candidates matching that Area, and vice versa. That's up to two extra searches. Neither dropdown is narrowed by its own selection, so you can still switch Area freely. The linked issues in a narrowed facet are a subset of step 1's, so step 2's labels already cover them.

Because of this, any Area + Component pair picked from the lists returns at least one candidate. Only a shared link can name a pair that matches nothing, for example after a PR is relabelled or closed. The stale label stays selected, and the page shows "No candidates match these filters." Clearing the filters is done in the controls themselves.

Linked Issue labels are read on every request rather than copied onto PR documents. Retagging an issue shows up after the next issue sync, with no PR re-sync needed.

GitHub label names are unique ignoring case, so labels that differ only in case are treated as one. Renaming a label doesn't bump `updatedAt`, so after a case-only rename the incremental syncs leave the old spelling on documents until the weekly full sync. One more search (a terms aggregation over both indices, with the max `updated_at` per spelling) picks the spelling on the most recently updated document. Options, row pills and Linked Issue labels use that spelling, and the label `term` filter is `case_insensitive`, so a shared link in the old spelling still matches. A rename that changes the words isn't merged; it waits for the weekly full sync.

`area` must start with `Area: ` and `component` with `Component: `; any other value fails validation and never reaches OpenSearch.

### Filtering by title

`?title=<words>` is a text field. It uses `match_bool_prefix` on the already-indexed `title` text field with `operator: and`. Every word must appear in the title, and the last word may be a prefix, so "orphan plug" finds "Exclude orphan plugins…". The match is a filter, not a relevance ranking, so the chosen sort still applies.

A bare number (`41427` or `#41427`, up to 18 digits so it fits a `long`) also matches the PR with that number (`id`), OR'd with the title match, since titles can contain numbers too. A number among other words is title text only.

It narrows the Area, Component and Author options like any other filter. It's validated as a string of at most 200 characters; the value goes to OpenSearch as query text, never as query syntax.

### Filtering by author

`?author=<login>` matches the PR author's GitHub login (`author.keyword`), ignoring case, and ANDs with the label filters. It's a text field with a native `<datalist>` of candidate authors (several hundred, too many for a `<select>`). The facet aggregation also collects `authors`, so author suggestions and label options narrow each other the same way Area and Component do. With all three filters set, each request runs up to three narrowed facet searches; searches narrowed by the same set of filters are shared.

`author` must look like a GitHub login (letters, digits and hyphens, optionally ending in `[bot]`, at most 50 characters).

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

- `app/Http/Controllers/CommunityPickedController.php` — page controller, `rows` validation
- `app/Queries/Dashboard/CommunityPickCandidatesQuery.php` — candidate rule, sort, depth
- `resources/views/communityPicked/` — list view, `_styles`, `_script` (filter-as-you-type, Show 50 more)
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
