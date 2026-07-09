/* global lwsopRum, jQuery */
(function ($) {
    'use strict';

    if (typeof lwsopRum === 'undefined') return;

    var ajaxurl = lwsopRum.ajaxurl;
    var nonce   = lwsopRum.nonce;
    var i18n    = lwsopRum.i18n || {};

    // ── DataTable ────────────────────────────────────────────────────────────

    var table = null;

    function scoreLabel(cls) {
        if (cls === 'rum-good')  return i18n.good  || 'Good';
        if (cls === 'rum-needs') return i18n.needs  || 'Needs work';
        if (cls === 'rum-poor')  return i18n.poor   || 'Poor';
        return '—';
    }

    function renderMetricCell(value, cls) {
        var label = scoreLabel(cls);
        var badge = cls !== 'rum-na'
            ? '<span class="rum-cell-badge">' + label + '</span>'
            : '';
        return '<div class="rum-cell ' + cls + '">' +
                   '<span class="rum-cell-val">' + (value !== null ? value : '—') + '</span>' +
                   badge +
               '</div>';
    }

    function formatMs(v) {
        if (v === null || v === undefined) return null;
        var ms = Number(v);
        if (ms >= 1000) return (ms / 1000).toFixed(2) + ' s';
        return Math.round(ms) + ' ms';
    }

    function formatCls(v) {
        if (v === null || v === undefined) return null;
        return parseFloat(v).toFixed(3);
    }

    // Zero-pad a non-negative integer to 8 digits so string comparison == numeric order.
    // Negative / null values (sentinel -1 used for "no data") sort as "00000000" (first).
    function padSort(n) {
        var s = '' + Math.round(n < 0 ? 0 : n);
        while (s.length < 8) { s = '0' + s; }
        return s;
    }

    // DataTables 1.13 ships without 'num-pre/asc/desc' sorters, so 'num' columns
    // fall through to 'string-asc/desc' which calls .toString() on each value
    // before comparing — turning the pre-processed number 208 back into "208"
    // so "208" > "10376" lexicographically (because "2" > "1"). Register all
    // three handlers so numeric arithmetic comparison is used throughout.
    if (!$.fn.dataTable.ext.type.order['num-pre']) {
        $.fn.dataTable.ext.type.order['num-pre'] = function (d) {
            return typeof d === 'number' ? d : (parseFloat(d) || 0);
        };
    }
    $.fn.dataTable.ext.type.order['num-asc']  = function (a, b) { return a - b; };
    $.fn.dataTable.ext.type.order['num-desc'] = function (a, b) { return b - a; };

    function buildSamplesTable(samples) {
        var html = '<div class="rum-samples-wrap"><table class="rum-samples-table"><thead><tr>' +
            '<th>' + (i18n.colDate    || 'Date')  + '</th>' +
            '<th>LCP</th><th>CLS</th><th>INP</th><th>TTFB</th>' +
            '</tr></thead><tbody>';
        samples.forEach(function (s) {
            html += '<tr>' +
                '<td>' + s.collected_at + '</td>' +
                '<td>' + (s.lcp  !== null ? formatMs(s.lcp)              : '—') + '</td>' +
                '<td>' + (s.cls  !== null ? parseFloat(s.cls).toFixed(3) : '—') + '</td>' +
                '<td>' + (s.inp  !== null ? formatMs(s.inp)              : '—') + '</td>' +
                '<td>' + (s.ttfb !== null ? formatMs(s.ttfb)             : '—') + '</td>' +
                '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function initDataTable(data) {
        var deviceFilter = lwsopRum.device || 'all';

        // Apply device filter client-side before handing to DataTables
        var rows = data;
        if (deviceFilter !== 'all') {
            rows = data.filter(function (r) { return r.device === deviceFilter; });
        }

        // Map to display rows
        var display = rows.map(function (r) {
            return {
                path:     r.path,
                device:   r.device,
                lcp:      formatMs(r.lcp),
                cls:      formatCls(r.cls),
                inp:      formatMs(r.inp),
                ttfb:     formatMs(r.ttfb),
                visits:   r.visits,
                lcp_cls:  r.lcp_cls,
                cls_cls:  r.cls_cls,
                inp_cls:  r.inp_cls,
                ttfb_cls: r.ttfb_cls,
                // Numeric sorts for DataTables ordering
                lcp_raw:  r.lcp  !== null ? parseFloat(r.lcp)  : -1,
                cls_raw:  r.cls  !== null ? parseFloat(r.cls)  : -1,
                inp_raw:  r.inp  !== null ? parseFloat(r.inp)  : -1,
                ttfb_raw: r.ttfb !== null ? parseFloat(r.ttfb) : -1,
            };
        });

        table = $('#lwsop-rum-table').DataTable({
            data: display,
            order: [[2, 'desc']], // sort by LCP desc by default
            orderCellsTop: true,  // bind sort clicks to the first <thead> row, not the filter row
            pageLength: 25,
            lengthMenu: [[25, 50, 100, 200, -1], [25, 50, 100, 200, i18n.all || 'All']],
            language: {
                search:      i18n.search      || 'Search:',
                lengthMenu:  i18n.lengthMenu  || 'Show _MENU_ pages',
                info:        i18n.info        || 'Showing _START_ to _END_ of _TOTAL_ pages',
                infoEmpty:   i18n.infoEmpty   || 'No pages found',
                zeroRecords: i18n.zeroRecords || 'No matching pages found',
                paginate: {
                    next:     i18n.next     || 'Next',
                    previous: i18n.previous || 'Previous',
                },
            },
            columns: [
                {
                    title: '<span class="th-main">' + (i18n.colPage || 'Page') + '</span>',
                    data: 'path',
                    render: function (d) {
                        var short     = d.length > 55 ? d.substr(0, 52) + '…' : d;
                        var safeFull  = $('<div>').text(d).html();
                        var safeShort = $('<div>').text(short).html();
                        var icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" width="12" height="12"><path d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/><path d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/></svg>';
                        return '<div class="rum-url-block">' +
                                   '<span class="rum-url" title="' + safeFull + '">' +
                                       safeShort +
                                       '<a class="rum-url-link" href="' + window.location.origin + safeFull + '" target="_blank" rel="noopener">' + icon + '</a>' +
                                   '</span>' +
                               '</div>';
                    },
                },
                {
                    title: '<span class="th-main">' + (i18n.colDevice || 'Device') + '</span>',
                    data: 'device',
                    render: function (d) {
                        var icon  = d === 'desktop' ? '🖥' : '📱';
                        var label = d === 'desktop'
                            ? (i18n.desktop || 'Desktop')
                            : (i18n.mobile  || 'Mobile');
                        return '<span class="rum-device-badge">' + icon + ' ' + label + '</span>';
                    },
                },
                {
                    title: '<span class="th-main">' + (i18n.colLcp  || 'Page Load Speed')  + '</span><span class="th-sub">LCP p75</span>',
                    data: 'lcp_raw',
                    type: 'num',
                    className: 'th-right',
                    render: function (d, type, row) {
                        if (type === 'filter') return row.lcp_cls || '';
                        if (type !== 'display') return d;
                        return renderMetricCell(row.lcp, row.lcp_cls);
                    },
                },
                {
                    title: '<span class="th-main">' + (i18n.colCls  || 'Layout Stability') + '</span><span class="th-sub">CLS p75</span>',
                    data: 'cls_raw',
                    type: 'num',
                    className: 'th-right',
                    render: function (d, type, row) {
                        if (type === 'filter') return row.cls_cls || '';
                        if (type !== 'display') return d;
                        return renderMetricCell(row.cls, row.cls_cls);
                    },
                },
                {
                    title: '<span class="th-main">' + (i18n.colInp  || 'Responsiveness')   + '</span><span class="th-sub">INP p75</span>',
                    data: 'inp_raw',
                    type: 'num',
                    className: 'th-right',
                    render: function (d, type, row) {
                        if (type === 'filter') return row.inp_cls || '';
                        if (type !== 'display') return d;
                        return renderMetricCell(row.inp, row.inp_cls);
                    },
                },
                {
                    title: '<span class="th-main">' + (i18n.colTtfb || 'Server Speed')     + '</span><span class="th-sub">TTFB p75</span>',
                    data: 'ttfb_raw',
                    type: 'num',
                    className: 'th-right',
                    render: function (d, type, row) {
                        if (type === 'filter') return row.ttfb_cls || '';
                        if (type !== 'display') return d;
                        return renderMetricCell(row.ttfb, row.ttfb_cls);
                    },
                },
                {
                    title: '<span class="th-main">' + (i18n.colVisits || 'Visits') + '</span>',
                    data: 'visits',
                    className: 'th-right rum-visits',
                },
                {
                    title: '',
                    data: null,
                    orderable: false,
                    className: 'th-center',
                    render: function (d, type, row) {
                        if (type !== 'display') return '';
                        return '<button class="rum-detail-btn" data-path="' +
                            $('<div>').text(row.path).html() +
                            '" data-device="' + row.device + '">' +
                            (i18n.details || 'Details') + '</button>';
                    },
                },
            ],
            // Column-level search row inserted after init
            initComplete: function () {
                var api = this.api();
                var headerRow = $('<tr class="rum-col-filter"></tr>');

                api.columns().every(function (idx) {
                    var col = this;
                    var td  = $('<th></th>');

                    if (idx === 0) {
                        // Path: text search
                        $('<input type="text" placeholder="' + (i18n.filterPath || 'Filter path…') + '">')
                            .appendTo(td)
                            .on('input', function () { col.search(this.value).draw(); });
                    } else if (idx === 1) {
                        // Device: dropdown
                        var sel = $('<select><option value="">' + (i18n.allDevices || 'All') + '</option>' +
                                    '<option value="desktop">🖥 ' + (i18n.desktop || 'Desktop') + '</option>' +
                                    '<option value="mobile">📱 ' + (i18n.mobile  || 'Mobile')  + '</option>' +
                                    '</select>').appendTo(td);
                        // Pre-select if device filter was passed from PHP
                        if (deviceFilter !== 'all') sel.val(deviceFilter);
                        sel.on('change', function () { col.search(this.value).draw(); });
                        if (deviceFilter !== 'all') col.search(deviceFilter).draw();
                    } else if (idx >= 2 && idx <= 5) {
                        // Metric columns: score filter
                        var scoreMap = {
                            'rum-good':  i18n.good  || 'Good',
                            'rum-needs': i18n.needs || 'Needs work',
                            'rum-poor':  i18n.poor  || 'Poor',
                        };
                        var s = $('<select><option value="">' + (i18n.allScores || 'All') + '</option></select>').appendTo(td);
                        $.each(scoreMap, function (val, lbl) {
                            s.append('<option value="' + val + '">' + lbl + '</option>');
                        });
                        s.on('change', function () { col.search(this.value).draw(); });
                    }

                    headerRow.append(td);
                });

                $(api.table().header()).append(headerRow);
            },
        });
    }

    // Detail-row toggle: fetch raw samples for a page/device and expand below the row.
    $(document).on('click', '.rum-detail-btn', function () {
        var btn  = $(this);
        var path   = btn.data('path');
        var device = btn.data('device');
        var tr  = btn.closest('tr');
        var row = table.row(tr);

        if (row.child.isShown()) {
            row.child.hide();
            btn.text(i18n.details || 'Details');
            return;
        }

        btn.prop('disabled', true).text(i18n.loading || 'Loading…');

        var fd = new FormData();
        fd.append('action',      'lwsop_rum_get_page_samples');
        fd.append('_ajax_nonce', nonce);
        fd.append('path',        path);
        fd.append('device',      device);

        fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                btn.prop('disabled', false);
                if (!j.success || !j.data || !j.data.length) {
                    row.child('<div class="rum-notice info">' + (i18n.noSamples || 'No visit detail available.') + '</div>').show();
                } else {
                    row.child(buildSamplesTable(j.data)).show();
                }
                btn.text(i18n.hideDetails || 'Close');
            })
            .catch(function () {
                btn.prop('disabled', false).text(i18n.details || 'Details');
            });
    });

    function loadTableData() {
        var wrap = $('#lwsop-rum-table-wrap');
        wrap.html('<div class="rum-dt-loading">' + (i18n.loading || 'Loading data…') + '</div>');

        var fd = new FormData();
        fd.append('action', 'lwsop_rum_get_table_data');
        fd.append('_ajax_nonce', nonce);

        fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) {
                    wrap.html('<div class="rum-notice warn">' + (i18n.loadError || 'Failed to load data.') + '</div>');
                    return;
                }
                if (!j.data || j.data.length === 0) {
                    wrap.html('<div class="rum-notice info">' +
                        (i18n.noData || 'No data collected yet. RUM starts recording as soon as visitors browse your site.') +
                        '</div>');
                    return;
                }
                wrap.html(
                    '<div class="rum-table-wrap">' +
                    '<table id="lwsop-rum-table" class="rum-table"></table>' +
                    '</div>'
                );
                initDataTable(j.data);
            })
            .catch(function () {
                wrap.html('<div class="rum-notice warn">' + (i18n.loadError || 'Failed to load data.') + '</div>');
            });
    }

    // ── Force aggregate button ───────────────────────────────────────────────

    var forceBtn = document.getElementById('lwsop_rum_force_agg');
    if (forceBtn) {
        forceBtn.addEventListener('click', function () {
            forceBtn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'lwsop_rum_force_aggregate');
            fd.append('_ajax_nonce', nonce);
            fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.success) {
                        if (typeof callPopup === 'function') callPopup('success', i18n.aggDone || 'Data refreshed — reloading…');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        if (typeof callPopup === 'function') callPopup('error', i18n.aggFail || 'Refresh failed');
                        forceBtn.disabled = false;
                    }
                })
                .catch(function () { forceBtn.disabled = false; });
        });
    }

    // ── Purge button ─────────────────────────────────────────────────────────

    var purgeBtn = document.getElementById('lwsop_rum_purge');
    if (purgeBtn) {
        purgeBtn.addEventListener('click', function () {
            var sel  = document.getElementById('lwsop_rum_purge_days');
            var days = sel ? parseInt(sel.value, 10) : 30;
            var msg  = days === 0
                ? (i18n.confirmAll  || 'Delete ALL visit data?')
                : (i18n.confirmDays || 'Delete visit data older than {d} days?').replace('{d}', days);
            if (!confirm(msg)) return;

            purgeBtn.disabled = true;
            var fd = new FormData();
            fd.append('action', 'lwsop_rum_purge');
            fd.append('_ajax_nonce', nonce);
            fd.append('days', days);
            fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.success) {
                        if (typeof callPopup === 'function') callPopup('success', i18n.purgedDone || 'Data deleted — reloading…');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        if (typeof callPopup === 'function') callPopup('error', i18n.purgedFail || 'Deletion failed');
                        purgeBtn.disabled = false;
                    }
                })
                .catch(function () { purgeBtn.disabled = false; });
        });
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    $(function () {
        if ($('#lwsop-rum-table-wrap').length) {
            loadTableData();
        }
    });

}(jQuery));

