{{-- Progressive enhancement for the #21 board: search, jump-to-rank, in-place
     pagination. No build step — plain DOM, runs after the server-rendered board.
     Without it the "Show 25 more" link and month chips still work by reloading. --}}
<script>
(function () {
    var board = document.querySelector('.lb-board');
    if (!board) { return; }

    var PAGE = 25;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var totalNum = parseInt(board.dataset.total, 10) || 0;
    var noun = board.dataset.noun || '';
    var windowCaption = board.dataset.window || '';

    var rowsEl = board.querySelector('.lb-rows');
    var rowEls = Array.prototype.slice.call(board.querySelectorAll('.lbr'));
    var pop = board.querySelector('.lb-pop');
    var countStmt = board.querySelector('.lb-count-stmt');
    var moreBtn = board.querySelector('.lb-more');
    var pager = board.querySelector('.lb-pager');
    var input = board.querySelector('.lb-search-input');
    var clearBtn = board.querySelector('.lb-search-clear');
    var empty = board.querySelector('.lb-empty');
    var live = board.querySelector('.lb-live');

    var rows = rowEls.map(function (el) {
        return { el: el, rank: parseInt(el.id.replace('rank-', ''), 10) || 0, search: el.dataset.search || '' };
    });

    var query = '';
    var fullDepth = parseInt(board.dataset.shown, 10) || PAGE;
    var searchDepth = PAGE;

    function fmt(n) { return n.toLocaleString('en-US'); }

    function list() {
        return query ? rows.filter(function (r) { return r.search.indexOf(query) !== -1; }) : rows;
    }
    function depth() { return query ? searchDepth : fullDepth; }
    function setDepth(d) { if (query) { searchDepth = d; } else { fullDepth = d; } }

    function announce(msg) { if (live) { live.textContent = msg; } }

    function apply(focusFrom) {
        var l = list();
        var d = Math.min(depth(), l.length);

        for (var i = 0; i < rows.length; i++) { rows[i].el.classList.add('is-beyond'); }
        for (var j = 0; j < d; j++) { l[j].el.classList.remove('is-beyond'); }

        var noMatch = query && l.length === 0;

        // Caption / count
        if (pop) {
            pop.textContent = query
                ? fmt(l.length) + ' of ' + fmt(totalNum) + ' ' + noun
                : fmt(totalNum) + ' ' + noun + ' · ' + windowCaption;
        }
        if (countStmt) {
            if (query) {
                countStmt.textContent = l.length === 0 ? 'No matches' : 'Showing ' + fmt(d) + ' of ' + fmt(l.length) + ' matches';
            } else {
                countStmt.textContent = d >= totalNum ? 'Showing all ' + fmt(totalNum) : 'Showing 1–' + fmt(d) + ' of ' + fmt(totalNum);
            }
        }

        // Pager visibility
        if (moreBtn) { moreBtn.hidden = d >= l.length; }
        if (pager) { pager.hidden = noMatch; }

        // No-results block
        if (empty) {
            empty.hidden = !noMatch;
            if (noMatch) {
                var q = input ? input.value.trim() : '';
                if (q.length > 40) { q = q.slice(0, 40) + '…'; }
                var l1 = empty.querySelector('.lb-empty-1');
                var l2 = empty.querySelector('.lb-empty-2');
                if (l1) { l1.textContent = 'No one matching "' + q + '" on this board.'; }
                if (l2 && !l2.dataset.filled) {
                    var href = l2.dataset.otherHref, name = l2.dataset.otherName;
                    var link = '<a href="' + href + '">' + name + '</a>';
                    l2.innerHTML = l2.dataset.monthly === '1'
                        ? 'They may not have been active in ' + l2.dataset.window + ' — try the ' + link + '.'
                        : 'They may be on the ' + link + ', or try a shorter search.';
                    l2.dataset.filled = '1';
                }
            }
        }

        if (typeof focusFrom === 'number' && l[focusFrom]) {
            l[focusFrom].el.focus({ preventScroll: true });
        }
    }

    // ---- Search ----
    var debounce;
    function onSearch() {
        var v = input.value.trim().toLowerCase();
        if (v === query) { return; }
        query = v;
        searchDepth = PAGE;
        if (clearBtn) { clearBtn.hidden = v === ''; }
        apply();
    }
    function clearSearch() {
        if (!input) { return; }
        input.value = '';
        query = '';
        searchDepth = PAGE;
        if (clearBtn) { clearBtn.hidden = true; }
        apply();
    }
    if (input) {
        input.addEventListener('input', function () {
            window.clearTimeout(debounce);
            debounce = window.setTimeout(onSearch, 150);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { clearSearch(); }
        });
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function () { clearSearch(); input.focus(); });
    }

    // ---- Pagination ----
    if (moreBtn) {
        moreBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var before = Math.min(depth(), list().length);
            setDepth(depth() + PAGE);
            apply(before);
            var shown = Math.min(depth(), list().length);
            if (!query) {
                try { history.replaceState(null, '', updateParam(location.href, 'rows', fullDepth)); } catch (err) {}
            }
            announce('25 more loaded. ' + (query
                ? 'Showing ' + fmt(shown) + ' of ' + fmt(list().length) + ' matches.'
                : 'Showing 1–' + fmt(shown) + ' of ' + fmt(totalNum) + '.'));
        });
    }

    function updateParam(url, key, value) {
        var u = new URL(url);
        u.searchParams.set(key, value);
        return u.pathname + u.search;
    }

    // ---- Jump to my rank ----
    function jumpTo(rank, highlight) {
        var target = document.getElementById('rank-' + rank);
        if (!target) { return; }
        if (query) { clearSearch(); }
        if (rank > fullDepth) { setDepth(rank); apply(); }

        var y = target.getBoundingClientRect().top + window.pageYOffset - window.innerHeight / 3;
        window.scrollTo({ top: Math.max(0, y), behavior: reduce ? 'auto' : 'smooth' });
        target.focus({ preventScroll: true });

        if (highlight) {
            target.classList.add('is-jumped');
            window.setTimeout(function () { target.classList.remove('is-jumped'); }, 2400);
            announce('Jumped to your rank, ' + rank + ' of ' + fmt(totalNum) + '.');
        }
        try { history.replaceState(null, '', '#rank-' + rank); } catch (err) {}
    }

    var jump = board.querySelector('.lb-jump[data-rank]');
    if (jump) {
        jump.addEventListener('click', function (e) {
            e.preventDefault();
            jumpTo(parseInt(jump.dataset.rank, 10), true);
        });
    }

    // Deep link: #rank-N reveals up to N and scrolls, no highlight.
    var m = location.hash.match(/^#rank-(\d+)$/);
    if (m) {
        window.requestAnimationFrame(function () { jumpTo(parseInt(m[1], 10), false); });
    }
})();
</script>
