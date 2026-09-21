<link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;500;600;700&family=Martian+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    .lb {
        --font-sans: 'Libre Franklin', system-ui, sans-serif;
        --font-mono: 'Martian Mono', ui-monospace, monospace;
        font-family: var(--font-sans);
        color: #15171b;
    }

    .lb-intro {
        max-width: 760px;
        margin: 0 0 10px;
        font-size: 15px;
        line-height: 1.6;
        color: #3c4148;
        text-wrap: pretty;
    }

    .lb-tallied {
        display: inline-block;
        padding: 0;
        border: 0;
        background: none;
        font-family: var(--font-sans);
        font-size: 14px;
        font-weight: 500;
        color: #ee6524;
        text-decoration: underline;
        text-underline-offset: 2px;
    }
    .lb-tallied:hover { color: #c2521a; }
    .lb-d-tallied-row { margin: 9px 0 0; }

    /* Tabs — restyle the shared Bootstrap nav-tabs within the leaderboard. */
    .lb .nav-tabs {
        gap: 4px;
        margin: 20px 0 0;
        border-bottom: 1px solid #dfe1e4;
    }
    .lb .nav-tabs .nav-item { margin-bottom: -1px; }
    .lb .nav-tabs .nav-link {
        padding: 11px 20px;
        border: 0;
        border-radius: 7px 7px 0 0;
        font-family: var(--font-sans);
        font-size: 14.5px;
        font-weight: 500;
        color: #ee6524;
    }
    .lb .nav-tabs .nav-link:hover { color: #c2521a; border: 0; }
    .lb .nav-tabs .nav-link.active {
        font-weight: 600;
        color: #15171b;
        background: #fff;
        border: 1px solid #dfe1e4;
        border-bottom-color: #fff;
    }

    /* Month selector chips (monthly board). */
    .lb-months {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0 0 24px;
    }
    /* Detail page (#18a): chips sit 16px under the toggle; the first group carries the gap below. */
    .lb-detail .lb-months { margin: 16px 0 0; }
    .lb-month {
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
    .lb-month:hover { border-color: #ee6524; color: #ee6524; }
    .lb-month.active {
        background: #15171b;
        border-color: #15171b;
        color: #fff;
    }

    /* Borderless table, flush to the page gutter (detail-page standard): the rank
       column aligns with the H1, no card box to inset it. Dividers do the grouping. */
    .lb-card { border: 0; }
    .lb-row {
        display: grid;
        grid-template-columns: 56px 1fr 136px;
        align-items: center;
        gap: 0 12px;
        padding: 10px 0;
        border-bottom: 1px solid #eff0f2;
    }
    .lb-row:last-child { border-bottom: 0; }
    .lb-head {
        padding: 11px 0 9px;
        border-bottom: 1px solid #e6e7ea;
        font-family: var(--font-mono);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #4c525a;
    }
    .lb-head .lb-score-col { text-align: center; }

    .lb-rank {
        font-family: var(--font-mono);
        font-size: 11px;
        font-weight: 500;
        color: #4c525a;
    }

    .lb-contributor {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .lb-avatar {
        position: relative;
        flex: none;
        width: 34px;
        height: 34px;
        border-radius: 7px;
        background: #e9eaed;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        box-shadow: 0 0 0 0 rgba(194, 82, 26, 0);
        transition: box-shadow 100ms ease;
    }
    .lb-avatar:hover { box-shadow: 0 0 0 2px #ee6524; }
    .lb-avatar-initials {
        font-family: var(--font-mono);
        font-size: 9px;
        color: #4c525a;
    }
    .lb-avatar img {
        position: absolute;
        inset: 0;
        width: 34px;
        height: 34px;
        border-radius: 7px;
        object-fit: cover;
    }

    .lb-namewrap { min-width: 0; }
    .lb-nameline {
        display: flex;
        align-items: baseline;
        gap: 8px;
        min-width: 0;
    }
    .lb-name {
        font-size: 16px;
        font-weight: 600;
        letter-spacing: -.012em;
        line-height: 1.3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lb-handle {
        flex: none;
        font-family: var(--font-mono);
        font-size: 9.5px;
        color: #5d636c;
    }
    .lb-count {
        display: inline-block;
        font-family: var(--font-mono);
        font-size: 9.5px;
        line-height: 1.7;
        color: #ee6524;
        text-decoration: none;
        border-bottom: 1px solid rgba(238, 101, 36, .4);
    }
    .lb-count:hover { color: #ee6524; border-bottom-color: #ee6524; }

    .lb-score-cell {
        display: flex;
        justify-content: center;
        position: relative;
    }
    .lb-score {
        font-family: var(--font-mono);
        font-size: 13px;
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
        grid-template-columns: 1fr 150px 92px 54px;
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
        grid-template-columns: 1fr 150px 92px 54px;
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
    @media (max-width: 640px) {
        .lb-row { grid-template-columns: 40px 1fr auto; }
        .lb-nameline { flex-wrap: wrap; }
        .lb-score-cell { justify-content: flex-end; }
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
