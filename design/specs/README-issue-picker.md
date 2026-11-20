# Handoff: Issue picker

## Overview
A page for finding an issue to work on. A contributor narrows the open issues by label (Area, then Component,
as dependent dropdowns, plus Progress and Issue status), then removes any issue that is already taken: assigned
to someone, or already has a PR. The result is a short list of issues a person can open and claim.

Reached from the Issues menu as **Pick an Issue** (AP title case, per `README.md`, *Capitalisation*: the article "an" stays lowercase), beside *By Month*. Route name `issues.pick`, path `/issues/pick`.

Approved type direction, as elsewhere: **Libre Franklin (sans) + Martian Mono (mono)**.

## Data
Each filter is specified against the data it reads, all from the `github-issues` and `github-pull-requests` indexes:

| Filter | Source |
|---|---|
| Open issues only | issue `is_open` |
| Area label | issue `labels` |
| Component label | issue `labels` |
| Progress label | issue `labels` starting with "progress", ignoring case |
| Issue label | issue `labels` starting with "issue", ignoring case |
| Has a PR | `linked_issues` on open PR documents, plus `closed_by_merged_pr` on the issue (see *Has PR*) |
| Assigned | issue `assignees` |
| Sort: age, comment count | issue `created_at`, `comments_count` |

There is no State control: the page lists open issues only.

The issue and PR syncs fetch up to 50 labels per item (`labels(first: 50)`). It was 10, which dropped labels from
issues that carry more (#27072 has 13).

## About the design files
`../MageForger.html` is a design reference in HTML, not production code. The prototype is option `31b`, turn 31 in the `MageForger` canvas.
Reproduce the spec in the Laravel/Blade + Bootstrap codebase using its conventions. Do not port the markup.

## Fidelity
**High-fidelity** for type, colour, spacing, and the filter behaviour. Result rows are placeholders in the prototype;
real data varies in length.

---

## Page frame

Standard site chrome. Dark header, white title block, dark footer.

- Title block: `padding: 26px 36px 24px`, `border-bottom: 1px solid #e6e7ea`. H1 "Pick an Issue", Libre Franklin 700,
  40px, `letter-spacing: -.032em`, `line-height: 1.05`.
- Body: `padding: 24px 36px 0`.

## Intro paragraph

Libre Franklin 400, 14.5px, `line-height: 1.65`, colour `#3c4148`, `max-width: 660px`.
"Narrow the open issues by area and component. Pick one, then open it on GitHub to claim it." Copy is final.

## Filter panel

Two rows under the intro, `margin-top: 18px`, no bordered box: `padding: 14px 0; border-bottom: 1px solid #e6e7ea`.
Each row is `display: flex; flex-wrap: nowrap; align-items: center; gap: 10px`, with `margin-top: 10px` between them.

- Row 1: Area, Component, Progress, Issue.
- Row 2: Sort, then the two chips (Include assigned, Include with PRs).

At narrow widths the rows wrap (see *Narrow widths*).

The dropdowns carry no visible label above them; the placeholder option names them ("Any area", "Any component",
"Any progress", "Any issue status"). Each has a `<label>` that is visually hidden but read by screen readers (see
*Accessibility*). Option text omits the prefix up to ": " ("Catalog", "Product Grid", "Confirmed"). Options show no counts.
Progress and Issue option text is AP title case (`README.md`, *Capitalisation*), whatever case GitHub uses:
"ready for dev" → "Ready for Dev", "Format is not valid" → "Format Is Not Valid", "PR Created" stays "PR Created"
(all-caps words are kept). Area and Component names stay as GitHub spells them.

### Dropdowns (Area, Component, Progress, Issue, Sort)

- Field: `width: 200px; padding: 9px 34px 9px 12px`, `1px solid #d5d8dc`, `border-radius: 7px`, Libre Franklin 500, 14px, `#15171b`,
  background `#ffffff`. Native `<select>` styled to match.
- Caret: the site-standard SVG chevron, 7×5px, colour `#6b7178` on light controls (`README.md`, *Dropdown caret*).
- Hover: `border-color: #15171b`. Focus: site focus ring.
- While the placeholder option "Any area" / "Any component" is selected, the field text is `#9aa3ae`.
- Options are sorted by name, ignoring case ("alpha" before "Zeta"), as on Community-Picked PRs.
- **The four label dropdowns all depend on each other.** Each lists only the labels on open issues the Include chips
  leave in that also match every *other* label selection. Picking an Area narrows Component, Progress and Issue;
  picking a Progress value narrows Area, Component and Issue; and so on. So any combination picked from the lists has
  at least one issue, and turning a chip on can add options back. The lists use the labels as they are, with no
  separate mapping file (confirmed).
- **Conflicts clear the lowest priority first.** If selections stop fitting together (a chip was turned off, or a shared
  link combines labels no issue has), the lowest-priority one is cleared and the lists recomputed, until every
  selection is listed. Priority: Area, then Component, then Progress, then Issue. A selection that still fits is kept.
- With nothing to list, a dropdown shows its empty text in `#5d636c` and is disabled: "No areas", "No components for
  this area" (or "No components" with no Area selected), "No progress labels", "No issue labels".

**Area** — the first dropdown: labels named `Area: …`. "Any area" is the default.

**Component** — the second: labels named `Component: …`.

**Progress** and **Issue** — the third and fourth. Progress lists labels whose names start with "progress", Issue those
starting with "issue", both ignoring case ("Progress: ready for dev", "Issue: Confirmed").

Progress options follow the workflow, not the alphabet: Ready for Grooming, Ready for Dev, Dev in Progress, PR Created,
PR in Progress, Done (matched ignoring case). Any other Progress label comes after these, by name. Issue options stay
sorted by name.

**Sort** — "Oldest first" (default), "Newest first", "Most comments" (ties: oldest first). It always has a value, so it has
no placeholder option.

### Toggles (Filters, not dropdowns)

On the same row as the dropdowns. Each toggle is a two-state chip:

- Chip: `padding: 7px 12px`, `border-radius: 7px`, Libre Franklin 600, 13.5px. Off: `1px solid #d5d8dc`, `#15171b`, background
  `#ffffff`. On: background `#15171b`, text `#ffffff`, border `#15171b`, with a leading check mark (SVG, `aria-hidden`).
- Hover: off → `border-color: #15171b`; on → background `#2a2e34`.

The chips each **add back** a kind of taken issue. Both are off by default, so taken issues stay out of the list:

| Chip | Tooltip | When on, adds back |
|---|---|---|
| Include assigned | "Include issues that are already assigned" | issues with any assignee |
| Include with PRs | "Include issues that already have a PR" | issues with a PR that closes them (see *Has PR*) |

The tooltip is the browser's native one (the button's `title`): it shows on pointer hover and screen readers read it as
the button's description. It does not show on keyboard focus or touch.

The default state: **Include assigned** off, **Include with PRs** off, so assigned issues and issues with a PR are hidden.
A reader who wants to see taken work turns a chip on.


### Has PR — definition

"Has a PR" means an open (draft or not) or merged PR whose `closingIssuesReferences` includes the issue, so the PR says it
fixes the issue. PRs that mention the issue without closing it do not hide it; the picker does not use cross-reference data.

Closed-without-merge PRs do not count. An issue whose only PR was closed unmerged stays in the list.

Sources, and why:

- **Open PRs** — the `linked_issues` of open PRs in the PR index. The reactions sync re-reads every open PR every
  15 minutes, so a new or closed PR shows within about 15 minutes.
- **Merged PRs** — `closed_by_merged_pr` on the issue, set by the PR sync.
- **Not used: the issue's own `has_closing_pr`.** Opening a PR that closes an issue does not change the issue's
  `updatedAt` on GitHub (checked 2026-11-17 on #41875 and #41870), so the incremental issue sync does not re-read the
  issue and a new PR is missed until the weekly full sync. Closing a PR unmerged probably behaves the same, leaving
  the issue hidden; that case has not been checked.

## Results

Below the filter panel, `margin-top: 24px`.

**Result count** — Martian Mono 9.5px, uppercase, `letter-spacing: .06em`, `#6b7178`:
"Showing 14 of 412 open issues". "412" is every open issue, before the filters and chips; "14" is after.

**Result table** — one row per issue, full width, `border-collapse: collapse`, `table-layout: fixed`, so columns keep their
widths as the filters change. Cells: `padding: 12px 10px 12px 0;
border-bottom: 1px solid #f0f1f3; vertical-align: baseline`. Hover `background: #faf9f7`. Long text wraps inside its column.

Column header row, Martian Mono 9px, uppercase, `letter-spacing: .06em`, `#6b7178`, `padding: 8px 10px 8px 0`,
`border-bottom: 1px solid #e6e7ea`: "Issue", "Area", "Component", "Opened", "Comments" (right-aligned).

| Column | Width |
|---|---|
| Issue | the rest |
| Area | 170px |
| Component | 190px |
| Opened | 110px |
| Comments | 90px |

- **Issue** — the issue number, then the title, side by side with a 6px gap. Number: Martian Mono 500, 12px, `#ee6524`,
  underlined with a 2px offset; it turns `#c74e16` while the row is hovered. Title: Libre Franklin 600, 14px, `#15171b`.
  A long title wraps under itself, never under the number.
  Under the title, aligned with it, the issue's Progress and Issue labels as pills, full name in AP title case (the word
  after the colon starts the title: "Progress: Ready for Dev"), sorted ignoring case: `padding: 4px 8px`, `border-radius: 5px`, Libre Franklin 11.5px `#15171b`, `gap: 5px`,
  `margin-top: 7px`. Background tells the two apart, using the Community-Picked pill pair: Issue labels `#e6e7ea`
  (grey, the area pill), Progress labels `#fbddcb` (orange, the component pill). Shown at all widths; other labels are
  not shown.
- **Area** — Libre Franklin 14px, `#3c4148`, the label name without the "Area:" prefix. Several are comma-separated.
- **Component** — Libre Franklin 14px, `#3c4148`, the label name without the "Component:" prefix. Several are comma-separated.
- **Opened** — Martian Mono 11px, `#5d636c`, format "17 Aug 2026".
- **Comments** — Martian Mono 11px, `#5d636c`, right-aligned.

Rows show 25 at a time with the same pagination as the boards: "Show 25 more" (`README-leaderboard-pages.md`, *Pagination*).
It appears only while more rows remain, and it appends the next 25 below. There is no count statement beside it; the
result count above already gives the total.

**Pick** — no per-row button. The row is the action: the link on the number and title stretches over the whole row and
opens the issue on GitHub (new tab, `rel="noopener"`), where claiming happens. The intro says so, so nobody expects an
in-site claim.

## Filter behaviour

- Filters apply on change. There is no Apply button.
- Changing any filter resets pagination to 25 rows. If the reader has scrolled past the filter panel, the page scrolls back
  so the panel is at the top; otherwise it does not move.
- The entire filter state lives in the URL:
  `?area=Area%3A%20Catalog&component=…&progress=Progress%3A%20ready%20for%20dev&issue=Issue%3A%20Confirmed&sort=oldest&showAssigned=1&showPr=1`.
  `sort` is always present; `area`, `component`, `progress` and `issue` only when set; `showAssigned` and `showPr`
  only when their chip is on. A cleared value is dropped from the URL. "Show 25 more" adds `rows=50`, `rows=75`, and so on.
  Reloads and shared links return the same list.
- **Reset** — a text link, Libre Franklin 500, 13.5px, `#ee6524`, underlined, at the right end of the result-count line:
  "Reset filters".
  It restores the defaults above.

## States

- **Results found**: as above.
- **No results**: the result-count line stays; the table is replaced by one block, `padding: 40px 0 44px`, `border-bottom: 1px solid #f0f1f3`:
  - Line 1, Libre Franklin 500, 15px, `#15171b`: "No open issues match these filters."
  - Line 2, Libre Franklin 14px, `line-height: 1.6`, `#5d636c`, `margin-top: 6px`: "Try turning on an Include filter, or choose a different area." The
    "Reset filters" link is repeated here in `#ee6524`.
- **Index missing** (dev only): the site's standard data-missing notice, with no filter panel or count.
- **Taken-issue preview** — not built. Was optional and off by default: a count line beneath the result count,
  "38 hidden as assigned or with a PR".
- **Stale data** — not built. The Stats-page caption needs the last successful sync time, and the syncs do not record one yet.

## Narrow widths

Drawn at 420px as option `31c` in the `MageForger` canvas. Applies at 700px and below.

- The header is the site's mobile bar: hairline, brand mark and "Forger", the account chip as the avatar only, and the
  hamburger toggler.
- The filter rows wrap: Area, Component, Progress, Issue and Sort take a row each at full width (10px vertical
  padding), then the chips share a row.
- The result-count line wraps, with "Reset filters" staying on the right of it.
- The table drops the Component and Opened columns; Issue, Area (110px), and Comments (76px) stay, and the Component name
  moves under the title, aligned with it, in Libre Franklin 12px `#3c4148`, `margin-top: 4px`.
- "Show 25 more" is a full-width button.
- The H1 is 28px (at 560px and below, as on the boards).

## Hover states

Per `README-leaderboard-pages.md`, *Hover states*: 120ms ease-out, no size or position changes.

| Element | Rest | Hover |
|---|---|---|
| Result row | no background | `#faf9f7`; the issue number turns `#c74e16` |
| Dropdown | `#d5d8dc` border | `#15171b` border |
| Chip, off | `#d5d8dc` border | `#15171b` border |
| Chip, on | `#15171b` fill | `#2a2e34` fill |
| Text link ("Reset filters") | `#ee6524` | `#c74e16` |

## Focus rings — site-wide

**2px `#f26322` outline, `outline-offset: 2px`**, on `:focus-visible` only. Applies to dropdowns, chips, rows,
"Show 25 more", and Reset filters. Rows: focus sits on the row link, and the ring is drawn round the whole row it covers.

## Accessibility

- Each dropdown has a `<label>` ("Area", "Component", "Progress", "Issue", "Sort") that is visually hidden, so it is
  named without a visible label.
- Chips are real `<button type="button" aria-pressed="true|false">` elements. Their label states what the chip adds
  ("Include assigned"), so the on state reads "Include assigned, pressed". The tooltip text is the button's description.
- When a filter change clears Area, Component, Progress or Issue, a polite live region announces it before the count:
  "Area cleared." / "Component cleared." / "Progress cleared." / "Issue cleared.", one per cleared dropdown.
- After each filter change the same live region announces the result count: "Showing 14 of 412 open issues."
  After "Show 25 more" it announces "25 more shown." and focus moves to the first new row.
- Result rows are full links. The link's accessible name is the number, the title, the Progress/Issue pills, and (at
  narrow widths) the component line under the title; the Area, Component, Opened and Comments cells are read in order.

## Out of scope

- Claiming an issue from inside the site. Claiming stays on GitHub.
- Saved filter presets or notifications when new matching issues appear.
- Filters by milestone, author, or PR review state.
- Ranking issues by difficulty or estimating effort.
