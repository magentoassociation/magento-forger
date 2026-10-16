<style>
    /* Community-picked PRs (#27b, #27c). Builds on the board styles in leaderboard._lb-styles. */
    .cp .lb-intro { margin: 0; }

    /* Control strip */
    .cp-strip {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 22px;
        padding: 16px 0 14px;
        border-top: 1px solid #e6e7ea;
    }
    .cp .cp-search { margin-left: 0; }
    /* No ⌕ icon here, unlike the boards' search. */
    .cp .lb-search-input { min-width: 200px; padding: 7px 12px; }
    .cp-input,
    .cp-select {
        padding: 7px 12px;
        border: 1px solid #d5d8dc;
        border-radius: 7px;
        background: #fff;
        font-family: var(--font-sans);
        font-size: 13.5px;
        color: #15171b;
    }
    .cp-input { min-width: 130px; }
    .cp-input::placeholder { color: #9aa3ae; }
    /* Chevron per README "Dropdown caret": 7×5px, 8px after the label. */
    .cp-select {
        appearance: none;
        /* Native selects size to their longest option; cap so the strip stays one row. */
        max-width: 190px;
        text-overflow: ellipsis;
        padding-right: 27px;
        background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8' fill='none'%3E%3Cpath d='M1.5 1.75 6 6.25l4.5-4.5' stroke='%236b7178' stroke-width='2.25' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") no-repeat right 12px center / 7px 5px;
        cursor: pointer;
    }
    /* A control holding a filter (#27d). */
    .cp .lb-search-input.is-set,
    .cp-input.is-set,
    .cp-select.is-set { border-color: #15171b; font-weight: 600; }

    .cp-clear {
        padding: 0 4px;
        border: 0;
        background: none;
        font-family: var(--font-sans);
        font-size: 13.5px;
        font-weight: 500;
        color: #ee6524;
        text-decoration: underline;
        text-underline-offset: 2px;
        cursor: pointer;
        transition: color 120ms ease-out;
    }
    .cp-clear:hover { color: #c74e16; }
    .cp-clear[hidden] { display: none; }

    .cp-select--dark {
        margin-left: auto;
        border-color: #15171b;
        background-color: #15171b;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 8' fill='none'%3E%3Cpath d='M1.5 1.75 6 6.25l4.5-4.5' stroke='%239aa3ae' stroke-width='2.25' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        color: #fff;
        font-weight: 600;
        transition: background-color 120ms ease-out;
    }
    .cp-select--dark:hover { border-color: #2a2e34; background-color: #2a2e34; }
    .cp-select--dark option { background: #fff; color: #15171b; font-weight: 400; }

    .cp-count {
        margin: 4px 0 22px;
        font-family: var(--font-mono);
        font-size: 9.5px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }

    /* Column heads + rows share one grid: Votes | Pull request | Author · Age. */
    .cp-colhead,
    .cpr {
        display: grid;
        grid-template-columns: 48px minmax(0, 1fr) 150px;
        column-gap: 18px;
    }
    .cp-colhead {
        padding-bottom: 8px;
        border-bottom: 1px solid #e6e7ea;
        font-family: var(--font-mono);
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }
    .cp-col-meta { text-align: right; }

    .cpr {
        align-items: start;
        padding: 13px 0;
        border-bottom: 1px solid #f0f1f3;
        outline: none;
        transition: background 120ms ease-out;
    }
    .cpr:hover { background: #faf9f7; }
    .cpr-votes {
        font-family: var(--font-mono);
        font-weight: 700;
        font-size: 17px;
        line-height: 1.3;
        font-variant-numeric: tabular-nums;
        color: #15171b;
    }
    .cpr-line1 {
        display: flex;
        align-items: baseline;
        gap: 8px;
    }
    .cpr-num,
    .cpr-issue {
        flex: none;
        font-family: var(--font-mono);
        color: #ee6524;
        text-decoration: underline;
        text-underline-offset: 2px;
        transition: color 120ms ease-out;
    }
    .cpr-num { font-weight: 500; font-size: 12px; }
    .cpr-issue { font-size: 11px; }
    .cpr-num:hover,
    .cpr-issue:hover { color: #c74e16; }
    .cpr-title {
        font-weight: 600;
        font-size: 15px;
        line-height: 1.4;
        color: #15171b;
        text-wrap: pretty;
    }
    .cpr-line2 {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 5px;
        margin-top: 7px;
    }
    /* Pill colours follow the By Month charts' grey/orange pair (#28f). */
    .cpr-area,
    .cpr-comp {
        padding: 4px 8px;
        border-radius: 5px;
        font-size: 11.5px;
        line-height: 1.2;
        color: #15171b;
    }
    .cpr-area { background: #e6e7ea; }
    .cpr-comp { background: #fbddcb; }
    .cpr-issues {
        display: inline-flex;
        align-items: baseline;
        gap: 6px;
        margin-left: 6px;
    }
    .cpr-issues-label {
        font-family: var(--font-mono);
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }
    .cpr-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        padding-top: 3px;
        min-width: 0;
    }
    .cpr-author {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-family: var(--font-mono);
        font-size: 11px;
        color: #3c4148;
        text-decoration: underline;
        text-decoration-color: rgba(60, 65, 72, .35);
        text-underline-offset: 2px;
        transition: color 120ms ease-out;
    }
    .cpr-author:hover { color: #ee6524; }
    .cpr-age {
        font-family: var(--font-mono);
        font-size: 10px;
        color: #6b7178;
    }

    .cp .cp-input:focus-visible,
    .cp .cp-select:focus-visible,
    .cp .cp-clear:focus-visible,
    .cp .cpr-num:focus-visible,
    .cp .cpr-issue:focus-visible,
    .cp .cpr-author:focus-visible {
        outline: 2px solid #f26322;
        outline-offset: 2px;
    }

    /* Narrow (#27c): two-column control grid, number inline with the title,
       author and age on a third line. */
    @media (max-width: 700px) {
        .cp-strip {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 8px;
        }
        .cp .cp-search { grid-column: 1 / -1; }
        .cp .lb-search-input { width: 100%; min-width: 0; padding-top: 10px; padding-bottom: 10px; }
        .cp-input,
        .cp-select { width: 100%; min-width: 0; max-width: none; padding-top: 10px; padding-bottom: 10px; }
        .cp-select--dark { margin-left: 0; }
        /* Own row under Author | Sort. */
        .cp-clear { grid-column: 1 / -1; grid-row: 4; justify-self: start; }

        .cp-colhead,
        .cpr { grid-template-columns: 34px minmax(0, 1fr); column-gap: 12px; }
        .cp-col-meta { display: none; }
        .cpr-votes { font-size: 15px; }
        .cpr-line1 { display: block; }
        .cpr-num { margin-right: 7px; }
        .cpr-title { font-size: 14.5px; }
        .cpr-meta {
            grid-column: 2;
            flex-direction: row;
            align-items: baseline;
            gap: 8px;
            margin-top: 8px;
            padding-top: 0;
        }
        .cp .lb-pager { flex-direction: column; align-items: stretch; }
        .cp .lb-more { width: 100%; padding: 11px 18px; text-align: center; }
    }
</style>
