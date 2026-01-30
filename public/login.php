<?php
session_start();
require_once __DIR__ . "/../config/db.php";

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if ($senha === "" || ($email === "" && $telefone === "")) {
        $erro = "Informe email OU telefone, e a senha.";
    } else {
        if ($email !== "") {
            $stmt = $pdo->prepare("SELECT id, nome, email, telefone, senha_hash, tipo, ativo FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
        } else {
            $stmt = $pdo->prepare("SELECT id, nome, email, telefone, senha_hash, tipo, ativo FROM usuarios WHERE telefone = ? LIMIT 1");
            $stmt->execute([$telefone]);
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $erro = "Usuário não encontrado.";
        } elseif ((int)$user["ativo"] !== 1) {
            $erro = "Usuário desativado.";
        } elseif (!password_verify($senha, $user["senha_hash"])) {
            $erro = "Senha incorreta.";
        } else {
            $_SESSION["user"] = [
                "id" => (int)$user["id"],
                "nome" => $user["nome"],
                "tipo" => $user["tipo"],
                "email" => $user["email"],
                "telefone" => $user["telefone"]
            ];
            header("Location: dashboard.php");
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Login</title>
</head>
<body>
  <h1>Login</h1>

  <?php if ($erro): ?>
    <p style="color:red"><?= htmlspecialchars($erro) ?></p>
  <?php endif; ?>

  <form method="post">
    <p>Você pode entrar com <b>email</b> ou <b>telefone</b>.</p>

    <label>Email (opcional):</label><br>
    <input name="email" type="email"><br><br>

    <label>Telefone (opcional):</label><br>
    <input name="telefone"><br><br>

    <label>Senha:</label><br>
    <input name="senha" type="password" required><br><br>

    <button type="submit">Entrar</button>
  </form>

  <p><a href="cadastro.php">Não tenho conta → Cadastrar</a></p>
  <p><a href="index.php">Voltar</a></p>
</body>
</html>
