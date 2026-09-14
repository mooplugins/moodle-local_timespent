// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Time spent report UI (AMD).
 *
 * @module     local_timespent/report
 * @copyright  2026 Mooplugins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    var initialized = false;
    var config = {
        downloadurl: '',
        sesskey: '',
        nodata: 'No data available in table',
        showingrecords: 'Showing %%FROM%% - %%TO%% of %%TOTAL%%',
        colcount: 4
    };

    /**
     * @param {string} id
     * @return {HTMLElement|null}
     */
    function byId(id) {
        return document.getElementById(id);
    }

    /**
     * @return {number}
     */
    function currentPage() {
        var el = byId('pagenumber');
        return el ? (parseInt(el.value, 10) || 1) : 1;
    }

    /**
     * @param {number} page
     */
    function setPage(page) {
        var el = byId('pagenumber');
        if (el) {
            el.value = String(page);
        }
    }

    /**
     * @return {number}
     */
    function selectedCourseId() {
        var el = byId('courseid');
        return el ? (parseInt(el.value, 10) || 0) : 0;
    }

    /**
     * @param {boolean} isLoading
     */
    function setLoading(isLoading) {
        var el = byId('ajaxloading');
        if (!el) {
            return;
        }
        el.style.display = isLoading ? 'block' : 'none';
    }

    /**
     */
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

    /**
     * @param {number} from
     * @param {number} to
     * @param {number} total
     * @return {string}
     */
    function formatPagingSummary(from, to, total) {
        return config.showingrecords
            .replace('%%FROM%%', String(from))
            .replace('%%TO%%', String(to))
            .replace('%%TOTAL%%', String(total));
    }

    /**
     * @param {HTMLElement|null} link
     * @param {boolean} disabled
     */
    function setPagerLinkState(link, disabled) {
        if (!link) {
            return;
        }
        link.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        if (disabled) {
            link.setAttribute('tabindex', '-1');
        } else {
            link.removeAttribute('tabindex');
        }
        var item = link.closest('.page-item');
        if (item) {
            item.classList.toggle('disabled', disabled);
        }
        // Theme override uses buttons with aria-disabled.
        if (link.tagName === 'BUTTON') {
            link.disabled = disabled;
        }
    }

    /**
     * @param {HTMLElement|null} link
     * @return {boolean}
     */
    function isPagerDisabled(link) {
        if (!link) {
            return true;
        }
        if (link.tagName === 'BUTTON') {
            return !!link.disabled || link.getAttribute('aria-disabled') === 'true';
        }
        var item = link.closest('.page-item');
        return item ? item.classList.contains('disabled') : true;
    }

    /**
     * @param {Object} data
     */
    function updatePager(data) {
        var total = parseInt(data.total, 10) || 0;
        var from = parseInt(data.strarfrom, 10) || 0;
        var to = parseInt(data.limitto, 10) || 0;
        var perPageEl = byId('rec_per_page');
        var perPage = perPageEl ? (parseInt(perPageEl.value, 10) || 10) : 10;
        var page = currentPage();
        var maxPage = total > 0 ? Math.ceil(total / perPage) : 1;

        var root = document.querySelector('.local-timespent-report') || document;
        var summaryEl = root.querySelector('.timespent-paging-summary');
        if (summaryEl) {
            summaryEl.textContent = total > 0 ? formatPagingSummary(from, to, total) : '';
        }

        // Legacy / theme markup may use strarfrom + limitto spans.
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

        setPagerLinkState(byId('timespent-prev'), page <= 1 || total === 0);
        setPagerLinkState(byId('timespent-next'), page >= maxPage || total === 0);
    }

    /**
     * @return {number}
     */
    function columnCount() {
        if (config.colcount > 0) {
            return config.colcount;
        }
        var ths = document.querySelectorAll('#timespent-index-table thead th');
        return ths.length || 4;
    }

    /**
     * @param {Object} row
     * @return {string}
     */
    function escapeHtml(row) {
        return String(row)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * @param {Array} reports
     */
    function renderRows(reports) {
        var table = byId('timespent-index-table');
        if (!table) {
            return;
        }
        var tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        if (!reports || !reports.length) {
            if (selectedCourseId() > 0) {
                var emptyTr = document.createElement('tr');
                var emptyTd = document.createElement('td');
                emptyTd.colSpan = columnCount();
                emptyTd.className = 'timespent-empty-row dataTables_empty text-center text-muted';
                emptyTd.textContent = config.nodata;
                emptyTr.appendChild(emptyTd);
                tbody.appendChild(emptyTr);
            }
            return;
        }
        reports.forEach(function(row) {
            var tr = document.createElement('tr');
            var nameHtml = '<a href="' + escapeHtml(row.profileurl) + '">' + escapeHtml(row.fullname) + '</a>';
            [row.rownumber, nameHtml, escapeHtml(row.duration), escapeHtml(row.lastsessionlogout)]
                .forEach(function(cellHtml) {
                    var td = document.createElement('td');
                    td.innerHTML = cellHtml;
                    tr.appendChild(td);
                });
            tbody.appendChild(tr);
        });
    }

    /**
     */
    function filtertable() {
        updateEmptyState();
        if (!selectedCourseId()) {
            renderRows([]);
            updatePager({total: 0, strarfrom: 0, limitto: 0});
            setLoading(false);
            return;
        }

        setLoading(true);
        var perPageEl = byId('rec_per_page');
        var searchEl = byId('searchdata');
        var requests = Ajax.call([{
            methodname: 'local_timespent_get_index_report',
            args: {
                courseid: selectedCourseId(),
                page: currentPage(),
                perpage: perPageEl ? (parseInt(perPageEl.value, 10) || 10) : 10,
                searchdata: (searchEl && searchEl.value) ? searchEl.value : ''
            }
        }]);

        requests[0].then(function(data) {
            renderRows(data.reports || []);
            updatePager(data);
            return null;
        }).catch(function(err) {
            Notification.exception(err);
            renderRows([]);
            updatePager({total: 0, strarfrom: 0, limitto: 0});
            return null;
        }).then(function() {
            setLoading(false);
        });
    }

    /**
     * @param {string} dataformat
     */
    function download(dataformat) {
        if (!selectedCourseId() || !config.downloadurl) {
            return;
        }
        var searchEl = byId('searchdata');
        var url;
        try {
            url = new URL(config.downloadurl, window.location.href);
        } catch (e) {
            url = document.createElement('a');
            url.href = config.downloadurl;
            var qs = [
                'courseid=' + encodeURIComponent(selectedCourseId()),
                'searchdata=' + encodeURIComponent((searchEl && searchEl.value) || ''),
                'dataformat=' + encodeURIComponent(dataformat),
                'sesskey=' + encodeURIComponent(config.sesskey)
            ].join('&');
            window.location.href = url.href + (url.href.indexOf('?') === -1 ? '?' : '&') + qs;
            return;
        }
        url.searchParams.set('courseid', selectedCourseId());
        url.searchParams.set('searchdata', (searchEl && searchEl.value) || '');
        url.searchParams.set('dataformat', dataformat);
        url.searchParams.set('sesskey', config.sesskey);
        window.location.href = url.toString();
    }

    /**
     * @param {Object} cfg
     */
    function init(cfg) {
        if (initialized) {
            return;
        }
        var root = document.querySelector('.local-timespent-report');
        var courseSelect = byId('courseid');
        if (!root || !courseSelect) {
            return;
        }
        initialized = true;

        config = Object.assign(config, cfg || {});
        if (root.getAttribute('data-nodata')) {
            config.nodata = root.getAttribute('data-nodata');
        }
        if (root.getAttribute('data-showingrecords')) {
            config.showingrecords = root.getAttribute('data-showingrecords');
        }
        if (root.getAttribute('data-colcount')) {
            config.colcount = parseInt(root.getAttribute('data-colcount'), 10) || config.colcount;
        }
        if (!config.sesskey && typeof M !== 'undefined' && M.cfg && M.cfg.sesskey) {
            config.sesskey = M.cfg.sesskey;
        }
        var downloadInput = byId('downloadajaxurl');
        if (!config.downloadurl && downloadInput) {
            config.downloadurl = downloadInput.value;
        }

        setLoading(false);
        updateEmptyState();
        updatePager({total: 0, strarfrom: 0, limitto: 0});

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
            prev.addEventListener('click', function(event) {
                event.preventDefault();
                if (isPagerDisabled(prev)) {
                    return;
                }
                setPage(Math.max(1, currentPage() - 1));
                filtertable();
            });
        }

        var next = byId('timespent-next');
        if (next) {
            next.addEventListener('click', function(event) {
                event.preventDefault();
                if (isPagerDisabled(next)) {
                    return;
                }
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

    return {
        init: init
    };
});
