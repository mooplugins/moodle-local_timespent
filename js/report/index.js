/**
 * Time spent report UI.
 *
 * @package   local_timespent
 * @copyright 2026 Mooplugins
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var ajaxUrl = '';
    var initialized = false;

    function byId(id) {
        return document.getElementById(id);
    }

    function sesskey() {
        var el = byId('timespent-sesskey');
        if (el && el.value) {
            return el.value;
        }
        if (typeof M !== 'undefined' && M.cfg && M.cfg.sesskey) {
            return M.cfg.sesskey;
        }
        return '';
    }

    function currentPage() {
        var el = byId('pagenumber');
        return el ? (parseInt(el.value, 10) || 1) : 1;
    }

    function setPage(page) {
        var el = byId('pagenumber');
        if (el) {
            el.value = String(page);
        }
    }

    function selectedCourseId() {
        var el = byId('courseid');
        return el ? (parseInt(el.value, 10) || 0) : 0;
    }

    function buildUrl(base, params) {
        var url;
        try {
            url = new URL(base, window.location.href);
        } catch (e) {
            url = document.createElement('a');
            url.href = base;
            var qs = Object.keys(params).map(function(key) {
                return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
            }).join('&');
            return url.href + (url.href.indexOf('?') === -1 ? '?' : '&') + qs;
        }
        Object.keys(params).forEach(function(key) {
            if (params[key] !== undefined && params[key] !== null) {
                url.searchParams.set(key, params[key]);
            }
        });
        return url.toString();
    }

    function setLoading(isLoading) {
        var el = byId('ajaxloading');
        if (!el) {
            return;
        }
        el.style.display = isLoading ? 'block' : 'none';
    }

    function updateEmptyState() {
        var empty = byId('timespent-empty');
        if (!empty) {
            return;
        }
        if (selectedCourseId() > 0) {
            empty.classList.add('d-none');
            empty.style.display = 'none';
        } else {
            empty.classList.remove('d-none');
            empty.style.display = '';
        }
    }

    function updatePager(data) {
        var total = parseInt(data.total, 10) || 0;
        var from = parseInt(data.strarfrom, 10) || 0;
        var to = parseInt(data.limitto, 10) || 0;
        var perPage = parseInt(byId('rec_per_page').value, 10) || 10;
        var page = currentPage();
        var maxPage = total > 0 ? Math.ceil(total / perPage) : 1;

        var root = document.querySelector('.local-timespent-report') || document;
        var fromEl = root.querySelector('.strarfrom');
        var toEl = root.querySelector('.limitto');
        var totalEl = root.querySelector('.totalrecords');
        if (fromEl) {
            fromEl.textContent = String(from);
        }
        if (toEl) {
            toEl.textContent = String(to);
        }
        if (totalEl) {
            totalEl.textContent = total ? (' / ' + total) : '';
        }

        var prev = byId('timespent-prev');
        var next = byId('timespent-next');
        if (prev) {
            prev.disabled = page <= 1 || total === 0;
        }
        if (next) {
            next.disabled = page >= maxPage || total === 0;
        }
    }

    function noDataMessage() {
        var root = document.querySelector('.local-timespent-report');
        if (root && root.getAttribute('data-nodata')) {
            return root.getAttribute('data-nodata');
        }
        return 'No data available in table';
    }

    function columnCount() {
        var root = document.querySelector('.local-timespent-report');
        var count = root ? parseInt(root.getAttribute('data-colcount'), 10) : 0;
        if (count > 0) {
            return count;
        }
        var ths = document.querySelectorAll('#timespent-index-table thead th');
        return ths.length || 4;
    }

    function renderRows(reports) {
        var tbody = byId('timespent-index-table').querySelector('tbody');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        if (!reports || !reports.length) {
            // Show empty message only after a course is selected.
            if (selectedCourseId() > 0) {
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                td.colSpan = columnCount();
                td.className = 'dataTables_empty text-center';
                td.textContent = noDataMessage();
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
            return;
        }
        reports.forEach(function(row) {
            var tr = document.createElement('tr');
            row.forEach(function(cell) {
                var td = document.createElement('td');
                td.innerHTML = cell;
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }

    function filtertable() {
        updateEmptyState();
        if (!selectedCourseId()) {
            renderRows([]);
            updatePager({total: 0, strarfrom: 0, limitto: 0});
            setLoading(false);
            return;
        }
        if (!ajaxUrl) {
            return;
        }

        setLoading(true);
        var params = {
            courseid: selectedCourseId(),
            currentpagenumber: currentPage(),
            rec_per_page: byId('rec_per_page').value,
            searchdata: (byId('searchdata') && byId('searchdata').value) || '',
            sesskey: sesskey()
        };

        fetch(buildUrl(ajaxUrl, params), {credentials: 'same-origin'})
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Request failed (' + response.status + ')');
                }
                return response.text();
            })
            .then(function(text) {
                var data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response');
                }
                renderRows(data.reports || []);
                updatePager(data);
            })
            .catch(function(err) {
                if (window.console && console.error) {
                    console.error('Time spent report load failed:', err);
                }
                renderRows([]);
                updatePager({total: 0, strarfrom: 0, limitto: 0});
            })
            .finally(function() {
                setLoading(false);
            });
    }

    function download(dataformat) {
        if (!selectedCourseId()) {
            return;
        }
        var downloadUrl = byId('downloadajaxurl').value;
        window.location.href = buildUrl(downloadUrl, {
            courseid: selectedCourseId(),
            searchdata: (byId('searchdata') && byId('searchdata').value) || '',
            dataformat: dataformat,
            sesskey: sesskey()
        });
    }

    function init() {
        if (initialized) {
            return;
        }
        var root = document.querySelector('.local-timespent-report');
        var courseSelect = byId('courseid');
        var ajaxInput = byId('ajaxUrl');
        if (!root || !courseSelect || !ajaxInput) {
            return;
        }
        initialized = true;

        ajaxUrl = ajaxInput.value;
        setLoading(false);
        updateEmptyState();

        courseSelect.addEventListener('change', function() {
            setPage(1);
            filtertable();
        });

        var searchBtn = byId('btnsearch');
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                setPage(1);
                filtertable();
            });
        }

        var searchInput = byId('searchdata');
        if (searchInput) {
            searchInput.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    setPage(1);
                    filtertable();
                }
            });
        }

        var perPage = byId('rec_per_page');
        if (perPage) {
            perPage.addEventListener('change', function() {
                setPage(1);
                filtertable();
            });
        }

        var prev = byId('timespent-prev');
        if (prev) {
            prev.addEventListener('click', function() {
                setPage(Math.max(1, currentPage() - 1));
                filtertable();
            });
        }

        var next = byId('timespent-next');
        if (next) {
            next.addEventListener('click', function() {
                setPage(currentPage() + 1);
                filtertable();
            });
        }

        document.querySelectorAll('#dataexport [data-export]').forEach(function(item) {
            item.addEventListener('click', function() {
                download(item.getAttribute('data-export'));
            });
        });
    }

    // Moodle footer JS often loads after DOMContentLoaded has already fired.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
