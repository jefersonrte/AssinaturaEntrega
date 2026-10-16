<?php
/**
 * Painel: clientes e seus tokens.
 * Cada cliente (empresa que comprou o app) recebe um token; esse token vai no
 * appsettings.json de cada instalação do cliente (o instalador pede).
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
exigir_login();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'criar') {
        $nome = mb_substr(trim($_POST['nome'] ?? ''), 0, 120);
        if ($nome === '') {
            $msg = 'Informe o nome do cliente.';
        } else {
            db()->prepare('INSERT INTO clientes (nome, token) VALUES (?, ?)')->execute([$nome, gerar_token()]);
            $msg = "Cliente \"$nome\" criado. Copie o token abaixo para a instalação.";
        }
    } elseif ($acao === 'ativo') {
        db()->prepare('UPDATE clientes SET ativo = ? WHERE id = ?')
            ->execute([(int)($_POST['valor'] ?? 0), (int)($_POST['id'] ?? 0)]);
        $msg = 'Status atualizado.';
    } elseif ($acao === 'novo_token') {
        db()->prepare('UPDATE clientes SET token = ? WHERE id = ?')->execute([gerar_token(), (int)($_POST['id'] ?? 0)]);
        $msg = 'Novo token gerado — o token antigo deixou de funcionar; atualize as instalações do cliente.';
    }
}

$clientes = db()->query('SELECT c.*, (SELECT COUNT(*) FROM assinaturas a WHERE a.cliente_id = c.id) AS total,
                                   (SELECT MAX(recebido_em) FROM assinaturas a WHERE a.cliente_id = c.id) AS ultimo
                         FROM clientes c ORDER BY c.nome')->fetchAll();
$csrf = csrf_token();

layout_inicio('Clientes e tokens');
?>
<?php if ($msg): ?><p class="aviso"><?= h($msg) ?></p><?php endif; ?>

<form class="filtros" method="post">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="acao" value="criar">
  <label>Novo cliente <input name="nome" placeholder="Nome da empresa" required></label>
  <button type="submit">Criar e gerar token</button>
</form>

<?php if (!$clientes): ?>
  <p class="vazio">Nenhum cliente ainda. Crie o primeiro acima.</p>
<?php else: ?>
<table class="lista">
  <thead><tr><th>Cliente</th><th>Token (vai no instalador)</th><th>Assinaturas</th><th>Último envio</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($clientes as $c): ?>
    <tr class="<?= $c['ativo'] ? '' : 'inativo' ?>">
      <td><strong><?= h($c['nome']) ?></strong><br><small>desde <?= h(fmt_data($c['criado_em'] . ' UTC')) ?></small></td>
      <td><code class="token" onclick="navigator.clipboard&&navigator.clipboard.writeText(this.textContent)" title="clique para copiar"><?= h($c['token']) ?></code></td>
      <td><?= (int)$c['total'] ?></td>
      <td><?= h(fmt_data($c['ultimo'] ? $c['ultimo'] . ' UTC' : null)) ?: '—' ?></td>
      <td><?= $c['ativo'] ? '<span class="ok">ativo</span>' : '<span class="off">desativado</span>' ?></td>
      <td class="acoes">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="acao" value="ativo">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <input type="hidden" name="valor" value="<?= $c['ativo'] ? 0 : 1 ?>">
          <button class="btn-sec" type="submit"><?= $c['ativo'] ? 'Desativar' : 'Ativar' ?></button>
        </form>
        <form method="post" onsubmit="return confirm('Gerar novo token? As instalações atuais deste cliente param de enviar até serem atualizadas.')">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="acao" value="novo_token">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn-sec" type="submit">Novo token</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<section class="ajuda">
  <h2>Como funciona</h2>
  <ol>
    <li>Crie um cliente para cada empresa que comprar o aplicativo.</li>
    <li>Ao instalar o aplicativo nos computadores do cliente, informe a <strong>URL deste servidor</strong> e o <strong>token</strong> dele.</li>
    <li>Tudo o que esses computadores enviarem fica separado por cliente, nesta lista e na API.</li>
    <li>Para encerrar um contrato, <em>Desativar</em>; para trocar a chave, <em>Novo token</em>.</li>
  </ol>
</section>
<?php layout_fim();
