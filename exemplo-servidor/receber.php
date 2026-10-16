<?php
/**
 * Exemplo de endpoint para receber a assinatura enviada pelo aplicativo.
 * Contrato: POST multipart/form-data em /api/assinaturas
 *
 * Campos de texto: id, orderNumber, recipientName, notes, deviceId, capturedAtUtc
 * Arquivos:        signature (image/png) e, opcional, original (image/png)
 *
 * Coloque este arquivo no seu servidor e aponte o appsettings.json:
 *   "BaseUrl": "https://seu-dominio.com.br"
 *   "UploadPath": "/receber.php"
 *
 * Em produção: valide o token (Authorization: Bearer ...), autentique e
 * grave os metadados no seu banco de dados.
 */

header('Content-Type: application/json; charset=utf-8');

// ---- (Opcional) validação de token ----
// $expected = 'SEU_TOKEN';
// $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
// if ($auth !== "Bearer $expected") { http_response_code(401); echo json_encode(['error' => 'nao autorizado']); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'use POST']);
    exit;
}

$id         = $_POST['id']            ?? '';
$order      = $_POST['orderNumber']   ?? '';
$recipient  = $_POST['recipientName'] ?? '';
$notes      = $_POST['notes']         ?? '';
$deviceId   = $_POST['deviceId']      ?? '';
$capturedAt = $_POST['capturedAtUtc'] ?? '';

$baseDir = __DIR__ . '/uploads';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0775, true);
}

$saved = [];
foreach (['signature', 'original'] as $field) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $safeOrder = preg_replace('/[^A-Za-z0-9_-]/', '-', $order ?: 'sem-pedido');
        $name = sprintf('%s_%s_%s.png', $safeOrder, $field, substr($id, 0, 6));
        $dest = $baseDir . '/' . $name;
        if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
            $saved[$field] = 'uploads/' . $name;
        }
    }
}

// Aqui você gravaria no seu banco:
// INSERT INTO assinaturas (id, pedido, destinatario, ...) VALUES (...)

http_response_code(201);
echo json_encode([
    'ok'          => true,
    'id'          => $id,
    'orderNumber' => $order,
    'recipient'   => $recipient,
    'deviceId'    => $deviceId,
    'capturedAt'  => $capturedAt,
    'files'       => $saved,
], JSON_UNESCAPED_UNICODE);
