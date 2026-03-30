let currentPeriod = 'live';
let currentMetric = 'all';
let currentDate   = new Date().toISOString().split('T')[0];
let liveInterval  = null;

// Plugin : valeur actuelle à droite de chaque courbess
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

// Création des charts
function makeChart(canvasId, labels, data, color, unit, ymin, ymax) {
    return new Chart(document.getElementById(canvasId).getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data,
                borderColor: color,
                backgroundColor: color + '22',
                fill: true,
                tension: 0.3,
                pointRadius: 2
            }]
        },
        options: {
            responsive: true,
            animation: false,
            plugins: { legend: { display: false } },
            layout: { padding: { right: 55 } },
            scales: {
                x: {
                    display: true,
                    title: { display: true, text: 'Temps' },
                    ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12 }
                },
                y: {
                    display: true,
                    title: { display: true, text: unit },
                    min: ymin,
                    max: ymax,
                    grace: '10%'
                }
            }
        }
    });
}

const chartTemp = makeChart('chartTemp', INIT_DATA.dates, INIT_DATA.temps,      '#e74c3c', '°C', 0,   40);
const chartHum  = makeChart('chartHum',  INIT_DATA.dates, INIT_DATA.humidites,  '#27ae60', '%',  0,  100);
const chartDist = makeChart('chartDist', INIT_DATA.dates, INIT_DATA.distances,  '#2980b9', 'cm', 0,  400);

// // création des graphes
// function makeChart(canvasId, labels, data, color, unit) {
//     return new Chart(document.getElementById(canvasId).getContext('2d'), {
//         type: 'line',
//         data: {
//             labels,
//             datasets: [{
//                 data,
//                 borderColor: color,
//                 backgroundColor: color + '22',
//                 fill: true,
//                 tension: 0.3,
//                 pointRadius: 2
//             }]
//         },
//         options: {
//             responsive: true,
//             animation: false,
//             plugins: { legend: { display: false } },
//             layout: { padding: { right: 50 } },
//             scales: {
//                 x: {
//                     display: true,
//                     ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 10, font: { size: 10 } }
//                 },
//                 y: {
//                     display: true,
//                     title: { display: true, text: unit, font: { size: 10 } },
//                     grace: '10%'
//                 }
//             }
//         }
//     });
// }

// const chartTemp = makeChart('chartTemp', INIT_DATA.dates, INIT_DATA.temps,      '#e74c3c', '°C');
// const chartHum  = makeChart('chartHum',  INIT_DATA.dates, INIT_DATA.humidites,  '#27ae60', '%');
// const chartDist = makeChart('chartDist', INIT_DATA.dates, INIT_DATA.distances,  '#2980b9', 'cm');

// Visibilité des graphiques (JS dans html)
function applyMetricVisibility() {
    document.getElementById('boxTemp').style.display = ['all','temp'].includes(currentMetric) ? '' : 'none';
    document.getElementById('boxHum').style.display  = ['all','hum'].includes(currentMetric)  ? '' : 'none';
    document.getElementById('boxDist').style.display = ['all','dist'].includes(currentMetric) ? '' : 'none';
}

// Mise à jour charts 
function updateCharts(chart_data) {
    chartTemp.data.labels           = chart_data.dates;
    chartTemp.data.datasets[0].data = chart_data.temps;
    chartHum.data.labels            = chart_data.dates;
    chartHum.data.datasets[0].data  = chart_data.humidites;
    chartDist.data.labels           = chart_data.dates;
    chartDist.data.datasets[0].data = chart_data.distances;
    chartTemp.update('none');
    chartHum.update('none');
    chartDist.update('none');
}

// Mise à jour stats
function updateStats(s) {
    document.getElementById('liveTemp').textContent = s.last_temp + ' °C';
    document.getElementById('liveHum').textContent  = s.last_hum  + ' %';
    document.getElementById('liveDist').textContent = s.last_dist + ' cm';

    document.getElementById('tempMax').innerHTML = s.temp_max + '<span class="stat-unit">°C</span>';
    document.getElementById('tempMin').innerHTML = s.temp_min + '<span class="stat-unit">°C</span>';
    document.getElementById('tempAvg').innerHTML = s.temp_avg + '<span class="stat-unit">°C</span>';
    document.getElementById('humMax').innerHTML  = s.hum_max  + '<span class="stat-unit">%</span>';
    document.getElementById('humMin').innerHTML  = s.hum_min  + '<span class="stat-unit">%</span>';
    document.getElementById('humAvg').innerHTML  = s.hum_avg  + '<span class="stat-unit">%</span>';
    document.getElementById('distMax').innerHTML = s.dist_max + '<span class="stat-unit">cm</span>';
    document.getElementById('distMin').innerHTML = s.dist_min + '<span class="stat-unit">cm</span>';
    document.getElementById('distAvg').innerHTML = s.dist_avg + '<span class="stat-unit">cm</span>';

    const badge = document.getElementById('alertBadge');
    badge.className = 'alert-badge ' + (s.alert ? 'alert-on' : 'alert-off');
    badge.textContent = s.alert ? 'Objet trop proche' : 'Distance normale';

    const list = document.getElementById('alertsList');
    if(s.alerts.length === 0){
        list.innerHTML = '<p class="no-alerts">Aucune alerte</p>';
    } else {
        list.innerHTML = s.alerts.map(a =>
            `<div class="alert-row">
                <span>Distance : ${a.dist} cm</span>
                <span class="alert-time">${a.datetime.substring(11, 19)}</span>
            </div>`
        ).join('');
    }
}

// Fetch des data avec JSON
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
        document.getElementById('status').textContent = 'Erreur de connexion';
    }
}

// Live toutes les secondes
function startLive() { fetchData(); liveInterval = setInterval(fetchData, 1000); }
function stopLive()  { clearInterval(liveInterval); liveInterval = null; }

// Boutons pour les filtres par période
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

// Boutons pour les filtres de courbes
document.querySelectorAll('#metricGroup .btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#metricGroup .btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentMetric = btn.dataset.metric;
        applyMetricVisibility();
    });
});

// Date picker
document.getElementById('datePicker').value = currentDate;
document.getElementById('datePicker').addEventListener('change', e => {
    currentDate = e.target.value;
    fetchData();
});

// initialisation
applyMetricVisibility();
updateStats(INIT_STATS);
startLive();