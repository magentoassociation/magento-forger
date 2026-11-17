{{-- Issue picker: filters apply on change (no Apply button) and "Show 25 more" appends.
     Both fetch the page's own URL and swap in the parts that changed; the URL is kept in
     step so a filtered view can be reloaded or shared. Without JavaScript, Enter on a
     select submits the form and "Show 25 more" is a plain link. --}}
<script>
(function () {
    var form = document.querySelector('.ip-filters');
    var live = document.querySelector('.ip-live');
    if (!form || !window.fetch) { return; }

    var pending;

    function results() { return document.querySelector('.ip-results'); }

    function filterUrl() {
        var query = new URLSearchParams(new FormData(form)).toString();

        return form.action + (query ? '?' + query : '');
    }

    function announce(text) { if (live) { live.textContent = text; } }

    function load(url, append) {
        if (pending) { pending.abort(); }
        pending = new AbortController();
        // Selects whose options the server recomputes, with the names announced if it clears one.
        var dependent = { area: 'Area', component: 'Component', progress: 'Progress', issue: 'Issue' };
        var selectedBefore = {};
        Object.keys(dependent).forEach(function (name) { selectedBefore[name] = form.elements[name].value; });

        return fetch(url, { signal: pending.signal, headers: { Accept: 'text/html' } })
            .then(function (response) {
                if (!response.ok) { throw new Error(response.status); }
                // A value that fails validation redirects back; keep the current results.
                return response.redirected ? null : response.text();
            })
            .then(function (html) {
                if (html === null) { return; }
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var next = doc.querySelector('.ip-results');
                if (!next) { throw new Error('no results'); }

                if (append) {
                    var body = results().querySelector('tbody');
                    var before = body.children.length;
                    var added = Array.prototype.slice.call(next.querySelectorAll('tbody .ipr'), before);
                    added.forEach(function (row) { body.appendChild(document.adoptNode(row)); });
                    var pager = results().querySelector('.lb-pager');
                    var nextPager = next.querySelector('.lb-pager');
                    if (nextPager) { pager.replaceWith(document.adoptNode(nextPager)); } else { pager.remove(); }
                    if (added[0]) { added[0].querySelector('.ipr-link').focus({ preventScroll: true }); }
                    announce(added.length + ' more shown.');
                    try { history.replaceState(null, '', url); } catch (err) {}

                    return;
                }

                results().replaceWith(document.adoptNode(next));
                // Options depend on the other filters, and the server clears a value that no longer fits.
                ['area', 'component', 'progress', 'issue'].forEach(function (name) {
                    var fresh = doc.querySelector('.ip-filters select[name="' + name + '"]');
                    var field = form.elements[name];
                    field.innerHTML = fresh.innerHTML;
                    field.disabled = fresh.disabled;
                    field.className = fresh.className;
                });

                var cleared = Object.keys(dependent).filter(function (name) {
                    return selectedBefore[name] !== '' && form.elements[name].value === '';
                }).map(function (name) { return dependent[name] + ' cleared. '; }).join('');
                var count = results().querySelector('.ip-count');
                announce(cleared + (count ? count.textContent + '.' : ''));

                // Keep the filters in view: scroll back only when the list top has scrolled away.
                if (form.getBoundingClientRect().top < 0) { form.scrollIntoView({ block: 'start' }); }
                try { history.replaceState(null, '', filterUrl()); } catch (err) {}
            })
            .catch(function (err) {
                if (err.name !== 'AbortError') { location.href = url; }
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        load(filterUrl(), false);
    });
    form.addEventListener('change', function (e) {
        if (e.target.tagName === 'SELECT') { load(filterUrl(), false); }
    });

    form.querySelectorAll('.ip-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var on = chip.getAttribute('aria-pressed') !== 'true';
            chip.setAttribute('aria-pressed', on ? 'true' : 'false');
            form.elements[chip.dataset.param].disabled = !on;
            load(filterUrl(), false);
        });
    });

    document.addEventListener('click', function (e) {
        var more = e.target.closest('.ip-results .lb-more');
        if (!more) { return; }
        e.preventDefault();
        load(more.href, true);
    });
})();
</script>
