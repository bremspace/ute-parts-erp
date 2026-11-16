(function () {
    'use strict';
    const STORAGE_KEY = 'wms_produk_col_widths';
    const ORDER_KEY = 'wms_produk_col_order';

    function getTable() { return document.querySelector('table.border-collapse'); }

    function applyWidths() {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            const table = getTable();
            if (!table) return;
            const ths = table.querySelectorAll('thead th');
            ths.forEach(function (th, idx) {
                const origIdx = parseInt(th.getAttribute('data-orig-idx')) || idx;
                const key = 'col_' + origIdx;
                if (saved[key]) {
                    th.style.width = saved[key] + 'px';
                    th.style.minWidth = saved[key] + 'px';
                    const tds = table.querySelectorAll('tbody tr td:nth-child(' + (idx + 1) + ')');
                    tds.forEach(function (td) { td.style.width = saved[key] + 'px'; });
                }
            });
        } catch (e) {}
    }

    function applyOrder() {
        try {
            const saved = JSON.parse(localStorage.getItem(ORDER_KEY) || 'null');
            if (!saved || !Array.isArray(saved)) return;
            const table = getTable();
            if (!table) return;
            const thead = table.querySelector('thead tr');
            const tbody = table.querySelector('tbody');
            if (!thead || !tbody) return;
            const ths = Array.from(thead.querySelectorAll('th'));
            if (saved.length !== ths.length) return;
            // rearrange
            saved.forEach(function (origIdx) {
                const th = ths[origIdx];
                if (th) thead.appendChild(th);
            });
            tbody.querySelectorAll('tr').forEach(function (row) {
                const tds = Array.from(row.querySelectorAll('td'));
                if (tds.length !== saved.length) return;
                saved.forEach(function (origIdx) {
                    const td = tds[origIdx];
                    if (td) row.appendChild(td);
                });
            });
        } catch (e) {}
    }

    function initResize() {
        const table = getTable();
        if (!table) return;
        table.querySelectorAll('thead th').forEach(function (th, idx) {
            if (th.querySelector('.col-resize-handle')) return;
            // mark original index on th and corresponding tds
            th.setAttribute('data-orig-idx', idx);
            const tds0 = table.querySelectorAll('tbody tr td:nth-child(' + (idx + 1) + ')');
            tds0.forEach(function (td) { td.setAttribute('data-orig-idx', idx); });
            const handle = document.createElement('div');
            handle.className = 'col-resize-handle';
            handle.style.cssText = 'position:absolute;right:0;top:0;bottom:0;width:6px;cursor:col-resize;z-index:10;background:transparent;';
            handle.addEventListener('mouseenter', function () { handle.style.background = 'rgba(99,102,241,0.4)'; });
            handle.addEventListener('mouseleave', function () { if (!handle._dragging) handle.style.background = 'transparent'; });
            th.style.position = 'relative';
            th.appendChild(handle);
            let startX, startW;
            handle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                handle._dragging = true;
                handle.style.background = 'rgba(99,102,241,0.8)';
                startX = e.clientX;
                startW = th.offsetWidth;
                const onMove = function (ev) {
                    const newW = Math.max(60, startW + (ev.clientX - startX));
                    th.style.width = newW + 'px';
                    th.style.minWidth = newW + 'px';
                    const colIdx = parseInt(th.getAttribute('data-orig-idx'));
                    const tds = table.querySelectorAll('tbody tr td:nth-child(' + (colIdx + 1) + ')');
                    tds.forEach(function (td) { td.style.width = newW + 'px'; });
                };
                const onUp = function () {
                    handle._dragging = false;
                    handle.style.background = 'transparent';
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    try {
                        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
                        const colIdx = parseInt(th.getAttribute('data-orig-idx'));
                        saved['col_' + colIdx] = th.offsetWidth;
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(saved));
                    } catch (e) {}
                };
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    }

    function initReorder() {
        const table = getTable();
        if (!table) return;
        const thead = table.querySelector('thead tr');
        if (!thead) return;
        const ths = thead.querySelectorAll('th');
        ths.forEach(function (th, idx) {
            if (th.getAttribute('data-reorder-init')) return;
            th.setAttribute('data-reorder-init', 'true');
            th.setAttribute('draggable', 'true');
            th.style.cursor = 'grab';
            th.addEventListener('dragstart', function (e) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', th.getAttribute('data-orig-idx'));
                th.style.opacity = '0.5';
            });
            th.addEventListener('dragend', function () {
                th.style.opacity = '1';
            });
            th.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                th.style.background = 'rgba(99,102,241,0.15)';
            });
            th.addEventListener('dragleave', function () {
                th.style.background = '';
            });
            th.addEventListener('drop', function (e) {
                e.preventDefault();
                th.style.background = '';
                const fromOrigIdx = parseInt(e.dataTransfer.getData('text/plain'));
                const toOrigIdx = parseInt(th.getAttribute('data-orig-idx'));
                if (isNaN(fromOrigIdx) || fromOrigIdx === toOrigIdx) return;
                // find current th order
                const currentThs = Array.from(thead.querySelectorAll('th'));
                const fromTh = currentThs.find(function (t) { return parseInt(t.getAttribute('data-orig-idx')) === fromOrigIdx; });
                const toTh = currentThs.find(function (t) { return parseInt(t.getAttribute('data-orig-idx')) === toOrigIdx; });
                if (!fromTh || !toTh) return;
                // reorder in DOM
                if (fromOrigIdx < toOrigIdx) {
                    toTh.after(fromTh);
                } else {
                    toTh.before(fromTh);
                }
                // reorder tds in each row
                table.querySelectorAll('tbody tr').forEach(function (row) {
                    const tds = Array.from(row.querySelectorAll('td'));
                    const fromTd = tds.find(function (td) { return parseInt(td.getAttribute('data-orig-idx')) === fromOrigIdx; });
                    const toTd = tds.find(function (td) { return parseInt(td.getAttribute('data-orig-idx')) === toOrigIdx; });
                    if (fromTd && toTd) {
                        if (fromOrigIdx < toOrigIdx) {
                            toTd.after(fromTd);
                        } else {
                            toTd.before(fromTd);
                        }
                    }
                });
                // save new order
                const newOrder = Array.from(thead.querySelectorAll('th')).map(function (t) {
                    return parseInt(t.getAttribute('data-orig-idx'));
                });
                localStorage.setItem(ORDER_KEY, JSON.stringify(newOrder));
                // re-init resize handles
                initResize();
            });
        });
    }

    function init() {
        applyOrder();
        applyWidths();
        initResize();
        initReorder();
    }

    // Apply on load (retry a few times because Livewire renders async)
    [500, 1000, 2000, 3500].forEach(function (delay) {
        setTimeout(init, delay);
    });
    // Re-apply after Livewire updates
    if (window.Livewire) {
        Livewire.hook('morph.updated', function () {
            setTimeout(init, 300);
        });
    }
})();
