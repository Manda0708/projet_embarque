<?php
require_once 'function.php';
$chartData = prepareChartData(get_mesures($conn));
$stats     = get_stats($conn);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Arduino</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Dashboard Arduino</h1>
    <div id="status">En attente…</div>
</header>

<div class="dashboard">

    <!-- ── Colonne gauche : graphiques + filtres ────────────────────────────── -->
    <div class="left">
        <div class="card">

            <div class="controls">
                <div>
                    <label>Période</label>
                    <div class="btn-group" id="periodGroup">
                        <button class="btn active" data-period="live">Temps réel</button>
                        <!-- <button class="btn" data-period="hour">1h</button>
                        <button class="btn" data-period="day">Jour</button>
                        <button class="btn" data-period="week">Semaine</button> -->
                    </div>
                </div>
                <div id="datePickerBox" style="display:none">
                    <label>Date</label>
                    <input type="date" id="datePicker">
                </div>
                <div>
                    <label>Afficher</label>
                    <div class="btn-group" id="metricGroup">
                        <button class="btn metric-all  active" data-metric="all">Les trois</button>
                        <button class="btn metric-temp"        data-metric="temp">Température</button>
                        <button class="btn metric-hum"         data-metric="hum">Humidité</button>
                        <button class="btn metric-dist"        data-metric="dist">Distance</button>
                    </div>
                </div>
            </div>

            <div class="chart-box" id="boxDist">
                <h2>Distance <span class="unit">cm</span></h2>
                <canvas id="chartDist" height="100"></canvas>
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

    <!--Colonne droite : stats + alertes -->
    <div class="right">

        <!-- Valeurs actuelles -->
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
                <span class="live-label dist-color">Distance</span>
                <span class="live-val" id="liveDist"><?= $stats['last_dist'] ?> cm</span>
            </div>
            <div class="alert-badge <?= $stats['alert'] ? 'alert-on' : 'alert-off' ?>" id="alertBadge">
                <?= $stats['alert'] ? 'Objet trop proche' : 'Distance normale' ?>
            </div>
        </div>

        <!-- Stats température -->
        <div class="card">
            <h3 class="temp-color">Température</h3>
            <div class="stat-grid">
                <div class="stat">
                    <div class="stat-label">Maximum</div>
                    <div class="stat-value" id="tempMax"><?= $stats['temp_max'] ?><span class="stat-unit">°C</span></div>
                </div>
                <div class="stat">
                    <div class="stat-label">Minimum</div>
                    <div class="stat-value" id="tempMin"><?= $stats['temp_min'] ?><span class="stat-unit">°C</span></div>
                </div>
                <div class="stat full">
                    <div class="stat-label">Moyenne</div>
                    <div class="stat-value" id="tempAvg"><?= $stats['temp_avg'] ?><span class="stat-unit">°C</span></div>
                </div>
            </div>
        </div>

        <!-- Stats humidité -->
        <div class="card">
            <h3 class="hum-color">Humidité</h3>
            <div class="stat-grid">
                <div class="stat">
                    <div class="stat-label">Maximum</div>
                    <div class="stat-value" id="humMax"><?= $stats['hum_max'] ?><span class="stat-unit">%</span></div>
                </div>
                <div class="stat">
                    <div class="stat-label">Minimum</div>
                    <div class="stat-value" id="humMin"><?= $stats['hum_min'] ?><span class="stat-unit">%</span></div>
                </div>
                <div class="stat full">
                    <div class="stat-label">Moyenne</div>
                    <div class="stat-value" id="humAvg"><?= $stats['hum_avg'] ?><span class="stat-unit">%</span></div>
                </div>
            </div>
        </div>

        <!-- Stats distance -->
        <div class="card">
            <h3 class="dist-color">Distance</h3>
            <div class="stat-grid">
                <div class="stat">
                    <div class="stat-label">Maximum</div>
                    <div class="stat-value" id="distMax"><?= $stats['dist_max'] ?><span class="stat-unit">cm</span></div>
                </div>
                <div class="stat">
                    <div class="stat-label">Minimum</div>
                    <div class="stat-value" id="distMin"><?= $stats['dist_min'] ?><span class="stat-unit">cm</span></div>
                </div>
                <div class="stat full">
                    <div class="stat-label">Moyenne</div>
                    <div class="stat-value" id="distAvg"><?= $stats['dist_avg'] ?><span class="stat-unit">cm</span></div>
                </div>
            </div>
        </div>

        <!-- Alertes récentes -->
        <div class="card">
            <h3>Alertes récentes</h3>
            <div id="alertsList">
                <?php if(empty($stats['alerts'])): ?>
                    <p class="no-alerts">Aucune alerte</p>
                <?php else: foreach($stats['alerts'] as $a): ?>
                    <div class="alert-row">
                        <span>Distance : <?= round($a['dist'], 1) ?> cm</span>
                        <span class="alert-time"><?= substr($a['datetime'], 11, 8) ?></span>
                    </div>
                <?php endforeach; endif; ?>
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