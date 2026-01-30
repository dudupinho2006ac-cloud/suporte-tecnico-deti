<?php
$host = "localhost";
$dbname = "suporte_tecnico";
$user = "root";
$pass = ""; // XAMPP normalmente é vazio

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar no banco: " . $e->getMessage());
}
