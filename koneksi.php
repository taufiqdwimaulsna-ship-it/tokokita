<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_tokokita";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi ke database db_tokokita gagal: " . mysqli_connect_error());
}
?>