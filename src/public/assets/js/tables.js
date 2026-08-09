/*
 * tables.js — first-party DataTables initialiser for the module list views.
 *
 * Enhances every <table class="js-dt"> on the page. All four lists run in
 * DataTables SERVER-SIDE mode (data-dt-url points at a *_data.php endpoint that
 * does the sort/filter/paginate in SQL), so the browser only ever holds one
 * page. Configuration is read from data-* attributes; there is no per-table JS.
 *
 * Expected thead markup (two rows):
 *   <thead>
 *     <tr class="dt-title">
 *       <th data-col="id">#</th>
 *       <th data-col="title">Title</th>
 *       <th data-col="stage">Stage</th>
 *       <th data-orderable="false" data-searchable="false">Actions</th>
 *     </tr>
 *     <tr class="dt-filter">
 *       <th data-filter="text"></th>
 *       <th data-filter="text"></th>
 *       <th data-filter="select" data-options='["New","Won"]'></th>
 *       <th></th>
 *     </tr>
 *   </thead>
 *
 * Export buttons (CSV / PDF / XML) reuse DataTables' own request params — search,
 * per-column filters, sort, and the page window (start/length) — so an export
 * contains exactly the rows on screen. Picking "All" in the length menu exports
 * the whole filtered set.
 * Requires: jQuery, DataTables, DataTables Buttons (see datatables_assets.php).
 */
(function ($) {
    'use strict';

    var FILTER_DEBOUNCE_MS = 400;

    function buildColumns($table) {
        return $table.find('thead tr.dt-title th').map(function () {
            var $th = $(this);
            return {
                data: $th.data('col') || null,
                orderable: $th.data('orderable') !== false,
                searchable: $th.data('searchable') !== false,
                className: $th.data('class') || ''
            };
        }).get();
    }

    // Serialise DataTables' current request params (+ format) for an export link.
    function exportQuery(dt, format) {
        var params = dt.ajax.params();
        delete params.draw;
        params.format = format;
        return $.param(params);
    }

    function wireFilters($table, dt) {
        $table.find('thead tr.dt-filter th').each(function (i) {
            var $cell = $(this);
            var type = $cell.data('filter');
            $cell.empty();
            if (!type) { return; } // e.g. the Actions column

            if (type === 'select') {
                var opts = $cell.data('options') || [];
                var $sel = $('<select class="dt-col-filter"><option value="">All</option></select>');
                opts.forEach(function (o) { $sel.append($('<option>').val(o).text(o)); });
                $cell.append($sel);
                $sel.on('change', function () {
                    dt.column(i).search(this.value).draw();
                });
            } else {
                var $in = $('<input type="text" class="dt-col-filter" placeholder="Filter…">');
                $cell.append($in);
                var timer;
                $in.on('keyup change', function () {
                    var value = this.value;
                    clearTimeout(timer);
                    timer = setTimeout(function () { dt.column(i).search(value).draw(); }, FILTER_DEBOUNCE_MS);
                });
                // Keep clicks in the filter box from triggering a column sort.
                $in.on('click', function (e) { e.stopPropagation(); });
            }
        });
    }

    function showAllWarning($table) {
        var $wrap = $table.closest('.dt-container, .dataTables_wrapper');
        if ($wrap.find('.dt-all-warning').length) { return; }
        var $note = $('<div class="dt-all-warning">⚠️ Loading all rows can be slow on large tables.</div>');
        $wrap.prepend($note);
        setTimeout(function () { $note.fadeOut(500, function () { $(this).remove(); }); }, 6000);
    }

    $(function () {
        $('table.js-dt').each(function () {
            var $table = $(this);
            var dataUrl = $table.data('dt-url');
            var exportUrl = $table.data('dt-export');

            var buttons = [];
            if (exportUrl) {
                [['csv', 'CSV'], ['pdf', 'PDF'], ['xml', 'XML']].forEach(function (f) {
                    buttons.push({
                        text: f[1],
                        className: 'dt-export-btn',
                        action: function (e, dt) {
                            var href = exportUrl + '?' + exportQuery(dt, f[0]);
                            if (f[0] === 'pdf') {
                                window.open(href, '_blank'); // print-to-PDF page
                            } else {
                                window.location = href;      // file download
                            }
                        }
                    });
                });
            }

            var dt = $table.DataTable({
                serverSide: true,
                processing: true,
                ajax: { url: dataUrl, type: 'GET' },
                columns: buildColumns($table),
                orderCellsTop: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, '⚠️ All']],
                layout: {
                    topStart: buttons.length ? ['pageLength', 'buttons'] : 'pageLength',
                    topEnd: 'search',
                    bottomStart: 'info',
                    bottomEnd: 'paging'
                },
                buttons: buttons,
                language: {
                    search: 'Search all:',
                    lengthMenu: '_MENU_',
                    info: 'Showing _START_–_END_ of _TOTAL_',
                    infoFiltered: ' (filtered from _MAX_)',
                    processing: 'Loading…'
                }
            });

            dt.on('length.dt', function (e, settings, len) {
                if (len === -1) { showAllWarning($table); }
            });

            wireFilters($table, dt);
        });
    });
})(jQuery);
