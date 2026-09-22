# Forger leaderboard — handoff

Two things in this folder.

**`Leaderboard prototype.html`** — open it in any browser. No server, no build, works offline.
It is the visual reference: every approved design, newest first, each with an id badge
(`21a`, `19b`, `24d`). The specs cite those ids.

**`specs/`** — ten markdown documents. Start with `specs/README.md`; it opens with an index
mapping each spec to the prototype ids it describes.

## Reading order

1. `README.md` — type scale, tokens, capitalisation, the contributor table.
2. `README-header.md` — masthead and footer; they frame every other page.
3. `README-leaderboard-pages.md` — the three boards. The longest document, and the one that
   carries three site-wide rules: focus rings, hover states, and narrow widths.
4. Everything else as you build it.

## What the prototype is not

It is a design reference written in HTML, not production code. Forger is Laravel/Blade +
Bootstrap; reproduce the specs in that codebase using its own template and CSS conventions.
Do not port the markup.

The screenshots in `specs/` are the current live pages, kept for before-and-after comparison.
