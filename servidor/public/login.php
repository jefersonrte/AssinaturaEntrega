<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
sessao_iniciar();

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['usuario'] ?? '';
    $s = $_POST['senha'] ?? '';
    if (hash_equals(admin_usuario(), $u) && hash_equals(admin_senha(), $s)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = $u;
        header('Location: index.php');
        exit;
    }
    usleep(500_000); // desacelera tentativas de força bruta
    $erro = 'Usuário ou senha inválidos.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar — Assinaturas de Entrega</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<form class="login" method="post" autocomplete="off">
  <h1>✍️ Assinaturas de Entrega</h1>
  <p class="sub">Painel do servidor</p>
  <?php if ($erro): ?><p class="erro"><?= h($erro) ?></p><?php endif; ?>
  <label>Usuário <input name="usuario" required autofocus></label>
  <label>Senha <input name="senha" type="password" required></label>
  <button type="submit">Entrar</button>
</form>
</body>
</html>
