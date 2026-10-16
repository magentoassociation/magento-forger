{{-- Progressive enhancement for Community-picked PRs: filters apply without a
     button (selects on change, text fields on Enter or a 300ms pause) and
     "Show 50 more" appends. Both fetch the page's own URL and swap in the parts
     that changed, so focus stays in the field being typed in; the URL is updated
     so a filtered view can be shared and survives Back. Without JavaScript, Enter
     submits the form and "Show 50 more" is a plain link. --}}
<script>
(function () {
    var form = document.querySelector('.cp-strip');
    var live = document.querySelector('.cp-live');
    if (!form || !window.fetch) { return; }

    var pending;

    function results() { return document.querySelector('.cp-results'); }

    function filterUrl() {
        var params = new URLSearchParams();
        new FormData(form).forEach(function (value, key) {
            value = String(value).trim();
            if (value !== '' && !(key === 'sort' && value === 'votes')) { params.append(key, value); }
        });
        var query = params.toString();

        return form.action + (query ? '?' + query : '');
    }

    function load(url, append) {
        if (pending) { pending.abort(); }
        pending = new AbortController();

        return fetch(url, { signal: pending.signal, headers: { Accept: 'text/html' } })
            .then(function (response) {
                if (!response.ok) { throw new Error(response.status); }
                // A value that fails validation redirects back; keep the current results.
                return response.redirected ? null : response.text();
            })
            .then(function (html) {
                if (html === null) { return; }
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var next = doc.querySelector('.cp-results');
                if (!next) { throw new Error('no results'); }

                if (append) {
                    var rows = results().querySelector('.cp-rows');
                    var before = rows.children.length;
                    var added = Array.prototype.slice.call(next.querySelectorAll('.cpr'), before);
                    added.forEach(function (row) { rows.appendChild(document.adoptNode(row)); });
                    results().querySelector('.lb-pager').replaceWith(document.adoptNode(next.querySelector('.lb-pager')));
                    if (added[0]) { added[0].focus({ preventScroll: true }); }
                    if (live) { live.textContent = added.length + ' more shown. ' + results().querySelector('.lb-count-stmt').textContent + '.'; }
                } else {
                    results().replaceWith(document.adoptNode(next));
                    // Options depend on the other filters; the fields being typed in are left alone.
                    ['select[name="area"]', 'select[name="component"]', '#cp-author-options'].forEach(function (sel) {
                        var fresh = doc.querySelector(sel);
                        if (fresh) { form.querySelector(sel).innerHTML = fresh.innerHTML; }
                    });
                    if (live) { live.textContent = results().querySelector('.cp-count').textContent; }
                }
                try { history.replaceState(null, '', url); } catch (err) {}
            })
            .catch(function (err) {
                if (err.name !== 'AbortError') { location.href = url; }
            });
    }

    var debounce;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        window.clearTimeout(debounce);
        load(filterUrl(), false);
    });
    form.addEventListener('change', function (e) {
        if (e.target.tagName === 'SELECT') { load(filterUrl(), false); }
    });
    form.addEventListener('input', function (e) {
        if (e.target.tagName !== 'INPUT') { return; }
        window.clearTimeout(debounce);
        debounce = window.setTimeout(function () { load(filterUrl(), false); }, 300);
    });

    document.addEventListener('click', function (e) {
        var more = e.target.closest('.cp-results .lb-more');
        if (!more) { return; }
        e.preventDefault();
        load(more.href, true);
    });
})();
</script>
