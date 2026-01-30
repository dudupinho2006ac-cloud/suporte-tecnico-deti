<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

/* ===== CONEXÃO COM BANCO ===== */
$host = "localhost";
$dbname = "suporte_tecnico";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro no banco: " . $e->getMessage());
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
function tipoLabel($tipo) { return $tipo === "cliente" ? "Usuário" : ($tipo === "tecnico" ? "Técnico" : $tipo); }

$action = $_GET["a"] ?? "home";
$msg = "";
$err = "";

/* ===== LOGOUT ===== */
if ($action === "logout") {
    session_destroy();
    header("Location: index.php");
    exit;
}

/* ===== CADASTRO ===== */
if ($action === "cadastro" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST["nome"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $senha = $_POST["senha"] ?? "";
    $tipo = $_POST["tipo"] ?? "cliente";

    if ($nome === "" || $telefone === "" || $senha === "") {
        $err = "Preencha todos os campos.";
    } elseif (!in_array($tipo, ["cliente", "tecnico"], true)) {
        $err = "Tipo inválido.";
    } else {
        $check = $pdo->prepare("SELECT id FROM usuarios WHERE telefone = ? LIMIT 1");
        $check->execute([$telefone]);

        if ($check->fetch()) {
            $err = "Telefone já cadastrado.";
        } else {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO usuarios (nome, telefone, senha_hash, tipo) VALUES (?, ?, ?, ?)");
            $ins->execute([$nome, $telefone, $senha_hash, $tipo]);

            $novoId = (int)$pdo->lastInsertId();

            if ($tipo === "tecnico") {
                $pdo->prepare("INSERT INTO tecnicos (usuario_id, bio, area, nivel, disponivel) VALUES (?, '', '', 'junior', 1)")
                    ->execute([$novoId]);
            }

            $msg = "Cadastro realizado! Agora faça login.";
            $action = "login";
        }
    }
}

/* ===== LOGIN ===== */
if ($action === "login" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $telefone = trim($_POST["telefone"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if ($telefone === "" || $senha === "") {
        $err = "Informe telefone e senha.";
    } else {
        $st = $pdo->prepare("SELECT id, nome, telefone, senha_hash, tipo, ativo FROM usuarios WHERE telefone = ? LIMIT 1");
        $st->execute([$telefone]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if (!$u) {
            $err = "Usuário não encontrado.";
        } elseif ((int)$u["ativo"] !== 1) {
            $err = "Usuário desativado.";
        } elseif (!password_verify($senha, $u["senha_hash"])) {
            $err = "Telefone ou senha incorretos.";
        } else {
            $_SESSION["user"] = [
                "id" => (int)$u["id"],
                "nome" => $u["nome"],
                "telefone" => $u["telefone"],
                "tipo" => $u["tipo"]
            ];
            if ($u["tipo"] === "tecnico") {
    header("Location: index.php?a=chamados_tecnico");
} else {
    header("Location: index.php?a=dashboard");
}
exit;

        }
    }
}

/* ===== ABRIR CHAMADO (USUÁRIO) ===== */
if ($action === "abrir_chamado" && $_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_SESSION["user"]) || $_SESSION["user"]["tipo"] !== "cliente") {
        header("Location: index.php?a=login");
        exit;
    }

    $titulo = trim($_POST["titulo"] ?? "");
    $descricao = trim($_POST["descricao"] ?? "");
    $prioridade = $_POST["prioridade"] ?? "media";
    $tecnico_id = (int)($_POST["tecnico_id"] ?? 0);

    if ($titulo === "" || $descricao === "") {
        $err = "Preencha título e descrição.";
    } elseif (!in_array($prioridade, ["baixa","media","alta"], true)) {
        $err = "Prioridade inválida.";
    } elseif ($tecnico_id <= 0) {
        $err = "Escolha um técnico.";
    } else {
        $st = $pdo->prepare("
            SELECT u.id
            FROM usuarios u
            JOIN tecnicos t ON t.usuario_id = u.id
            WHERE u.id = ? AND u.tipo = 'tecnico' AND t.disponivel = 1
            LIMIT 1
        ");
        $st->execute([$tecnico_id]);

        if (!$st->fetch()) {
            $err = "Técnico inválido ou indisponível.";
        } else {
            $ins = $pdo->prepare("
                INSERT INTO chamados (cliente_id, tecnico_id, titulo, descricao, prioridade, status)
                VALUES (?, ?, ?, ?, ?, 'aberto')
            ");
            $ins->execute([$_SESSION["user"]["id"], $tecnico_id, $titulo, $descricao, $prioridade]);

            $msg = "Chamado criado com sucesso!";
            $action = "meus_chamados";
        }
    }
}

/* ===== PROTEÇÃO ===== */
$precisa_login = ["dashboard","abrir_chamado","meus_chamados","chamados_tecnico"];
if (in_array($action, $precisa_login, true) && !isset($_SESSION["user"])) {
    header("Location: index.php?a=login");
    exit;
}

/* ===== DADOS ===== */
$tecnicos_disponiveis = [];
if ($action === "abrir_chamado" && isset($_SESSION["user"]) && $_SESSION["user"]["tipo"] === "cliente") {
    $st = $pdo->query("
        SELECT u.id, u.nome
        FROM usuarios u
        JOIN tecnicos t ON t.usuario_id = u.id
        WHERE u.tipo = 'tecnico' AND t.disponivel = 1
        ORDER BY u.nome ASC
    ");
    $tecnicos_disponiveis = $st->fetchAll(PDO::FETCH_ASSOC);
}

$meus_chamados = [];
if ($action === "meus_chamados" && isset($_SESSION["user"]) && $_SESSION["user"]["tipo"] === "cliente") {
    $st = $pdo->prepare("
        SELECT c.id, c.titulo, c.prioridade, c.status, c.criado_em, u.nome AS tecnico_nome
        FROM chamados c
        LEFT JOIN usuarios u ON u.id = c.tecnico_id
        WHERE c.cliente_id = ?
        ORDER BY c.id DESC
    ");
    $st->execute([$_SESSION["user"]["id"]]);
    $meus_chamados = $st->fetchAll(PDO::FETCH_ASSOC);
}

$chamados_do_tecnico = [];
if ($action === "chamados_tecnico" && isset($_SESSION["user"]) && $_SESSION["user"]["tipo"] === "tecnico") {
    $st = $pdo->prepare("
        SELECT c.id, c.titulo, c.prioridade, c.status, c.criado_em, u.nome AS cliente_nome
        FROM chamados c
        JOIN usuarios u ON u.id = c.cliente_id
        WHERE c.tecnico_id = ?
        ORDER BY c.id DESC
    ");
    $st->execute([$_SESSION["user"]["id"]]);
    $chamados_do_tecnico = $st->fetchAll(PDO::FETCH_ASSOC);
}

$userLogged = $_SESSION["user"] ?? null;
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Suporte Técnico - DETI</title>

<style>
:root{
  --bg:#eef2f7;
  --card:#ffffff;
  --text:#111827;
  --muted:#6b7280;
  --line:#e5e7eb;
  --primary:#2563eb;
  --primary2:#1d4ed8;
  --shadow:0 14px 34px rgba(0,0,0,0.08);
  --radius:16px;
}
*{ box-sizing:border-box; }
body{
  margin:0;
  font-family: Arial, sans-serif;
  background:var(--bg);
  color:var(--text);
}

/* ===== BANNER EM TELA CHEIA (CORTADO) ===== */
.banner{
  width: 100vw;
  height: 60vh;          /* era 100vh */
  overflow: hidden;
  position: relative;
}

.banner img{
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

/* ===== CONTAINER WIDE ===== */
.container{
  width: min(1600px, calc(100% - 32px));
  margin: 0 auto;
  padding: 12px 0;

  position: relative;
  margin-top: -70px;   /* sobe tudo (aumente pra -90 se quiser mais junto) */
  z-index: 10;
}

.navbar{
  margin-top: 0;       /* era 10px */
}

/* ===== NAVBAR ===== */
.navbar{
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(8px);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 12px 14px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  margin-top: 10px;
}
.brand{ display:flex; align-items:center; gap:10px; }
.logo{
  width:38px; height:38px; border-radius:12px;
  background: linear-gradient(135deg, var(--primary), #22c55e);
}
.brand h1{ font-size: 16px; margin:0; line-height:1.2; }
.brand small{ color: var(--muted); }

.navlinks{ display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
.btn, .btn-outline{
  border-radius: 12px;
  padding: 10px 12px;
  font-weight: 700;
  border: 1px solid transparent;
  cursor: pointer;
  display:inline-flex;
  align-items:center;
  gap:8px;
  text-decoration:none;
  font-size: 14px;
}
.btn{ background: var(--primary); color:#fff; }
.btn:hover{ background: var(--primary2); }
.btn-outline{ background:#fff; color:var(--text); border-color:var(--line); }
.btn-outline:hover{ border-color:#cbd5e1; }

/* ===== CARD ===== */
.card{
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 18px;
  margin-top: 12px;
}
.title{ margin:0 0 4px; font-size: 22px; }
.subtitle{ margin:0 0 14px; color: var(--muted); }

/* ===== GRID ===== */
.grid{
  display:grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
.feature{
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 16px;
  background: #fff;
}
.feature h3{ margin:0 0 6px; }
.feature p{ margin:0 0 12px; color: var(--muted); }

/* ===== ALERTAS ===== */
.alert{
  border-radius: 14px;
  padding: 12px 14px;
  border: 1px solid var(--line);
  margin-bottom: 12px;
}
.alert.ok{ background: #ecfdf5; border-color: #bbf7d0; color:#065f46; }
.alert.err{ background: #fef2f2; border-color: #fecaca; color:#991b1b; }

/* ===== FORM ===== */
.form{ display:grid; gap: 12px; margin-top: 6px; max-width: 720px; }
label{ font-weight:700; font-size: 14px; }
.input, select, textarea{
  width:100%;
  border:1px solid var(--line);
  border-radius: 12px;
  padding: 12px;
  outline: none;
  font-size: 14px;
  background:#fff;
}
textarea{ min-height: 120px; resize: vertical; }
.input:focus, select:focus, textarea:focus{
  border-color: #93c5fd;
  box-shadow: 0 0 0 4px rgba(37,99,235,0.12);
}

/* ===== TABELA ===== */
table{ width:100%; border-collapse: collapse; margin-top: 10px; }
th, td{ padding: 12px 10px; border-bottom: 1px solid var(--line); text-align:left; }
th{ color: var(--muted); font-size: 13px; }
.badge{
  display:inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid var(--line);
  background:#f9fafb;
  font-size: 12px;
  font-weight: 700;
}
.small{ color: var(--muted); font-size: 12px; }
.link{ color: var(--primary); font-weight: 700; text-decoration:none; }
.link:hover{ text-decoration: underline; }

@media (max-width: 900px){
  .grid{ grid-template-columns: 1fr; }
  .navlinks{ justify-content:flex-start; }
  .form{ max-width: 100%; }
}
</style>
</head>

<body>

<div class="banner">
  <img src="img/banner.png" alt="Suporte Técnico Profissional - DETI">
</div>

<div class="container">
  <div class="navbar">
    <div class="brand">
      <div class="logo"></div>
      <div>
        <h1>Suporte Técnico • <span style="letter-spacing:2px;">DETI</span></h1>
        <small>
          <?= $userLogged ? "Logado como: ".h($userLogged["nome"])." (".h(tipoLabel($userLogged["tipo"])).")"
                          : "Abra chamados e acompanhe o suporte" ?>
        </small>
      </div>
    </div>

    <div class="navlinks">
      <a class="btn-outline" href="index.php">Home</a>
      <?php if ($userLogged): ?>
        <a class="btn-outline" href="index.php?a=dashboard">Dashboard</a>
        <a class="btn" href="index.php?a=logout">Sair</a>
      <?php else: ?>
        <a class="btn-outline" href="index.php?a=login">Entrar</a>
        <a class="btn" href="index.php?a=cadastro">Cadastrar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <?php if ($msg): ?><div class="alert ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert err"><?= h($err) ?></div><?php endif; ?>

    <?php if ($action === "home"): ?>
      <?php if ($userLogged): ?>
        <h2 class="title">Bem-vindo, <?= h($userLogged["nome"]) ?> 👋</h2>
        <p class="subtitle">Acesse seu painel para abrir ou acompanhar chamados.</p>

        <div class="grid">
          <div class="feature">
            <h3>Dashboard</h3>
            <p>Ver suas opções e atalhos do sistema.</p>
            <a class="btn" href="index.php?a=dashboard">Ir para o Dashboard</a>
          </div>
          <div class="feature">
            <h3>Sair</h3>
            <p>Encerrar sessão com segurança.</p>
            <a class="btn-outline" href="index.php?a=logout">Sair</a>
          </div>
        </div>
      <?php else: ?>
        <h2 class="title">Bem-vindo 👋</h2>
        <p class="subtitle">Entre para abrir chamados ou cadastre-se rapidamente.</p>

        <div class="grid">
          <div class="feature">
            <h3>Entrar</h3>
            <p>Acesse sua conta com telefone e senha.</p>
            <a class="btn" href="index.php?a=login">Entrar</a>
          </div>
          <div class="feature">
            <h3>Cadastrar</h3>
            <p>Crie sua conta (Usuário ou Técnico).</p>
            <a class="btn-outline" href="index.php?a=cadastro">Cadastrar</a>
          </div>
        </div>
      <?php endif; ?>

    <?php elseif ($action === "login"): ?>
      <h2 class="title">Entrar</h2>
      <p class="subtitle">Use seu telefone e senha para acessar.</p>
      <form class="form" method="post" action="index.php?a=login">
        <div><label>Telefone</label><input class="input" name="telefone" required></div>
        <div><label>Senha</label><input class="input" type="password" name="senha" required></div>
        <button class="btn" type="submit">Entrar</button>
        <a class="link" href="index.php?a=cadastro">Não tem conta? Cadastre-se</a>
      </form>

    <?php elseif ($action === "cadastro"): ?>
      <h2 class="title">Cadastrar</h2>
      <p class="subtitle">Crie uma conta em poucos segundos.</p>
      <form class="form" method="post" action="index.php?a=cadastro">
        <div><label>Nome</label><input class="input" name="nome" required></div>
        <div><label>Telefone</label><input class="input" name="telefone" required></div>
        <div><label>Senha</label><input class="input" type="password" name="senha" required></div>
        <div>
          <label>Tipo</label>
          <select name="tipo" required>
            <option value="cliente">Usuário</option>
            <option value="tecnico">Técnico</option>
          </select>
        </div>
        <button class="btn" type="submit">Cadastrar</button>
        <a class="link" href="index.php?a=login">Já tem conta? Entrar</a>
      </form>

    <?php elseif ($action === "dashboard"): ?>
      <h2 class="title">Dashboard</h2>
      <p class="subtitle">Atalhos do seu painel.</p>
      <p><b>Tipo:</b> <?= h(tipoLabel($userLogged["tipo"])) ?></p>

      <?php if ($userLogged["tipo"] === "cliente"): ?>
        <div class="grid">
          <div class="feature">
            <h3>➕ Abrir chamado</h3>
            <p>Escolha um técnico e descreva o problema.</p>
            <a class="btn" href="index.php?a=abrir_chamado">Abrir chamado</a>
          </div>
          <div class="feature">
            <h3>📄 Meus chamados</h3>
            <p>Acompanhe seus chamados criados.</p>
            <a class="btn-outline" href="index.php?a=meus_chamados">Ver chamados</a>
          </div>
        </div>
      <?php else: ?>
        <div class="grid">
          <div class="feature">
            <h3>📥 Chamados atribuídos</h3>
            <p>Veja os chamados enviados pelos usuários para você.</p>
            <a class="btn" href="index.php?a=chamados_tecnico">Ver chamados</a>
          </div>
          <div class="feature">
            <h3>🚪 Sair</h3>
            <p>Encerrar sessão.</p>
            <a class="btn-outline" href="index.php?a=logout">Sair</a>
          </div>
        </div>
      <?php endif; ?>

    <?php elseif ($action === "abrir_chamado"): ?>
      <?php if (!$userLogged || $userLogged["tipo"] !== "cliente"): ?>
        <div class="alert err">Somente usuários podem abrir chamado.</div>
      <?php else: ?>
        <h2 class="title">Abrir chamado</h2>
        <p class="subtitle">Explique seu problema e escolha um técnico disponível.</p>

        <?php if (count($tecnicos_disponiveis) === 0): ?>
          <div class="alert err">Nenhum técnico disponível no momento.</div>
        <?php else: ?>
          <form class="form" method="post" action="index.php?a=abrir_chamado">
            <div><label>Título</label><input class="input" name="titulo" required></div>
            <div><label>Descrição</label><textarea name="descricao" required></textarea></div>
            <div>
              <label>Prioridade</label>
              <select name="prioridade">
                <option value="baixa">Baixa</option>
                <option value="media" selected>Média</option>
                <option value="alta">Alta</option>
              </select>
            </div>
            <div>
              <label>Técnico</label>
              <select name="tecnico_id" required>
                <option value="">-- Selecione --</option>
                <?php foreach ($tecnicos_disponiveis as $t): ?>
                  <option value="<?= (int)$t["id"] ?>"><?= h($t["nome"]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn" type="submit">Criar chamado</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>

    <?php elseif ($action === "meus_chamados"): ?>
      <h2 class="title">Meus chamados</h2>
      <p class="subtitle">Aqui estão seus chamados criados.</p>
      <?php if (count($meus_chamados) === 0): ?>
        <div class="alert">Você ainda não tem chamados.</div>
      <?php else: ?>
        <table>
          <tr><th>#</th><th>Título</th><th>Técnico</th><th>Prioridade</th><th>Status</th><th>Criado</th></tr>
          <?php foreach ($meus_chamados as $c): ?>
            <tr>
              <td><?= (int)$c["id"] ?></td>
              <td><?= h($c["titulo"]) ?></td>
              <td><?= h($c["tecnico_nome"] ?? "—") ?></td>
              <td><span class="badge"><?= h($c["prioridade"]) ?></span></td>
              <td><span class="badge"><?= h($c["status"]) ?></span></td>
              <td><span class="small"><?= h($c["criado_em"]) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>

    <?php elseif ($action === "chamados_tecnico"): ?>
      <h2 class="title">Chamados atribuídos a mim</h2>
      <p class="subtitle">Chamados enviados pelos usuários para você.</p>
      <?php if (count($chamados_do_tecnico) === 0): ?>
        <div class="alert">Nenhum chamado atribuído ainda.</div>
      <?php else: ?>
        <table>
          <tr><th>#</th><th>Título</th><th>Usuário</th><th>Prioridade</th><th>Status</th><th>Criado</th></tr>
          <?php foreach ($chamados_do_tecnico as $c): ?>
            <tr>
              <td><?= (int)$c["id"] ?></td>
              <td><?= h($c["titulo"]) ?></td>
              <td><?= h($c["cliente_nome"]) ?></td>
              <td><span class="badge"><?= h($c["prioridade"]) ?></span></td>
              <td><span class="badge"><?= h($c["status"]) ?></span></td>
              <td><span class="small"><?= h($c["criado_em"]) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>

    <?php else: ?>
      <h2 class="title">Página não encontrada</h2>
      <p class="subtitle"><a class="link" href="index.php">Voltar</a></p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
