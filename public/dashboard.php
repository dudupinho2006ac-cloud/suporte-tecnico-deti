<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
</head>
<body>
  <h1>Dashboard</h1>
  <p>Bem-vindo, <b><?= htmlspecialchars($user["nome"]) ?></b>!</p>
  <p>Tipo: <b><?= htmlspecialchars($user["tipo"]) ?></b></p>

  <p><a href="logout.php">Sair</a></p>
  <p><a href="index.php">Home</a></p>
</body>
</html>
