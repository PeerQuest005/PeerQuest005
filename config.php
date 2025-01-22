<?php
// Set the default timezone for PHP
date_default_timezone_set('Asia/Singapore');

$host = '152.42.235.233';
$db = 'crud_db';
$user = 'root';
$pass = '1ngbaiF()_WE()R)(_#IOfvj';

try {
    // Create the PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set the timezone for MySQL
    $pdo->exec("SET time_zone = '+08:00'"); // Singapore Standard Time (UTC+8)
} catch (PDOException $e) {
    die("Could not connect to the database $db :" . $e->getMessage());
}
?>
