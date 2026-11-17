<?php
// Koneksi database (PDO). Sesuaikan user/password dengan server lokal Anda.
$host = 'localhost';
$db   = 'webgis';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Koneksi database gagal: ' . htmlspecialchars($e->getMessage()));
}