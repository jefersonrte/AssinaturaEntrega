<?php
/**
 * API usada pelo aplicativo Windows.
 *
 *   POST /api/assinaturas   multipart/form-data  (Authorization: Bearer <token do cliente>)
 *        campos: id, orderNumber, recipientName, notes, deviceId, capturedAtUtc
 *        arquivos: signature (png) e, opcional, original (png)
 *        → 201 {ok:true,id}  |  200 {ok:true,id,duplicado:true} se o id já existia (reenvio)
 *
 *   GET  /api/assinaturas?pedido=&de=&ate=&dispositivo=&limite=   (mesmo token)
 *        → lista JSON das assinaturas do cliente
 */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

$cliente = exigir_cliente();
$metodo  = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $sql = 'SELECT id, pedido, destinatario, observacoes, dispositivo, capturado_em, recebido_em,
                   (arquivo_assin IS NOT NULL) AS tem_assinatura, (arquivo_orig IS NOT NULL) AS tem_original
            FROM assinaturas WHERE cliente_id = :c';
    $p = [':c' => $cliente['id']];
    if (($v = trim($_GET['pedido'] ?? '')) !== '')      { $sql .= ' AND pedido LIKE :pedido';      $p[':pedido'] = "%$v%"; }
    if (($v = trim($_GET['dispositivo'] ?? '')) !== '') { $sql .= ' AND dispositivo = :disp';      $p[':disp'] = $v; }
    if (($v = trim($_GET['de'] ?? '')) !== '')          { $sql .= ' AND capturado_em >= :de';      $p[':de'] = $v; }
    if (($v = trim($_GET['ate'] ?? '')) !== '')         { $sql .= ' AND capturado_em <= :ate';     $p[':ate'] = $v; }
    $limite = max(1, min(500, (int)($_GET['limite'] ?? 100)));
    $sql .= " ORDER BY capturado_em DESC LIMIT $limite";
    $st = db()->prepare($sql);
    $st->execute($p);
    json_out(['ok' => true, 'cliente' => $cliente['nome'], 'itens' => $st->fetchAll()]);
}

if ($metodo !== 'POST') {
    json_out(['ok' => false, 'error' => 'use POST (envio) ou GET (consulta)'], 405);
}

// ---- validação dos campos ----
$id = trim($_POST['id'] ?? '');
if ($id === '' || !preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id)) {
    json_out(['ok' => false, 'error' => 'campo "id" ausente ou inválido'], 400);
}
$pedido       = mb_substr(trim($_POST['orderNumber']   ?? ''), 0, 100);
$destinatario = mb_substr(trim($_POST['recipientName'] ?? ''), 0, 200);
$observacoes  = mb_substr(trim($_POST['notes']         ?? ''), 0, 2000);
$dispositivo  = mb_substr(trim($_POST['deviceId']      ?? ''), 0, 100);
$capturadoEm  = trim($_POST['capturedAtUtc'] ?? '');
try {
    $capturadoEm = (new DateTimeImmutable($capturadoEm ?: 'now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
} catch (Throwable) {
    json_out(['ok' => false, 'error' => 'capturedAtUtc inválido'], 400);
}

if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
    $erro = $_FILES['signature']['error'] ?? 'ausente';
    json_out(['ok' => false, 'error' => "arquivo \"signature\" obrigatório (erro $erro)"], 400);
}

// ---- idempotência: reenvio do mesmo id não duplica ----
$st = db()->prepare('SELECT 1 FROM assinaturas WHERE id = ? AND cliente_id = ?');
$st->execute([$id, $cliente['id']]);
if ($st->fetchColumn()) {
    json_out(['ok' => true, 'id' => $id, 'duplicado' => true]);
}

// ---- grava arquivos: uploads/{cliente}/{yyyy}/{mm}/{id}_{tipo}.png ----
$sub = sprintf('%d/%s', $cliente['id'], substr($capturadoEm, 0, 4) . '/' . substr($capturadoEm, 5, 2));
$dir = UPLOAD_DIR . '/' . $sub;
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    json_out(['ok' => false, 'error' => 'não foi possível criar a pasta de destino'], 500);
}

function salvar_png(string $campo, string $destino): ?string
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $tmp = $_FILES[$campo]['tmp_name'];
    // Aceita apenas PNG/JPEG de verdade (não confia na extensão enviada).
    $mime = mime_content_type($tmp) ?: '';
    if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
        json_out(['ok' => false, 'error' => "arquivo \"$campo\" não é PNG/JPEG ($mime)"], 415);
    }
    $ext = $mime === 'image/jpeg' ? '.jpg' : '.png';
    if (!move_uploaded_file($tmp, $destino . $ext)) {
        json_out(['ok' => false, 'error' => "falha ao gravar \"$campo\""], 500);
    }
    return $destino . $ext;
}

$arqAssin = salvar_png('signature', "$dir/{$id}_signature");
$arqOrig  = salvar_png('original',  "$dir/{$id}_original");
$rel = fn(?string $abs) => $abs ? substr($abs, strlen(UPLOAD_DIR) + 1) : null;

db()->prepare('INSERT INTO assinaturas
        (id, cliente_id, pedido, destinatario, observacoes, dispositivo, capturado_em, arquivo_assin, arquivo_orig, ip_origem)
        VALUES (?,?,?,?,?,?,?,?,?,?)')
    ->execute([$id, $cliente['id'], $pedido, $destinatario, $observacoes, $dispositivo,
               $capturadoEm, $rel($arqAssin), $rel($arqOrig), $_SERVER['REMOTE_ADDR'] ?? null]);

json_out(['ok' => true, 'id' => $id, 'cliente' => $cliente['nome']], 201);
