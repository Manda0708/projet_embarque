<?php
require_once 'bdd.php';

//Requêtes génériques
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

// Récupération des mesures
function get_mesures($conn) {
    return array_reverse(query($conn, "SELECT * FROM v_last10"));
}

function get_mesures_by_period($conn, $period, $date = null) {
    if($period === 'live') return get_mesures($conn);
    if($period === 'hour') return query($conn, "SELECT * FROM mesures WHERE datetime >= NOW() - INTERVAL 1 HOUR ORDER BY id ASC");
    if($period === 'week') return query($conn, "SELECT * FROM mesures WHERE datetime >= NOW() - INTERVAL 7 DAY ORDER BY id ASC");
    if($period === 'day') {
        $d = $date ? mysqli_real_escape_string($conn, $date) : date('Y-m-d');
        return query($conn, "SELECT * FROM mesures WHERE DATE(datetime) = '$d' ORDER BY id ASC");
    }
    return get_mesures($conn);
}

function get_last_mesure($conn) {
    return query_one($conn, "SELECT * FROM v_last1");
}

// Préparation des données pour les graphiques
function prepareChartData($mesures) {
    $dates = []; $temps = []; $humidites = []; $niveaux = [];
    $anomalies_temp = []; $anomalies_niveau = [];

    $temps_vals   = array_column($mesures, 'temp');
    $niveaux_vals = array_column($mesures, 'dist');

    $avg_temp   = count($temps_vals)   ? array_sum($temps_vals)   / count($temps_vals)   : 0;
    $avg_niveau = count($niveaux_vals) ? array_sum($niveaux_vals) / count($niveaux_vals) : 0;

    $std_temp   = calcul_std($temps_vals,   $avg_temp);
    $std_niveau = calcul_std($niveaux_vals, $avg_niveau);

    foreach($mesures as $row) {
        $dates[]     = $row['datetime'];
        $temps[]     = $row['temp'];
        $humidites[] = $row['hum'];
        $niveaux[]   = $row['dist'];

        $anomalies_temp[]   = abs($row['temp'] - $avg_temp)   > 2 * $std_temp   ? $row['temp'] : null;
        $anomalies_niveau[] = ($row['dist'] < 100) ? $row['dist'] : null;
    }

    return [
        'dates'            => $dates,
        'temps'            => $temps,
        'humidites'        => $humidites,
        'niveaux'          => $niveaux,
        'anomalies_temp'   => $anomalies_temp,
        'anomalies_niveau' => $anomalies_niveau,
    ];
}

// Calculs statistiques

/*
 * ÉCART-TYPE (standard deviation)
 * Mesure la dispersion des valeurs autour de la moyenne.
 * Plus l'écart-type est grand, plus les valeurs sont dispersées.
 *
 * Formule :
 *   σ = √( Σ(xi - x̄)² / n )
 *
 *   xi  = chaque valeur
 *   x̄   = moyenne de toutes les valeurs
 *   n   = nombre de valeurs
 *
 * Exemple : valeurs [18, 19, 20, 19, 21]
 *   moyenne = 19.4
 *   variance = ((18-19.4)² + (19-19.4)² + ...) / 5 = 0.84
 *   écart-type = √0.84 ≈ 0.92
 *   → une valeur à 22°C serait à (22-19.4)/0.92 ≈ 2.8σ → anomalie détectée
 */
function calcul_std($values, $avg) {
    if(count($values) < 2) return 0;
    $variance = array_sum(array_map(fn($v) => pow($v - $avg, 2), $values)) / count($values);
    return sqrt($variance);
}

/*
 * TENDANCE par régression linéaire simple
 * ─────────────────────────────────────────────────────────────────────────────
 * Calcule la "pente" d'une droite qui passe au mieux à travers toutes
 * les mesures dans le temps. Cela indique si la valeur monte, descend
 * ou reste stable globalement.
 *
 * Formule de la pente (slope) :
 *   slope = Σ( (xi - x̄)(yi - ȳ) ) / Σ( (xi - x̄)² )
 *
 *   xi  = indice de la mesure (0, 1, 2, 3...)
 *   x̄   = moyenne des indices
 *   yi  = valeur de la mesure (temp, hum, dist...)
 *   ȳ   = moyenne des valeurs
 *
 * Interprétation de la pente :
 *   slope > +0.1  → tendance à la HAUSSE  ↑
 *   slope < -0.1  → tendance à la BAISSE  ↓
 *   entre les deux → STABLE               →
 *
 * Exemple : températures [18, 19, 19.5, 20, 21]
 *   La droite monte → slope ≈ +0.75 → direction = 'up'
 */
function calcul_trend($values) {
    $n = count($values);
    if($n < 2) return ['direction' => 'stable', 'slope' => 0];

    $x_avg = ($n - 1) / 2;
    $y_avg = array_sum($values) / $n;

    $num = 0; $den = 0;
    foreach($values as $i => $v) {
        $num += ($i - $x_avg) * ($v - $y_avg);
        $den += pow($i - $x_avg, 2);
    }

    $slope = $den != 0 ? $num / $den : 0;

    if     ($slope >  0.1) $direction = 'up';
    elseif ($slope < -0.1) $direction = 'down';
    else                   $direction = 'stable';

    return ['direction' => $direction, 'slope' => round($slope, 3)];
}

/*
 * INDICE DE CONFORT THERMIQUE (Heat Index)
 * ─────────────────────────────────────────────────────────────────────────────
 * Calcule la température RESSENTIE en combinant la température réelle
 * et l'humidité relative. Plus l'humidité est élevée, plus il fait
 * chaud "ressenti" car la transpiration s'évapore moins bien.
 *
 * Formule de Steadman (version polynomiale à 9 termes) :
 *   HI = c1 + c2*T + c3*H + c4*T*H + c5*T² + c6*H² + c7*T²*H + c8*T*H² + c9*T²*H²
 *
 *   T  = température en °C
 *   H  = humidité relative en %
 *   c1..c9 = constantes empiriques de Steadman
 *
 * Seuils de confort :
 *   HI < 27°C  → Confortable  (vert)
 *   HI < 32°C  → Chaud        (orange)
 *   HI < 38°C  → Très chaud   (rouge)
 *   HI >= 38°C → Étouffant    (danger)
 */
function calcul_heat_index($temp, $hum) {
    $hi = -8.784695
        + 1.61139411 * $temp
        + 2.3385489  * $hum
        - 0.14611605 * $temp * $hum
        - 0.012308094 * pow($temp, 2)
        - 0.016424828 * pow($hum,  2)
        + 0.002211732 * pow($temp, 2) * $hum
        + 0.00072546  * $temp * pow($hum, 2)
        - 0.000003582 * pow($temp, 2) * pow($hum, 2);

    if     ($hi < 27) return ['value' => round($hi, 1), 'label' => 'Confortable', 'color' => 'green'];
    elseif ($hi < 32) return ['value' => round($hi, 1), 'label' => 'Chaud',       'color' => 'orange'];
    elseif ($hi < 38) return ['value' => round($hi, 1), 'label' => 'Très chaud',  'color' => 'red'];
    else              return ['value' => round($hi, 1), 'label' => 'Étouffant',   'color' => 'danger'];
}

/*
 * COMPTAGE DES ANOMALIES
 * ─────────────────────────────────────────────────────────────────────────────
 * Une valeur est considérée comme anomalie si elle s'écarte de plus
 * de 2 écarts-types (2σ) par rapport à la moyenne.
 *
 * Règle des 2σ :
 *   Dans une distribution normale, ~95% des valeurs se trouvent
 *   dans l'intervalle [moyenne - 2σ, moyenne + 2σ].
 *   Toute valeur en dehors de cet intervalle est statistiquement
 *   peu probable → considérée comme anomalie.
 *
 *   Condition : |valeur - moyenne| > 2 × écart-type
 *
 * Exemple : moyenne = 19°C, écart-type = 0.5°C
 *   Intervalle normal : [18°C, 20°C]
 *   Une valeur à 21.5°C → |21.5 - 19| = 2.5 > 2×0.5 = 1 → anomalie
 */
function count_anomalies($values, $avg, $std) {
    return count(array_filter($values, fn($v) => abs($v - $avg) > 2 * $std));
}

// Statistiques par vue/période
function get_stats_view($conn, $period) {
    $views = [
        'live' => 'v_stats_live',
        'hour' => 'v_stats_hour',
        'week' => 'v_stats_week',
    ];
    return isset($views[$period])
        ? query_one($conn, "SELECT * FROM {$views[$period]}")
        : null;
}

function get_stats_day($conn, $date = null) {
    $d = $date ? mysqli_real_escape_string($conn, $date) : date('Y-m-d');
    return query_one($conn, "SELECT
        MAX(temp) as temp_max, MIN(temp) as temp_min, ROUND(AVG(temp),1) as temp_avg,
        MAX(hum)  as hum_max,  MIN(hum)  as hum_min,  ROUND(AVG(hum), 1) as hum_avg,
        MAX(dist) as dist_max, MIN(dist) as dist_min,  ROUND(AVG(dist),1) as dist_avg
        FROM mesures WHERE DATE(datetime) = '$d'"
    );
}

function format_stats($row) {
    return [
        'temp_max' => round($row['temp_max'], 1), 'temp_min' => round($row['temp_min'], 1), 'temp_avg' => round($row['temp_avg'], 1),
        'hum_max'  => round($row['hum_max'],  1), 'hum_min'  => round($row['hum_min'],  1), 'hum_avg'  => round($row['hum_avg'],  1),
        'dist_max' => round($row['dist_max'], 1), 'dist_min' => round($row['dist_min'], 1), 'dist_avg' => round($row['dist_avg'], 1),
    ];
}

/*
 * FORMAT DE LA DERNIÈRE MESURE + NIVEAU D'EAU
 * ─────────────────────────────────────────────────────────────────────────────
 * Logique du capteur ultrason placé EN HAUT (ex: sous un pont, au dessus de l'eau) :
 *
 *   distance GRANDE  →  eau LOIN du capteur  →  niveau BAS   →  pas de danger
 *   distance PETITE  →  eau PRÈS du capteur  →  niveau HAUT  →  danger inondation
 *
 *                    [capteur ultrason]
 *                          |
 *                          | ← distance mesurée
 *                          |
 *   ══════════════════ surface de l'eau ══════════════════
 *
 *   Seuils d'alerte (distance en cm) :
 *     dist < 50  → Critique  (eau très proche du capteur = inondation)
 *     dist < 100 → Élevé
 *     dist < 150 → Modéré
 *     dist >= 150 → Faible   (eau loin = niveau normal)
 */
function format_last($conn) {
    $last = query_one($conn, "SELECT * FROM v_last1");
    $hi   = calcul_heat_index($last['temp'], $last['hum']);

    // Plus la distance est petite, plus le niveau d'eau est dangereux
    $dist = $last['dist'];
    if     ($dist < 50)  { $risque = 'Critique'; $risque_color = 'critique'; }
    elseif ($dist < 100) { $risque = 'Élevé';    $risque_color = 'eleve';    }
    elseif ($dist < 150) { $risque = 'Modéré';   $risque_color = 'modere';   }
    else                 { $risque = 'Faible';   $risque_color = 'faible';   }

    return [
        'last_temp'    => round($last['temp'], 1),
        'last_hum'     => round($last['hum'],  1),
        'last_niveau'  => $dist,
        'alert_eau'    => $dist < 100,        // alerte si distance < 100 cm
        'risque'       => $risque,
        'risque_color' => $risque_color,
        'heat_index'   => $hi,
    ];
}

/*
 * TENDANCES + ANOMALIES sur les 10 dernières mesures
 * ─────────────────────────────────────────────────────────────────────────────
 * Pour le niveau d'eau, une tendance 'down' (distance qui diminue)
 * signifie que l'eau MONTE → c'est le signal le plus important à surveiller.
 */
function format_trends($conn) {
    $rows    = query($conn, "SELECT temp, hum, dist FROM v_last10");
    $temps   = array_column($rows, 'temp');
    $hums    = array_column($rows, 'hum');
    $niveaux = array_column($rows, 'dist');

    $avg_temp   = array_sum($temps)   / max(count($temps),   1);
    $avg_niveau = array_sum($niveaux) / max(count($niveaux), 1);
    $std_temp   = calcul_std($temps,   $avg_temp);
    $std_niveau = calcul_std($niveaux, $avg_niveau);

    $trend_niveau_raw = calcul_trend($niveaux);

    /*
     * INVERSION de la tendance du niveau d'eau pour l'affichage :
     * Le capteur mesure une DISTANCE qui DIMINUE quand l'eau monte.
     * On inverse donc le sens pour que l'affichage soit intuitif :
     *   distance ↓  (slope négatif)  →  eau ↑  →  on affiche 'up'   (danger)
     *   distance ↑  (slope positif)  →  eau ↓  →  on affiche 'down' (normal)
     */
    $inv = ['up' => 'down', 'down' => 'up', 'stable' => 'stable'];
    $trend_niveau_affichage = [
        'direction' => $inv[$trend_niveau_raw['direction']],
        'slope'     => $trend_niveau_raw['slope'] * -1,  // pente inversée
    ];

    return [
        'trend_temp'       => calcul_trend($temps),
        'trend_hum'        => calcul_trend($hums),
        'trend_niveau'     => $trend_niveau_affichage,   // tendance de l'EAU (pas de la distance)
        'trend_niveau_raw' => $trend_niveau_raw,         // tendance brute de la distance (pour debug)
        'anomalies_temp'   => count_anomalies($temps,   $avg_temp,   $std_temp),
        'anomalies_niveau' => count_anomalies($niveaux, $avg_niveau, $std_niveau),
    ];
}

// Alertes (distance < 100 cm = eau dangereusement proche)
function get_alerts($conn) {
    return query($conn,
        "SELECT dist, datetime FROM mesures WHERE dist < 100 ORDER BY id DESC LIMIT 5"
    );
}

// Fonction principale des statistiques
function get_stats($conn, $period = 'live', $date = null) {
    $row = $period === 'day' ? get_stats_day($conn, $date) : get_stats_view($conn, $period);
    return array_merge(
        format_stats($row),
        format_last($conn),
        format_trends($conn),
        ['alerts' => get_alerts($conn)]
    );
}

//API JSON
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