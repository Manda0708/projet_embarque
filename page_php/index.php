<?php
require_once 'function.php';

$chartData = prepareChartData(get_mesures($conn));
$stats     = get_stats($conn);

$trend_icons  = ['up' => '↑', 'down' => '↓', 'stable' => '→'];
$trend_labels = ['up' => 'En hausse', 'down' => 'En baisse', 'stable' => 'Stable'];

// Descriptions du risque (affichage uniquement)
$descriptions = [
    'critique' => 'Niveau très élevé, évacuation recommandée.',
    'eleve'    => 'Surveillance renforcée nécessaire.',
    'modere'   => 'Niveau à surveiller.',
    'faible'   => 'Aucun risque immédiat.'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Station Météo</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div class="header-left">
        <span class="header-icon">⛅</span>
        <div>
            <h1>Station Météo</h1>
            <span class="header-sub">Surveillance inondations</span>
        </div>
    </div>
    <div class="header-right">
        <div id="status">En attente…</div>

        <!-- Boutons de vue -->
        <div class="view-toggle">
            <button class="view-btn active" id="btnVueDetails" onclick="setView('details')">
                📊 Détails
            </button>
            <button class="view-btn" id="btnVueTendances" onclick="setView('tendances')">
                📈 Tendances
            </button>
        </div>
    </div>
</header>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- VUE DÉTAILS                                                               -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div id="vueDetails" class="dashboard">

    <div class="left">
        <div class="card">

            <div class="controls">
                <div>
                    <label>Période</label>
                    <div class="btn-group" id="periodGroup">
                        <button class="btn active" data-period="live">Temps réel</button>
                        <button class="btn" data-period="hour">1h</button>
                        <button class="btn" data-period="day">Jour</button>
                        <button class="btn" data-period="week">Semaine</button>
                    </div>
                </div>

                <div id="datePickerBox" style="display:none">
                    <label>Date</label>
                    <input type="date" id="datePicker">
                </div>

                <div>
                    <label>Afficher</label>
                    <div class="btn-group" id="metricGroup">
                        <button class="btn metric-all active" data-metric="all">Les trois</button>
                        <button class="btn metric-temp" data-metric="temp">Température</button>
                        <button class="btn metric-hum" data-metric="hum">Humidité</button>
                        <button class="btn metric-niveau" data-metric="niveau">Niveau d'eau</button>
                    </div>
                </div>
            </div>

            <div class="chart-box" id="boxNiveau">
                <h2>Niveau d'eau <span class="unit">cm</span></h2>
                <canvas id="chartNiveau" height="100"></canvas>
            </div>

            <div class="chart-box" id="boxTemp">
                <h2>Température <span class="unit">°C</span></h2>
                <canvas id="chartTemp" height="100"></canvas>
            </div>

            <div class="chart-box" id="boxHum">
                <h2>Humidité <span class="unit">%</span></h2>
                <canvas id="chartHum" height="100"></canvas>
            </div>

        </div>
    </div>

    <div class="right">

        <div class="card">
            <h3>Valeurs actuelles</h3>

            <div class="live-row">
                <span class="live-label temp-color">Température</span>
                <span class="live-val" id="liveTemp"><?= $stats['last_temp'] ?> °C</span>
            </div>

            <div class="live-row">
                <span class="live-label hum-color">Humidité</span>
                <span class="live-val" id="liveHum"><?= $stats['last_hum'] ?> %</span>
            </div>

            <div class="live-row">
                <span class="live-label niveau-color">Niveau d'eau</span>
                <span class="live-val" id="liveNiveau"><?= $stats['last_niveau'] ?> cm</span>
            </div>

            <div class="alert-badge <?= $stats['alert_eau'] ? 'alert-on' : 'alert-off' ?>" id="alertBadge">
                <?= $stats['alert_eau'] ? '⚠️ Risque inondation' : '✓ Niveau normal' ?>
            </div>
        </div>

        <div class="card">
            <h3 class="temp-color">Température</h3>
            <div class="stat-grid">
                <div class="stat"><div class="stat-label">Maximum</div><div class="stat-value" id="tempMax"><?= $stats['temp_max'] ?><span class="stat-unit">°C</span></div></div>
                <div class="stat"><div class="stat-label">Minimum</div><div class="stat-value" id="tempMin"><?= $stats['temp_min'] ?><span class="stat-unit">°C</span></div></div>
                <div class="stat full"><div class="stat-label">Moyenne</div><div class="stat-value" id="tempAvg"><?= $stats['temp_avg'] ?><span class="stat-unit">°C</span></div></div>
            </div>
        </div>

        <div class="card">
            <h3 class="hum-color">Humidité</h3>
            <div class="stat-grid">
                <div class="stat"><div class="stat-label">Maximum</div><div class="stat-value" id="humMax"><?= $stats['hum_max'] ?><span class="stat-unit">%</span></div></div>
                <div class="stat"><div class="stat-label">Minimum</div><div class="stat-value" id="humMin"><?= $stats['hum_min'] ?><span class="stat-unit">%</span></div></div>
                <div class="stat full"><div class="stat-label">Moyenne</div><div class="stat-value" id="humAvg"><?= $stats['hum_avg'] ?><span class="stat-unit">%</span></div></div>
            </div>
        </div>

        <div class="card">
            <h3 class="niveau-color">Niveau d'eau</h3>
            <div class="stat-grid">
                <div class="stat"><div class="stat-label">Maximum</div><div class="stat-value" id="niveauMax"><?= $stats['dist_max'] ?><span class="stat-unit">cm</span></div></div>
                <div class="stat"><div class="stat-label">Minimum</div><div class="stat-value" id="niveauMin"><?= $stats['dist_min'] ?><span class="stat-unit">cm</span></div></div>
                <div class="stat full"><div class="stat-label">Moyenne</div><div class="stat-value" id="niveauAvg"><?= $stats['dist_avg'] ?><span class="stat-unit">cm</span></div></div>
            </div>
        </div>

        <div class="card">
            <h3>Alertes récentes</h3>
            <div id="alertsList">
                <?php if(empty($stats['alerts'])): ?>
                    <p class="no-alerts">Aucune alerte</p>
                <?php else: foreach($stats['alerts'] as $a): ?>
                    <div class="alert-row">
                        <span>Niveau : <?= round($a['dist'], 1) ?> cm</span>
                        <span class="alert-time"><?= substr($a['datetime'], 11, 8) ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- VUE TENDANCES                                                             -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div id="vueTendances" class="dashboard" style="display:none">

    <div class="left">

        <!-- Indice de confort -->
        <div class="card">
            <h3>Indice de confort thermique</h3>
            <div class="comfort-box comfort-<?= $stats['heat_index']['color'] ?>" id="comfortBox">
                <div class="comfort-label" id="comfortLabel"><?= $stats['heat_index']['label'] ?></div>
                <div class="comfort-value" id="comfortValue"><?= $stats['heat_index']['value'] ?> °C</div>
                <div class="comfort-sub">Ressenti combiné temp + humidité</div>
            </div>
        </div>

        <!-- Tendances -->
        <div class="card">
            <h3>Tendances sur les 10 dernières mesures</h3>

            <div class="trend-block">
                <div class="trend-title temp-color">Température</div>
                <div class="trend-row">
                    <span class="trend-badge trend-<?= $stats['trend_temp']['direction'] ?>" id="trendTemp">
                        <?= $trend_icons[$stats['trend_temp']['direction']] ?>
                        <?= $trend_labels[$stats['trend_temp']['direction']] ?>
                    </span>
                    <span class="trend-slope">pente : <?= $stats['trend_temp']['slope'] ?> °C/mesure</span>
                </div>
            </div>

            <div class="trend-block">
                <div class="trend-title hum-color">Humidité</div>
                <div class="trend-row">
                    <span class="trend-badge trend-<?= $stats['trend_hum']['direction'] ?>" id="trendHum">
                        <?= $trend_icons[$stats['trend_hum']['direction']] ?>
                        <?= $trend_labels[$stats['trend_hum']['direction']] ?>
                    </span>
                    <span class="trend-slope">pente : <?= $stats['trend_hum']['slope'] ?> %/mesure</span>
                </div>
            </div>

            <div class="trend-block" style="border-bottom:none">
                <div class="trend-title niveau-color">Niveau d'eau</div>
                <div class="trend-row">
                    <span class="trend-badge trend-<?= $stats['trend_niveau']['direction'] ?>" id="trendNiveau">
                        <?= $trend_icons[$stats['trend_niveau']['direction']] ?>
                        <?= $trend_labels[$stats['trend_niveau']['direction']] ?>
                    </span>
                    <span class="trend-slope">pente : <?= $stats['trend_niveau']['slope'] ?> cm/mesure</span>
                </div>
            </div>
        </div>

        <!-- Anomalies -->
        <div class="card">
            <h3>Anomalies détectées <span class="anomaly-note">(écart &gt; 2σ)</span></h3>

            <div class="anomaly-block">
                <div class="anomaly-header">
                    <span class="temp-color">Température</span>
                    <span class="anomaly-count <?= $stats['anomalies_temp'] > 0 ? 'anomaly-on' : 'anomaly-off' ?>" id="anomalyTemp">
                        <?= $stats['anomalies_temp'] ?> anomalie<?= $stats['anomalies_temp'] > 1 ? 's' : '' ?>
                    </span>
                </div>
            </div>

            <div class="anomaly-block" style="border-bottom:none">
                <div class="anomaly-header">
                    <span class="niveau-color">Niveau d'eau</span>
                    <span class="anomaly-count <?= $stats['anomalies_niveau'] > 0 ? 'anomaly-on' : 'anomaly-off' ?>" id="anomalyNiveau">
                        <?= $stats['anomalies_niveau'] ?> anomalie<?= $stats['anomalies_niveau'] > 1 ? 's' : '' ?>
                    </span>
                </div>
            </div>
        </div>

    </div>

    <div class="right">

        <!-- Risque inondation -->
        <div class="card">
            <h3>Risque inondation</h3>

            <div class="risque-box risque-<?= $stats['risque_color'] ?>" id="risqueBox">
                <div class="risque-label" id="risqueLabel"><?= $stats['risque'] ?></div>
                <div class="risque-niveau">
                    Distance actuelle par rapport au capteur : <span id="risqueNiveau"><?= $stats['last_niveau'] ?></span> cm
                </div>
                <div class="risque-desc" id="risqueDesc">
                    <?= $descriptions[$stats['risque_color']] ?>
                </div>
            </div>

            <div class="risque-bar-wrap">
                <div class="risque-bar-track">
                    <div class="risque-bar-fill" id="risqueBarFill"
                    width: <?= min(100, round(($stats['last_niveau'] / 300) * 100)) ?>%></div>
                </div>
                <div class="risque-bar-labels">
                    <span>0</span><span>150</span><span>200</span><span>250</span><span>300+</span>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    const INIT_DATA  = <?php echo json_encode($chartData); ?>;
    const INIT_STATS = <?php echo json_encode($stats); ?>;
</script>

<script src="chart.js"></script>
</body>
</html>