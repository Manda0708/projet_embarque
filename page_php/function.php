<?php
require_once 'bdd.php';

// Requête générique 
function query($conn, $sql) {
    $res  = mysqli_query($conn, $sql);
    $rows = [];
    while($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    return $rows;
}

function query_one($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($res);
}

// Mesures
function get_mesures($conn) {
    return array_reverse(query($conn, "SELECT * FROM v_last10"));
}

//filtrer les mesures par périodes
function get_mesures_by_period($conn, $period, $date = null) {
    if($period === 'live') return get_mesures($conn);
    if($period === 'hour') return query($conn, "SELECT * FROM mesures WHERE datetime >= NOW() - INTERVAL 1 HOUR ORDER BY id ASC");
    if($period === 'week') return query($conn, "SELECT * FROM mesures WHERE datetime >= NOW() - INTERVAL 7 DAY ORDER BY id ASC");
    if($period === 'day')  {
        $d = $date ? mysqli_real_escape_string($conn, $date) : date('Y-m-d');
        return query($conn, "SELECT * FROM mesures WHERE DATE(datetime) = '$d' ORDER BY id ASC");
    }
    return get_mesures($conn);
}

// préparation des courbes
function prepareChartData($mesures) {
    $dates = []; $temps = []; $humidites = []; $distances = [];
    foreach($mesures as $row) {
        $dates[]     = $row['datetime'];
        $temps[]     = $row['temp'];
        $humidites[] = $row['hum'];
        $distances[] = $row['dist'];
    }
    return ['dates' => $dates, 'temps' => $temps, 'humidites' => $humidites, 'distances' => $distances];
}

// Statistiques
function get_stats_view($conn, $period) {
    $views = [
        'live' => 'v_stats_live',
        'hour' => 'v_stats_hour',
        'week' => 'v_stats_week',
    ];
    // Pour 'day', pas de vue (date dynamique) → requête directe
    return isset($views[$period])
        ? query_one($conn, "SELECT * FROM {$views[$period]}")
        : null;
}

//prendre statistiques par jour
function get_stats_day($conn, $date = null) {
    $d = $date ? mysqli_real_escape_string($conn, $date) : date('Y-m-d');
    return query_one($conn, "SELECT
        MAX(temp) as temp_max, MIN(temp) as temp_min, ROUND(AVG(temp),1) as temp_avg,
        MAX(hum)  as hum_max,  MIN(hum)  as hum_min,  ROUND(AVG(hum), 1) as hum_avg,
        MAX(dist) as dist_max, MIN(dist) as dist_min,  ROUND(AVG(dist),1) as dist_avg
        FROM mesures WHERE DATE(datetime) = '$d'"
    );
}

//format des statistiques
function format_stats($row) {
    return [
        'temp_max' => round($row['temp_max'], 1), 'temp_min' => round($row['temp_min'], 1), 'temp_avg' => round($row['temp_avg'], 1),
        'hum_max'  => round($row['hum_max'],  1), 'hum_min'  => round($row['hum_min'],  1), 'hum_avg'  => round($row['hum_avg'],  1),
        'dist_max' => round($row['dist_max'], 1), 'dist_min' => round($row['dist_min'], 1), 'dist_avg' => round($row['dist_avg'], 1),
    ];
}

//format de la dernière donnée
function format_last($conn) {
    $last = query_one($conn, "SELECT * FROM v_last1");
    return [
        'last_temp' => round($last['temp'], 1),
        'last_hum'  => round($last['hum'],  1),
        'last_dist' => round($last['dist'], 1),
        'alert'     => $last['dist'] < 5,
    ];
}

function get_alerts($conn) {
    return query($conn, "SELECT * FROM v_alerts");
}

//fonction principale pour prendre les statistiques
function get_stats($conn, $period = 'live', $date = null) {
    $row = $period === 'day'
        ? get_stats_day($conn, $date)
        : get_stats_view($conn, $period);

    return array_merge(
        format_stats($row),
        format_last($conn),
        ['alerts' => get_alerts($conn)]
    );
}

// API JSON
function handle_api($conn) {
    header('Content-Type: application/json');
    $period  = $_GET['period'] ?? 'live';
    $date    = $_GET['date']   ?? null;
    $mesures = get_mesures_by_period($conn, $period, $date);
    echo json_encode([
        'chart' => prepareChartData($mesures),
        'stats' => get_stats($conn, $period, $date)
    ]);
    exit;
}

if(isset($_GET['api'])) handle_api($conn);
?>