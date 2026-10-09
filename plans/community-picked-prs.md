# Plan: Community-picked PRs

> Source PRD: https://github.com/magentoassociation/magento-forger/issues/83

## Architectural decisions

Durable decisions that apply across all phases:

- **Routes**:
  - `GET /issues/by-month` → `issues.byMonth`
  - `GET /prs/by-month` → `prs.byMonth`
  - `GET /prs/community-picked` → `prs.communityPicked` (public, no auth)
  - Query params on community-picked: `area`, `component`, `page`
  - Old `/issuesByMonth` and `/prsByMonth` removed, no redirects
- **Schema** (PR documents in the pull-requests index, dynamic mapping, no migration):
  - `linked_issues`: list of issue numbers from `closingIssuesReferences`
  - `thumbs_up_count`: total 👍 reactions on the PR body
- **Domain terms**: Community Pick Candidate, Linked Issue, Effective Labels (see PRD / CONTEXT.md)
- **Candidate rule**: open, non-draft, none of `github.community_picked.exclude_labels` (default `Release Line: 2.5`, `Project: Community Picked`)
- **Ranking**: `thumbs_up_count` desc, then `created_at` asc; 50 per page
- **Voting**: link-out to the PR on GitHub only; no writes to GitHub, no vote storage, no OAuth scope change
- **Label join**: resolved at query time (issues index → issue numbers → PRs by own labels OR `linked_issues`); never denormalised onto PR docs
- **Navigation**: main menu auto-groups by route-name prefix; `prs.*` routes form the "PRs" dropdown
- **Testing**: mock the OpenSearch client via the container, as existing tests do; no live OpenSearch

---

## Phase 1: Consistent by-month URLs

**User stories**: 30

### What to build

Move the Issues by month and PRs by month pages to `/issues/by-month` and `/prs/by-month`. Rename their route names to `issues.byMonth` and `prs.byMonth`. Update every reference (header titles, footer links, homepage history links, tests). Remove the old paths outright.

### Acceptance criteria

- [x] `/issues/by-month` and `/prs/by-month` render the existing pages
- [x] `/issuesByMonth` and `/prsByMonth` return 404
- [x] Header titles, footer links, and homepage "full history" links still work
- [x] PRs and Issues entries still appear in the main menu
- [x] Existing by-month and homepage tests pass with the new route names
- [x] Analytics feature doc shows the new paths

---

## Phase 2: Tracer: unfiltered candidate list

**User stories**: 1, 2, 3, 4, 5, 6, 14, 15, 16, 18, 19, 21, 22, 23, 24, 25, 26

### What to build

End-to-end path from GitHub to page, with no filtering yet. The main PR sync starts storing `thumbs_up_count` (👍 only) and `linked_issues` on PR documents. A public `/prs/community-picked` page lists Community Pick Candidates, ranked by 👍 then oldest first, paginated at 50. Each row shows the 👍 count (linking to the PR on GitHub in a new tab), #number + title, linked issues, author, and relative age.

The excluded labels come from config. The page appears under the PRs dropdown, has the header title "Community-picked PRs", and is linked from the footer. A missing index shows the standard "data missing" placeholder. Add the feature doc and the three CONTEXT.md terms.

Demoable after one full PR sync.

### Acceptance criteria

- [x] PR documents contain `thumbs_up_count` and `linked_issues` after a PR sync
- [x] Page lists only open, non-draft PRs without any excluded label
- [x] Changing the config exclude list changes which PRs appear, with no code change
- [x] Sort is 👍 desc, ties broken by oldest `created_at`
- [x] Pagination at 50 per page works
- [x] 👍 count links to the PR URL, opening a new tab
- [x] Rows show number, title, linked issues, author, relative age
- [x] Page is reachable without login
- [x] "Community Picked" appears in the PRs dropdown; header shows "Community-picked PRs"; footer links to it
- [x] Missing index renders the placeholder, not an error
- [x] Tests: candidate query (exclusions, drafts, sort, pagination), PR document fields, controller rendering + placeholder
- [x] Feature doc added; CONTEXT.md has Community Pick Candidate, Linked Issue, Effective Labels

---

## Phase 3: Effective Labels and Area/Component filter

**User stories**: 7, 8, 9, 10, 11, 12, 13, 17

### What to build

Compute Effective Labels (the PR's own labels plus its Linked Issues' labels, read from the issues index at query time) and show the Area/Component ones on each row. Add a GET filter form with Area and Component dropdowns, combined with AND. A PR matches a label if it carries the label itself or any Linked Issue does.

Dropdown options are aggregated from the current candidate set only, so no option ever returns an empty list. Filter params are validated, and filtered views have shareable URLs.

### Acceptance criteria

- [x] Rows show Effective Area/Component labels
- [x] Filtering by a label set only on a linked issue returns that PR
- [x] Filtering by a label set only on the PR returns that PR
- [x] Area + Component together return only PRs matching both
- [x] Dropdowns list only `Area:` / `Component:` labels present on current candidates
- [x] Retagging an issue is reflected after the next issue sync, with no PR re-sync needed
- [x] Filtered URL reproduces the same results when shared
- [x] Invalid params are rejected, not passed to OpenSearch
- [x] Pagination preserves active filters
- [x] Tests: union matching (PR only / issue only / both), AND, facets from candidates only, empty result, controller param handling
- [x] Feature doc describes the filter and the query-time join

---

## Phase 4: Fresh votes via `SyncGitHubPrReactions`

**User stories**: 20, 27, 28, 29

### What to build

First, verify the PRD assumption: react to a PR and compare its `updatedAt` before and after.

- **If reactions bump `updatedAt`**: the existing 15-minute incremental PR sync already keeps counts fresh. Drop the light sync and record why in the feature doc.
- **Otherwise**: add `sync:github:pr-reactions`. It pages open PRs (100 per page), fetching only the number, timestamps, draft flag, 👍 total and closing issue references. It partially updates `thumbs_up_count`, `linked_issues` and `is_draft` on existing PR documents, with a plain update and no upsert, so unindexed PRs are skipped. It reuses the existing sync engine and concern, honours `github.history_start`, is isolatable, and is scheduled every 15 minutes, in the background, without overlap.

### Acceptance criteria

- [x] `updatedAt` behaviour verified and recorded in the feature doc (reactions do **not** bump it; built)
- [x] (If built) a new 👍 shows on the page within one 15-minute cycle
- [x] (If built) PRs not yet indexed are skipped; no title-less documents created
- [x] (If built) command exits non-zero when a page fails
- [x] (If built) scheduled every 15 min, `withoutOverlapping`, background
- [x] (If built) tests: command pages and forwards nodes, error exit code, partial update uses no upsert, schedule entry
- [x] (If built) GitHub sync doc lists the new job in its schedule table and key files
