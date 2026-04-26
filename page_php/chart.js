let currentPeriod = 'live';
let currentMetric = 'all';
let currentDate   = new Date().toISOString().split('T')[0];
let liveInterval  = null;
let currentView   = 'details';

const TREND_ICONS  = { up: '↑', down: '↓', stable: '→' };
const TREND_LABELS = { up: 'En hausse', down: 'En baisse', stable: 'Stable' };

// ── Changement de vue ─────────────────────────────────────────────────────────
function setView(view) {
    currentView = view;
    document.getElementById('vueDetails').style.display   = view === 'details'   ? 'grid' : 'none';
    document.getElementById('vueTendances').style.display = view === 'tendances' ? 'grid' : 'none';
    document.getElementById('btnVueDetails').classList.toggle('active',   view === 'details');
    document.getElementById('btnVueTendances').classList.toggle('active', view === 'tendances');
}

// ── Plugin : valeur actuelle à droite ─────────────────────────────────────────
const lastValuePlugin = {
    id: 'lastValue',
    afterDatasetsDraw(chart) {
        const { ctx, data, scales } = chart;
        const dataset = data.datasets[0];
        const values  = dataset.data;

        if (!values || values.length === 0) return;

        const lastValue = values[values.length - 1];
        if (lastValue === null || lastValue === undefined) return;

        const x = scales.x.right + 8;
        const y = scales.y.getPixelForValue(lastValue);

        ctx.save();
        ctx.font         = 'bold 12px sans-serif';
        ctx.fillStyle    = dataset.borderColor;
        ctx.textBaseline = 'middle';
        ctx.fillText(lastValue, x, y);
        ctx.restore();
    }
};
Chart.register(lastValuePlugin);

// ── Création chart ────────────────────────────────────────────────────────────
function makeChart(canvasId, labels, data, anomalies, color, unit, reverseY = false, yMin, yMax) {
    return new Chart(document.getElementById(canvasId).getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    data,
                    borderColor: color,
                    backgroundColor: color + '22',

                    // Toujours remplir vers le bas visuellement
                    fill: reverseY ? 'start' : 'start',

                    tension: 0.3,
                    pointRadius: 3,
                    pointBackgroundColor: color,
                },
                {
                    data: anomalies || [],
                    borderColor: 'transparent',
                    backgroundColor: '#e74c3c',
                    pointRadius: 7,
                    pointHoverRadius: 9,
                    fill: false,
                    tension: 0,
                    showLine: false,
                }
            ]
        },
        options: {
            responsive: true,
            animation: false,
            plugins: { legend: { display: false } },
            layout: { padding: { right: 50 } },
            scales: {
                x: {
                    display: true,
                    ticks: {
                        maxRotation: 45,
                        autoSkip: true,
                        maxTicksLimit: 10,
                        font: { size: 10 }
                    }
                },
                y: {
                    display: true,
                    reverse: reverseY,

                    // Axe fixe mais adaptable
                    suggestedMin: yMin,
                    suggestedMax: yMax,

                    title: { display: true, text: unit, font: { size: 10 } }
                }
            }
        }
    });
}

// ── Initialisation des graphiques ─────────────────────────────────────────────
const chartTemp = makeChart(
    'chartTemp',
    INIT_DATA.dates,
    INIT_DATA.temps,
    INIT_DATA.anomalies_temp,
    '#e74c3c',
    '°C',
    false,
    0,
    50
);

const chartHum = makeChart(
    'chartHum',
    INIT_DATA.dates,
    INIT_DATA.humidites,
    [],
    '#27ae60',
    '%',
    false,
    0,
    100
);

const chartNiveau = makeChart(
    'chartNiveau',
    INIT_DATA.dates,
    INIT_DATA.niveaux,
    INIT_DATA.anomalies_niveau,
    '#0ea5e9',
    'cm',
    true,
    0,
    300
);

// ── Visibilité graphiques ─────────────────────────────────────────────────────
function applyMetricVisibility() {
    document.getElementById('boxTemp').style.display   = ['all','temp'].includes(currentMetric)   ? '' : 'none';
    document.getElementById('boxHum').style.display    = ['all','hum'].includes(currentMetric)    ? '' : 'none';
    document.getElementById('boxNiveau').style.display = ['all','niveau'].includes(currentMetric) ? '' : 'none';
}

// ── Mise à jour charts ────────────────────────────────────────────────────────
function updateCharts(d) {
    chartTemp.data.labels             = d.dates;
    chartTemp.data.datasets[0].data   = d.temps;
    chartTemp.data.datasets[1].data   = d.anomalies_temp;

    chartHum.data.labels              = d.dates;
    chartHum.data.datasets[0].data    = d.humidites;

    chartNiveau.data.labels           = d.dates;
    chartNiveau.data.datasets[0].data = d.niveaux;
    chartNiveau.data.datasets[1].data = d.anomalies_niveau;

    chartTemp.update('none');
    chartHum.update('none');
    chartNiveau.update('none');
}

// ── Mise à jour stats ─────────────────────────────────────────────────────────
function updateStats(s) {

    // ── Vue Détails ───────────────────────────────────────────────────────────
    document.getElementById('liveTemp').textContent   = s.last_temp   + ' °C';
    document.getElementById('liveHum').textContent    = s.last_hum    + ' %';
    document.getElementById('liveNiveau').textContent = s.last_niveau + ' cm';

    const badge = document.getElementById('alertBadge');
    badge.className   = 'alert-badge ' + (s.alert_eau ? 'alert-on' : 'alert-off');
    badge.textContent = s.alert_eau ? '⚠️ Risque inondation' : '✓ Niveau normal';

    document.getElementById('tempMax').innerHTML   = s.temp_max  + '<span class="stat-unit">°C</span>';
    document.getElementById('tempMin').innerHTML   = s.temp_min  + '<span class="stat-unit">°C</span>';
    document.getElementById('tempAvg').innerHTML   = s.temp_avg  + '<span class="stat-unit">°C</span>';
    document.getElementById('humMax').innerHTML    = s.hum_max   + '<span class="stat-unit">%</span>';
    document.getElementById('humMin').innerHTML    = s.hum_min   + '<span class="stat-unit">%</span>';
    document.getElementById('humAvg').innerHTML    = s.hum_avg   + '<span class="stat-unit">%</span>';
    document.getElementById('niveauMax').innerHTML = s.dist_max  + '<span class="stat-unit">cm</span>';
    document.getElementById('niveauMin').innerHTML = s.dist_min  + '<span class="stat-unit">cm</span>';
    document.getElementById('niveauAvg').innerHTML = s.dist_avg  + '<span class="stat-unit">cm</span>';
    document.getElementById('risqueNiveau').textContent = s.last_niveau;

    const list = document.getElementById('alertsList');
    list.innerHTML = s.alerts.length === 0
        ? '<p class="no-alerts">Aucune alerte</p>'
        : s.alerts.map(a => `
            <div class="alert-row">
                <span>Niveau : ${a.dist} cm</span>
                <span class="alert-time">${a.datetime.substring(11,19)}</span>
            </div>`).join('');

    // ── Vue Tendances ─────────────────────────────────────────────────────────
    const setTrend = (id, t) => {
        const el = document.getElementById(id);
        el.className   = 'trend-badge trend-' + t.direction;
        el.textContent = TREND_ICONS[t.direction] + ' ' + TREND_LABELS[t.direction];
    };

    setTrend('trendTemp',   s.trend_temp);
    setTrend('trendHum',    s.trend_hum);
    setTrend('trendNiveau', s.trend_niveau);

    const setAnomaly = (id, count) => {
        const el = document.getElementById(id);
        el.className   = 'anomaly-count ' + (count > 0 ? 'anomaly-on' : 'anomaly-off');
        el.textContent = count + ' anomalie' + (count > 1 ? 's' : '');
    };

    setAnomaly('anomalyTemp',   s.anomalies_temp);
    setAnomaly('anomalyNiveau', s.anomalies_niveau);

    // Confort thermique
    const comfort = document.getElementById('comfortBox');
    comfort.className = 'comfort-box comfort-' + s.heat_index.color;
    document.getElementById('comfortLabel').textContent = s.heat_index.label;
    document.getElementById('comfortValue').textContent = s.heat_index.value + ' °C';

    // ── Risque inondation (UTILISE LE BACKEND) ────────────────────────────────
    const risqueBox = document.getElementById('risqueBox');

    risqueBox.className = 'risque-box risque-' + s.risque_color;
    document.getElementById('risqueLabel').textContent = s.risque;

    const descriptions = {
        critique: 'Niveau très élevé, évacuation recommandée.',
        eleve: 'Surveillance renforcée nécessaire.',
        modere: 'Niveau à surveiller.',
        faible: 'Aucun risque immédiat.'
    };

    document.getElementById('risqueDesc').textContent = descriptions[s.risque_color];

    const pct = Math.min(100, Math.round(s.last_niveau / 300 * 100));
    document.getElementById('risqueBarFill').style.width = pct + '%';
}

// ── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchData() {
    try {
        let url = '?api=1&period=' + currentPeriod;
        if (currentPeriod === 'day') url += '&date=' + currentDate;

        const res  = await fetch(url);
        const data = await res.json();

        updateCharts(data.chart);
        updateStats(data.stats);

        document.getElementById('status').textContent =
            'Mise à jour : ' + new Date().toLocaleTimeString();
    } catch(e) {
        document.getElementById('status').textContent = '⚠️ Erreur de connexion';
    }
}

function startLive() {
    fetchData();
    liveInterval = setInterval(fetchData, 1000);
}

function stopLive() {
    clearInterval(liveInterval);
    liveInterval = null;
}

// ── Boutons période ───────────────────────────────────────────────────────────
document.querySelectorAll('#periodGroup .btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#periodGroup .btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        currentPeriod = btn.dataset.period;

        document.getElementById('datePickerBox').style.display =
            currentPeriod === 'day' ? '' : 'none';

        stopLive();
        currentPeriod === 'live' ? startLive() : fetchData();
    });
});

// ── Boutons métrique ──────────────────────────────────────────────────────────
document.querySelectorAll('#metricGroup .btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#metricGroup .btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        currentMetric = btn.dataset.metric;
        applyMetricVisibility();
    });
});

// ── Date picker ───────────────────────────────────────────────────────────────
document.getElementById('datePicker').value = currentDate;

document.getElementById('datePicker').addEventListener('change', e => {
    currentDate = e.target.value;
    fetchData();
});

// ── Init ──────────────────────────────────────────────────────────────────────
applyMetricVisibility();
updateStats(INIT_STATS);
startLive();