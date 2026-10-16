<?php
/**
 * GET /api/ping  (Authorization: Bearer <token>)  → confirma servidor + token.
 * Usado pelo menu "Envio → Testar conexão" do aplicativo.
 */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

$cliente = exigir_cliente();
$total = db()->prepare('SELECT COUNT(*) FROM assinaturas WHERE cliente_id = ?');
$total->execute([$cliente['id']]);

json_out([
    'ok'        => true,
    'servidor'  => APP_NOME,
    'versao'    => APP_VERSAO,
    'cliente'   => $cliente['nome'],
    'total'     => (int)$total->fetchColumn(),
    'horaUtc'   => gmdate('Y-m-d\TH:i:s\Z'),
]);
