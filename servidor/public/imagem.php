<?php
/** Serve as imagens armazenadas (fora do DocumentRoot) para usuários logados no painel. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
exigir_login();

$id   = $_GET['id'] ?? '';
$tipo = ($_GET['tipo'] ?? 'assinatura') === 'original' ? 'arquivo_orig' : 'arquivo_assin';

$st = db()->prepare("SELECT $tipo AS arq, pedido FROM assinaturas WHERE id = ?");
$st->execute([$id]);
$row = $st->fetch();
if (!$row || !$row['arq']) {
    http_response_code(404);
    exit('não encontrado');
}
$abs = realpath(UPLOAD_DIR . '/' . $row['arq']);
if ($abs === false || !str_starts_with($abs, realpath(UPLOAD_DIR) . DIRECTORY_SEPARATOR) || !is_file($abs)) {
    http_response_code(404);
    exit('arquivo ausente');
}

$mime = mime_content_type($abs) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($abs));
header('Cache-Control: private, max-age=86400');
if (!empty($_GET['baixar'])) {
    $nome = preg_replace('/[^A-Za-z0-9_-]+/', '-', $row['pedido'] ?: 'assinatura');
    $suf  = $tipo === 'arquivo_orig' ? 'original' : 'assinatura';
    header("Content-Disposition: attachment; filename=\"{$nome}_{$suf}" . substr($abs, strrpos($abs, '.')) . '"');
}
readfile($abs);
