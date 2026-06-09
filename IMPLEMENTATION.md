# Magento Forger Homepage — Contributor Call-to-Action

## Goal
Replace the analytics-only homepage (PR/Issue age + over-time charts) with a
call-to-action homepage that recruits developers to contribute to the
Magento 2 open source project (https://github.com/magento/magento2).

The existing site already provides: Issues, PRs, Labels (List All / PRs Without
Component Label), Leaderboard, Employment, and "Login with GitHub" (/auth/github).
Reuse these. Demote the charts to social proof rather than the main content.

## Data (open-issue counts, GitHub, as of 2026-06-09)

### Workflow / contributor-entry labels
| Label                          | Open | Use as |
|--------------------------------|------|--------|
| Issue: ready for confirmation  | 128  | Triage path (low barrier, no code) |
| Issue: Ready for Work          | 20   | "Ready to code" path |
| Issue: needs update            | 19   | "Move issues forward" path |
| CD (Contribution Day)          | 6    | Event/newcomer curation (optional) |
| good first issue               | 0    | DO NOT LINK — empty (102 closed) |
| help wanted                    | 1    | AVOID — nearly empty (93 closed) |

Total open issues in repo: ~1.2k

### Component (Area) labels by open issues — for "contribute by expertise" tiles
| Component              | Open |
|------------------------|------|
| Area: Framework        | 220  |
| Area: Catalog          | 102  |
| Area: Product          | 58   |
| Area: Cart & Checkout  | 56   |
| Area: Order            | 52   |
| Area: Admin UI         | 52   |
| Area: Content          | 49   |
| Area: APIs             | 35   |
| Area: UI Framework     | 27   |
| Area: Account          | 21   |
| Area: Performance      | 20   |
| Area: Import / export  | 16   |
| Area: Tax              | 15   |
| Area: Test framework   | 14   |
| Area: Other Dev Tools  | 14   |
| Area: SEO              | 13   |
| Area: Customer Account | 12   |
| Area: Pricing          | 11   |
| Area: Security         | 8    |
| Area: Shipping         | 8    |
| Area: Payments         | 7    |

Recommendation: feature top 8–10 components as tiles; pull counts dynamically
(the Forger backend already ingests this data) so they stay accurate.

## Pre-encoded GitHub links

### Workflow buckets
- Ready for Work:
  https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Issue%3A+Ready+for+Work%22
- Ready for confirmation:
  https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Issue%3A+ready+for+confirmation%22
- Needs update:
  https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Issue%3A+needs+update%22
- Contribution Day (CD):
  https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3ACD

### Component tiles (pattern: label%3A%22Area%3A+<Name>%22; encode space=+, &=%26, /=%2F)
- Framework:       https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Area%3A+Framework%22
- Catalog:         https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Area%3A+Catalog%22
- Product:         https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Area%3A+Product%22
- Cart & Checkout: https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Area%3A+Cart+%26+Checkout%22
- Order:           https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Area%3A+Order%22
- Admin UI:        https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Area%3A+Admin+UI%22
- Content:         https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Area%3A+Content%22
- APIs:            https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Area%3A+APIs%22
- UI Framework:    https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Area%3A+UI+Framework%22
- Import / export: https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A%22Area%3A+Import+%2F+export%22

## Page structure / sections

1. **Hero** — headline "Help build Magento Open Source", subtext, two CTAs:
   "Find an issue to work on →" (anchor to section 3) and "Login with GitHub"
   (existing /auth/github route).
2. **Why contribute** — 3 cards: Make real impact / Level up / Get recognized
   (link "recognized" to /leaderboard).
3. **Choose how you want to help** — 3 path cards:
   - Ready to code (20) → Ready for Work link
   - Help us triage (128) → ready for confirmation link
   - Move issues forward (19) → needs update link
4. **Contribute by area** — grid of component tiles (name + live count → filtered link).
5. **Momentum** — keep 1–2 existing charts (Pull Requests / Issues over time),
   reframed as social proof; optional "X PRs merged in last 30 days" stat.
6. **First time contributing?** — 3-step onboarding: Contribution Guidelines →
   dev environment setup → claim an issue. Mention "PRs Without Component Label"
   as an easy help task.
7. **Footer CTA** — repeat "Find an issue →" + "Login with GitHub".

## Copy

### Hero
Title: Help build Magento Open Source
Body: Magento powers thousands of stores worldwide — and it's maintained in the
open by developers like you. Pick an issue, open a pull request, and ship a fix
that real merchants will use.
CTAs: [Find an issue to work on →] [Login with GitHub]

### Why contribute (cards)
- Make real impact — Your fix ships to a platform behind thousands of live storefronts.
- Level up — Work on a large, modern PHP codebase alongside experienced maintainers.
- Get recognized — Every merged PR moves you up the contributor leaderboard.

### Choose how you want to help
- 🛠 Ready to code — N issues are confirmed, prioritized, and waiting for a developer. [Browse "Ready for Work" →]
- 🔍 Help us triage — N reported issues need someone to reproduce and confirm them. No fix required — great first step. [Browse issues awaiting confirmation →]
- 💬 Move issues forward — N issues are stalled waiting for more detail. [Browse issues needing an update →]

### Contribute by area
Heading: Pick your area
Sub: Jump straight to open issues in the part of Magento you know best.
(Tiles: "<Component> — <N> open" linking to the filtered issue list.)

### First time contributing?
New here? Start in three steps:
1. Read the Contribution Guidelines
2. Set up your development environment
3. Claim an issue above and open your first PR
Tidy-up win: help by labeling PRs that are missing a component.

## Implementation notes
- Counts should be fetched dynamically from the same GitHub data the Forger
  backend already ingests, so tiles/paths never go stale. Static values above
  are a fallback for a first ship.
- DO NOT surface "good first issue" / "help wanted" — currently empty.
- Match existing site styling (orange #E8662? header, white cards, rounded
  corners). Reuse existing nav and the /auth/github login button.
- Section 3 cards and section 4 tiles can share one card component.

## Reference links
- Repo issues: https://github.com/magento/magento2/issues
- All labels:  https://github.com/magento/magento2/labels
- Leaderboard: https://forger.magento-opensource.com/leaderboard
- Contribution guidelines: https://github.com/magento/magento2/blob/2.4-develop/.github/CONTRIBUTING.md

---

## Resolved decisions (grill session, 2026-06-09) — IMPLEMENTED

These supersede the exploratory notes above where they conflict.

1. **Charts** — keep exactly **one** chart (PRs Over Time) in §5 Momentum. Both *Age
   Over Time* charts deleted from `/`. The `github-stats` include was trimmed to the
   `prChart` block only, and the issue aggregation was removed from `WelcomeController`.
2. **Counts** — live, **cached 1h**, reusing the existing `OpenLabelsByIssueQuery`.
   Wrapped in `HomepageCountsService`, which caches **only on success** so a transient
   OpenSearch failure degrades to an empty map instead of poisoning the cache.
3. **Area tiles** — **curated 10-name allowlist** (`config/homepage.php`), live counts,
   any label resolving to **0 skipped** at render. (Sidesteps the dirty `Component:` /
   lowercase `area` prefixes in the index.)
4. **GitHub URLs** — **generated** from the label string via
   `App\Helpers\GitHubLinkHelper::issueLabelUrl()` (reads `config('github.repo')`).
   The hand-encoded "Pre-encoded GitHub links" section above is now reference only —
   nothing is hand-encoded. Config lives in `config/homepage.php`.
5. **Card** — one parameterized `<x-issue-card>` shared by path cards and area tiles;
   the whole card is an `<a>`.
6. **Failure / empty state** — cards always render; a missing count drops the pill only;
   the label query can never 500 `/`. The sync banner is kept for the empty-index dev case.
7. **Count placement** — count is a discrete **pill** (`N open`), never woven into prose;
   §3 blurbs rewritten count-free so the degrade path stays grammatical.
8. **Scope** — all 7 sections in one PR + one feature test (`WelcomeControllerTest`:
   renders counts; survives label-query failure; still 200 on the degrade path).
9. **Auth CTA** — when authenticated, the secondary CTA swaps "Login with GitHub" →
   "View the leaderboard →" (hero + footer).
10. **Onboarding §6 step 2** — links to the developer.adobe.com contributor guide; all
    outbound URLs centralized in `config/homepage.php`.

**Out of scope (noted, not built):** the optional "X PRs merged in last 30 days" stat
(chose the chart over it); cleanup of the `Component:` / lowercase `area` dirty prefixes
(the allowlist avoids them).

**Files:** `config/homepage.php` (new), `app/Helpers/GitHubLinkHelper.php` (new),
`app/Services/HomepageCountsService.php` (new), `app/Http/Controllers/WelcomeController.php`,
`resources/views/welcome.blade.php`, `resources/views/components/issue-card.blade.php` (new),
`resources/views/components/charts/github-stats.blade.php`,
`tests/Feature/Http/Controllers/WelcomeControllerTest.php` (new).

**Note:** this is a Laravel app — style tooling is **Pint** (`vendor/bin/pint`), not
php-cs-fixer/phpcbf.
