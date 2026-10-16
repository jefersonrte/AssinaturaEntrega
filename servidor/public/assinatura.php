<?php
/** Painel: detalhe de uma assinatura (imagens + metadados). */
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
exigir_login();

$id = $_GET['id'] ?? '';
$st = db()->prepare('SELECT a.*, c.nome AS cliente_nome FROM assinaturas a JOIN clientes c ON c.id = a.cliente_id WHERE a.id = ?');
$st->execute([$id]);
$a = $st->fetch();
if (!$a) {
    http_response_code(404);
    layout_inicio('Assinatura não encontrada');
    echo '<p><a href="index.php">« Voltar</a></p>';
    layout_fim();
    exit;
}

layout_inicio('Pedido ' . ($a['pedido'] !== '' ? $a['pedido'] : '(sem número)'));
?>
<p><a href="index.php">« Voltar para a lista</a></p>

<div class="detalhe">
  <section class="imagens">
    <h2>Assinatura</h2>
    <?php if ($a['arquivo_assin']): ?>
      <img class="assin" src="imagem.php?id=<?= h($a['id']) ?>&tipo=assinatura" alt="assinatura">
      <p><a class="btn-sec" href="imagem.php?id=<?= h($a['id']) ?>&tipo=assinatura&baixar=1">⬇ Baixar assinatura</a></p>
    <?php else: ?><p class="vazio">Sem imagem.</p><?php endif; ?>

    <?php if ($a['arquivo_orig']): ?>
      <h2>Foto original (canhoto completo)</h2>
      <img class="orig" src="imagem.php?id=<?= h($a['id']) ?>&tipo=original" alt="original">
      <p><a class="btn-sec" href="imagem.php?id=<?= h($a['id']) ?>&tipo=original&baixar=1">⬇ Baixar original</a></p>
    <?php endif; ?>
  </section>

  <section class="meta">
    <h2>Dados</h2>
    <dl>
      <dt>Pedido</dt><dd><?= h($a['pedido']) ?></dd>
      <dt>Destinatário</dt><dd><?= h($a['destinatario']) ?></dd>
      <dt>Observações</dt><dd><?= nl2br(h($a['observacoes'])) ?></dd>
      <dt>Capturado em</dt><dd><?= h(fmt_data($a['capturado_em'])) ?></dd>
      <dt>Recebido no servidor</dt><dd><?= h(fmt_data($a['recebido_em'] . ' UTC')) ?></dd>
      <dt>Dispositivo</dt><dd><?= h($a['dispositivo']) ?></dd>
      <dt>Cliente</dt><dd><?= h($a['cliente_nome']) ?></dd>
      <dt>IP de origem</dt><dd><?= h($a['ip_origem']) ?></dd>
      <dt>ID</dt><dd><code><?= h($a['id']) ?></code></dd>
    </dl>
  </section>
</div>
<?php layout_fim();
