<link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;500;600;700&family=Martian+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    .lb {
        --font-sans: 'Libre Franklin', system-ui, sans-serif;
        --font-mono: 'Martian Mono', ui-monospace, monospace;
        font-family: var(--font-sans);
        color: #15171b;
    }

    .lb-intro {
        max-width: 660px;
        margin: 0 0 10px;
        font-size: 14.5px;
        line-height: 1.65;
        color: #3c4148;
        text-wrap: pretty;
    }

    .lb-tallied {
        display: inline-block;
        padding: 0;
        border: 0;
        background: none;
        font-family: var(--font-sans);
        font-size: 14.5px;
        font-weight: 500;
        color: #ee6524;
        text-decoration: underline;
        text-underline-offset: 2px;
        cursor: pointer;
    }
    .lb-tallied:hover { color: #c74e16; }

    /* Tabs — restyle the shared Bootstrap nav-tabs within the leaderboard (#21). */
    .lb .nav-tabs {
        gap: 2px;
        margin: 22px 0 0;
        border-bottom: 1px solid #e6e7ea;
    }
    .lb .nav-tabs .nav-item { margin-bottom: -1px; }
    .lb .nav-tabs .nav-link {
        padding: 9px 14px;
        border: 0;
        border-radius: 7px 7px 0 0;
        font-family: var(--font-sans);
        font-size: 14px;
        font-weight: 500;
        color: #5d636c;
        transition: color 120ms ease-out;
    }
    .lb .nav-tabs .nav-link:hover { color: #15171b; border: 0; }
    .lb .nav-tabs .nav-link.active {
        font-weight: 600;
        color: #15171b;
        background: #fff;
        border: 1px solid #e6e7ea;
        border-bottom-color: #fff;
    }

    /* Month selector chips. */
    .lb-months {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 7px;
        margin: 16px 0 0;
    }
    /* Detail page (#18a): outlined chips under the view toggle. */
    .lb-detail .lb-month {
        padding: 7px 12px;
        font-family: var(--font-mono);
        font-size: 11px;
        font-weight: 500;
        border-radius: 8px;
        border: 1px solid #dfe1e4;
        background: #fff;
        color: #ee6524;
        text-decoration: none;
    }
    .lb-detail .lb-month:hover { border-color: #ee6524; color: #ee6524; }
    .lb-detail .lb-month.active {
        background: #15171b;
        border-color: #15171b;
        color: #fff;
    }

    /* Monthly board chips (21c): filled, uppercase, month-only unless selected. */
    .lb:not(.lb-detail) .lb-month {
        padding: 6px 11px;
        font-family: var(--font-mono);
        font-size: 10px;
        font-weight: 700;
        border: 0;
        border-radius: 6px;
        background: #f7f5f2;
        color: #3c4148;
        text-transform: uppercase;
        letter-spacing: .04em;
        text-decoration: none;
        transition: background 120ms ease-out;
    }
    .lb:not(.lb-detail) .lb-month:hover { background: #ece9e4; }
    .lb:not(.lb-detail) .lb-month.active { background: #15171b; color: #fff; }
    .lb-month-all {
        font-family: var(--font-mono);
        font-size: 10px;
        color: #6b7178;
        text-decoration: none;
        padding: 6px 4px;
    }
    .lb-month-all:hover { color: #15171b; }

    /* #21 board: control strip, four-column flex rows, activity + pagination.
       Flush to the page gutter (no card box); dividers do the grouping. */

    /* Control strip */
    .lb-strip {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        padding: 16px 0 14px;
    }
    .lb-pop {
        font-family: var(--font-mono);
        font-size: 9.5px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }
    .lb-search {
        position: relative;
        margin-left: auto;
        display: flex;
        align-items: center;
    }
    .lb-search-ico {
        position: absolute;
        left: 11px;
        font-size: 12px;
        color: #9aa3ae;
        pointer-events: none;
    }
    .lb-search-input {
        min-width: 210px;
        padding: 7px 30px 7px 30px;
        border: 1px solid #d5d8dc;
        border-radius: 7px;
        font-family: var(--font-sans);
        font-size: 13.5px;
        color: #15171b;
        background: #fff;
    }
    .lb-search-input::placeholder { color: #9aa3ae; }
    .lb-search-clear {
        position: absolute;
        right: 6px;
        border: 0;
        background: none;
        padding: 4px 6px;
        font-family: var(--font-sans);
        font-size: 13px;
        line-height: 1;
        color: #9aa3ae;
        cursor: pointer;
    }
    .lb-search-clear:hover { color: #15171b; }

    /* Jump to my rank */
    .lb-jump {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 12px;
        border-radius: 7px;
        background: #15171b;
        text-decoration: none;
        transition: background 120ms ease-out;
    }
    .lb-jump:hover { background: #2a2e34; }
    .lb-jump-av { border-radius: 5px; flex: none; }
    .lb-jump-label {
        font-family: var(--font-sans);
        font-weight: 600;
        font-size: 13px;
        color: #fff;
    }
    .lb-jump-rank {
        font-family: var(--font-mono);
        font-size: 11px;
        color: #9aa3ae;
    }
    .lb-jump--out {
        background: none;
        border: 1px solid #d5d8dc;
        transition: border-color 120ms ease-out;
    }
    .lb-jump--out:hover { background: none; border-color: #15171b; }
    .lb-jump--out .lb-jump-label { color: #3c4148; font-weight: 500; }

    /* Column header + rows share the flex column model. */
    .lb-colhead,
    .lbr {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .lb-colhead {
        padding-bottom: 8px;
        border-bottom: 1px solid #e6e7ea;
        font-family: var(--font-mono);
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }
    .lb-col-rank { width: 42px; }
    .lb-col-name { flex: 1; }
    .lb-col-act { width: 130px; text-align: right; }
    .lb-col-score { width: 70px; text-align: right; }

    .lbr {
        padding: 10px 0;
        border-bottom: 1px solid #f0f1f3;
        transition: background 120ms ease-out;
        outline: none;
    }
    .lbr.is-beyond { display: none; }
    .lbr:hover { background: #faf9f7; }
    .lbr.is-jumped {
        background: #fff6f1;
        box-shadow: inset 3px 0 0 #f26322;
        transition: background 400ms ease-out, box-shadow 400ms ease-out;
    }
    .lbr-rank {
        width: 42px;
        flex: none;
        font-family: var(--font-mono);
        font-size: 13px;
        color: #3c4148;
    }
    .lbr-rank.is-top { font-weight: 700; color: #15171b; }

    .lbr-avatar {
        position: relative;
        flex: none;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        background: #e9eaed;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: box-shadow 100ms ease;
    }
    .lbr-avatar:hover { box-shadow: 0 0 0 2px #ee6524; }
    .lbr-avatar-initials {
        font-family: var(--font-mono);
        font-size: 8.5px;
        color: #4c525a;
    }
    .lbr-avatar img {
        position: absolute;
        inset: 0;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        object-fit: cover;
    }

    .lbr-id {
        flex: 1;
        min-width: 0;
        display: flex;
        align-items: baseline;
        gap: 8px;
    }
    .lbr-name {
        font-family: var(--font-sans);
        font-size: 14.5px;
        font-weight: 600;
        color: #15171b;
        text-decoration: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: color 120ms ease-out;
    }
    .lbr-name:hover { color: #ee6524; }
    .lbr-handle {
        flex: none;
        font-family: var(--font-mono);
        font-size: 10px;
        color: #6b7178;
    }
    .lbr-activity {
        width: 130px;
        flex: none;
        text-align: right;
        font-family: var(--font-mono);
        font-size: 10px;
        color: #6b7178;
        white-space: nowrap;
    }
    .lbr-activity a {
        color: #ee6524;
        text-decoration: none;
        border-bottom: 1px solid rgba(238, 101, 36, .4);
    }
    .lbr-activity a:hover { border-bottom-color: #ee6524; }

    .lb-score-cell {
        width: 70px;
        flex: none;
        display: flex;
        justify-content: flex-end;
        position: relative;
    }
    .lb-score {
        font-family: var(--font-mono);
        font-size: 14px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: #15171b;
    }
    .lb-score-has-tip {
        cursor: help;
        border-bottom: 1px dotted #9aa0a8;
        padding-bottom: 1px;
    }

    .lb-tip {
        position: absolute;
        top: calc(100% + 9px);
        right: 0;
        z-index: 20;
        display: flex;
        flex-direction: column;
        gap: 5px;
        background: #15171b;
        color: #fff;
        border-radius: 9px;
        padding: 12px 16px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .22);
        width: max-content;
        max-width: 380px;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transition: opacity 100ms ease;
    }
    .lb-score-cell:hover .lb-tip,
    .lb-score-cell:focus-within .lb-tip {
        opacity: 1;
        visibility: visible;
    }
    .lb-tip-line {
        display: flex;
        align-items: baseline;
        gap: 10px;
        font-size: 13px;
        line-height: 1.35;
        white-space: nowrap;
    }
    .lb-tip-label { flex: 1; color: #e3e5e8; }
    .lb-tip-count {
        font-family: var(--font-mono);
        font-size: 9.5px;
        color: #9aa3ae;
    }
    .lb-tip-pts {
        font-family: var(--font-mono);
        font-size: 10px;
        font-weight: 700;
        color: #fff;
        font-variant-numeric: tabular-nums;
    }
    .lb-tip-arrow {
        position: absolute;
        bottom: 100%;
        right: 52px;
        width: 0;
        height: 0;
        border-left: 7px solid transparent;
        border-right: 7px solid transparent;
        border-bottom: 7px solid #15171b;
    }

    /* Pagination */
    .lb-pager {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        padding: 20px 0 30px;
    }
    .lb-more {
        padding: 9px 18px;
        border: 1px solid #d5d8dc;
        border-radius: 7px;
        background: #fff;
        font-family: var(--font-sans);
        font-size: 13.5px;
        font-weight: 600;
        color: #15171b;
        text-decoration: none;
        transition: border-color 120ms ease-out;
    }
    .lb-more:hover { border-color: #15171b; }
    .lb-count-stmt {
        font-family: var(--font-mono);
        font-size: 9.5px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }

    /* No-results / whole-board failure block */
    .lb-empty {
        padding: 40px 0 44px;
        border-bottom: 1px solid #f0f1f3;
    }
    .lb-empty-1 {
        margin: 0;
        font-family: var(--font-sans);
        font-weight: 500;
        font-size: 15px;
        color: #15171b;
    }
    .lb-empty-2 {
        margin: 6px 0 0;
        font-family: var(--font-sans);
        font-size: 14px;
        line-height: 1.6;
        color: #5d636c;
    }
    .lb-empty-2 a { color: #ee6524; text-decoration: underline; text-underline-offset: 2px; }
    .lb-empty-2 a:hover { color: #c74e16; }

    /* Focus rings — site-wide, on :focus-visible only. */
    .lb .lb-search-input:focus-visible,
    .lb .lb-search-clear:focus-visible,
    .lb .lb-jump:focus-visible,
    .lb .lb-month:focus-visible,
    .lb .lb-month-all:focus-visible,
    .lb .lb-more:focus-visible,
    .lb .nav-tabs .nav-link:focus-visible,
    .lb .lbr-name:focus-visible,
    .lb .lbr-avatar:focus-visible,
    .lb .lbr-activity a:focus-visible,
    .lb .lb-tallied:focus-visible,
    .lb .lb-score:focus-visible {
        outline: 2px solid #f26322;
        outline-offset: 2px;
    }
    .lb .lb-search-input:focus-visible { border-color: #d5d8dc; }

    .visually-hidden {
        position: absolute !important;
        width: 1px; height: 1px;
        padding: 0; margin: -1px;
        overflow: hidden; clip: rect(0, 0, 0, 0);
        white-space: nowrap; border: 0;
    }

    /* Highlights (#8b): ranked lists with bars + comeback card grid. */
    .lb-section-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e6e7ea;
    }
    .lb-section-title {
        margin: 0;
        font-size: 19px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: #15171b;
    }
    .lb-section-unit {
        flex: none;
        font-family: var(--font-mono);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #4c525a;
    }
    .lb-section-desc {
        margin: 10px 0 4px;
        font-size: 13.5px;
        line-height: 1.5;
        color: #3c4148;
        text-wrap: pretty;
    }
    .lb-hl-spotlight { margin-top: 28px; }
    .lb-hl-comebacks { margin: 30px 0 34px; }
    .lb-hl-cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 28px;
        margin-bottom: 34px;
    }

    .lb-hl-row {
        align-items: center;
        padding: 9px 0;
        border-bottom: 1px solid #eff0f2;
        text-decoration: none;
        color: inherit;
    }
    .lb-hl-row:hover { background: #fafbfb; }
    .lb-hl-row--spot {
        display: grid;
        grid-template-columns: 28px 250px 1fr 52px;
        gap: 0 14px;
    }
    .lb-hl-row--rank {
        display: grid;
        grid-template-columns: 24px 1fr 76px;
        gap: 0 12px;
    }
    .lb-hl-rank {
        font-family: var(--font-mono);
        font-size: 10px;
        color: #4c525a;
    }
    .lb-hl-id {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }
    .lb-hl-avatar {
        position: relative;
        flex: none;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        background: #e9eaed;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .lb-hl-initials {
        font-family: var(--font-mono);
        font-size: 8.5px;
        color: #4c525a;
    }
    .lb-hl-avatar img {
        position: absolute;
        inset: 0;
        width: 30px;
        height: 30px;
        border-radius: 6px;
        object-fit: cover;
    }
    .lb-hl-idcol { min-width: 0; }
    .lb-hl-name {
        display: block;
        font-size: 14.5px;
        font-weight: 600;
        letter-spacing: -.012em;
        line-height: 1.3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lb-hl-handle {
        display: block;
        font-family: var(--font-mono);
        font-size: 9.5px;
        line-height: 1.5;
        color: #5d636c;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lb-hl-bar {
        height: 7px;
        border-radius: 4px;
        background: #f0f1f3;
        overflow: hidden;
    }
    .lb-hl-bar-fill {
        display: block;
        height: 100%;
        background: #ee6524;
    }
    .lb-hl-value {
        text-align: right;
        font-family: var(--font-mono);
        font-size: 12px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .lb-hl-grid3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px 16px;
    }
    .lb-hl-card {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        padding: 8px 10px;
        border: 1px solid #eff0f2;
        border-radius: 8px;
        text-decoration: none;
        color: inherit;
    }
    .lb-hl-card:hover { border-color: #d3a98f; background: #fdfaf8; }
    .lb-hl-card .lb-hl-id { flex: 1; }
    .lb-hl-card .lb-hl-idcol { flex: 1; }
    .lb-hl-card .lb-hl-name { font-size: 14px; }
    .lb-hl-away {
        flex: none;
        font-family: var(--font-mono);
        font-size: 10px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: #15171b;
    }
    .lb-hl-note {
        margin: 16px 0 0;
        font-size: 13px;
        line-height: 1.5;
        color: #3c4148;
    }
    .lb-hl-empty {
        padding: 9px 0;
        font-size: 14px;
        color: #5d636c;
    }

    /* Contribution detail page (#9b, #18a). */

    /* #18a: title block — same full-bleed white block as every other page.
       Padding is inherited from .page-title-bar (26/24); the body's intro
       supplies the gap below the rule, so no bottom margin here. */
    .lb-d-titlebar { margin-bottom: 0; }
    /* Body below the rule carries its own vertical rhythm; main is full-bleed here. */
    .lb-detail { padding-bottom: 48px; }

    .lb-d-back {
        display: inline-block;
        margin-bottom: 16px;
        font-size: 13.5px;
        font-weight: 500;
        color: #ee6524;
        text-decoration: none;
    }
    .lb-d-back:hover { color: #ee6524; text-decoration: underline; text-underline-offset: 3px; }
    /* First body block sits 24px below the toggle / month chips / tallied link. */
    .lb-detail :is(.lb-months, .lb-d-controls, .lb-d-tallied-row) + :is(.lb-d-group, .lb-a-stats, .lb-a-head, .lb-d-back, .lb-d-empty) { margin-top: 24px; }

    /* Other-board line — a footnote below the content, not beside the back link. */
    .lb-d-otherboard {
        max-width: 560px;
        margin: 22px 0 0;
        font-size: 12.5px;
        line-height: 1.55;
        color: #6b7178;
    }
    .lb-d-otherboard--zero { margin-top: 16px; }
    .lb-d-otherboard a {
        color: #ee6524;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    /* Zero-score state (#17b/#17c). */
    .lb-d-score--zero { color: #6b7178; }
    .lb-d-empty {
        border: 1px solid #e3e5e8;
        border-radius: 10px;
        overflow: hidden;
    }
    .lb-d-empty-head {
        padding: 16px 20px 13px;
        background: #faf9f7;
        border-bottom: 1px solid #eeeff1;
        font-family: var(--font-mono);
        font-size: 9px;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #6b7178;
    }
    .lb-d-empty-row {
        display: flex;
        align-items: baseline;
        gap: 12px;
        padding: 13px 20px;
        border-bottom: 1px solid #eeeff1;
    }
    .lb-d-empty-name {
        flex: 1;
        font-size: 14.5px;
        font-weight: 600;
        letter-spacing: -.012em;
        color: #3c4148;
    }
    .lb-d-empty-count {
        font-family: var(--font-mono);
        font-size: 9px;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #6b7178;
    }
    .lb-d-empty-pts {
        width: 62px;
        text-align: right;
        font-family: var(--font-mono);
        font-size: 12px;
        font-weight: 500;
        color: #6b7178;
        font-variant-numeric: tabular-nums;
    }
    .lb-d-empty-foot {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
        padding: 16px 20px 18px;
    }
    .lb-d-empty-cta {
        flex: none;
        padding: 10px 17px;
        border-radius: 7px;
        background: #f26322;
        color: #15171b;
        font-weight: 700;
        font-size: 14px;
        text-decoration: none;
    }
    .lb-d-empty-cta:hover { background: #ff7433; color: #15171b; }
    .lb-d-empty-hint {
        flex: 1;
        min-width: 220px;
        font-size: 13px;
        line-height: 1.5;
        color: #5d636c;
    }

    .lb-d-head {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .lb-d-avatar {
        position: relative;
        flex: none;
        width: 52px;
        height: 52px;
        border-radius: 9px;
        background: #e9eaed;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        box-shadow: 0 0 0 0 rgba(238, 101, 36, 0);
        transition: box-shadow 100ms ease;
    }
    .lb-d-avatar:hover { box-shadow: 0 0 0 2px #ee6524; }
    .lb-d-avatar-initials {
        font-family: var(--font-mono);
        font-size: 12px;
        color: #4c525a;
    }
    .lb-d-avatar img {
        position: absolute;
        inset: 0;
        width: 52px;
        height: 52px;
        border-radius: 9px;
        object-fit: cover;
    }
    .lb-d-idcol { min-width: 0; }
    .lb-d-name {
        margin: 0;
        font-size: 32px;
        font-weight: 700;
        letter-spacing: -.03em;
        line-height: 1.1;
        color: #15171b;
    }
    .lb-d-handle {
        font-family: var(--font-mono);
        font-size: 10px;
        color: #5d636c;
    }
    .lb-d-scoreblock {
        margin-left: auto;
        text-align: right;
    }
    .lb-d-score {
        display: block;
        font-family: var(--font-mono);
        font-size: 30px;
        font-weight: 700;
        letter-spacing: -.04em;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }
    .lb-d-score-label {
        display: block;
        margin-top: 5px;
        font-family: var(--font-mono);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #4c525a;
    }
    .lb-d-intro {
        max-width: 660px;
        margin: 0;
        padding-top: 24px;
        font-size: 14.5px;
        line-height: 1.6;
        color: #3c4148;
        text-wrap: pretty;
    }

    /* #18a: the rolling detail page's title-block header (larger than the monthly
       drill-down, which keeps its in-container header). */
    .lb-d-titlebar .lb-d-head { gap: 16px; }
    .lb-d-titlebar .lb-d-avatar,
    .lb-d-titlebar .lb-d-avatar img { width: 56px; height: 56px; }
    .lb-d-titlebar .lb-d-name {
        font-size: 40px;
        letter-spacing: -.032em;
        line-height: 1.05;
    }
    .lb-d-titlebar .lb-d-handle {
        display: inline-block;
        margin-top: 2px;
        font-size: 11px;
        text-decoration: none;
    }
    .lb-d-titlebar .lb-d-handle:hover { text-decoration: underline; text-underline-offset: 2px; }
    .lb-d-titlebar .lb-d-score {
        font-size: 34px;
        letter-spacing: -.02em;
    }
    .lb-d-titlebar .lb-d-score-label {
        font-weight: 400;
        letter-spacing: .06em;
        color: #6b7178;
    }

    .lb-d-group { margin-bottom: 22px; }
    .lb-d-grouphead {
        display: grid;
        grid-template-columns: 1fr 112px 66px;
        align-items: baseline;
        gap: 0 16px;
        padding-bottom: 9px;
        border-bottom: 1px solid #15171b;
    }
    .lb-d-group-name {
        margin: 0;
        font-size: 17px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: #15171b;
    }
    .lb-d-group-name--single { font-size: 19px; }
    .lb-d-group-count {
        font-family: var(--font-mono);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #4c525a;
    }
    .lb-d-group-total {
        text-align: right;
        font-family: var(--font-mono);
        font-size: 15px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .lb-d-row {
        display: grid;
        grid-template-columns: 1fr 112px 66px;
        align-items: start;
        gap: 0 16px;
        padding: 9px 0;
        border-bottom: 1px solid #eeeff1;
        text-decoration: none;
        color: inherit;
    }
    .lb-d-row:hover { background: #fafbfb; }
    .lb-d-row-title {
        min-width: 0;
        font-size: 14.5px;
        line-height: 1.45;
        text-wrap: pretty;
    }
    .lb-d-row-date {
        margin-top: 3px;
        font-family: var(--font-mono);
        font-size: 9.5px;
        color: #5d636c;
        white-space: nowrap;
    }
    .lb-d-row-pts {
        position: relative;
        margin-top: 1px;
        text-align: right;
        font-family: var(--font-mono);
        font-size: 12px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .lb-d-row-pts:has(.lb-tip) { cursor: help; }
    .lb-d-row-pts:hover .lb-tip,
    .lb-d-row-pts:focus-within .lb-tip {
        opacity: 1;
        visibility: visible;
    }
    .lb-d-more {
        display: inline-block;
        margin-top: 10px;
        font-family: var(--font-mono);
        font-size: 9.5px;
        color: #ee6524;
        text-decoration: none;
        border-bottom: 1px solid rgba(238, 101, 36, .4);
    }
    .lb-d-more:hover { color: #ee6524; border-bottom-color: #ee6524; }

    /* Detail controls: view toggle + month filter. */
    .lb-d-controls {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 16px;
        margin-top: 20px;
    }
    .lb-d-toggle {
        display: flex;
        gap: 14px;
        font-family: var(--font-mono);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .lb-d-toggle a {
        color: #4c525a;
        text-decoration: none;
        padding-bottom: 2px;
    }
    .lb-d-toggle a:hover { color: #ee6524; }
    .lb-d-toggle a.active {
        color: #15171b;
        border-bottom: 2px solid #ee6524;
    }

    /* Flat list view (#9a). */
    .lb-a-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 24px;
    }
    .lb-a-card {
        border: 1px solid #e6e7ea;
        border-radius: 8px;
        padding: 13px 14px 12px;
    }
    .lb-a-card-label {
        display: block;
        min-height: 34px;
        font-size: 12.5px;
        line-height: 1.35;
        color: #3c4148;
        text-wrap: pretty;
    }
    .lb-a-card-val {
        display: flex;
        align-items: baseline;
        gap: 7px;
        margin-top: 6px;
    }
    .lb-a-card-pts {
        font-family: var(--font-mono);
        font-size: 17px;
        font-weight: 700;
        letter-spacing: -.03em;
        font-variant-numeric: tabular-nums;
    }
    .lb-a-card-count {
        font-family: var(--font-mono);
        font-size: 9.5px;
        color: #5d636c;
    }
    .lb-a-bar {
        display: block;
        height: 5px;
        border-radius: 3px;
        background: #f0f1f3;
        margin-top: 9px;
        overflow: hidden;
    }
    .lb-a-bar-fill {
        display: block;
        height: 100%;
        background: #ee6524;
    }
    .lb-a-head {
        display: grid;
        grid-template-columns: 1fr 104px 92px 54px;
        align-items: baseline;
        gap: 0 28px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e6e7ea;
    }
    .lb-a-sort {
        justify-self: start;
        font-family: var(--font-mono);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #4c525a;
        text-decoration: none;
        padding-bottom: 2px;
    }
    .lb-a-sort--right { justify-self: end; }
    .lb-a-sort:hover { color: #ee6524; }
    .lb-a-sort.active {
        color: #15171b;
        border-bottom: 2px solid #ee6524;
    }
    .lb-a-row {
        display: grid;
        grid-template-columns: 1fr 104px 92px 54px;
        align-items: start;
        gap: 0 28px;
        padding: 10px 0;
        border-bottom: 1px solid #eeeff1;
        text-decoration: none;
        color: inherit;
    }
    .lb-a-row:hover { background: #fafbfb; }
    .lb-a-chip {
        justify-self: start;
        margin-top: 1px;
        font-family: var(--font-mono);
        font-size: 9px;
        font-weight: 500;
        color: #4c525a;
        background: #f2f3f5;
        border-radius: 4px;
        padding: 3px 7px;
        white-space: nowrap;
    }

    /* Narrow widths: handle drops to its own line, score right-aligns. */
    /* Board narrow widths (#24a). Below ~700px the activity column drops and the
       handle moves under the name; below ~560px the strip and pager unstack. */
    @media (max-width: 700px) {
        .lb-col-act, .lbr-activity { display: none; }
        .lbr-id { flex-wrap: wrap; }
        .lbr-handle { flex-basis: 100%; }
    }
    @media (max-width: 560px) {
        .lb-strip { flex-direction: column; align-items: stretch; }
        .lb-search { margin-left: 0; }
        .lb-search-input { min-width: 0; width: 100%; }
        .lb-jump { justify-content: center; }
        .lb .nav-tabs { flex-wrap: nowrap; overflow-x: auto; }
        .lb-col-rank, .lbr-rank { width: 38px; }
        .lb-colhead, .lbr { gap: 12px; }
        .lb-pager { flex-direction: column; align-items: stretch; }
        .lb-more { width: 100%; text-align: center; }
    }
    @media (max-width: 640px) {
        .lb-tip { max-width: 260px; }

        .lb-hl-cols { grid-template-columns: 1fr; gap: 30px; }
        .lb-hl-grid3 { grid-template-columns: 1fr; }
        .lb-hl-row--spot { grid-template-columns: 24px 1fr 52px; }
        .lb-hl-row--spot .lb-hl-bar { display: none; }

        .lb-d-head { flex-wrap: wrap; }
        .lb-d-scoreblock { margin-left: 0; text-align: left; width: 100%; }
        .lb-d-row { grid-template-columns: 1fr 92px 54px; }

        .lb-a-stats { grid-template-columns: 1fr 1fr; }
        .lb-a-row { grid-template-columns: 1fr 80px 48px; }
        .lb-a-row .lb-a-chip { display: none; }
    }
</style>
