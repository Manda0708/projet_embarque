<?php
// bdd.php
// Connexion MySQL avec mysqli

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "capteurs_bd";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Erreur de connexion à la base de données : " . mysqli_connect_error());
}
?>