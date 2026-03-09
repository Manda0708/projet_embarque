<?php
// bdd.php
// Connexion MySQL avec mysqli

$host = "localhost";
$user = "root";
$pass = "ton_mdp";
$dbname = "ton_db";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Erreur de connexion à la base de données : " . mysqli_connect_error());
}
?>