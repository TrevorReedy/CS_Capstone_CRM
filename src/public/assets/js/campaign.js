// Campaign module JavaScript

// ── Top Performers: type filter ───────────────────────────────────────────────
(function () {
    var btns = document.querySelectorAll('.camp-type-btn');
    if (!btns.length) return;

    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var type = btn.getAttribute('data-type');
            btns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            var rank = 1;
            document.querySelectorAll('#top-performers-table tbody tr').forEach(function (row) {
                var show = type === 'all' || row.getAttribute('data-type') === type;
                row.style.display = show ? '' : 'none';
                if (show) row.cells[0].textContent = rank++;
            });
        });
    });
}());

// ── Campaign Momentum Chart ───────────────────────────────────────────────────
(function () {
    var canvas = document.getElementById('momentum-chart');
    if (!canvas || typeof Chart === 'undefined' || !window.__campMomentum) return;

    var d             = window.__campMomentum;
    var activeMetric  = 'recipients';
    var activeRange   = '12w';
    var activeSegment = 'all';

    // barDefs hold style only — data is injected dynamically in makeDatasets
    // so that reassigning `d` after a fetch is picked up correctly.
    var barDefs = {
        recipients: {
            label: 'Recipients Reached',
            backgroundColor: 'rgba(26,86,219,0.75)',
            borderColor: '#1a56db',
            borderWidth: 1,
            borderRadius: 4,
        },
        sent: {
            label: 'Campaigns Sent',
            backgroundColor: 'rgba(25,135,84,0.75)',
            borderColor: '#198754',
            borderWidth: 1,
            borderRadius: 4,
        },
        created: {
            label: 'Campaigns Created',
            backgroundColor: 'rgba(109,40,217,0.65)',
            borderColor: '#6d28d9',
            borderWidth: 1,
            borderRadius: 4,
        }
    };

    function makeDatasets(metric) {
        return [
            Object.assign({ type: 'bar', yAxisID: 'y', order: 2, data: d[metric] }, barDefs[metric]),
        ];
    }

    var chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'top',
                labels: { font: { size: 12 }, padding: 18, usePointStyle: true }
            },
            tooltip: {
                callbacks: {
                    label: function (ctx) {
                        var v = ctx.parsed.y;
                        if (ctx.dataset.yAxisID === 'y2')
                            return ' ' + ctx.dataset.label + ': ' + (v !== null ? v.toFixed(1) + '%' : '—');
                        return ' ' + ctx.dataset.label + ': ' + (v !== null ? v.toLocaleString() : '—');
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { size: 11 } }
            },
            y: {
                beginAtZero: true,
                position: 'left',
                title: { display: true, text: 'Volume', font: { size: 11 }, color: '#6b7280' },
                ticks: { precision: 0, font: { size: 11 } },
                grid: { color: 'rgba(0,0,0,0.06)' }
            }
        }
    };

    function buildChart() {
        return new Chart(canvas, {
            data: { labels: d.labels, datasets: makeDatasets(activeMetric) },
            options: chartOptions
        });
    }

    var chart = buildChart();

    // destroy + recreate so Chart.js fully re-registers the new label array and dataset count.
    // rAF defers buildChart() until after the browser resets canvas dimensions post-destroy,
    // otherwise new Chart() renders into a 0×0 canvas and nothing appears.
    function applyData(data) {
        d = data;
        chart.destroy();
        requestAnimationFrame(function () {
            chart = buildChart();
            canvas.style.opacity = '1';
        });
    }

function fetchMomentum(e) {
    if (e) e.preventDefault();

    var params = new URLSearchParams({ range: activeRange, segment: activeSegment });

    if (activeRange === 'custom') {
        var fromVal = document.getElementById('momentum-from').value;
        var toVal   = document.getElementById('momentum-to').value;
        if (!fromVal || !toVal) return;
        params.set('from', fromVal);
        params.set('to', toVal);
    }

    canvas.style.opacity = '0.35';

    fetch('/modules/campaign/momentum_data.php?' + params.toString(), {
        headers: { 'Accept': 'application/json' }
    })
        .then(function (r) {
            return r.text().then(function (text) {
                console.log('Raw momentum response:', text);

                if (!r.ok) {
                    throw new Error('HTTP ' + r.status + ': ' + text);
                }

                return JSON.parse(text);
            });
        })
        .then(applyData)
        .catch(function (err) {
            console.error('Momentum chart failed:', err);
            canvas.style.opacity = '1';
        });
}

var applyBtn = document.getElementById('momentum-apply');
if (applyBtn) {
    applyBtn.addEventListener('click', fetchMomentum);
}

    // Metric toggle (no fetch — just re-slices current data)
    document.querySelectorAll('.camp-metric-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activeMetric = btn.getAttribute('data-metric');
            document.querySelectorAll('.camp-metric-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            chart.data.datasets = makeDatasets(activeMetric);
            chart.update();
        });
    });

    // Date range filter — updates state only, fetch on Apply
    document.querySelectorAll('.camp-range-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activeRange = btn.getAttribute('data-range');
            document.querySelectorAll('.camp-range-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('momentum-custom-dates').style.display =
                activeRange === 'custom' ? 'flex' : 'none';
        });
    });

    // Audience segment filter — updates state only, fetch on Apply
    document.querySelectorAll('.camp-segment-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activeSegment = btn.getAttribute('data-segment');
            document.querySelectorAll('.camp-segment-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
        });
    });

    // Apply button — single trigger for all filter changes
    var applyBtn = document.getElementById('momentum-apply');
    if (applyBtn) {
        applyBtn.addEventListener('click', fetchMomentum);
    }
}());
