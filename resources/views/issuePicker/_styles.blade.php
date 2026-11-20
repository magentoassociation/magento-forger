<style>
    /* Issue picker (README-issue-picker, #31b, #31c). Builds on the board styles in leaderboard._lb-styles. */
    .ip .lb-intro { margin: 0; }

    /* Filter panel, no box. Row 1: the four label dropdowns. Row 2: Sort and the chips. */
    .ip-filters {
        margin-top: 18px;
        padding: 14px 0;
        border-bottom: 1px solid #e6e7ea;
    }
    .ip-row {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 10px;
    }
    .ip-row + .ip-row { margin-top: 10px; }
    /* Chevron per README "Dropdown caret": 7×5px, #6b7178 on light controls. */
    .ip-select {
        width: 200px;
        min-width: 0;
        padding: 9px 34px 9px 12px;
        border: 1px solid #d5d8dc;
        border-radius: 7px;
        appearance: none;
        background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8' fill='none'%3E%3Cpath d='M1.5 1.75 6 6.25l4.5-4.5' stroke='%236b7178' stroke-width='2.25' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") no-repeat right 12px center / 7px 5px;
        font-family: var(--font-sans);
        font-weight: 500;
        font-size: 14px;
        color: #15171b;
        text-overflow: ellipsis;
        cursor: pointer;
        transition: border-color 120ms ease-out;
    }
    .ip-select:hover { border-color: #15171b; }
    /* Placeholder option showing ("Any area", "Any component"). */
    .ip-select.is-empty { color: #9aa3ae; }
    .ip-select option { color: #15171b; }
    .ip-select:disabled { color: #5d636c; cursor: default; }
    .ip-select:disabled:hover { border-color: #d5d8dc; }

    .ip-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: none;
        padding: 7px 12px;
        border: 1px solid #d5d8dc;
        border-radius: 7px;
        background: #fff;
        font-family: var(--font-sans);
        font-weight: 600;
        font-size: 13.5px;
        color: #15171b;
        white-space: nowrap;
        cursor: pointer;
        transition: border-color 120ms ease-out, background-color 120ms ease-out;
    }
    .ip-chip:hover { border-color: #15171b; }
    .ip-chip[aria-pressed="true"] { border-color: #15171b; background: #15171b; color: #fff; }
    .ip-chip[aria-pressed="true"]:hover { border-color: #2a2e34; background: #2a2e34; }
    .ip-check { display: none; }
    .ip-chip[aria-pressed="true"] .ip-check { display: block; }

    /* Results */
    .ip-results { margin-top: 24px; scroll-margin-top: 16px; }
    .ip-countline {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 6px 16px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }
    .ip-count {
        margin: 0;
        font-family: var(--font-mono);
        font-size: 9.5px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }
    .ip-reset {
        margin-left: auto;
        font-family: var(--font-sans);
        font-weight: 500;
        font-size: 13.5px;
        color: #ee6524;
        text-decoration: underline;
        text-underline-offset: 2px;
        transition: color 120ms ease-out;
    }
    .ip-reset:hover { color: #c74e16; }
    .lb-empty-2 .ip-reset { margin-left: 4px; font-size: inherit; }

    /* Fixed layout: widths come from the header row, not the rows shown, so columns hold
       still as filters change. Issue takes what is left. */
    .ip-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .ip-table .ip-col-area { width: 170px; }
    .ip-table .ip-col-component { width: 190px; }
    .ip-table .ip-col-opened { width: 110px; }
    .ip-table .ip-col-comments { width: 90px; }
    .ip-table th {
        padding: 8px 10px 8px 0;
        border-bottom: 1px solid #e6e7ea;
        font-family: var(--font-mono);
        font-weight: 400;
        font-size: 9px;
        text-align: left;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }
    .ip-table td {
        padding: 12px 10px 12px 0;
        border-bottom: 1px solid #f0f1f3;
        vertical-align: baseline;
    }
    .ip-table .ip-col-comments { padding-right: 0; text-align: right; }
    .ipr { position: relative; transition: background 120ms ease-out; }
    .ipr:hover { background: #faf9f7; }
    /* Number and title side by side; a wrapped title hangs under itself, not the number. */
    .ipr-link { display: flex; align-items: baseline; gap: 6px; text-decoration: none; }
    .ipr-text { min-width: 0; }
    /* Stretches the link over the row: the whole row opens the issue. */
    .ipr-link::after { content: ''; position: absolute; inset: 0; }
    .ipr-num {
        flex: none;
        font-family: var(--font-mono);
        font-weight: 500;
        font-size: 12px;
        color: #ee6524;
        text-decoration: underline;
        text-underline-offset: 2px;
        transition: color 120ms ease-out;
    }
    .ipr:hover .ipr-num { color: #c74e16; }
    .ipr-title {
        font-weight: 600;
        font-size: 14px;
        line-height: 1.4;
        color: #15171b;
        text-wrap: pretty;
    }
    /* Long labels wrap inside their fixed column instead of pushing it wider. */
    .ipr-label { font-size: 14px; color: #3c4148; overflow-wrap: anywhere; }
    .ipr-title { overflow-wrap: anywhere; }
    .ipr-mono {
        font-family: var(--font-mono);
        font-size: 11px;
        color: #5d636c;
        white-space: nowrap;
    }
    /* Component under the title, narrow widths only. */
    .ipr-sub { display: none; margin-top: 4px; font-size: 12px; color: #3c4148; }
    /* Progress and Issue labels, in the Community-Picked pill pair: Issue grey (area pill), Progress orange (component pill). */
    .ipr-pills { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 7px; }
    .ipr-pill {
        padding: 4px 8px;
        border-radius: 5px;
        background: #e6e7ea;
        font-size: 11.5px;
        line-height: 1.2;
        color: #15171b;
    }
    .ipr-pill--progress { background: #fbddcb; }

    .ip .ip-select:focus-visible,
    .ip .ip-chip:focus-visible,
    .ip .ip-reset:focus-visible,
    .ip .lb-more:focus-visible {
        outline: 2px solid #f26322;
        outline-offset: 2px;
    }
    /* The ring is drawn round the row the link covers. */
    .ip .ipr-link:focus-visible { outline: none; }
    .ip .ipr-link:focus-visible::after { outline: 2px solid #f26322; outline-offset: 2px; }

    /* Narrow (#31c): dropdowns a row each, chips share a row; Component and Opened columns drop. */
    @media (max-width: 700px) {
        .ip-row { flex-wrap: wrap; }
        .ip-select { flex-basis: 100%; width: 100%; padding-top: 10px; padding-bottom: 10px; }
        .ip-col-component,
        .ip-col-opened { display: none; }
        .ip-table .ip-col-area { width: 110px; }
        .ip-table .ip-col-comments { width: 76px; }
        .ipr-sub { display: block; }
        .ip .lb-pager { flex-direction: column; align-items: stretch; }
        .ip .lb-more { width: 100%; text-align: center; }
    }
</style>
