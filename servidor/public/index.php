<?php
/** Painel: lista e busca de assinaturas recebidas. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
exigir_login();

$f = [
    'pedido'       => trim($_GET['pedido'] ?? ''),
    'destinatario' => trim($_GET['destinatario'] ?? ''),
    'dispositivo'  => trim($_GET['dispositivo'] ?? ''),
    'cliente'      => (int)($_GET['cliente'] ?? 0),
    'de'           => trim($_GET['de'] ?? ''),
    'ate'          => trim($_GET['ate'] ?? ''),
];
$porPagina = 50;
$pagina = max(1, (int)($_GET['p'] ?? 1));

$where = ['1=1'];
$p = [];
if ($f['pedido'] !== '')       { $where[] = 'a.pedido LIKE :pedido';             $p[':pedido'] = "%{$f['pedido']}%"; }
if ($f['destinatario'] !== '') { $where[] = 'a.destinatario LIKE :dest';         $p[':dest']   = "%{$f['destinatario']}%"; }
if ($f['dispositivo'] !== '')  { $where[] = 'a.dispositivo LIKE :disp';          $p[':disp']   = "%{$f['dispositivo']}%"; }
if ($f['cliente'] > 0)         { $where[] = 'a.cliente_id = :cli';               $p[':cli']    = $f['cliente']; }
// Datas do formulário são locais; converte para UTC para comparar com capturado_em.
$tz = new DateTimeZone(date_default_timezone_get());
if ($f['de'] !== '')  { $where[] = 'a.capturado_em >= :de';  $p[':de']  = (new DateTimeImmutable($f['de'] . ' 00:00:00', $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'); }
if ($f['ate'] !== '') { $where[] = 'a.capturado_em <= :ate'; $p[':ate'] = (new DateTimeImmutable($f['ate'] . ' 23:59:59', $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'); }
$w = implode(' AND ', $where);

$total = db()->prepare("SELECT COUNT(*) FROM assinaturas a WHERE $w");
$total->execute($p);
$total = (int)$total->fetchColumn();
$paginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $paginas);

$st = db()->prepare("SELECT a.*, c.nome AS cliente_nome
                     FROM assinaturas a JOIN clientes c ON c.id = a.cliente_id
                     WHERE $w ORDER BY a.capturado_em DESC
                     LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina));
$st->execute($p);
$itens = $st->fetchAll();

$clientes = db()->query('SELECT id, nome FROM clientes ORDER BY nome')->fetchAll();

function link_pagina(int $n): string
{
    return 'index.php?' . http_build_query(array_merge($_GET, ['p' => $n]));
}

layout_inicio('Assinaturas recebidas');
?>
<form class="filtros" method="get">
  <label>Pedido <input name="pedido" value="<?= h($f['pedido']) ?>"></label>
  <label>Destinatário <input name="destinatario" value="<?= h($f['destinatario']) ?>"></label>
  <label>Dispositivo <input name="dispositivo" value="<?= h($f['dispositivo']) ?>"></label>
  <label>Cliente
    <select name="cliente">
      <option value="0">Todos</option>
      <?php foreach ($clientes as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $f['cliente'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>De <input type="date" name="de" value="<?= h($f['de']) ?>"></label>
  <label>Até <input type="date" name="ate" value="<?= h($f['ate']) ?>"></label>
  <button type="submit">Buscar</button>
  <a class="btn-sec" href="index.php">Limpar</a>
</form>

<p class="resumo"><?= $total ?> registro(s)<?= $paginas > 1 ? " — página $pagina de $paginas" : '' ?></p>

<?php if (!$itens): ?>
  <p class="vazio">Nenhuma assinatura encontrada. Assim que um aplicativo enviar, ela aparece aqui.</p>
<?php else: ?>
<table class="lista">
  <thead><tr>
    <th>Assinatura</th><th>Pedido</th><th>Destinatário</th><th>Capturado em</th><th>Dispositivo</th><th>Cliente</th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($itens as $a): ?>
    <tr>
      <td class="thumb">
        <?php if ($a['arquivo_assin']): ?>
          <a href="assinatura.php?id=<?= h($a['id']) ?>"><img src="imagem.php?id=<?= h($a['id']) ?>&tipo=assinatura" alt="assinatura" loading="lazy"></a>
        <?php else: ?>—<?php endif; ?>
      </td>
      <td><strong><?= h($a['pedido']) ?: '<em>sem pedido</em>' ?></strong></td>
      <td><?= h($a['destinatario']) ?></td>
      <td><?= h(fmt_data($a['capturado_em'])) ?></td>
      <td><?= h($a['dispositivo']) ?></td>
      <td><?= h($a['cliente_nome']) ?></td>
      <td><a class="btn-sec" href="assinatura.php?id=<?= h($a['id']) ?>">Abrir</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($paginas > 1): ?>
<nav class="paginacao">
  <?php if ($pagina > 1): ?><a href="<?= h(link_pagina($pagina - 1)) ?>">« Anterior</a><?php endif; ?>
  <span>Página <?= $pagina ?> / <?= $paginas ?></span>
  <?php if ($pagina < $paginas): ?><a href="<?= h(link_pagina($pagina + 1)) ?>">Próxima »</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
<?php layout_fim();
