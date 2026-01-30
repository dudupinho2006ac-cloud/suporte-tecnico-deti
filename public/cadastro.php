<?php
session_start();
require_once __DIR__ . "/../config/db.php";

$erro = "";
$ok = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST["nome"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $senha = $_POST["senha"] ?? "";
    $tipo = $_POST["tipo"] ?? "cliente"; // cliente ou tecnico

    if ($nome === "" || $telefone === "" || $senha === "") {
        $erro = "Preencha nome, telefone e senha.";
    } elseif (!in_array($tipo, ["cliente", "tecnico"], true)) {
        $erro = "Tipo inválido.";
    } else {
        if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "Email inválido.";
        } else {
            if ($email !== "") {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $erro = "Esse email já está cadastrado.";
                }
            }

            if ($erro === "") {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, telefone, senha_hash, tipo) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $nome,
                    $email !== "" ? $email : null,
                    $telefone,
                    $senha_hash,
                    $tipo
                ]);

                $novoId = (int)$pdo->lastInsertId();

                if ($tipo === "tecnico") {
                    $stmt = $pdo->prepare("INSERT INTO tecnicos (usuario_id, bio, area, nivel, disponivel) VALUES (?, '', '', 'junior', 1)");
                    $stmt->execute([$novoId]);
                }

                $ok = "Cadastro feito! Agora você pode entrar.";
            }
        }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Cadastro</title>
</head>
<body>
  <h1>Cadastro</h1>

  <?php if ($erro): ?>
    <p style="color:red"><?= htmlspecialchars($erro) ?></p>
  <?php endif; ?>

  <?php if ($ok): ?>
    <p style="color:green"><?= htmlspecialchars($ok) ?></p>
  <?php endif; ?>

  <form method="post">
    <label>Nome:</label><br>
    <input name="nome" required><br><br>

    <label>Email (opcional):</label><br>
    <input name="email" type="email"><br><br>

    <label>Telefone:</label><br>
    <input name="telefone" required><br><br>

    <label>Senha:</label><br>
    <input name="senha" type="password" required><br><br>

    <label>Tipo:</label><br>
    <select name="tipo">
      <option value="cliente">Cliente</option>
      <option value="tecnico">Técnico</option>
    </select><br><br>

    <button type="submit">Cadastrar</button>
  </form>

  <p><a href="login.php">Já tenho conta → Login</a></p>
  <p><a href="index.php">Voltar</a></p>
</body>
</html>
