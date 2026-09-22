<link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@400;500;600;700&family=Martian+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    /* ===== Site chrome: dark header + footer (design_handoff README-header.md) ===== */

    /* Sticky footer: body fills the viewport, main grows, footer sits at the
       bottom on short pages. (Replaces the removed Tailwind flex utilities.) */
    body { display: flex; flex-direction: column; min-height: 100vh; }
    main[role="main"] { flex: 1 0 auto; }

    /* Orange hairline — the only full-strength brand orange in the chrome.
       Shared by header (top) and footer (bottom). */
    .chrome-hairline { height: 3px; background: #f26322; }

    /* ---- Page width: one 36px gutter site-wide (README-detail "Page gutter"). ----
       The centred .container carries the gutter; title block and content share it so
       the rank column / body align with the H1. The dark nav overrides to 24px below;
       hero, footer and homepage sections set their own explicit 36px. */
    .container { padding-left: 36px; padding-right: 36px; }
    @media (max-width: 575.98px) {
        .container { padding-left: 20px; padding-right: 20px; }
    }

    /* ---- Dark bar ---- */
    .site-nav {
        background: #15171b;
        min-height: 64px;
        padding-top: 0;
        padding-bottom: 0;
    }
    /* Dark band is full-bleed; contents sit in the centred page container
       with a 24px gutter (README-header "Page width"). */
    .site-nav > .container {
        padding: 0 24px;
        gap: 30px;
    }

    /* Logo lockup */
    .site-brand {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 0;
        margin: 0;
    }
    .site-brand .brand-mark { width: 30px; height: 30px; border-radius: 7px; }
    .site-brand .brand-word {
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 16px;
        letter-spacing: -.022em;
        line-height: 1;
    }
    .site-brand .brand-word .w1 { font-weight: 500; color: #c9ced4; }
    .site-brand .brand-word .w2 { font-weight: 700; color: #fff; }

    /* Primary nav */
    .site-nav .navbar-nav .nav-link {
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 500;
        font-size: 14px;
        color: #c9ced4;
        padding: 8px 13px;
        border-radius: 6px;
        margin: 0 1px;
    }
    .site-nav .navbar-nav .nav-link:hover,
    .site-nav .navbar-nav .nav-link:focus {
        color: #fff;
        background: #23262b;
    }
    .site-nav .navbar-nav .nav-link.active,
    .site-nav .navbar-nav .nav-item.dropdown.show > .nav-link {
        color: #fff;
        background: #2a2e34;
        font-weight: 600;
    }

    /* Right-hand group: utility links + login */
    .site-endgroup {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Login button (shared look with the footer Slack button) */
    .site-login,
    .sf-slack {
        padding: 8px 15px;
        border-radius: 7px;
        background: #f26322;
        color: #15171b;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        border: 0;
        white-space: nowrap;
    }
    .site-login { font-size: 13.5px; gap: 6px; }
    .site-login:hover,
    .sf-slack:hover { background: #ff7433; color: #15171b; }

    /* Account chip + menu (16a, signed in) */
    .acct-chip {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 5px 11px 5px 6px;
        border: 0;
        border-radius: 999px;
        background: #23262b;
        white-space: nowrap;
    }
    .acct-chip:hover { background: #2f333a; }
    .acct-avatar {
        position: relative;
        width: 26px;
        height: 26px;
        flex: none;
        border-radius: 50%;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #33383f;
    }
    .acct-avatar-initials {
        position: absolute;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 10px;
        color: #c9ced4;
    }
    .acct-avatar img { position: relative; width: 26px; height: 26px; border-radius: 50%; }
    .acct-name {
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 500;
        font-size: 13.5px;
        color: #fff;
        max-width: 160px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .acct-caret { font-size: 10px; color: #9aa3ae; line-height: 1; }

    .acct-menu {
        width: 196px;
        margin-top: 8px;
        padding: 6px;
        background: #1c1f24;
        border: 1px solid #2b2f36;
        border-radius: 9px;
        box-shadow: 0 12px 28px rgba(0, 0, 0, .28);
    }
    .acct-head {
        padding: 8px 10px 9px;
        border-bottom: 1px solid #262a30;
        margin-bottom: 5px;
    }
    .acct-head-name {
        display: block;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 600;
        font-size: 13px;
        color: #fff;
    }
    .acct-head-handle {
        display: block;
        margin-top: 2px;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 9.5px;
        color: #9aa3ae;
    }
    .acct-menu form { margin: 0; }
    .acct-item {
        display: block;
        width: 100%;
        text-align: left;
        padding: 7px 10px;
        border: 0;
        border-radius: 6px;
        background: transparent;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 500;
        font-size: 13.5px;
        color: #c9ced4;
        text-decoration: none;
    }
    .acct-item:hover, .acct-item:focus { background: #262a30; color: #fff; }
    .acct-logout {
        margin-top: 5px;
        padding-top: 5px;
        border-top: 1px solid #262a30;
        border-radius: 0 0 6px 6px;
    }

    /* Focus ring — default browser ring is invisible on #15171b */
    .site-nav a:focus-visible,
    .site-nav button:focus-visible,
    .acct-chip:focus-visible,
    .acct-item:focus-visible,
    .site-footer-dark a:focus-visible {
        outline: 2px solid #f26322;
        outline-offset: 2px;
    }

    /* Below lg the chip collapses to the avatar alone; stays in the bar */
    @media (max-width: 991.98px) {
        .acct-name, .acct-caret { display: none; }
        .acct-chip { padding: 6px; }
    }

    /* ---- Page title block (kept white) ---- */
    .page-title-bar {
        background: #fff;
        padding-top: 26px;
        padding-bottom: 24px;
        margin-bottom: 24px;
        box-shadow: none;
        border-bottom: 1px solid #e6e7ea;
    }
    .page-title-bar h1 {
        margin: 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 40px;
        font-weight: 700;
        letter-spacing: -.032em;
        line-height: 1.05;
        color: #15171b;
    }

    /* ---- Inline info text (Issues/PRs by month) — full content width ---- */
    .info-text { margin: 0 0 26px; }
    .info-text-title {
        margin: 0 0 10px;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 17px;
        letter-spacing: -.016em;
        color: #15171b;
    }
    .info-text-p {
        margin: 0 0 9px;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 14.5px;
        line-height: 1.6;
        color: #3c4148;
        text-wrap: pretty;
    }
    .info-text-p:last-child { margin-bottom: 0; }

    /* ---- By-month timeline (14b) ---- */
    .bm-timeline {
        list-style: none;
        margin: 0;
        padding: 0 0 9px;
        display: flex;
        align-items: flex-end;
        gap: 14px;
        border-bottom: 1px solid #e3e5e8;
    }
    .bm-year { flex: 1; }
    .bm-bars {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        align-items: flex-end;
        gap: 2px;
        height: 124px;
    }
    .bm-slot { flex: 1; display: flex; align-items: flex-end; }
    .bm-bar {
        display: block;
        width: 100%;
        border-radius: 2px 2px 0 0;
        transition: background .12s ease;
    }
    a.bm-bar:hover { background: #15171b !important; }
    .bm-bar--zero { height: 2px; background: #e6e8ea; }

    .bm-years {
        display: flex;
        gap: 14px;
        margin-top: 8px;
    }
    .bm-year-label {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 1px;
        background: none;
        border: 0;
        padding: 4px 0 0;
        text-align: left;
        cursor: pointer;
        border-top: 2px solid transparent;
    }
    .bm-year-label.active { border-top-color: #f26322; }
    .bm-year-num {
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 15px;
        letter-spacing: -.02em;
        color: #5d636c;
    }
    .bm-year-label:hover .bm-year-num,
    .bm-year-label.active .bm-year-num { color: #15171b; }
    .bm-year-label:focus-visible { outline: 2px solid #f26322; outline-offset: 2px; }
    .bm-year-total {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 9.5px;
        color: #5d636c;
    }

    .bm-caption {
        margin: 16px 0 0;
        max-width: 620px;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 13px;
        line-height: 1.6;
        color: #5d636c;
    }

    /* ---- Month picker ---- */
    .bm-picker { margin-top: 36px; }
    .bm-picker-head { display: flex; align-items: baseline; gap: 10px; margin-bottom: 12px; }
    .bm-picker-title {
        margin: 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 17px;
        letter-spacing: -.016em;
        color: #15171b;
    }
    .bm-picker-year {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }
    .bm-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 5px; }
    .bm-tile {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1px;
        padding: 8px 0 7px;
        border-radius: 6px;
        border: 1px solid #e3e5e8;
        text-decoration: none;
    }
    a.bm-tile:hover { border-color: #15171b; }
    .bm-tile--empty { background: #f7f8f9; border-color: transparent; }
    .bm-tile-mon {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 8.5px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6b7178;
    }
    .bm-tile-count {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 700;
        font-size: 13px;
        color: #15171b;
    }
    .bm-tile--empty .bm-tile-count { font-weight: 400; color: #6b7178; }

    /* Focus rings match the chrome */
    .bm-bar:focus-visible,
    a.bm-tile:focus-visible { outline: 2px solid #f26322; outline-offset: 2px; }

    @media (max-width: 991.98px) {
        .bm-scroll { overflow-x: auto; }
        .bm-year, .bm-year-label { min-width: 120px; }
        .bm-grid { grid-template-columns: repeat(6, 1fr); }
    }
    @media (max-width: 575.98px) {
        .bm-grid { grid-template-columns: repeat(4, 1fr); }
    }

    /* ---- How Scores Work — standalone page (20a); reuses .scm-* components ---- */
    .hsw-intro {
        max-width: 680px;
        margin: 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 14.5px;
        line-height: 1.65;
        color: #3c4148;
        text-wrap: pretty;
    }
    .hsw-formula {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 22px;
        padding: 14px 18px;
        background: #faf9f7;
        border: 1px solid #e6e7ea;
        border-radius: 9px;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-size: 10.5px;
        letter-spacing: .02em;
        text-transform: uppercase;
    }
    .hsw-formula-cap {
        margin-left: auto;
        font-size: 9px;
        letter-spacing: .06em;
        color: #6b7178;
    }
    .hsw-boards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 34px;
        margin-top: 34px;
    }
    .hsw-h2 {
        margin: 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 21px;
        letter-spacing: -.024em;
        color: #15171b;
    }
    .hsw-thead {
        display: flex;
        justify-content: space-between;
        margin-top: 12px;
        padding: 12px 0 9px;
        border-bottom: 1px solid #e6e7ea;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }

    /* Worked-example panel scaled down for the narrower page columns.
       Double class beats the base .scm-example rule that is defined later in this file. */
    .scm-example.scm-example--sm { margin: 18px 0 0; padding: 16px 18px; }
    .scm-example--sm .scm-ex-prose { font-size: 13.5px; }
    .scm-example--sm .scm-ex-mono { font-size: 11.5px; }
    .scm-example--sm .scm-ex-num,
    .scm-example--sm .scm-ex-op { font-size: 14px; }
    .scm-example--sm .scm-ex-result { font-size: 18px; }
    .scm-example--sm .scm-ex-unit { font-size: 9.5px; }

    .hsw-mult {
        margin-top: 38px;
        padding-top: 22px;
        border-top: 1px solid #e6e7ea;
    }
    .hsw-mult-head { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
    .hsw-cap {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }
    .hsw-lead {
        max-width: 680px;
        margin: 10px 0 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 14px;
        line-height: 1.6;
        color: #3c4148;
        text-wrap: pretty;
    }
    .hsw-lead strong { font-weight: 700; }
    .hsw-mult-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 34px;
        margin-top: 24px;
    }
    .hsw-mult-grid .scm-sechead { border-bottom: 1px solid #e6e7ea; }

    @media (max-width: 900px) {
        .hsw-boards, .hsw-mult-grid { grid-template-columns: 1fr; }
        .hsw-formula-cap { margin-left: 0; flex-basis: 100%; }
    }

    /* ================= Scoring modal (19a / 19b) ================= */
    .modal-backdrop.show { background: #15171b; opacity: .55; }
    .scm-dialog { max-width: 1000px; }
    .scm-panel {
        border: 0;
        border-top: 3px solid #f26322;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 24px 60px rgba(0, 0, 0, .35);
    }

    .scm-header {
        display: flex;
        align-items: flex-start;
        gap: 24px;
        padding: 24px 30px 20px;
        border-bottom: 1px solid #e6e7ea;
    }
    .scm-headtext { flex: 1; min-width: 0; }
    .scm-title {
        margin: 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 25px;
        letter-spacing: -.028em;
        line-height: 1.1;
        color: #15171b;
    }
    .scm-intro {
        max-width: 620px;
        margin: 9px 0 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 400;
        font-size: 14px;
        line-height: 1.6;
        color: #3c4148;
    }
    .scm-intro strong { font-weight: 600; }
    .scm-close {
        flex: none;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 7px;
        background: transparent;
        font-size: 17px;
        line-height: 1;
        color: #5d636c;
    }
    .scm-close:hover { background: #f4f5f6; color: #15171b; }

    /* Formula strip */
    .scm-formula {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        padding: 14px 30px;
        background: #faf9f7;
        border-bottom: 1px solid #e6e7ea;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-size: 10.5px;
        letter-spacing: .02em;
        text-transform: uppercase;
    }
    .scm-tok { padding: 5px 9px; border-radius: 5px; }
    .scm-tok--base { background: #15171b; color: #fff; font-weight: 700; }
    .scm-tok--out { border: 1px solid #d5d8dc; color: #15171b; }
    .scm-tok--score { background: #fdece3; color: #8f3a10; font-weight: 700; }
    .scm-op { color: #9aa3ae; }

    /* Body grid */
    .scm-body { padding: 24px 30px 4px; }
    .scm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 34px; }
    .scm-sechead {
        margin: 0;
        padding-bottom: 9px;
        border-bottom: 1px solid #e6e7ea;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7178;
    }
    .scm-sechead--rec { margin-top: 26px; }

    .scm-row {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        padding: 11px 0;
        border-bottom: 1px solid #f0f1f3;
    }
    .scm-row:last-child { border-bottom: 0; }
    .scm-action {
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 14px;
        color: #15171b;
    }
    .scm-val {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 700;
        font-size: 13px;
        color: #15171b;
        text-align: right;
    }
    .scm-tag {
        display: inline-block;
        margin-left: 4px;
        padding: 3px 6px;
        border-radius: 4px;
        background: #fdece3;
        color: #8f3a10;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-size: 8.5px;
        text-transform: uppercase;
        letter-spacing: .04em;
        white-space: nowrap;
        vertical-align: middle;
    }
    .scm-tag--inline { margin-left: 0; }

    .scm-para {
        margin: 10px 0 4px;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 13px;
        line-height: 1.6;
        color: #3c4148;
    }
    .scm-para strong { font-weight: 700; }

    /* Priority chips */
    .scm-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
    .scm-chip {
        display: flex;
        align-items: baseline;
        gap: 7px;
        padding: 6px 10px;
        border-radius: 6px;
        background: #f7f5f2;
    }
    .scm-chip-label {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-size: 10px;
        color: #3c4148;
    }
    .scm-chip-name {
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 11.5px;
        color: #3c4148;
    }
    .scm-chip-val {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 700;
        font-size: 12.5px;
        color: #15171b;
    }

    /* Decay bar */
    .scm-decay { display: flex; align-items: flex-end; gap: 10px; margin-top: 14px; }
    .scm-decay-col {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
    }
    .scm-bar { width: 100%; border-radius: 4px 4px 0 0; }
    .scm-decay-val {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 700;
        font-size: 11px;
        color: #15171b;
    }
    .scm-decay-val--zero { color: #6b7178; }
    .scm-decay-label {
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 10.5px;
        color: #6b7178;
        text-align: center;
    }

    /* Scoring example */
    .scm-example {
        margin: 26px 30px 30px;
        padding: 18px 20px;
        background: #15171b;
        border-radius: 9px;
    }
    .scm-ex-eyebrow {
        margin: 0;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #9aa3ae;
    }
    .scm-ex-prose {
        max-width: 700px;
        margin: 10px 0 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 14px;
        line-height: 1.65;
        color: #e8eaec;
    }
    .scm-ex-prose strong { font-weight: 600; color: #fff; }
    .scm-ex-mono {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-size: 12px;
        color: #fff;
    }
    .scm-ex-eq { display: flex; align-items: baseline; gap: 10px; margin-top: 14px; }
    .scm-ex-num {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-size: 15px;
        color: #c9ced4;
    }
    .scm-ex-op {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-size: 15px;
        color: #6b7178;
    }
    .scm-ex-result {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 700;
        font-size: 19px;
        color: #f26322;
    }
    .scm-ex-unit {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #9aa3ae;
    }

    @media (max-width: 820px) {
        .scm-grid { grid-template-columns: 1fr; gap: 26px 0; }
        .scm-sechead--rec { margin-top: 0; }
        .scm-example { margin: 26px 16px 16px; }
        .scm-panel { border-radius: 10px; }
    }

    /* ================= Homepage (15a) ================= */

    /* Hero — full-bleed dark, continues the header bar with no seam */
    .hp-hero { background: #15171b; }
    .hp-hero-inner {
        padding: 48px 36px 54px;
        display: flex;
        gap: 44px;
        align-items: flex-start;
    }
    .hp-hero-left { flex: 1.05; }
    .hp-hero-right { flex: 1; }

    .hp-eyebrow {
        margin: 0;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #9aa3ae;
    }
    .hp-h1 {
        margin: 14px 0 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 46px;
        letter-spacing: -.034em;
        line-height: 1.04;
        color: #fff;
        text-wrap: pretty;
    }
    .hp-lead {
        margin: 16px 0 0;
        max-width: 420px;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 400;
        font-size: 15.5px;
        line-height: 1.6;
        color: #c9ced4;
        text-wrap: pretty;
    }
    .hp-cta { display: flex; gap: 10px; margin-top: 26px; flex-wrap: wrap; }
    .hp-cta-primary, .hp-cta-secondary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border-radius: 7px;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 14.5px;
        text-decoration: none;
        white-space: nowrap;
    }
    .hp-cta-primary { background: #f26322; color: #15171b; font-weight: 700; border: 0; }
    .hp-cta-primary:hover { background: #ff7433; color: #15171b; }
    .hp-cta-secondary { background: transparent; color: #fff; font-weight: 600; border: 1px solid #3a3f46; }
    .hp-cta-secondary:hover { border-color: #c9ced4; color: #fff; }

    /* Leaderboard card */
    .hp-board {
        list-style: none;
        margin: 0;
        padding: 18px 20px 16px;
        background: #1c1f24;
        border: 1px solid #2b2f36;
        border-radius: 10px;
    }
    .hp-board-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .hp-board-label {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #9aa3ae;
    }
    .hp-board-full {
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 600;
        font-size: 12.5px;
        color: #c9ced4;
        text-decoration: none;
    }
    .hp-board-full:hover { color: #fff; }

    .hp-board-row { border-bottom: 1px solid #262a30; }
    .hp-row {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 8px 0;
        text-decoration: none;
        border-radius: 6px;
    }
    a.hp-row:hover { background: #22262c; }
    a.hp-row:hover .hp-name, a.hp-row:hover .hp-score { color: #fff; }
    .hp-board-row--you .hp-row { border-left: 2px solid #f26322; padding-left: 8px; margin-left: -10px; }

    .hp-rank {
        width: 18px;
        flex: none;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 10.5px;
        color: #8a919b;
    }
    .hp-rank--you, .hp-rank--empty { color: #f26322; }
    .hp-avatar {
        position: relative;
        width: 26px;
        height: 26px;
        flex: none;
        border-radius: 50%;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #33383f;
    }
    .hp-avatar-initials {
        position: absolute;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 10px;
        color: #c9ced4;
    }
    .hp-avatar img { position: relative; width: 26px; height: 26px; border-radius: 50%; }
    .hp-avatar--dashed { background: transparent; border: 1px dashed #4a5057; overflow: visible; }
    .hp-name {
        flex: 1;
        min-width: 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 500;
        font-size: 13.5px;
        color: #fff;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .hp-name--empty { font-weight: 600; color: #f9a279; }
    .hp-score {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 700;
        font-size: 12px;
        color: #fff;
        text-align: right;
    }
    .hp-score--empty { font-weight: 400; color: #9aa3ae; }

    .hp-board-you { padding: 11px 0 3px; }
    .hp-board-you .hp-row { padding: 0; }
    .hp-you-caption {
        margin: 12px 0 0 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 400;
        font-size: 12px;
        line-height: 1.5;
        color: #9aa3ae;
        text-wrap: pretty;
    }

    /* Focus ring on every link/button across the homepage, not just the hero. */
    .hp-hero a:focus-visible, .hp-hero button:focus-visible,
    .hp-section a:focus-visible, .hp-section button:focus-visible,
    .hp-first a:focus-visible, .hp-first button:focus-visible {
        outline: 2px solid #f26322;
        outline-offset: 2px;
    }

    /* Sections below the hero */
    .hp-section { padding-left: 36px; padding-right: 36px; }
    .hp-start { padding-top: 32px; }
    .hp-area { padding-top: 26px; padding-bottom: 34px; }
    .hp-area .hp-sub { margin-bottom: 14px; }
    .hp-h2 {
        margin: 0 0 4px;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 21px;
        letter-spacing: -.024em;
        color: #15171b;
    }
    .hp-sub {
        margin: 0 0 16px;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 14.5px;
        color: #3c4148;
    }

    /* Ready to code row */
    .hp-ready {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 18px 20px;
        border: 1px solid #e3e5e8;
        border-radius: 10px;
        text-decoration: none;
    }
    .hp-ready:hover { border-color: #15171b; }
    .hp-ready-body { flex: 1; min-width: 0; }
    .hp-ready-title {
        display: block;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 16.5px;
        letter-spacing: -.018em;
        color: #15171b;
    }
    .hp-ready-desc {
        display: block;
        margin-top: 3px;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 13.5px;
        color: #5d636c;
    }
    .hp-chip {
        flex: none;
        padding: 5px 9px;
        border-radius: 5px;
        background: #fdf1ea;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 700;
        font-size: 11px;
        color: #a8420f;
    }
    .hp-ready-arrow { flex: none; font-size: 15px; font-weight: 700; color: #ee6524; }

    /* Area grid — hairline-ruled two-column list */
    .hp-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1px;
        background: #e6e7ea;
        border: 1px solid #e6e7ea;
        border-radius: 8px;
        overflow: hidden;
    }
    .hp-cell {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 13px 16px;
        background: #fff;
        text-decoration: none;
    }
    a.hp-cell:hover { background: #faf9f7; }
    .hp-cell--empty { pointer-events: none; }
    .hp-cell-name {
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 600;
        font-size: 14.5px;
        letter-spacing: -.012em;
        color: #15171b;
    }
    .hp-cell-count {
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 10.5px;
        color: #5d636c;
        white-space: nowrap;
    }

    /* First time contributing */
    .hp-first {
        margin-top: 30px;
        padding-top: 24px;
        border-top: 1px solid #e6e7ea;
        display: flex;
        gap: 26px;
    }
    .hp-first-head { width: 200px; flex: none; }
    .hp-h3 {
        margin: 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 17px;
        letter-spacing: -.018em;
        color: #15171b;
    }
    .hp-first-sub {
        margin: 6px 0 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-size: 13.5px;
        line-height: 1.55;
        color: #5d636c;
    }
    .hp-steps {
        flex: 1;
        display: flex;
        margin: 0;
        padding: 0;
        list-style: none;
        counter-reset: none;
    }
    .hp-step {
        flex: 1;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 400;
        font-size: 14px;
        line-height: 1.5;
        color: #15171b;
    }
    /* Outer edges flush; only inner gutters padded, so the three steps are equidistant. */
    .hp-step:nth-child(1) { padding-right: 18px; }
    .hp-step:nth-child(2) { padding: 0 18px; border-left: 1px solid #e6e7ea; }
    .hp-step:nth-child(3) { padding-left: 18px; border-left: 1px solid #e6e7ea; }
    .hp-step-num {
        display: block;
        margin-bottom: 5px;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 10px;
        color: #a8420f;
    }
    .hp-step a { color: #ee6524; text-decoration: underline; }
    .hp-step a:hover { color: #8f3a10; }

    @media (max-width: 991.98px) {
        .hp-hero-inner { flex-direction: column; gap: 30px; padding: 34px 20px 38px; }
        .hp-h1 { font-size: 36px; }
        .hp-lead { max-width: none; }
        .hp-hero-right { width: 100%; align-self: stretch; }
        .hp-section { padding-left: 20px; padding-right: 20px; }
        .hp-grid { grid-template-columns: minmax(0, 1fr); }
        .hp-first { flex-direction: column; gap: 0; }
        .hp-first-head { width: auto; }
        .hp-steps { flex-direction: column; margin-top: 14px; }
        .hp-step, .hp-step:nth-child(1), .hp-step:nth-child(2), .hp-step:nth-child(3) {
            padding: 14px 0 0;
            border-left: 0;
            border-top: 1px solid #e6e7ea;
        }
    }
    @media (max-width: 575.98px) {
        .hp-cta-primary, .hp-cta-secondary { flex: 1; justify-content: center; }
    }

    /* ---- Footer (12a) ---- */
    .site-footer-dark { background: #15171b; }
    /* Band is full-bleed; body content in the centred container, 36px gutter. */
    .site-footer-dark .sf-inner { padding: 38px 36px 0; }
    .sf-body { display: flex; align-items: flex-start; gap: 40px; }
    .sf-cta { max-width: 420px; }
    .sf-heading {
        margin: 0;
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 700;
        font-size: 23px;
        letter-spacing: -.024em;
        line-height: 1.25;
        color: #fff;
        text-wrap: pretty;
    }
    .sf-slack { margin-top: 18px; padding: 11px 19px; font-size: 14px; gap: 9px; }
    .sf-cols { margin-left: auto; display: flex; gap: 56px; }
    .sf-col { display: flex; flex-direction: column; gap: 9px; }
    .sf-col-head {
        margin: 0 0 2px;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #9aa3ae;
    }
    .sf-col a {
        font-family: 'Libre Franklin', system-ui, sans-serif;
        font-weight: 500;
        font-size: 13.5px;
        color: #c9ced4;
        text-decoration: none;
    }
    .sf-col a:hover { color: #fff; }
    /* Full-bleed rule above the legal text; text stays in the container. */
    .sf-legal {
        margin-top: 34px;
        padding: 18px 0 30px;
        border-top: 1px solid #292d33;
    }
    .sf-legal > .container { padding-left: 36px; padding-right: 36px; }
    .sf-legal p {
        margin: 0;
        font-family: 'Martian Mono', ui-monospace, monospace;
        font-weight: 400;
        font-size: 9.5px;
        line-height: 1.85;
        color: #8a919b;
        max-width: 720px;
        text-wrap: pretty;
        text-align: left;
    }

    @media (max-width: 991.98px) {
        .site-footer-dark .sf-inner { padding: 28px 20px 0; }
        .sf-legal { padding: 18px 0 24px; }
        .sf-legal > .container { padding-left: 20px; padding-right: 20px; }
        .sf-body { flex-direction: column; gap: 24px; }
        .sf-cols { margin-left: 0; }
        .sf-heading { font-size: 20px; }
    }
</style>
