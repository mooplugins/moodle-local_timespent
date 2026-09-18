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
 * @author     BitKea Technologies LLP
 * @copyright  2026 BitKea Technologies LLP
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/ajax',
    'core/notification',
    'core/loadingicon',
    'core/pending'
], function(Ajax, Notification, LoadingIcon, Pending) {
    var initialized = false;
    var loading = false;
    var PICKER_LIMIT = 25;
    var DEBOUNCE_MS = 300;
    var config = {
        downloadurl: '',
        sesskey: '',
        nodata: '',
        showingrecords: '',
        colcount: 4,
        selectcourseprompt: '',
        selectuserprompt: '',
        searchRecord: '',
        searchUser: '',
        searchCourse: '',
        selectuserlabel: '',
        selectcourselabel: '',
        nousersfound: '',
        nocoursesfound: '',
        clearselection: ''
    };
    var courseHeaders = [];
    var userHeaders = [];
    var pickerTimers = {course: null, user: null};
    var pickerRequests = {course: 0, user: 0};
    var activeOptionIndex = -1;

    /**
     * @param {string} id
     * @return {HTMLElement|null}
     */
    function byId(id) {
        return document.getElementById(id);
    }

    /**
     * @return {string}
     */
    function reportMode() {
        var el = byId('reportmode');
        return el && el.value === 'user' ? 'user' : 'course';
    }

    /**
     * @return {boolean}
     */
    function isUserMode() {
        return reportMode() === 'user';
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
     * @return {number}
     */
    function selectedUserId() {
        var el = byId('reportuserid');
        return el ? (parseInt(el.value, 10) || 0) : 0;
    }

    /**
     * @return {boolean}
     */
    function hasActiveSelection() {
        return isUserMode() ? selectedUserId() > 0 : selectedCourseId() > 0;
    }

    /**
     * @param {string} type 'course'|'user'
     * @return {Object}
     */
    function pickerRefs(type) {
        var isUser = type === 'user';
        return {
            type: type,
            root: document.querySelector('[data-picker="' + type + '"]'),
            hidden: byId(isUser ? 'reportuserid' : 'courseid'),
            input: byId(isUser ? 'user-picker-input' : 'course-picker-input'),
            results: byId(isUser ? 'user-picker-results' : 'course-picker-results'),
            clear: document.querySelector('[data-clear="' + type + '"]'),
            method: isUser ? 'local_timespent_search_users' : 'local_timespent_search_courses',
            listKey: isUser ? 'users' : 'courses',
            emptyMsg: isUser ? config.nousersfound : config.nocoursesfound,
            placeholder: isUser ? config.searchUser : config.searchCourse
        };
    }

    /**
     * @param {Object} refs
     * @param {boolean} selected
     */
    function updateClearVisibility(refs, selected) {
        if (!refs.clear || !refs.input) {
            return;
        }
        refs.clear.classList.toggle('d-none', !selected);
        var control = refs.input.closest('.timespent-picker-control');
        if (control) {
            control.classList.toggle('has-selection', selected);
        }
    }

    /**
     * @param {Object} refs
     */
    function hideResults(refs) {
        if (!refs.results || !refs.input) {
            return;
        }
        refs.results.classList.add('d-none');
        refs.results.innerHTML = '';
        refs.input.setAttribute('aria-expanded', 'false');
        activeOptionIndex = -1;
    }

    /**
     * @param {Object} refs
     * @param {number} id
     * @param {string} label
     * @param {boolean} loadReport
     */
    function setSelection(refs, id, label, loadReport) {
        if (refs.hidden) {
            refs.hidden.value = String(id || 0);
        }
        if (refs.input) {
            refs.input.value = id > 0 ? (label || '') : '';
            refs.input.placeholder = refs.placeholder;
        }
        updateClearVisibility(refs, id > 0);
        hideResults(refs);
        updateSearchPlaceholder();
        updateEmptyState();
        if (loadReport) {
            setPage(1);
            var searchEl = byId('searchdata');
            if (searchEl && id > 0) {
                searchEl.value = '';
            }
            filtertable();
        }
    }

    /**
     * @param {Object} refs
     */
    function clearSelection(refs) {
        setSelection(refs, 0, '', true);
        if (refs.input) {
            refs.input.focus();
        }
    }

    /**
     * @param {Object} refs
     * @param {Array} items
     */
    function renderResults(refs, items) {
        if (!refs.results) {
            return;
        }
        refs.results.innerHTML = '';
        activeOptionIndex = -1;

        if (!items || !items.length) {
            var empty = document.createElement('li');
            empty.className = 'timespent-picker-empty';
            empty.textContent = refs.emptyMsg;
            refs.results.appendChild(empty);
            refs.results.classList.remove('d-none');
            refs.input.setAttribute('aria-expanded', 'true');
            return;
        }

        items.forEach(function(item, index) {
            var li = document.createElement('li');
            li.setAttribute('role', 'presentation');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'timespent-picker-option';
            btn.setAttribute('role', 'option');
            btn.setAttribute('data-id', String(item.id));
            btn.setAttribute('data-index', String(index));
            btn.textContent = item.fullname;
            btn.addEventListener('mousedown', function(event) {
                // Prevent input blur from closing before selection applies.
                event.preventDefault();
            });
            btn.addEventListener('click', function() {
                setSelection(refs, parseInt(item.id, 10) || 0, item.fullname, true);
            });
            li.appendChild(btn);
            refs.results.appendChild(li);
        });
        refs.results.classList.remove('d-none');
        refs.input.setAttribute('aria-expanded', 'true');
    }

    /**
     * @param {Object} refs
     * @param {number} delta
     */
    function moveActiveOption(refs, delta) {
        if (!refs.results || refs.results.classList.contains('d-none')) {
            return;
        }
        var options = refs.results.querySelectorAll('.timespent-picker-option');
        if (!options.length) {
            return;
        }
        activeOptionIndex += delta;
        if (activeOptionIndex < 0) {
            activeOptionIndex = options.length - 1;
        }
        if (activeOptionIndex >= options.length) {
            activeOptionIndex = 0;
        }
        options.forEach(function(option, index) {
            option.classList.toggle('is-active', index === activeOptionIndex);
        });
        options[activeOptionIndex].scrollIntoView({block: 'nearest'});
    }

    /**
     * @param {Object} refs
     */
    function activateCurrentOption(refs) {
        if (!refs.results || refs.results.classList.contains('d-none')) {
            return;
        }
        var options = refs.results.querySelectorAll('.timespent-picker-option');
        if (!options.length) {
            return;
        }
        var option = options[activeOptionIndex >= 0 ? activeOptionIndex : 0];
        if (!option) {
            return;
        }
        setSelection(
            refs,
            parseInt(option.getAttribute('data-id'), 10) || 0,
            option.textContent,
            true
        );
    }

    /**
     * Debounced AJAX search for course/user picker.
     *
     * @param {string} type
     * @param {string} query
     */
    function searchEntities(type, query) {
        var refs = pickerRefs(type);
        if (!refs.input || !refs.results) {
            return;
        }
        var trimmed = (query || '').trim();
        var requestId = ++pickerRequests[type];
        var pending = new Pending('local_timespent/report:search_' + type);

        Ajax.call([{
            methodname: refs.method,
            args: {
                query: trimmed,
                limit: PICKER_LIMIT
            }
        }])[0].then(function(data) {
            if (requestId !== pickerRequests[type]) {
                pending.resolve();
                return null;
            }
            renderResults(refs, (data && data[refs.listKey]) ? data[refs.listKey] : []);
            pending.resolve();
            return null;
        }).catch(function(err) {
            if (requestId === pickerRequests[type]) {
                Notification.exception(err);
                hideResults(refs);
            }
            pending.resolve();
            return null;
        });
    }

    /**
     * @param {string} type
     * @param {string} query
     * @param {boolean} immediate
     */
    function scheduleSearch(type, query, immediate) {
        if (pickerTimers[type]) {
            window.clearTimeout(pickerTimers[type]);
            pickerTimers[type] = null;
        }
        if (immediate) {
            searchEntities(type, query);
            return;
        }
        pickerTimers[type] = window.setTimeout(function() {
            pickerTimers[type] = null;
            searchEntities(type, query);
        }, DEBOUNCE_MS);
    }

    /**
     * @param {string} type
     */
    function bindPicker(type) {
        var refs = pickerRefs(type);
        if (!refs.input) {
            return;
        }

        refs.input.addEventListener('focus', function() {
            if (loading) {
                return;
            }
            // Browse first page or refine current typed query.
            var query = (parseInt(refs.hidden.value, 10) > 0) ? '' : refs.input.value;
            scheduleSearch(type, query, true);
        });

        refs.input.addEventListener('input', function() {
            if (loading) {
                return;
            }
            // Typing means the previous selection is no longer valid.
            if (refs.hidden && parseInt(refs.hidden.value, 10) > 0) {
                refs.hidden.value = '0';
                updateClearVisibility(refs, false);
                updateEmptyState();
                renderRows([]);
                updatePager({total: 0, strarfrom: 0, limitto: 0});
            }
            scheduleSearch(type, refs.input.value, false);
        });

        refs.input.addEventListener('keydown', function(event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                moveActiveOption(refs, 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                moveActiveOption(refs, -1);
            } else if (event.key === 'Enter') {
                if (!refs.results.classList.contains('d-none')) {
                    event.preventDefault();
                    activateCurrentOption(refs);
                }
            } else if (event.key === 'Escape') {
                hideResults(refs);
            }
        });

        refs.input.addEventListener('blur', function() {
            window.setTimeout(function() {
                hideResults(refs);
                // If nothing selected, clear typed text so placeholder returns.
                if (refs.hidden && parseInt(refs.hidden.value, 10) === 0) {
                    refs.input.value = '';
                }
            }, 150);
        });

        if (refs.clear) {
            refs.clear.addEventListener('click', function() {
                if (!loading) {
                    clearSelection(refs);
                }
            });
        }
    }

    /**
     * @param {boolean} disabled
     */
    function setEntitySelectDisabled(disabled) {
        var type = isUserMode() ? 'user' : 'course';
        var refs = pickerRefs(type);
        if (refs.input) {
            refs.input.disabled = disabled;
            refs.input.setAttribute('aria-busy', disabled ? 'true' : 'false');
        }
        if (refs.clear) {
            refs.clear.disabled = disabled;
        }
        if (refs.root) {
            refs.root.classList.toggle('timespent-controls-disabled', disabled);
        }
    }

    /**
     * @param {boolean} isLoading
     */
    function setLoading(isLoading) {
        loading = isLoading;
        var el = byId('ajaxloading');
        if (el) {
            el.hidden = !isLoading;
            if (isLoading) {
                el.removeAttribute('hidden');
            } else {
                el.setAttribute('hidden', 'hidden');
            }
        }
        setEntitySelectDisabled(isLoading);
        document.querySelectorAll('[data-reportmode]').forEach(function(btn) {
            btn.disabled = isLoading;
        });
        ['searchdata', 'btnsearch', 'timespent-export', 'rec_per_page'].forEach(function(id) {
            var control = byId(id);
            if (control) {
                control.disabled = isLoading;
            }
        });
    }

    /**
     */
    function updateEmptyState() {
        var empty = byId('timespent-empty');
        if (!empty) {
            return;
        }
        if (hasActiveSelection()) {
            empty.classList.add('d-none');
            return;
        }
        empty.textContent = isUserMode() ? config.selectuserprompt : config.selectcourseprompt;
        empty.classList.remove('d-none');
    }

    /**
     */
    function updateSearchPlaceholder() {
        var searchEl = byId('searchdata');
        if (!searchEl) {
            return;
        }
        var placeholder = config.searchRecord;
        if (isUserMode()) {
            placeholder = selectedUserId() > 0 ? config.searchCourse : config.searchUser;
        }
        searchEl.placeholder = placeholder;
        searchEl.setAttribute('aria-label', placeholder);
    }

    /**
     * @param {Array} headers
     */
    function setTableHeaders(headers) {
        var row = byId('timespent-table-head-row');
        if (!row || !headers || !headers.length) {
            return;
        }
        row.innerHTML = '';
        headers.forEach(function(header) {
            var th = document.createElement('th');
            th.setAttribute('scope', 'col');
            th.textContent = header.name || '';
            row.appendChild(th);
        });
        config.colcount = headers.length;
    }

    /**
     * @param {string} mode
     */
    function setReportMode(mode) {
        var value = mode === 'user' ? 'user' : 'course';
        var hidden = byId('reportmode');
        if (hidden) {
            hidden.value = value;
        }
        document.querySelectorAll('[data-reportmode]').forEach(function(btn) {
            var active = btn.getAttribute('data-reportmode') === value;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-outline-secondary', !active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    /**
     */
    function applyModeUi() {
        var courseFilter = document.querySelector('.timespent-course-filter');
        var userFilter = document.querySelector('.timespent-user-filter');
        if (courseFilter) {
            courseFilter.classList.toggle('d-none', isUserMode());
        }
        if (userFilter) {
            userFilter.classList.toggle('d-none', !isUserMode());
        }
        setTableHeaders(isUserMode() ? userHeaders : courseHeaders);
        updateSearchPlaceholder();
        updateEmptyState();
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
    }

    /**
     * @param {HTMLElement|null} link
     * @return {boolean}
     */
    function isPagerDisabled(link) {
        if (!link) {
            return true;
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
        setPagerLinkState(byId('timespent-prev'), page <= 1 || total === 0);
        setPagerLinkState(byId('timespent-next'), page >= maxPage || total === 0);
    }

    /**
     * @return {number}
     */
    function columnCount() {
        return config.colcount || 4;
    }

    /**
     * @param {string} value
     * @return {string}
     */
    function safeUrl(value) {
        var url = String(value || '');
        if (url.indexOf('http://') === 0 || url.indexOf('https://') === 0 || url.indexOf('/') === 0) {
            return url;
        }
        return '#';
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
        while (tbody.firstChild) {
            tbody.removeChild(tbody.firstChild);
        }
        if (!reports || !reports.length) {
            if (hasActiveSelection()) {
                var emptyTr = document.createElement('tr');
                var emptyTd = document.createElement('td');
                emptyTd.colSpan = columnCount();
                emptyTd.className = 'timespent-empty-row text-center text-muted';
                emptyTd.textContent = config.nodata;
                emptyTr.appendChild(emptyTd);
                tbody.appendChild(emptyTr);
            }
            return;
        }
        reports.forEach(function(row) {
            var tr = document.createElement('tr');

            var numTd = document.createElement('td');
            numTd.textContent = String(row.rownumber);
            tr.appendChild(numTd);

            var nameTd = document.createElement('td');
            var link = document.createElement('a');
            if (isUserMode()) {
                link.href = safeUrl(row.courseurl);
                link.textContent = row.coursename || '';
            } else {
                link.href = safeUrl(row.profileurl);
                link.textContent = row.fullname || '';
            }
            nameTd.appendChild(link);
            tr.appendChild(nameTd);

            var durationTd = document.createElement('td');
            durationTd.textContent = row.duration || '';
            tr.appendChild(durationTd);

            var lastTd = document.createElement('td');
            lastTd.textContent = row.lastsessionlogout || '';
            tr.appendChild(lastTd);

            tbody.appendChild(tr);
        });
    }

    /**
     */
    function filtertable() {
        updateEmptyState();
        updateSearchPlaceholder();

        if (!hasActiveSelection()) {
            renderRows([]);
            updatePager({total: 0, strarfrom: 0, limitto: 0});
            setLoading(false);
            return;
        }

        var pending = new Pending('local_timespent/report:filtertable');
        var filterRegion = document.querySelector(
            isUserMode() ? '[data-region="user-filter"]' : '[data-region="course-filter"]'
        );
        setLoading(true);
        if (filterRegion) {
            LoadingIcon.addIconToContainerRemoveOnCompletion(filterRegion, pending);
        }

        var perPageEl = byId('rec_per_page');
        var searchEl = byId('searchdata');
        var perpage = perPageEl ? (parseInt(perPageEl.value, 10) || 10) : 10;
        var searchdata = (searchEl && searchEl.value) ? searchEl.value : '';

        var request = isUserMode() ? {
            methodname: 'local_timespent_get_user_report',
            args: {
                userid: selectedUserId(),
                page: currentPage(),
                perpage: perpage,
                searchdata: searchdata
            }
        } : {
            methodname: 'local_timespent_get_index_report',
            args: {
                courseid: selectedCourseId(),
                page: currentPage(),
                perpage: perpage,
                searchdata: searchdata
            }
        };

        Ajax.call([request])[0].then(function(data) {
            renderRows(data.reports || []);
            updatePager(data);
            setLoading(false);
            pending.resolve();
            return null;
        }).catch(function(err) {
            Notification.exception(err);
            renderRows([]);
            updatePager({total: 0, strarfrom: 0, limitto: 0});
            setLoading(false);
            pending.resolve();
            return null;
        });
    }

    /**
     * Go / Enter on search.
     */
    function handleSearchAction() {
        setPage(1);
        filtertable();
    }

    /**
     * @param {string} dataformat
     */
    function download(dataformat) {
        if (!config.downloadurl || loading) {
            return;
        }
        if (isUserMode() && !selectedUserId()) {
            return;
        }
        if (!isUserMode() && !selectedCourseId()) {
            return;
        }
        var searchEl = byId('searchdata');
        var url;
        try {
            url = new URL(config.downloadurl, window.location.href);
        } catch (e) {
            return;
        }
        url.searchParams.set('reportmode', reportMode());
        url.searchParams.set('dataformat', dataformat);
        url.searchParams.set('sesskey', config.sesskey);
        url.searchParams.set('searchdata', (searchEl && searchEl.value) || '');
        if (isUserMode()) {
            url.searchParams.set('userid', selectedUserId());
            url.searchParams.delete('courseid');
        } else {
            url.searchParams.set('courseid', selectedCourseId());
            url.searchParams.delete('userid');
        }
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
        var courseHidden = byId('courseid');
        if (!root || !courseHidden) {
            return;
        }
        initialized = true;
        config = Object.assign(config, cfg || {});

        [
            ['data-nodata', 'nodata'],
            ['data-showingrecords', 'showingrecords'],
            ['data-selectcourseprompt', 'selectcourseprompt'],
            ['data-selectuserprompt', 'selectuserprompt'],
            ['data-search-record', 'searchRecord'],
            ['data-search-user', 'searchUser'],
            ['data-search-course', 'searchCourse'],
            ['data-selectuserlabel', 'selectuserlabel'],
            ['data-selectcourselabel', 'selectcourselabel'],
            ['data-nousersfound', 'nousersfound'],
            ['data-nocoursesfound', 'nocoursesfound'],
            ['data-clearselection', 'clearselection']
        ].forEach(function(pair) {
            if (root.getAttribute(pair[0])) {
                config[pair[1]] = root.getAttribute(pair[0]);
            }
        });
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

        try {
            courseHeaders = JSON.parse(byId('timespent-course-headers').textContent || '[]');
            userHeaders = JSON.parse(byId('timespent-user-headers').textContent || '[]');
        } catch (e) {
            courseHeaders = [];
            userHeaders = [];
        }

        bindPicker('course');
        bindPicker('user');

        setLoading(false);
        setReportMode('course');
        applyModeUi();
        updatePager({total: 0, strarfrom: 0, limitto: 0});

        document.querySelectorAll('[data-reportmode]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (loading) {
                    return;
                }
                var mode = btn.getAttribute('data-reportmode');
                if (mode === reportMode()) {
                    return;
                }
                setReportMode(mode);
                setPage(1);
                var searchEl = byId('searchdata');
                if (searchEl) {
                    searchEl.value = '';
                }
                setSelection(pickerRefs('course'), 0, '', false);
                setSelection(pickerRefs('user'), 0, '', false);
                hideResults(pickerRefs('course'));
                hideResults(pickerRefs('user'));
                applyModeUi();
                renderRows([]);
                updatePager({total: 0, strarfrom: 0, limitto: 0});
            });
        });

        var searchBtn = byId('btnsearch');
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                if (!loading) {
                    handleSearchAction();
                }
            });
        }

        var searchInput = byId('searchdata');
        if (searchInput) {
            searchInput.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    if (!loading) {
                        handleSearchAction();
                    }
                }
            });
        }

        var perPage = byId('rec_per_page');
        if (perPage) {
            perPage.addEventListener('change', function() {
                if (loading) {
                    return;
                }
                setPage(1);
                filtertable();
            });
        }

        var prev = byId('timespent-prev');
        if (prev) {
            prev.addEventListener('click', function(event) {
                event.preventDefault();
                if (loading || isPagerDisabled(prev)) {
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
                if (loading || isPagerDisabled(next)) {
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
