<?php
// function.php
require_once 'bdd.php';

// Fonction pour récupérer toutes les mesures
function get_mesures($conn){
    $query = "SELECT * FROM mesures ORDER BY id ASC"; // ou ORDER BY date_heure ASC
    $res = mysqli_query($conn, $query);

    $mesures = [];
    while($row = mysqli_fetch_assoc($res)){
        $mesures[] = $row;
    }
    return $mesures;
}

// Préparer les données pour Chart.js
function prepareChartData($mesures){
    $dates = [];
    $temps = [];
    $distances = [];

    foreach($mesures as $row){
        $dates[] = $row['date_heure'];
        $temps[] = $row['temperature'];
        $distances[] = $row['distance'];
    }

    return [
        'dates' => $dates,
        'temps' => $temps,
        'distances' => $distances
    ];
}
?>