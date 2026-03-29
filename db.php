<?php
// db.php — Database Connection
// MediQueue v3

$host     = "127.0.0.1";
$user     = "root";
$password = "";
$database = "clinic_queue";
$port     = 3306;

$conn = mysqli_connect($host, $user, $password, $database, $port);

if (!$conn) {
    die("
        <h2 style='font-family:sans-serif;color:red;padding:30px'>
            ❌ Database connection failed: " . mysqli_connect_error() . "
            <br><br>
            <small>Make sure MySQL is running in XAMPP and you have run setup.sql</small>
        </h2>
    ");
}
